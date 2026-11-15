-- Turns the audit request list into a work queue the operator can actually run
-- Phase 0 from: every request has a state, an owner and somewhere to keep notes.

ALTER TABLE audits
    ADD COLUMN status ENUM('new','in_progress','delivered','converted','declined')
        NOT NULL DEFAULT 'new' AFTER vertical,
    ADD COLUMN notes TEXT NULL AFTER results,
    ADD COLUMN handled_by_user_id BIGINT UNSIGNED NULL AFTER notes,
    ADD COLUMN handled_at DATETIME NULL AFTER handled_by_user_id,
    ADD KEY audits_status_index (status, created_at),
    ADD CONSTRAINT audits_handler_fk FOREIGN KEY (handled_by_user_id)
        REFERENCES users (id) ON DELETE SET NULL;

-- Same for agency applications.
ALTER TABLE waitlist
    ADD COLUMN status ENUM('new','contacted','approved','declined')
        NOT NULL DEFAULT 'new' AFTER role,
    ADD COLUMN notes TEXT NULL AFTER source,
    ADD KEY waitlist_status_index (status, created_at);

-- Failed admin logins, so lockout survives a process restart and is visible.
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
