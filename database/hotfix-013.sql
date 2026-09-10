-- HOTFIX: migration 013 was skipped, so superadmin login crashes.
--
-- Symptom: HTTP 500 after signing in, and the error log says
--   Base table or view not found: 1146 Table '..._promo.login_attempts' doesn't exist
--
-- Paste this whole file into phpMyAdmin → your database → SQL, and press Go.
--
-- Run it AS IS. Some statements may report an error like
--   #1060 Duplicate column name  /  #1061 Duplicate key name
-- if part of 013 was already applied by hand. Those are safe to ignore — the
-- column is there, which is all that matters. Any OTHER error is real.
--
-- The last block records migrations 001-014 as applied so this cannot happen
-- again, and so migrate-web.php / diagnose.php stop reporting them as pending.

-- 1. The missing table. This is the one causing the 500. ---------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(254) NOT NULL,
    ip         VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY login_attempts_email_index (email, created_at),
    KEY login_attempts_ip_index (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. The missing audit-queue columns, one statement each so a duplicate on
--    any single column does not stop the rest. -----------------------------
ALTER TABLE audits ADD COLUMN status ENUM('new','in_progress','delivered','converted','declined') NOT NULL DEFAULT 'new' AFTER vertical;
ALTER TABLE audits ADD COLUMN notes TEXT NULL AFTER results;
ALTER TABLE audits ADD COLUMN handled_by_user_id BIGINT UNSIGNED NULL AFTER notes;
ALTER TABLE audits ADD COLUMN handled_at DATETIME NULL AFTER handled_by_user_id;
ALTER TABLE audits ADD KEY audits_status_index (status, created_at);
ALTER TABLE audits ADD CONSTRAINT audits_handler_fk FOREIGN KEY (handled_by_user_id) REFERENCES users (id) ON DELETE SET NULL;

-- 3. The same for the agency waitlist. --------------------------------------
ALTER TABLE waitlist ADD COLUMN status ENUM('new','contacted','approved','declined') NOT NULL DEFAULT 'new' AFTER role;
ALTER TABLE waitlist ADD COLUMN notes TEXT NULL AFTER source;
ALTER TABLE waitlist ADD KEY waitlist_status_index (status, created_at);

-- 4. Start tracking migrations, and record the ones already on this database.
CREATE TABLE IF NOT EXISTS migrations (
    filename   VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (filename) VALUES
    ('001_create_waitlist.sql'),
    ('006_drop_panel_schema.sql'),
    ('007_create_accounts.sql'),
    ('008_create_contacts_and_consent.sql'),
    ('009_create_requests_and_reviews.sql'),
    ('010_create_messaging_registration.sql'),
    ('011_create_audit_log.sql'),
    ('012_alter_waitlist_roles.sql'),
    ('013_audit_workflow.sql'),
    ('014_force_password_change.sql');
