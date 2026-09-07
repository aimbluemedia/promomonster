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
