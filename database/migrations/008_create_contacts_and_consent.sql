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
