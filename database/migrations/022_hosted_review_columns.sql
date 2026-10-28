-- Catches up a database that ran 020 before 020 was finished.
--
-- This file exists because of how the runner tracks work: it records a
-- migration by filename, and never replays a name it has recorded. 020 gained
-- columns after it had already been run on the live database -- author_city
-- most recently -- and the guarded ALTERs appended to the bottom of it are
-- therefore dead code on the one server that needs them. The table was there,
-- the code wrote a column it did not have, and a member adding a review got a
-- 500 and a reference number for doing nothing wrong.
--
-- A file's contents may be corrected after it has run; what it does may not be
-- ADDED TO. Anything new goes in a new file, which is this one. The ALTERs in
-- 020 stay where they are so a fresh install still gets them in one pass.
--
-- Every statement is guarded, so this is safe on a database that already has
-- all three columns -- it does nothing at all -- and safe to paste twice.
--
-- Written for phpMyAdmin: every quoted string on one line, one ALTER per
-- column, ASCII only.

-- The account's public page address. From 020 as first written.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'public_slug'), 'DO 0', 'ALTER TABLE accounts ADD COLUMN public_slug VARCHAR(80) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND INDEX_NAME = 'accounts_public_slug_unique'), 'DO 0', 'CREATE UNIQUE INDEX accounts_public_slug_unique ON accounts (public_slug)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Where a Google review the business copied in can be read in the original.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosted_reviews' AND COLUMN_NAME = 'source_url'), 'DO 0', 'ALTER TABLE hosted_reviews ADD COLUMN source_url VARCHAR(500) NULL AFTER source'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- "Mesa, AZ". The one that was actually missing, and the reason for this file.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosted_reviews' AND COLUMN_NAME = 'author_city'), 'DO 0', 'ALTER TABLE hosted_reviews ADD COLUMN author_city VARCHAR(120) NULL AFTER author_name'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
