-- What Stripe knows, written down on our side.
--
-- accounts has carried stripe_customer_id since 007 and subscription_renews_at
-- with it, which was enough for a plan set up by hand. It is not enough for a
-- subscription that changes without anybody here touching it: a renewal date on
-- its own cannot say whether the card failed last night, which Price is being
-- paid for, or which subscription to look up when a webhook arrives.
--
-- stripe_status is Stripe's own word, stored unmapped (active, trialing,
-- past_due, canceled, incomplete, paused...). Mapping it on the way in would
-- throw away the only honest answer to "why is this account on Free", and
-- Stripe adds statuses faster than an ENUM can be migrated.
--
-- stripe_price_id is which Price, not which plan. accounts.plan already says
-- which plan; this says what they are actually being billed for, so a Price
-- renamed or replaced in the dashboard is visible rather than silently assumed.
--
-- stripe_events exists for one reason: Stripe retries an event for three days
-- until it gets a 2xx, and the same first payment arrives both as a webhook and
-- on the member's return from checkout. Every handler is written to be safe
-- twice over, but an audit log that records the same upgrade four times is its
-- own kind of wrong.
--
-- Written for phpMyAdmin: every quoted string on one line, one ALTER per
-- column, each behind its own guard, ASCII only, safe to paste twice.

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'stripe_subscription_id'), 'DO 0', 'ALTER TABLE accounts ADD COLUMN stripe_subscription_id VARCHAR(64) NULL DEFAULT NULL AFTER stripe_customer_id'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'stripe_price_id'), 'DO 0', 'ALTER TABLE accounts ADD COLUMN stripe_price_id VARCHAR(64) NULL DEFAULT NULL AFTER stripe_subscription_id'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'stripe_status'), 'DO 0', 'ALTER TABLE accounts ADD COLUMN stripe_status VARCHAR(32) NULL DEFAULT NULL AFTER stripe_price_id'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- One subscription is one account, and a webhook looks an account up by it.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND INDEX_NAME = 'accounts_stripe_subscription_unique'), 'DO 0', 'CREATE UNIQUE INDEX accounts_stripe_subscription_unique ON accounts (stripe_subscription_id)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Stripe's event id is the primary key, which is what makes the insert itself
-- the duplicate check. A SELECT then an INSERT would leave a window two of
-- Stripe's own retries fit through.
CREATE TABLE IF NOT EXISTS stripe_events (
    id          VARCHAR(64) NOT NULL,
    type        VARCHAR(80) NOT NULL,

    -- By the database, like every other timestamp here. PHP runs on
    -- America/Phoenix and MySQL on UTC, and a pruning query that mixed the two
    -- would delete seven hours of events it had only just written.
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY stripe_events_received_index (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
