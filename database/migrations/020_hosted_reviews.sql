-- Reviews hosted by PromoMonster itself, rather than fetched from Google.
--
-- Written for phpMyAdmin like the rest: ASCII only, no quoted string spans a
-- line, and safe to run more than once.
--
-- Why a new table and not `reviews`
-- ---------------------------------
-- `reviews` holds copies of what somebody else published: it is keyed on
-- (platform, external_id), every row has an owner elsewhere, and we can neither
-- edit nor vouch for any of it. These are the opposite -- written here, by a
-- named person, and ours to stand behind. Sharing one table would mean a
-- nullable external_id that must be null for half the rows and unique for the
-- other half, and a platform enum with a value that is not a platform.
--
-- What is deliberately NOT here
-- -----------------------------
-- There is no `hidden`, `approved` or `published` column, and that absence is
-- the feature. A page that shows only the reviews a business approved is the
-- pattern the FTC's rule on consumer reviews (16 CFR Part 465) exists to stop,
-- and it makes the widget worthless to the people reading it -- a wall of five
-- stars tells a customer nothing. A business answers a bad review here; it
-- cannot make it disappear. Leaving the column out means no later change can
-- quietly add the behaviour without also changing the schema in front of
-- somebody.
--
-- `source` is shown on screen, never just stored. A review typed in by the
-- business is labelled as typed in by the business, and one left through a link
-- we emailed a named customer is labelled verified. Presenting the first as the
-- second would be manufacturing testimonials.

-- The public page address. One per account, and it appears in a URL, so it is
-- generated rather than typed.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'public_slug'), 'DO 0', 'ALTER TABLE accounts ADD COLUMN public_slug VARCHAR(80) NULL AFTER name'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND INDEX_NAME = 'accounts_public_slug_unique'), 'DO 0', 'CREATE UNIQUE INDEX accounts_public_slug_unique ON accounts (public_slug)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS hosted_reviews (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id   BIGINT UNSIGNED NOT NULL,
    location_id  BIGINT UNSIGNED NULL,
    -- Set only when the review arrived through a link addressed to this
    -- contact. It is what "verified" means, and it is why the claim can be
    -- made honestly.
    contact_id   BIGINT UNSIGNED NULL,
    -- invited              through a personal link sent to a named customer
    -- public_link          through the business's open page
    -- entered_by_business  typed in by the business on the customer's behalf
    -- google               copied in from the business's Google listing
    --
    -- The last two are both the business's word for it and are labelled as
    -- such. Only 'invited' is called verified, because only 'invited' is.
    source       ENUM('invited','public_link','entered_by_business','google') NOT NULL DEFAULT 'public_link',
    author_name  VARCHAR(120) NOT NULL,
    -- Where they are, as they typed it: "Mesa, AZ". One free-text field rather
    -- than a city column and a state column, because a reviewer writes it in
    -- one breath and half of them are not in the United States.
    author_city  VARCHAR(120) NULL,
    author_email VARCHAR(254) NULL,
    rating       TINYINT UNSIGNED NOT NULL,
    body         TEXT NOT NULL,
    -- The business's public answer. Answering is the only thing it can do to a
    -- review it does not like, which is the whole design.
    reply_body   TEXT NULL,
    replied_at   DATETIME NULL,
    submitted_ip VARCHAR(45) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY hosted_reviews_account_index (account_id, created_at),
    KEY hosted_reviews_contact_index (contact_id),
    CONSTRAINT hosted_reviews_account_fk FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE,
    CONSTRAINT hosted_reviews_location_fk FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE SET NULL,
    CONSTRAINT hosted_reviews_contact_fk FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE SET NULL,
    CONSTRAINT hosted_reviews_rating_range CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For a database that applied an earlier copy of this file, before 'google' was
-- a source. Guarded, so a fresh install skips it.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosted_reviews' AND COLUMN_NAME = 'source' AND COLUMN_TYPE LIKE '%google%'), 'DO 0', 'ALTER TABLE hosted_reviews MODIFY COLUMN source ENUM(\'invited\',\'public_link\',\'entered_by_business\',\'google\') NOT NULL DEFAULT \'public_link\''));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Where the copied review can be read, so a claim about Google can be checked
-- rather than taken on trust.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosted_reviews' AND COLUMN_NAME = 'source_url'), 'DO 0', 'ALTER TABLE hosted_reviews ADD COLUMN source_url VARCHAR(500) NULL AFTER source'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- For a database that applied this file before author_city existed.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosted_reviews' AND COLUMN_NAME = 'author_city'), 'DO 0', 'ALTER TABLE hosted_reviews ADD COLUMN author_city VARCHAR(120) NULL AFTER author_name'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
