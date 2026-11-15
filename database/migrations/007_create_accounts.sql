-- Reviews platform: accounts, users and locations.

CREATE TABLE IF NOT EXISTS accounts (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                   VARCHAR(160) NOT NULL,
    plan                   ENUM('trial','starter','growth','pro','partner') NOT NULL DEFAULT 'trial',
    status                 ENUM('active','past_due','cancelled','suspended') NOT NULL DEFAULT 'active',
    stripe_customer_id     VARCHAR(64) NULL,
    subscription_renews_at DATETIME NULL,
    trial_ends_at          DATETIME NULL,
    -- Set when an agency manages this account on a client's behalf.
    partner_account_id     BIGINT UNSIGNED NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY accounts_stripe_unique (stripe_customer_id),
    KEY accounts_partner_index (partner_account_id),
    CONSTRAINT accounts_partner_fk FOREIGN KEY (partner_account_id)
        REFERENCES accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email             VARCHAR(254) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    first_name        VARCHAR(80) NOT NULL,
    last_name         VARCHAR(80) NOT NULL,
    is_admin          TINYINT(1) NOT NULL DEFAULT 0,   -- PromoMonster staff
    status            ENUM('pending_verification','active','suspended') NOT NULL DEFAULT 'pending_verification',
    email_verified_at DATETIME NULL,
    last_login_at     DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_users (
    account_id BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    -- 'staff' is the person who actually asks the customer. They need the
    -- playbook and their own send stats, not billing.
    role       ENUM('owner','manager','staff') NOT NULL DEFAULT 'owner',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id, user_id),
    KEY account_users_user_index (user_id),
    CONSTRAINT account_users_account_fk FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE,
    CONSTRAINT account_users_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id        BIGINT UNSIGNED NOT NULL,
    name              VARCHAR(160) NOT NULL,
    vertical          VARCHAR(60) NULL,           -- selects the playbook
    -- Google. There is no API that posts a review; the review URL is a deep
    -- link built from the Place ID. See docs/05-compliance.md section 5.
    google_place_id   VARCHAR(255) NULL,
    google_review_url VARCHAR(500) NULL,
    gbp_location_name VARCHAR(255) NULL,          -- Business Profile API resource name
    gbp_connected_at  DATETIME NULL,
    address_line1     VARCHAR(160) NULL,
    city              VARCHAR(80) NULL,
    region            VARCHAR(80) NULL,
    postal_code       VARCHAR(16) NULL,
    country_code      CHAR(2) NOT NULL DEFAULT 'US',
    timezone          VARCHAR(64) NOT NULL DEFAULT 'America/Phoenix',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY locations_account_index (account_id),
    KEY locations_place_index (google_place_id),
    CONSTRAINT locations_account_fk FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
