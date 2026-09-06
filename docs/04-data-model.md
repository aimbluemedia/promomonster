# 04 — Data Model

Postgres 16. Money is **`bigint` cents**, everywhere, no exceptions. Credits are
`numeric(12,2)` because task types cost fractional credits (1.5).

The draft's schema had `transactions.amount` untyped, a mutable `earners.balance` column,
and `tasks` assigned rather than leased. Each of those is a production incident waiting to
happen: float drift on money, lost updates on the balance column, and duplicate slot
assignment under concurrency.

## Identity

```sql
CREATE TYPE user_role AS ENUM ('panelist','business_owner','business_member','reviewer','admin');
CREATE TYPE user_status AS ENUM ('pending_verification','active','suspended','banned');

CREATE TABLE users (
  id                BIGSERIAL PRIMARY KEY,
  email             CITEXT NOT NULL UNIQUE,
  password_hash     TEXT NOT NULL,
  first_name        TEXT NOT NULL,
  last_name         TEXT NOT NULL,
  role              user_role NOT NULL,
  status            user_status NOT NULL DEFAULT 'pending_verification',
  email_verified_at TIMESTAMPTZ,
  country_code      CHAR(2) NOT NULL,
  region_code       TEXT,
  postal_code       TEXT,
  date_of_birth     DATE,                        -- 18+ enforced at signup
  timezone          TEXT,
  last_login_at     TIMESTAMPTZ,
  created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

## Businesses

```sql
CREATE TABLE businesses (
  id                     BIGSERIAL PRIMARY KEY,
  owner_user_id          BIGINT NOT NULL REFERENCES users(id),
  company_name           TEXT NOT NULL,
  primary_website        TEXT,
  industry               TEXT,
  runs_ad_network        BOOLEAN NOT NULL DEFAULT false,  -- blocks traffic products
  stripe_customer_id     TEXT UNIQUE,
  subscription_plan      TEXT,                            -- starter | growth | scale | null
  subscription_status    TEXT,
  subscription_renews_at TIMESTAMPTZ,
  credit_balance         NUMERIC(12,2) NOT NULL DEFAULT 0, -- derived, reconciled nightly
  credits_reserved       NUMERIC(12,2) NOT NULL DEFAULT 0,
  risk_tier              SMALLINT NOT NULL DEFAULT 1,
  created_at             TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

`credit_balance` is a **cache** of the credit ledger, not the source of truth. Never
`UPDATE ... SET credit_balance = credit_balance - x` outside the ledger write.

## Panelists

```sql
CREATE TABLE panelists (
  user_id              BIGINT PRIMARY KEY REFERENCES users(id),
  balance_cents        BIGINT NOT NULL DEFAULT 0,  -- derived from ledger
  pending_cents        BIGINT NOT NULL DEFAULT 0,
  lifetime_cents       BIGINT NOT NULL DEFAULT 0,
  trust_score          SMALLINT NOT NULL DEFAULT 50,
  payout_method        TEXT,                       -- paypal | tremendous
  payout_destination   TEXT,                       -- encrypted at rest
  w9_received_at       TIMESTAMPTZ,
  tax_year_cents       BIGINT NOT NULL DEFAULT 0,  -- for 1099 thresholding
  referred_by_user_id  BIGINT REFERENCES users(id),
  first_payout_at      TIMESTAMPTZ,
  banned_reason        TEXT,
  created_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE panel_profiles (
  user_id        BIGINT PRIMARY KEY REFERENCES users(id),
  age_band        TEXT, gender TEXT, income_band TEXT,
  housing         TEXT,               -- own | rent | other
  household_size  SMALLINT, children_at_home BOOLEAN,
  education       TEXT, employment TEXT,
  industry        TEXT, job_function TEXT, company_size TEXT,
  attributes      JSONB NOT NULL DEFAULT '{}',   -- pets, vehicles, intent flags
  completed_at    TIMESTAMPTZ,
  refreshed_at    TIMESTAMPTZ
);
CREATE INDEX ON panel_profiles USING GIN (attributes jsonb_path_ops);
```

Targeting queries hit `panel_profiles` + `users.region_code`; the GIN index on
`attributes` is what makes *"Arizona homeowners with a pool"* a fast query at 50k panelists.

## Campaigns

```sql
CREATE TYPE campaign_type AS ENUM
  ('site_feedback','head_to_head','serp_test','creative_test',
   'verified_visit','targeted_visit','content_read','unverified_visit');
CREATE TYPE campaign_status AS ENUM
  ('draft','pending_review','approved','active','paused','completed','rejected','cancelled');

CREATE TABLE campaigns (
  id                 BIGSERIAL PRIMARY KEY,
  business_id        BIGINT NOT NULL REFERENCES businesses(id),
  name               TEXT NOT NULL,
  type               campaign_type NOT NULL,
  status             campaign_status NOT NULL DEFAULT 'draft',
  urls               JSONB NOT NULL,               -- 1 url, or 2+ for head-to-head
  min_dwell_seconds  SMALLINT NOT NULL DEFAULT 60,
  targeting          JSONB NOT NULL DEFAULT '{}',  -- geo, device, profile predicates
  min_trust_score    SMALLINT NOT NULL DEFAULT 50,
  slots_total        INT NOT NULL,
  slots_completed    INT NOT NULL DEFAULT 0,
  credits_per_slot   NUMERIC(6,2) NOT NULL,
  credits_reserved   NUMERIC(12,2) NOT NULL,
  payout_cents       BIGINT NOT NULL,              -- per completed slot
  daily_slot_cap     INT,
  starts_at          TIMESTAMPTZ, ends_at TIMESTAMPTZ,
  reviewed_by        BIGINT REFERENCES users(id),
  reviewed_at        TIMESTAMPTZ,
  rejection_reason   TEXT,
  created_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ON campaigns (status, type) WHERE status = 'active';

CREATE TABLE campaign_questions (
  id           BIGSERIAL PRIMARY KEY,
  campaign_id  BIGINT NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
  position     SMALLINT NOT NULL,
  kind         TEXT NOT NULL,        -- short_text|long_text|single|multi|rating|yes_no
  prompt       TEXT NOT NULL,
  options      JSONB,
  required     BOOLEAN NOT NULL DEFAULT true,
  is_attention_check BOOLEAN NOT NULL DEFAULT false,
  expected_answer    TEXT,           -- attention checks only
  UNIQUE (campaign_id, position)
);
```

## Task slots (leased, not assigned)

```sql
CREATE TYPE slot_state AS ENUM
  ('open','leased','submitted','approved','rejected','expired','stalled');

CREATE TABLE task_slots (
  id             BIGSERIAL PRIMARY KEY,
  campaign_id    BIGINT NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
  state          slot_state NOT NULL DEFAULT 'open',
  panelist_id    BIGINT REFERENCES users(id),
  token          UUID NOT NULL DEFAULT gen_random_uuid(),  -- the ?pm_t= value
  leased_at      TIMESTAMPTZ,
  leased_until   TIMESTAMPTZ,
  submitted_at   TIMESTAMPTZ,
  resolved_at    TIMESTAMPTZ,
  attempt_count  SMALLINT NOT NULL DEFAULT 0,
  dwell_seconds  INT,
  scroll_depth   SMALLINT,           -- tag-reported, Phase 2
  payout_cents   BIGINT NOT NULL,
  reject_reason  TEXT,
  quality_flags  JSONB NOT NULL DEFAULT '[]',
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- the claim query's index
CREATE INDEX ON task_slots (campaign_id, id) WHERE state = 'open';
-- the lease sweeper's index
CREATE INDEX ON task_slots (leased_until) WHERE state = 'leased';
-- one attempt per panelist per campaign, ever
CREATE UNIQUE INDEX ON task_slots (campaign_id, panelist_id)
  WHERE panelist_id IS NOT NULL;
CREATE UNIQUE INDEX ON task_slots (token);
```

That partial unique index is the cheapest anti-fraud control in the schema: it makes
"same person answers the same study twenty times" structurally impossible rather than a
thing you have to detect.

```sql
CREATE TABLE responses (
  id            BIGSERIAL PRIMARY KEY,
  slot_id       BIGINT NOT NULL REFERENCES task_slots(id) ON DELETE CASCADE,
  question_id   BIGINT NOT NULL REFERENCES campaign_questions(id),
  answer_text   TEXT,
  answer_choice JSONB,
  answer_number SMALLINT,
  ms_to_answer  INT,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (slot_id, question_id)
);
CREATE INDEX ON responses (question_id);
```

## Money — double entry

One ledger, two currencies (`cents` for panelist cash, `credits` for business balance).
Every entry is immutable. Balances are `SUM()` and the columns above are caches.

```sql
CREATE TYPE ledger_currency AS ENUM ('cents','credits');
CREATE TYPE ledger_account_kind AS ENUM
  ('business_credits','panelist_payable','platform_revenue','platform_cost',
   'stripe_clearing','payout_clearing','promotional');

CREATE TABLE ledger_accounts (
  id          BIGSERIAL PRIMARY KEY,
  kind        ledger_account_kind NOT NULL,
  currency    ledger_currency NOT NULL,
  owner_user_id     BIGINT REFERENCES users(id),
  owner_business_id BIGINT REFERENCES businesses(id),
  UNIQUE (kind, owner_user_id, owner_business_id, currency)
);

CREATE TABLE ledger_transactions (
  id              BIGSERIAL PRIMARY KEY,
  kind            TEXT NOT NULL,   -- credit_purchase | campaign_reserve | slot_settle |
                                   -- payout | refund | adjustment | referral_bonus
  idempotency_key TEXT NOT NULL UNIQUE,
  reference_type  TEXT, reference_id BIGINT,
  actor_user_id   BIGINT REFERENCES users(id),
  memo            TEXT,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE ledger_entries (
  id             BIGSERIAL PRIMARY KEY,
  transaction_id BIGINT NOT NULL REFERENCES ledger_transactions(id),
  account_id     BIGINT NOT NULL REFERENCES ledger_accounts(id),
  currency       ledger_currency NOT NULL,
  amount         NUMERIC(16,4) NOT NULL,   -- cents or credits; sign = direction
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ON ledger_entries (account_id, created_at DESC);
```

**Invariant, asserted in a nightly job and in tests:** for every
`ledger_transactions.id` and every currency, `SUM(ledger_entries.amount) = 0`.
If it ever doesn't, page someone — the money is wrong.

Worked example, one approved feedback response (12 credits, $0.35 payout):

| Account | Currency | Amount |
|---|---|---|
| business_credits (biz 42) | credits | −12.00 |
| platform_revenue | credits | +12.00 |
| platform_cost | cents | −35 |
| panelist_payable (user 991) | cents | +35 |

`idempotency_key = 'slot_settle:<slot_id>'` — so a retried settlement is a no-op rather
than a double payment. Same pattern for `credit_purchase:<stripe_session_id>`.

## Payouts

```sql
CREATE TABLE payouts (
  id             BIGSERIAL PRIMARY KEY,
  panelist_id    BIGINT NOT NULL REFERENCES users(id),
  amount_cents   BIGINT NOT NULL CHECK (amount_cents >= 1000),  -- $10 minimum
  method         TEXT NOT NULL,
  destination    TEXT NOT NULL,
  status         TEXT NOT NULL DEFAULT 'requested',
       -- requested | approved | processing | paid | failed | rejected
  batch_id       BIGINT REFERENCES payout_batches(id),
  provider_ref   TEXT,
  failure_reason TEXT,
  requested_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
  paid_at        TIMESTAMPTZ
);
CREATE UNIQUE INDEX ON payouts (panelist_id)
  WHERE status IN ('requested','approved','processing');
```

That partial unique index prevents the classic double-withdrawal race: one in-flight
payout per panelist, enforced by the database rather than by application logic.

## Fraud

```sql
CREATE TABLE devices (
  id            BIGSERIAL PRIMARY KEY,
  fingerprint   TEXT NOT NULL,
  user_agent    TEXT, screen TEXT, timezone TEXT,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (fingerprint)
);

CREATE TABLE user_devices (
  user_id    BIGINT NOT NULL REFERENCES users(id),
  device_id  BIGINT NOT NULL REFERENCES devices(id),
  first_seen TIMESTAMPTZ NOT NULL DEFAULT now(),
  last_seen  TIMESTAMPTZ NOT NULL DEFAULT now(),
  ip_last    INET, country_last CHAR(2), seen_count INT NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, device_id)
);
-- a device on 3+ accounts is the strongest single fraud signal you have
CREATE INDEX ON user_devices (device_id);

CREATE TABLE risk_events (
  id         BIGSERIAL PRIMARY KEY,
  user_id    BIGINT REFERENCES users(id),
  slot_id    BIGINT REFERENCES task_slots(id),
  kind       TEXT NOT NULL,   -- shared_device | vpn_ip | speed | duplicate_text |
                              -- attention_fail | geo_jump | referral_ring
  severity   SMALLINT NOT NULL,
  detail     JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ON risk_events (user_id, created_at DESC);

CREATE TABLE audit_log (
  id           BIGSERIAL PRIMARY KEY,
  actor_user_id BIGINT REFERENCES users(id),
  action       TEXT NOT NULL,
  target_type  TEXT, target_id BIGINT,
  before_state JSONB, after_state JSONB,
  ip           INET,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

`audit_log` is append-only and must cover every admin action that touches money, trust
scores or account status. It is what you show a panelist who disputes a ban, and what your
accountant asks for.

## Retention

- `responses` — indefinite (it's the product), but strip PII from open text before it enters any aggregate export
- `risk_events`, `devices`, `user_devices` — 24 months rolling
- `audit_log`, `ledger_*` — 7 years (tax)
- Deleted accounts — anonymize `users` and `panel_profiles`, retain ledger rows with the user reference nulled and a tombstone id. Right-to-delete cannot be allowed to break the double-entry invariant; document this in the privacy policy.
