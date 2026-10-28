-- A2P 10DLC registration state, per account.
--
-- Every business that sends SMS needs its own brand and campaign registered
-- with the carriers. Unregistered traffic is blocked, not merely flagged, and
-- registration takes days and can be rejected -- so this is modelled as a
-- first-class workflow with a pending state, not a boolean.
-- docs/05-compliance.md section 4.

CREATE TABLE IF NOT EXISTS messaging_brands (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id        BIGINT UNSIGNED NOT NULL,
    provider          VARCHAR(40) NOT NULL,
    provider_brand_id VARCHAR(120) NULL,
    entity_type       ENUM('sole_proprietor','private_company','non_profit') NOT NULL,
    legal_name        VARCHAR(200) NOT NULL,
    ein               VARCHAR(32) NULL,
    status            ENUM('draft','submitted','pending_review','approved','rejected')
                      NOT NULL DEFAULT 'draft',
    rejection_reason  VARCHAR(500) NULL,
    submitted_at      DATETIME NULL,
    approved_at       DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY brands_account_unique (account_id),
    KEY brands_status_index (status),
    CONSTRAINT brands_account_fk FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messaging_campaigns (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id             BIGINT UNSIGNED NOT NULL,
    provider_campaign_id VARCHAR(120) NULL,
    use_case             VARCHAR(60) NOT NULL DEFAULT 'CUSTOMER_CARE',
    status               ENUM('draft','submitted','pending_review','approved','rejected','suspended')
                         NOT NULL DEFAULT 'draft',
    -- Carrier-assigned throughput. Sole proprietor registrations are heavily
    -- limited, which caps how fast a small account can send.
    messages_per_day     INT UNSIGNED NULL,
    sending_number       VARCHAR(20) NULL,
    rejection_reason     VARCHAR(500) NULL,
    approved_at          DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY campaigns_brand_index (brand_id),
    KEY campaigns_status_index (status),
    CONSTRAINT messaging_campaigns_brand_fk FOREIGN KEY (brand_id)
        REFERENCES messaging_brands (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
