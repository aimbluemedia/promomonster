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
