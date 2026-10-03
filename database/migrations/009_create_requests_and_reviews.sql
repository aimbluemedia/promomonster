-- Review requests, message events, monitored reviews, templates and playbooks.

CREATE TABLE IF NOT EXISTS templates (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id  BIGINT UNSIGNED NULL,           -- NULL = system template
    vertical    VARCHAR(60) NULL,
    channel     ENUM('sms','email') NOT NULL,
    kind        ENUM('request','follow_up') NOT NULL DEFAULT 'request',
    name        VARCHAR(120) NOT NULL,
    subject     VARCHAR(200) NULL,              -- email only
    body        TEXT NOT NULL,
    is_system   TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY templates_account_index (account_id),
    KEY templates_lookup_index (vertical, channel, kind),
    CONSTRAINT templates_account_fk FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The playbook for a vertical, as data rather than code so it can be edited and
-- eventually tuned from measured results. See docs/06-playbooks.md.
CREATE TABLE IF NOT EXISTS playbooks (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vertical        VARCHAR(60) NOT NULL,
    display_name    VARCHAR(120) NOT NULL,
    trigger_moment  VARCHAR(255) NOT NULL,
    who_asks        VARCHAR(120) NOT NULL,
    primary_channel ENUM('sms','email','qr') NOT NULL,
    verbal_ask      TEXT NOT NULL,
    follow_up_days  TINYINT UNSIGNED NULL,
    offline_method  VARCHAR(255) NULL,
    objection       TEXT NULL,
    compliance_note TEXT NULL,                  -- e.g. HIPAA, state bar rules
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY playbooks_vertical_unique (vertical)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One ask sent to one contact.
--
-- Note what is NOT here: any sentiment field, predicted rating, or alternate
-- destination. Review gating is prohibited (docs/05-compliance.md section 1),
-- so the schema offers nowhere to put it. Every request points at the same
-- public review URL for everyone.
CREATE TABLE IF NOT EXISTS review_requests (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id       BIGINT UNSIGNED NOT NULL,
    contact_id        BIGINT UNSIGNED NOT NULL,
    template_id       BIGINT UNSIGNED NULL,
    channel           ENUM('sms','email') NOT NULL,
    status            ENUM('queued','scheduled','sent','delivered','failed',
                           'clicked','review_detected','cancelled') NOT NULL DEFAULT 'queued',
    -- Instrumentation for the playbook dataset. Which trigger, which sender and
    -- which timing actually produce reviews, per vertical -- this is the moat.
    -- docs/06-playbooks.md section 11.
    trigger_moment    VARCHAR(60) NULL,
    asked_by_user_id  BIGINT UNSIGNED NULL,
    is_follow_up      TINYINT(1) NOT NULL DEFAULT 0,
    parent_request_id BIGINT UNSIGNED NULL,
    scheduled_for     DATETIME NULL,            -- respects recipient quiet hours
    sent_at           DATETIME NULL,
    first_clicked_at  DATETIME NULL,
    review_detected_at DATETIME NULL,
    failure_reason    VARCHAR(255) NULL,
    click_token       CHAR(36) NOT NULL,        -- per-request link for attribution
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY requests_click_token_unique (click_token),
    KEY requests_location_index (location_id, created_at),
    KEY requests_contact_index (contact_id, created_at),
    KEY requests_due_index (status, scheduled_for),
    KEY requests_parent_index (parent_request_id),
    CONSTRAINT requests_location_fk FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE CASCADE,
    CONSTRAINT requests_contact_fk FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
    CONSTRAINT requests_template_fk FOREIGN KEY (template_id) REFERENCES templates (id) ON DELETE SET NULL,
    CONSTRAINT requests_asker_fk FOREIGN KEY (asked_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT requests_parent_fk FOREIGN KEY (parent_request_id) REFERENCES review_requests (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_events (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id   BIGINT UNSIGNED NOT NULL,
    type         ENUM('queued','sent','delivered','failed','opened','clicked',
                      'bounced','complained','stop','help') NOT NULL,
    provider_ref VARCHAR(191) NULL,
    detail       JSON NULL,
    occurred_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY events_request_index (request_id, occurred_at),
    KEY events_type_index (type, occurred_at),
    -- Provider webhooks retry; the same delivery receipt must not be recorded
    -- twice.
    UNIQUE KEY events_idempotency_unique (request_id, type, provider_ref),
    CONSTRAINT events_request_fk FOREIGN KEY (request_id) REFERENCES review_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reviews read back from the platform. Requires Google Business Profile API
-- access, which must be applied for -- docs/05-compliance.md section 5.
CREATE TABLE IF NOT EXISTS reviews (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id     BIGINT UNSIGNED NOT NULL,
    platform        ENUM('google','facebook','trustpilot') NOT NULL DEFAULT 'google',
    external_id     VARCHAR(255) NOT NULL,
    author_name     VARCHAR(160) NULL,
    rating          TINYINT UNSIGNED NULL,
    body            TEXT NULL,
    posted_at       DATETIME NULL,
    replied_at      DATETIME NULL,
    reply_body      TEXT NULL,
    -- Best-effort link back to the request that produced it, for attribution.
    request_id      BIGINT UNSIGNED NULL,
    fetched_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY reviews_platform_external_unique (platform, external_id),
    KEY reviews_location_index (location_id, posted_at),
    KEY reviews_unanswered_index (location_id, replied_at),
    CONSTRAINT reviews_location_fk FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE CASCADE,
    CONSTRAINT reviews_request_fk FOREIGN KEY (request_id) REFERENCES review_requests (id) ON DELETE SET NULL,
    CONSTRAINT reviews_rating_range CHECK (rating IS NULL OR rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Private feedback a customer sends the business instead of a public review.
-- Kept strictly separate from `reviews`: republishing this as a review would be
-- manufacturing testimonials (docs/05-compliance.md section 6).
CREATE TABLE IF NOT EXISTS private_feedback (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id BIGINT UNSIGNED NOT NULL,
    contact_id  BIGINT UNSIGNED NULL,
    request_id  BIGINT UNSIGNED NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY feedback_location_index (location_id, created_at),
    CONSTRAINT feedback_location_fk FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE CASCADE,
    CONSTRAINT feedback_contact_fk FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE SET NULL,
    CONSTRAINT feedback_request_fk FOREIGN KEY (request_id) REFERENCES review_requests (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS growth_scores (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id  BIGINT UNSIGNED NOT NULL,
    score        TINYINT UNSIGNED NOT NULL,
    components   JSON NOT NULL,                 -- each check and whether it passed
    next_action  VARCHAR(255) NULL,
    computed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY growth_location_index (location_id, computed_at),
    CONSTRAINT growth_location_fk FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE CASCADE,
    CONSTRAINT growth_score_range CHECK (score BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The free Review Audit: the acquisition engine. Runs before signup, so it
-- carries an email rather than an account.
CREATE TABLE IF NOT EXISTS audits (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_name  VARCHAR(200) NOT NULL,
    google_place_id VARCHAR(255) NULL,
    email          VARCHAR(254) NULL,
    vertical       VARCHAR(60) NULL,
    results        JSON NULL,                   -- rating, count, velocity, competitor set
    account_id     BIGINT UNSIGNED NULL,        -- set if it converts
    ip             VARCHAR(45) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY audits_email_index (email),
    KEY audits_created_index (created_at),
    CONSTRAINT audits_account_fk FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
