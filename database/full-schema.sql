-- PromoMonster — full database schema (MySQL / MariaDB)
--
-- One-shot import for phpMyAdmin: select your database, open the SQL tab,
-- paste this file, and run it. Equivalent to running every file in
-- database/migrations/ in order.
--
-- Requires MySQL 8.0+ or MariaDB 10.6+ (the Phase 1 task-slot claim uses
-- SELECT ... FOR UPDATE SKIP LOCKED; without it two panelists can be
-- handed the same slot).
--
-- Phase 0 needs only 001. The rest is the Phase 1 platform schema and is
-- harmless to create ahead of time.
--
-- Generated from database/migrations/ — edit those, not this file.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 001_create_waitlist.sql
-- ============================================================
-- Phase 0 schema: waitlist capture and the rate-limit bucket it needs.
--
-- The full platform schema (users, campaigns, task_slots, the double-entry
-- ledger, payouts, fraud tables) is specified in docs/04-data-model.md and
-- lands in Phase 1. Deliberately not built yet -- docs/00-execution-plan.md
-- gates platform work behind 20 hand-sold studies.

CREATE TABLE IF NOT EXISTS waitlist (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email          VARCHAR(254)    NOT NULL,
    role           ENUM('business','panelist') NOT NULL,
    name           VARCHAR(120)    NULL,

    -- business side
    company        VARCHAR(160)    NULL,
    website        VARCHAR(300)    NULL,
    goal           TEXT            NULL,

    -- panel side
    country        CHAR(2)         NULL,
    region         VARCHAR(80)     NULL,
    postal_code    VARCHAR(16)     NULL,

    -- provenance
    source         VARCHAR(80)     NULL,
    referrer       VARCHAR(500)    NULL,
    ip             VARCHAR(45)     NULL,
    user_agent     VARCHAR(500)    NULL,

    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- One signup per email per side. A repeat submission is treated as success
    -- rather than an error, so the visitor never sees a failure for it.
    UNIQUE KEY waitlist_email_role_unique (email, role),
    KEY waitlist_created_at_index (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket_key        CHAR(64)  NOT NULL,
    attempts          INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME  NOT NULL,
    PRIMARY KEY (bucket_key),
    KEY rate_limits_window_index (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 002_create_accounts.sql
-- ============================================================
-- Phase 1: identity and accounts.
-- MySQL/MariaDB port of docs/04-data-model.md. Porting notes:
--   BIGSERIAL   -> BIGINT UNSIGNED AUTO_INCREMENT
--   CITEXT      -> VARCHAR with a _ci collation (case-insensitive by default)
--   TIMESTAMPTZ -> DATETIME storing UTC (TIMESTAMP has a 2038 limit)
--   JSONB       -> JSON
--   INET        -> VARCHAR(45), long enough for IPv6
--   CREATE TYPE -> inline ENUM

CREATE TABLE IF NOT EXISTS users (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email             VARCHAR(254) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    first_name        VARCHAR(80)  NOT NULL,
    last_name         VARCHAR(80)  NOT NULL,
    role              ENUM('panelist','business_owner','business_member','reviewer','admin') NOT NULL,
    status            ENUM('pending_verification','active','suspended','banned')
                      NOT NULL DEFAULT 'pending_verification',
    email_verified_at DATETIME NULL,
    country_code      CHAR(2)      NOT NULL,
    region_code       VARCHAR(80)  NULL,
    postal_code       VARCHAR(16)  NULL,
    date_of_birth     DATE         NULL,
    timezone          VARCHAR(64)  NULL,
    last_login_at     DATETIME     NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email),
    KEY users_role_status_index (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS businesses (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id          BIGINT UNSIGNED NOT NULL,
    company_name           VARCHAR(160) NOT NULL,
    primary_website        VARCHAR(300) NULL,
    industry               VARCHAR(80)  NULL,
    -- Blocks traffic products. Incentivised visits are invalid traffic under
    -- publisher policies and can terminate the customer's ad account.
    runs_ad_network        TINYINT(1)   NOT NULL DEFAULT 0,
    stripe_customer_id     VARCHAR(64)  NULL,
    subscription_plan      VARCHAR(32)  NULL,
    subscription_status    VARCHAR(32)  NULL,
    subscription_renews_at DATETIME     NULL,
    -- Cache of the credit ledger, NOT the source of truth. Never write this
    -- outside a ledger transaction; reconciled nightly.
    credit_balance         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    credits_reserved       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    risk_tier              TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY businesses_stripe_customer_unique (stripe_customer_id),
    KEY businesses_owner_index (owner_user_id),
    CONSTRAINT businesses_owner_fk FOREIGN KEY (owner_user_id)
        REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panelists (
    user_id             BIGINT UNSIGNED NOT NULL,
    -- Money is integer cents. Never floats, anywhere.
    balance_cents       BIGINT NOT NULL DEFAULT 0,
    pending_cents       BIGINT NOT NULL DEFAULT 0,
    lifetime_cents      BIGINT NOT NULL DEFAULT 0,
    trust_score         SMALLINT NOT NULL DEFAULT 50,
    payout_method       VARCHAR(32)  NULL,
    payout_destination  VARBINARY(512) NULL,   -- encrypted at rest
    w9_received_at      DATETIME NULL,
    tax_year_cents      BIGINT NOT NULL DEFAULT 0,
    referred_by_user_id BIGINT UNSIGNED NULL,
    first_payout_at     DATETIME NULL,
    banned_reason       VARCHAR(500) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY panelists_trust_index (trust_score),
    KEY panelists_referrer_index (referred_by_user_id),
    CONSTRAINT panelists_user_fk FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT panelists_referrer_fk FOREIGN KEY (referred_by_user_id)
        REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT panelists_trust_range CHECK (trust_score BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The asset that lets targeted studies be sold at 3-5x the untargeted price.
-- Frequently-targeted attributes are promoted to generated columns so they can
-- be indexed: MySQL cannot index a JSON document generically the way Postgres
-- does with GIN.
CREATE TABLE IF NOT EXISTS panel_profiles (
    user_id         BIGINT UNSIGNED NOT NULL,
    age_band        VARCHAR(16) NULL,
    gender          VARCHAR(24) NULL,
    income_band     VARCHAR(24) NULL,
    housing         ENUM('own','rent','other') NULL,
    household_size  TINYINT UNSIGNED NULL,
    children_at_home TINYINT(1) NULL,
    education       VARCHAR(40) NULL,
    employment      VARCHAR(40) NULL,
    industry        VARCHAR(80) NULL,
    job_function    VARCHAR(80) NULL,
    company_size    VARCHAR(24) NULL,
    attributes      JSON NULL,
    has_pets        TINYINT(1)  AS (JSON_VALUE(attributes, '$.pets'))            STORED,
    owns_vehicle    TINYINT(1)  AS (JSON_VALUE(attributes, '$.vehicle'))         STORED,
    intent_home     TINYINT(1)  AS (JSON_VALUE(attributes, '$.intent_home'))     STORED,
    intent_auto     TINYINT(1)  AS (JSON_VALUE(attributes, '$.intent_auto'))     STORED,
    completed_at    DATETIME NULL,
    refreshed_at    DATETIME NULL,
    PRIMARY KEY (user_id),
    KEY profiles_targeting_index (age_band, income_band, housing),
    KEY profiles_intent_index (intent_home, intent_auto),
    CONSTRAINT panel_profiles_user_fk FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 003_create_campaigns.sql
-- ============================================================
-- Phase 1: campaigns, questions, task slots and responses.

CREATE TABLE IF NOT EXISTS campaigns (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id       BIGINT UNSIGNED NOT NULL,
    name              VARCHAR(160) NOT NULL,
    type              ENUM('site_feedback','head_to_head','serp_test','creative_test',
                           'verified_visit','targeted_visit','content_read','unverified_visit')
                      NOT NULL,
    status            ENUM('draft','pending_review','approved','active','paused',
                           'completed','rejected','cancelled')
                      NOT NULL DEFAULT 'draft',
    urls              JSON NOT NULL,               -- 1 url, or 2+ for head-to-head
    min_dwell_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    targeting         JSON NULL,                   -- geo, device, profile predicates
    min_trust_score   SMALLINT NOT NULL DEFAULT 50,
    slots_total       INT UNSIGNED NOT NULL,
    slots_completed   INT UNSIGNED NOT NULL DEFAULT 0,
    credits_per_slot  DECIMAL(6,2) NOT NULL,
    credits_reserved  DECIMAL(12,2) NOT NULL,
    payout_cents      BIGINT NOT NULL,             -- per completed slot
    daily_slot_cap    INT UNSIGNED NULL,
    starts_at         DATETIME NULL,
    ends_at           DATETIME NULL,
    reviewed_by       BIGINT UNSIGNED NULL,
    reviewed_at       DATETIME NULL,
    rejection_reason  VARCHAR(500) NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY campaigns_business_index (business_id),
    KEY campaigns_status_type_index (status, type),
    CONSTRAINT campaigns_business_fk FOREIGN KEY (business_id)
        REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT campaigns_reviewer_fk FOREIGN KEY (reviewed_by)
        REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT campaigns_slots_positive CHECK (slots_total > 0),
    CONSTRAINT campaigns_payout_positive CHECK (payout_cents >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_questions (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id        BIGINT UNSIGNED NOT NULL,
    position           SMALLINT UNSIGNED NOT NULL,
    kind               ENUM('short_text','long_text','single','multi','rating','yes_no') NOT NULL,
    prompt             VARCHAR(500) NOT NULL,
    options            JSON NULL,
    required           TINYINT(1) NOT NULL DEFAULT 1,
    is_attention_check TINYINT(1) NOT NULL DEFAULT 0,
    expected_answer    VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY questions_campaign_position_unique (campaign_id, position),
    CONSTRAINT questions_campaign_fk FOREIGN KEY (campaign_id)
        REFERENCES campaigns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Slots are LEASED, never assigned. Claim with:
--
--   SELECT id FROM task_slots
--    WHERE campaign_id = ? AND state = 'open'
--    ORDER BY id LIMIT 1
--    FOR UPDATE SKIP LOCKED;
--
-- SKIP LOCKED requires MySQL 8.0+ or MariaDB 10.6+. On an older server it is a
-- syntax error, not a silent fallback -- check the version before deploying,
-- because without it concurrent claims serialise and, if the clause is simply
-- dropped, two panelists can be handed the same slot.
CREATE TABLE IF NOT EXISTS task_slots (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id   BIGINT UNSIGNED NOT NULL,
    state         ENUM('open','leased','submitted','approved','rejected','expired','stalled')
                  NOT NULL DEFAULT 'open',
    panelist_id   BIGINT UNSIGNED NULL,
    token         CHAR(36) NOT NULL,               -- app-generated UUID; the ?pm_t= value
    leased_at     DATETIME NULL,
    leased_until  DATETIME NULL,
    submitted_at  DATETIME NULL,
    resolved_at   DATETIME NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    dwell_seconds INT UNSIGNED NULL,
    scroll_depth  TINYINT UNSIGNED NULL,
    payout_cents  BIGINT NOT NULL,
    reject_reason VARCHAR(255) NULL,
    quality_flags JSON NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slots_token_unique (token),
    -- One attempt per panelist per campaign, ever. In MySQL a UNIQUE key treats
    -- NULLs as distinct, so unclaimed slots (panelist_id NULL) coexist freely
    -- while a second claim by the same panelist is rejected by the database.
    -- This is the same behaviour Postgres gets from a partial unique index.
    UNIQUE KEY slots_campaign_panelist_unique (campaign_id, panelist_id),
    KEY slots_claim_index (campaign_id, state, id),
    KEY slots_sweeper_index (state, leased_until),
    CONSTRAINT slots_campaign_fk FOREIGN KEY (campaign_id)
        REFERENCES campaigns (id) ON DELETE CASCADE,
    CONSTRAINT slots_panelist_fk FOREIGN KEY (panelist_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS responses (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slot_id       BIGINT UNSIGNED NOT NULL,
    question_id   BIGINT UNSIGNED NOT NULL,
    answer_text   TEXT NULL,
    answer_choice JSON NULL,
    answer_number SMALLINT NULL,
    ms_to_answer  INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY responses_slot_question_unique (slot_id, question_id),
    KEY responses_question_index (question_id),
    CONSTRAINT responses_slot_fk FOREIGN KEY (slot_id)
        REFERENCES task_slots (id) ON DELETE CASCADE,
    CONSTRAINT responses_question_fk FOREIGN KEY (question_id)
        REFERENCES campaign_questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 004_create_ledger.sql
-- ============================================================
-- Phase 1: double-entry ledger and payouts.
--
-- Balances are DERIVED from ledger_entries. The cached columns on businesses
-- and panelists are conveniences, reconciled nightly. Never move money by
-- updating a balance column.

CREATE TABLE IF NOT EXISTS ledger_accounts (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kind              ENUM('business_credits','panelist_payable','platform_revenue',
                           'platform_cost','stripe_clearing','payout_clearing','promotional') NOT NULL,
    currency          ENUM('cents','credits') NOT NULL,
    owner_user_id     BIGINT UNSIGNED NULL,
    owner_business_id BIGINT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ledger_accounts_identity_unique (kind, currency, owner_user_id, owner_business_id),
    CONSTRAINT ledger_accounts_user_fk FOREIGN KEY (owner_user_id)
        REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ledger_accounts_business_fk FOREIGN KEY (owner_business_id)
        REFERENCES businesses (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ledger_transactions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kind            ENUM('credit_purchase','campaign_reserve','campaign_release','slot_settle',
                         'payout','refund','adjustment','referral_bonus') NOT NULL,
    -- Makes every money mutation replay-safe: a retried settlement collides
    -- here instead of paying twice. e.g. 'slot_settle:12345'.
    idempotency_key VARCHAR(191) NOT NULL,
    reference_type  VARCHAR(40) NULL,
    reference_id    BIGINT UNSIGNED NULL,
    actor_user_id   BIGINT UNSIGNED NULL,
    memo            VARCHAR(500) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ledger_transactions_idempotency_unique (idempotency_key),
    KEY ledger_transactions_reference_index (reference_type, reference_id),
    CONSTRAINT ledger_transactions_actor_fk FOREIGN KEY (actor_user_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ledger_entries (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id BIGINT UNSIGNED NOT NULL,
    account_id     BIGINT UNSIGNED NOT NULL,
    currency       ENUM('cents','credits') NOT NULL,
    amount         DECIMAL(16,4) NOT NULL,   -- sign carries direction
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ledger_entries_account_index (account_id, created_at),
    KEY ledger_entries_transaction_index (transaction_id),
    CONSTRAINT ledger_entries_transaction_fk FOREIGN KEY (transaction_id)
        REFERENCES ledger_transactions (id) ON DELETE RESTRICT,
    CONSTRAINT ledger_entries_account_fk FOREIGN KEY (account_id)
        REFERENCES ledger_accounts (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- INVARIANT, asserted nightly and in tests: for every transaction_id and every
-- currency, SUM(amount) = 0. If it ever does not, the money is wrong -- page
-- someone.
--
--   SELECT transaction_id, currency, SUM(amount) AS drift
--     FROM ledger_entries
--    GROUP BY transaction_id, currency
--   HAVING drift <> 0;

CREATE TABLE IF NOT EXISTS payout_batches (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    method       VARCHAR(32) NOT NULL,
    status       ENUM('open','processing','paid','failed') NOT NULL DEFAULT 'open',
    total_cents  BIGINT NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payouts (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    panelist_id    BIGINT UNSIGNED NOT NULL,
    amount_cents   BIGINT NOT NULL,
    method         VARCHAR(32) NOT NULL,
    destination    VARBINARY(512) NOT NULL,
    status         ENUM('requested','approved','processing','paid','failed','rejected')
                   NOT NULL DEFAULT 'requested',
    batch_id       BIGINT UNSIGNED NULL,
    provider_ref   VARCHAR(191) NULL,
    failure_reason VARCHAR(500) NULL,
    requested_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at        DATETIME NULL,
    -- Postgres expresses "one in-flight payout per panelist" as a partial
    -- unique index. MySQL has no partial indexes, so this generated column
    -- holds the panelist id only while the payout is in flight and NULL
    -- otherwise; UNIQUE over it, with NULLs distinct, gives the same guarantee.
    -- Without this, a double-click can withdraw the same balance twice.
    active_payout_key BIGINT UNSIGNED
        AS (IF(status IN ('requested','approved','processing'), panelist_id, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY payouts_one_in_flight_unique (active_payout_key),
    KEY payouts_panelist_index (panelist_id, requested_at),
    KEY payouts_batch_index (batch_id),
    CONSTRAINT payouts_panelist_fk FOREIGN KEY (panelist_id)
        REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT payouts_batch_fk FOREIGN KEY (batch_id)
        REFERENCES payout_batches (id) ON DELETE SET NULL,
    CONSTRAINT payouts_minimum CHECK (amount_cents >= 1000)   -- $10 minimum
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 005_create_fraud_and_audit.sql
-- ============================================================
-- Phase 1: fraud signals and the audit trail.

CREATE TABLE IF NOT EXISTS devices (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fingerprint CHAR(64) NOT NULL,
    user_agent  VARCHAR(500) NULL,
    screen      VARCHAR(32)  NULL,
    timezone    VARCHAR(64)  NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY devices_fingerprint_unique (fingerprint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_devices (
    user_id      BIGINT UNSIGNED NOT NULL,
    device_id    BIGINT UNSIGNED NOT NULL,
    first_seen   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_last      VARCHAR(45) NULL,
    country_last CHAR(2) NULL,
    seen_count   INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, device_id),
    -- A device on 3+ accounts is the strongest single fraud signal available,
    -- so this index exists to be queried device-first:
    --   SELECT device_id, COUNT(*) c FROM user_devices
    --    GROUP BY device_id HAVING c >= 3;
    KEY user_devices_device_index (device_id),
    CONSTRAINT user_devices_user_fk FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT user_devices_device_fk FOREIGN KEY (device_id)
        REFERENCES devices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS risk_events (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NULL,
    slot_id    BIGINT UNSIGNED NULL,
    kind       ENUM('shared_device','vpn_ip','speed','duplicate_text','attention_fail',
                    'geo_jump','referral_ring','ai_text','straight_lining') NOT NULL,
    severity   TINYINT UNSIGNED NOT NULL,
    detail     JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY risk_events_user_index (user_id, created_at),
    KEY risk_events_kind_index (kind, created_at),
    CONSTRAINT risk_events_user_fk FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT risk_events_slot_fk FOREIGN KEY (slot_id)
        REFERENCES task_slots (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only. Covers every admin action touching money, trust scores or
-- account status. This is what you show a panelist disputing a ban, and what
-- your accountant asks for.
CREATE TABLE IF NOT EXISTS audit_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    action        VARCHAR(80) NOT NULL,
    target_type   VARCHAR(40) NULL,
    target_id     BIGINT UNSIGNED NULL,
    before_state  JSON NULL,
    after_state   JSON NULL,
    ip            VARCHAR(45) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY audit_log_actor_index (actor_user_id, created_at),
    KEY audit_log_target_index (target_type, target_id),
    CONSTRAINT audit_log_actor_fk FOREIGN KEY (actor_user_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
