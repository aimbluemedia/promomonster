-- Premium's three tools: drafted replies, the monthly report, review alerts.
--
-- Premium was volume plus an unbranded widget, which is not a reason to pay
-- thirty dollars more than Pro. These three are about what happens AFTER a
-- review arrives -- answering it, watching the competition, and hearing about
-- it at all -- which is a different job from getting reviews, not more of the
-- same one.
--
-- review_reply_drafts is both the draft and the meter. A draft costs an API
-- call, so the number of them in a month is the cap, counted from the rows
-- themselves the way SendLimit counts requests from review_requests. One
-- source of truth means the number on the screen and the number enforced can
-- never disagree, and there is no counter to drift when a row is deleted.
--
-- report_competitors is two rows per account at most, because the comparison
-- tool takes two and a monthly email about five businesses is not read.
--
-- alerted_at on hosted_reviews is a column rather than a table on purpose: the
-- question is "has this one been announced", it is asked once per review, and
-- a NULL that becomes a timestamp answers it idempotently. A sender that
-- crashes mid-batch resumes exactly where it stopped.
--
-- Written for phpMyAdmin: every quoted string on ONE line, one ALTER per
-- column behind its own guard, ASCII only, safe to paste twice.

-- --- Drafted replies -------------------------------------------------------

-- The draft is kept, not just counted. A member who presses the button, reads
-- the draft, then reloads the page must get the same draft back rather than a
-- second API call and a different answer -- and must not be charged twice
-- against their month for one question.
CREATE TABLE IF NOT EXISTS review_reply_drafts (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id      BIGINT UNSIGNED NOT NULL,
  hosted_review_id BIGINT UNSIGNED NOT NULL,
  draft           TEXT NOT NULL,
  model           VARCHAR(60) NULL DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY review_reply_drafts_account_month (account_id, created_at),
  KEY review_reply_drafts_review (hosted_review_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- The monthly report ----------------------------------------------------

-- Who the account is measured against. Nothing here is fetched automatically:
-- a member types the name, because guessing a competitor from a map radius is
-- how you end up emailing somebody a report about the wrong business.
CREATE TABLE IF NOT EXISTS report_competitors (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id  BIGINT UNSIGNED NOT NULL,
  name        VARCHAR(150) NOT NULL,
  rating      DECIMAL(2,1) NULL DEFAULT NULL,
  review_count INT UNSIGNED NULL DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY report_competitors_account (account_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- When the next one is due. A column rather than "30 days since the last row"
-- so that turning the report on, changing a competitor, or a failed run can
-- each move the date without rewriting history.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'report_next_at'), 'DO 0', 'ALTER TABLE accounts ADD COLUMN report_next_at DATETIME NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'report_last_at'), 'DO 0', 'ALTER TABLE accounts ADD COLUMN report_last_at DATETIME NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- Review alerts ---------------------------------------------------------

-- Announced, or not yet. Set once, by the sender, after the mail is away.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosted_reviews' AND COLUMN_NAME = 'alerted_at'), 'DO 0', 'ALTER TABLE hosted_reviews ADD COLUMN alerted_at DATETIME NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- An index on the thing the sender asks for every five minutes: the ones not
-- yet announced. Without it that is a table scan on the one table that grows
-- fastest, every run, forever.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosted_reviews' AND INDEX_NAME = 'hosted_reviews_unalerted'), 'DO 0', 'CREATE INDEX hosted_reviews_unalerted ON hosted_reviews (alerted_at, id)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Alerts are on by default and a member can turn them off. Default 1 rather
-- than NULL so an existing account starts hearing about its reviews without
-- having to find the setting first.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'alerts_on'), 'DO 0', 'ALTER TABLE accounts ADD COLUMN alerts_on TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
