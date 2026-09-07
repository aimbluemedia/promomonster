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
