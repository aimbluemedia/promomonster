-- Toutify becomes the place passwords live.
--
-- PromoMonster stops authenticating members itself: Toutify holds the account,
-- PromoMonster holds the reviews. A member arrives carrying a Toutify "sub",
-- a permanent 26-character id, and that is the link between the two.
--
-- users.password_hash becomes NULLABLE. It is NOT NULL today, which means a
-- Toutify member -- who has no password here and never will -- cannot be
-- inserted at all. Existing staff rows keep their hashes and keep logging in
-- the old way: superadmin is not a Toutify account.
--
-- The toutify_* columns are a cache of what Toutify told us, not a second
-- source of truth. toutify_updated_at is the guard: a webhook that arrives out
-- of order must not overwrite newer data with older, so every write compares
-- it first.
--
-- Written for phpMyAdmin: every quoted string on ONE line, one ALTER per
-- column behind its own guard, ASCII only, safe to paste twice.

-- --- users -----------------------------------------------------------------

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'toutify_sub'), 'DO 0', 'ALTER TABLE users ADD COLUMN toutify_sub CHAR(26) NULL DEFAULT NULL AFTER id'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'toutify_name'), 'DO 0', 'ALTER TABLE users ADD COLUMN toutify_name VARCHAR(150) NULL DEFAULT NULL AFTER last_name'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'business_name'), 'DO 0', 'ALTER TABLE users ADD COLUMN business_name VARCHAR(150) NULL DEFAULT NULL AFTER toutify_name'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone'), 'DO 0', 'ALTER TABLE users ADD COLUMN phone VARCHAR(40) NULL DEFAULT NULL AFTER business_name'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'website'), 'DO 0', 'ALTER TABLE users ADD COLUMN website VARCHAR(255) NULL DEFAULT NULL AFTER phone'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'toutify_status'), 'DO 0', 'ALTER TABLE users ADD COLUMN toutify_status VARCHAR(20) NULL DEFAULT NULL AFTER website'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'toutify_updated_at'), 'DO 0', 'ALTER TABLE users ADD COLUMN toutify_updated_at INT UNSIGNED NULL DEFAULT NULL AFTER toutify_status'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deleted_at'), 'DO 0', 'ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER toutify_updated_at'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- One Toutify member is one row here. NULL stays allowed and repeatable, which
-- is what keeps staff rows (no sub) working.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_toutify_sub_unique'), 'DO 0', 'CREATE UNIQUE INDEX users_toutify_sub_unique ON users (toutify_sub)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- The one that blocks everything else: a member signing in through Toutify has
-- no password to store, and NOT NULL refuses the INSERT.
SET @sql := (SELECT IF((SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_hash') = 'YES', 'DO 0', 'ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- The three tables Toutify needs ----------------------------------------

-- Which PHP session belongs to which Toutify login. Back-channel logout
-- arrives with a sid, or with only a sub, and has to end the right ones.
CREATE TABLE IF NOT EXISTS toutify_sessions (
    session_id VARCHAR(128) NOT NULL,
    sid        CHAR(32) NOT NULL,
    sub        CHAR(26) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id),
    KEY toutify_sessions_sid_index (sid),
    KEY toutify_sessions_sub_index (sub)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook de-duplication. The INSERT is the check: Toutify retries for 24
-- hours, and a SELECT-then-INSERT leaves a window two retries fit through.
CREATE TABLE IF NOT EXISTS toutify_events (
    event_id    VARCHAR(40) NOT NULL,
    type        VARCHAR(40) NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY toutify_events_received_index (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The member's websites, as Toutify lists them. id is Toutify's, not ours.
CREATE TABLE IF NOT EXISTS toutify_websites (
    id                 CHAR(26) NOT NULL,
    owner              CHAR(26) NOT NULL,
    url                VARCHAR(255) NOT NULL,
    business_name      VARCHAR(150) NULL DEFAULT NULL,
    phone              VARCHAR(40) NULL DEFAULT NULL,
    street             VARCHAR(150) NULL DEFAULT NULL,
    city               VARCHAR(120) NULL DEFAULT NULL,
    state              VARCHAR(60) NULL DEFAULT NULL,
    zip                VARCHAR(20) NULL DEFAULT NULL,
    country            VARCHAR(60) NULL DEFAULT NULL,
    tier               VARCHAR(20) NULL DEFAULT NULL,
    status             VARCHAR(20) NOT NULL DEFAULT 'active',
    toutify_updated_at INT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY toutify_websites_owner_index (owner)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
