-- Forgotten-password links for the members login.
--
-- ---------------------------------------------------------------------------
-- WRITTEN FOR phpMyAdmin, like 018: ASCII only, and every statement is safe to
-- run more than once. This one needs no information_schema guards because it
-- only adds a table, and CREATE TABLE IF NOT EXISTS is idempotent on its own.
-- ---------------------------------------------------------------------------
--
-- Why a table, when Tokens:: already signs links with no state at all
-- -------------------------------------------------------------------
-- An unsubscribe link is meant to work forever, from an email archived two
-- years ago, on a device that has never seen this site. Deriving it from a key
-- is exactly right for that.
--
-- A password reset is the opposite of forever. It has to expire, it has to stop
-- working the instant it is used, and asking for a second one has to kill the
-- first. None of that can be expressed in a signature: it is state, and state
-- needs a row.
--
-- The token is stored hashed
-- --------------------------
-- The only place the plaintext ever exists is the email that carried it. A
-- stolen copy of this table is then a list of useless hashes rather than a set
-- of live keys to other people's accounts -- which matters more here than in
-- most tables, because the thing a reset link opens is somebody's login.

CREATE TABLE IF NOT EXISTS password_resets (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED NOT NULL,
    -- sha256 of the 32 random bytes that were emailed, hex encoded. Unique so
    -- that a repeat is a hard error rather than two people sharing one link.
    token_hash   CHAR(64) NOT NULL,
    -- Who asked. Not used for anything automatic; it is what tells you, later,
    -- whether a run of requests against one account came from one place.
    requested_ip VARCHAR(45) NULL,
    expires_at   DATETIME NOT NULL,
    -- Set the moment the link is spent. The row stays behind so a second use
    -- can be refused rather than silently succeed.
    used_at      DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY password_resets_token_unique (token_hash),
    KEY password_resets_user_index (user_id, used_at),
    KEY password_resets_expiry_index (expires_at),
    CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
