-- Member-owned email templates.
--
-- The templates table has existed since 009 and has carried an account_id
-- column since then, but nothing has ever written a row with one in it: every
-- request goes out on the system template. This adds the two columns a member
-- needs to own their own wording, and nothing else -- the table, the merge
-- fields and the renderer are all already there.
--
-- is_default is only ever set on an account's OWN rows. A system template is
-- shared by every account, so marking one "default" would mark it for everybody.
-- The rule is instead: an account's default is its own is_default row for that
-- kind, and the system template when it has none. That makes the default
-- correct for a new account with no templates at all, which is every account
-- today, without a row having to be written for them first.
--
-- Written for phpMyAdmin: every quoted string on one line, one ALTER per
-- column, each behind its own guard so the file can be pasted twice.

-- Is this the account's chosen wording for this kind of message?
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND COLUMN_NAME = 'is_default'), 'DO 0', 'ALTER TABLE templates ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER is_system'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- When the member last saved it. created_at alone cannot tell "written and
-- forgotten" from "edited this morning", and the list is sorted on it.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND COLUMN_NAME = 'updated_at'), 'DO 0', 'ALTER TABLE templates ADD COLUMN updated_at DATETIME NULL AFTER created_at'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The lookup the templates page and the send form both make: one account's
-- templates of one kind. The existing index leads with vertical, which is NULL
-- on every row anybody has ever written.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND INDEX_NAME = 'templates_account_kind_index'), 'DO 0', 'CREATE INDEX templates_account_kind_index ON templates (account_id, channel, kind, is_default)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
