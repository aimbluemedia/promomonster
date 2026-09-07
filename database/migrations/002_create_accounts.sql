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
