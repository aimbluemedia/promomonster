-- PromoMonster — full database schema (MySQL / MariaDB)
--
-- One-shot import for phpMyAdmin: select your database, open the SQL tab,
-- paste this file, and run it. Equivalent to running every file in
-- database/migrations/ in order.
--
-- FOR A DATABASE THAT ALREADY RAN THE OLD PANEL SCHEMA (migrations 002-005):
-- do NOT paste this file. Run 'php database/migrate.php' instead, or apply
-- 006 onwards by hand -- 006 removes the panel tables first.
--
-- Generated from database/migrations/ — edit those, not this file.

SET NAMES utf8mb4;

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
-- 006_drop_panel_schema.sql
-- ============================================================
-- The product pivoted from a research panel to a reviews platform. These tables
-- belonged to the panel and have no equivalent here.
--
-- Safe on a fresh install (nothing to drop) and on a server that already ran
-- migrations 002-005 (which are no longer shipped).
--
-- Foreign key checks are disabled for the teardown rather than relying on drop
-- order: the panel tables reference each other in both directions (risk_events
-- points at task_slots, which points at campaigns), so no single ordering
-- satisfies every constraint.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS responses;
DROP TABLE IF EXISTS task_slots;
DROP TABLE IF EXISTS campaign_questions;
DROP TABLE IF EXISTS campaigns;
DROP TABLE IF EXISTS ledger_entries;
DROP TABLE IF EXISTS ledger_transactions;
DROP TABLE IF EXISTS ledger_accounts;
DROP TABLE IF EXISTS payouts;
DROP TABLE IF EXISTS payout_batches;
DROP TABLE IF EXISTS risk_events;
DROP TABLE IF EXISTS user_devices;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS panel_profiles;
DROP TABLE IF EXISTS panelists;
DROP TABLE IF EXISTS businesses;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 007_create_accounts.sql
-- ============================================================
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

-- ============================================================
-- 008_create_contacts_and_consent.sql
-- ============================================================
-- Contacts, consent records and the global suppression list.
--
-- This is the compliance core. docs/05-compliance.md section 4 explains why
-- each piece exists; the short version is that consent and opt-out have to be
-- enforced by the schema, not by policy.

CREATE TABLE IF NOT EXISTS contacts (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id    BIGINT UNSIGNED NOT NULL,
    first_name     VARCHAR(80) NULL,
    last_name      VARCHAR(80) NULL,
    email          VARCHAR(254) NULL,
    phone_e164     VARCHAR(20) NULL,            -- always E.164, never as typed
    -- Quiet hours are computed against the RECIPIENT's timezone, not the
    -- business's. Defaults to the location's until we know better.
    timezone       VARCHAR(64) NULL,
    source         ENUM('manual','csv','api','integration') NOT NULL DEFAULT 'manual',
    external_ref   VARCHAR(120) NULL,           -- job or appointment id from an integration
    -- Denormalised opt-out flags for fast filtering. The suppression table
    -- remains authoritative; these are a cache, checked as a first pass.
    email_opted_out TINYINT(1) NOT NULL DEFAULT 0,
    sms_opted_out   TINYINT(1) NOT NULL DEFAULT 0,
    last_requested_at DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- One record per person per location, per channel identifier. NULLs are
    -- distinct in MySQL, so a contact with only an email does not collide with
    -- one that has only a phone.
    UNIQUE KEY contacts_location_email_unique (location_id, email),
    UNIQUE KEY contacts_location_phone_unique (location_id, phone_e164),
    KEY contacts_location_index (location_id, created_at),
    CONSTRAINT contacts_location_fk FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE CASCADE,
    -- A contact with neither channel cannot be messaged and should not exist.
    CONSTRAINT contacts_has_channel CHECK (email IS NOT NULL OR phone_e164 IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable evidence that this person agreed to be contacted. Append only:
-- never UPDATE a row here. This is what is produced if a TCPA claim arrives.
CREATE TABLE IF NOT EXISTS consent_records (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id    BIGINT UNSIGNED NOT NULL,
    channel       ENUM('sms','email') NOT NULL,
    method        ENUM('web_form','in_person','phone','paper','import_attested','api') NOT NULL,
    -- The exact wording the person agreed to, captured verbatim at the time.
    evidence_text TEXT NOT NULL,
    evidence_url  VARCHAR(500) NULL,
    captured_ip   VARCHAR(45) NULL,
    -- Who attested to it, for bulk imports where the business asserts consent.
    attested_by_user_id BIGINT UNSIGNED NULL,
    captured_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY consent_contact_index (contact_id, channel, captured_at),
    CONSTRAINT consent_contact_fk FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
    CONSTRAINT consent_attester_fk FOREIGN KEY (attested_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GLOBAL opt-out. Deliberately not scoped to an account or location: once a
-- person says STOP, they are done across the whole platform. Deleting an
-- account must not resurrect them, which is why there is no foreign key here
-- and why the address is stored hashed rather than in the clear.
CREATE TABLE IF NOT EXISTS suppressions (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel      ENUM('sms','email') NOT NULL,
    address_hash CHAR(64) NOT NULL,             -- sha256 of the E.164 number or lowercased email
    reason       ENUM('stop_keyword','unsubscribe','complaint','bounce','manual','carrier') NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY suppressions_channel_address_unique (channel, address_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 009_create_requests_and_reviews.sql
-- ============================================================
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

-- ============================================================
-- 010_create_messaging_registration.sql
-- ============================================================
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

-- ============================================================
-- 011_create_audit_log.sql
-- ============================================================
-- Append-only record of anything that changes account state, billing, contact
-- data or sending permissions. What you produce when a customer disputes
-- something, and what a TCPA or carrier inquiry asks for.

CREATE TABLE IF NOT EXISTS audit_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    account_id    BIGINT UNSIGNED NULL,
    action        VARCHAR(80) NOT NULL,
    target_type   VARCHAR(40) NULL,
    target_id     BIGINT UNSIGNED NULL,
    before_state  JSON NULL,
    after_state   JSON NULL,
    ip            VARCHAR(45) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY audit_actor_index (actor_user_id, created_at),
    KEY audit_account_index (account_id, created_at),
    KEY audit_target_index (target_type, target_id),
    CONSTRAINT audit_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT audit_account_fk FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 012_alter_waitlist_roles.sql
-- ============================================================
-- The waitlist now serves the reviews platform's two audiences. 'panelist' is
-- retained only so rows captured before the pivot still validate.

ALTER TABLE waitlist
    MODIFY COLUMN role ENUM('business','agency','panelist') NOT NULL;

