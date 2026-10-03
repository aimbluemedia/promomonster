-- Everything the email sender needs that the schema did not already have.
--
-- Migrations 008-010 designed this feature well before it was built, so most of
-- it is here: contacts, consent_records, the global suppressions list,
-- review_requests with its click_token and parent_request_id, and
-- message_events with a webhook idempotency key. This adds the few gaps.
--
-- ---------------------------------------------------------------------------
-- WRITTEN FOR phpMyAdmin, which is stricter than the mysql client:
--
--   * Every quoted string sits on ONE line. phpMyAdmin parses the file before
--     sending it, and its analyser cannot follow a string literal that spans
--     lines -- it reports "Ending quote ' was expected" and refuses the whole
--     statement. The mysql client and PDO both accept the multi-line form, so
--     this only shows up in the panel most people actually use.
--
--   * One ALTER per column, each with its own guard. Grouping them meant a
--     partial apply left no way to resume: phpMyAdmin stops at the first error,
--     so if the second column failed the third and fourth never ran and a
--     re-run had nothing to check them against.
--
--   * Newlines inside data come from CONCAT_WS(CHAR(10 ...)), not from '\n'.
--     A backslash escape is silently a literal backslash-n under
--     NO_BACKSLASH_ESCAPES, which would put "\n" in every customer's email with
--     no error to notice.
--
--   * ASCII only, like every other migration here. An em dash in a .sql file
--     comes back as mojibake when the import client negotiates latin1.
--
-- Safe to run more than once. Every statement checks first.
-- ---------------------------------------------------------------------------

-- --------------------------------------------------------------------------
-- 1. Where a reply goes.
--
-- The email is sent from our domain so it authenticates, but a customer who
-- hits reply must reach the business, not us.
-- --------------------------------------------------------------------------
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'locations' AND COLUMN_NAME = 'reply_to_email'), 'DO 0', 'ALTER TABLE locations ADD COLUMN reply_to_email VARCHAR(254) NULL AFTER google_review_url'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------------------------
-- 2. What we actually sent.
--
-- The template can be edited after the fact, so it is not evidence of what a
-- particular customer received. For a channel carrying a legal opt-out and a
-- compliance claim, "we think it said roughly this" is not good enough.
-- --------------------------------------------------------------------------
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'review_requests' AND COLUMN_NAME = 'sent_subject'), 'DO 0', 'ALTER TABLE review_requests ADD COLUMN sent_subject VARCHAR(255) NULL AFTER failure_reason'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'review_requests' AND COLUMN_NAME = 'sent_body'), 'DO 0', 'ALTER TABLE review_requests ADD COLUMN sent_body TEXT NULL AFTER sent_subject'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The provider's own id for the message, which is how a bounce or a complaint
-- webhook finds the row it belongs to.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'review_requests' AND COLUMN_NAME = 'provider_ref'), 'DO 0', 'ALTER TABLE review_requests ADD COLUMN provider_ref VARCHAR(191) NULL AFTER sent_body'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'review_requests' AND INDEX_NAME = 'requests_provider_ref_index'), 'DO 0', 'CREATE INDEX requests_provider_ref_index ON review_requests (provider_ref)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------------------------
-- 3. Attempt counter, so a permanently broken row stops being retried.
--
-- Without it the runner picks the same failing send up every five minutes for
-- ever, and the provider sees a machine hammering a bad address.
-- --------------------------------------------------------------------------
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'review_requests' AND COLUMN_NAME = 'attempts'), 'DO 0', 'ALTER TABLE review_requests ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------------------------
-- 4. The system email templates.
--
-- Written to be read by somebody who has just had work done and is not
-- expecting an email. Short, specific about who is asking, honest that it takes
-- a minute, and with no incentive of any kind: offering anything in exchange
-- for a review breaks Google's policy outright, so the wording we ship has to
-- make the compliant path the easy one.
--
-- Merge fields are {{like_this}} and are replaced by the renderer. An unknown
-- field is removed rather than left on screen.
--
-- Each line of the body is its own quoted string, joined with a real newline.
-- That keeps every literal on one line for phpMyAdmin and keeps the stored text
-- free of backslash escapes.
--
-- INSERT ... SELECT with a NOT EXISTS guard, because MySQL has no
-- INSERT IF NOT EXISTS for a non-unique key and this has to be safe to re-run.
-- --------------------------------------------------------------------------
INSERT INTO templates (account_id, vertical, channel, kind, name, subject, body, is_system)
SELECT NULL,
       NULL,
       'email',
       'request',
       'Standard review request',
       'How did we do, {{first_name}}?',
       CONCAT_WS(CHAR(10 USING utf8mb4),
           'Hi {{first_name}},',
           '',
           'Thanks for choosing {{business_name}}. It was good to work with you.',
           '',
           'If you have a minute, would you leave us a review? It is the main way',
           'people find us, and honest feedback helps the next customer decide.',
           '',
           '{{review_url}}',
           '',
           'It takes about a minute, and you can say whatever you actually think.',
           '',
           'Thanks,',
           '{{business_name}}'
       ),
       1
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM templates WHERE is_system = 1 AND channel = 'email' AND kind = 'request' AND vertical IS NULL);

INSERT INTO templates (account_id, vertical, channel, kind, name, subject, body, is_system)
SELECT NULL,
       NULL,
       'email',
       'follow_up',
       'Standard reminder',
       'A quick reminder, {{first_name}}',
       CONCAT_WS(CHAR(10 USING utf8mb4),
           'Hi {{first_name}},',
           '',
           'I sent you a note a few days ago about leaving {{business_name}} a',
           'review. If you have already done it, thank you, and please ignore this.',
           '',
           'If not, the link is here:',
           '',
           '{{review_url}}',
           '',
           'This is the only reminder you will get from us.',
           '',
           'Thanks,',
           '{{business_name}}'
       ),
       1
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM templates WHERE is_system = 1 AND channel = 'email' AND kind = 'follow_up' AND vertical IS NULL);
