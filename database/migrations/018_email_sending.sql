-- Everything the email sender needs that the schema did not already have.
--
-- Migrations 008-010 designed this feature well before it was built, so most of
-- it is here: contacts, consent_records, the global suppressions list,
-- review_requests with its click_token and parent_request_id, and
-- message_events with a webhook idempotency key. This adds the few gaps.

-- --------------------------------------------------------------------------
-- 1. Where a reply goes.
--
-- The email is sent from our domain so it authenticates, but a customer who
-- hits reply must reach the business, not us. Without this column there is
-- nowhere to put that address.
-- --------------------------------------------------------------------------
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'locations'
               AND COLUMN_NAME  = 'reply_to_email'
        ),
        'DO 0',
        'ALTER TABLE locations ADD COLUMN reply_to_email VARCHAR(254) NULL AFTER google_review_url'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------------------------
-- 2. What we actually sent.
--
-- The template can be edited after the fact, so it is not evidence of what a
-- particular customer received. For a channel that carries a legal opt-out and
-- a compliance claim, "we think it said roughly this" is not good enough.
-- --------------------------------------------------------------------------
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'review_requests'
               AND COLUMN_NAME  = 'sent_subject'
        ),
        'DO 0',
        'ALTER TABLE review_requests
            ADD COLUMN sent_subject VARCHAR(255) NULL AFTER failure_reason,
            ADD COLUMN sent_body    TEXT NULL AFTER sent_subject,
            ADD COLUMN provider_ref VARCHAR(191) NULL AFTER sent_body'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------------------------
-- 3. Attempt counter, so a permanently broken row stops being retried.
--
-- Without it the runner picks the same failing send up every five minutes for
-- ever, and the provider sees a machine hammering a bad address.
-- --------------------------------------------------------------------------
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'review_requests'
               AND COLUMN_NAME  = 'attempts'
        ),
        'DO 0',
        'ALTER TABLE review_requests
            ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
-- INSERT ... SELECT with a NOT EXISTS guard, because this migration has to be
-- safe to run twice and MySQL has no INSERT IF NOT EXISTS for a non-unique key.
--
-- ASCII only, like every other migration here, and for a reason that cost an
-- afternoon to find: an em dash in this file arrives as mojibake when the
-- import client negotiates latin1, which `mysql < file` does by default. The
-- app reads over a utf8mb4 connection and would never corrupt it, but a
-- migration has to survive whichever client a hosting panel happens to use.
-- --------------------------------------------------------------------------
INSERT INTO templates (account_id, vertical, channel, kind, name, subject, body, is_system)
SELECT NULL, NULL, 'email', 'request', 'Standard review request',
       'How did we do, {{first_name}}?',
       'Hi {{first_name}},

Thanks for choosing {{business_name}}. It was good to work with you.

If you have a minute, would you leave us a review? It is the main way people
find us, and honest feedback helps the next customer decide.

{{review_url}}

It takes about a minute, and you can say whatever you actually think.

Thanks,
{{business_name}}',
       1
  FROM DUAL
 WHERE NOT EXISTS (
    SELECT 1 FROM templates
     WHERE is_system = 1 AND channel = 'email' AND kind = 'request' AND vertical IS NULL
 );

INSERT INTO templates (account_id, vertical, channel, kind, name, subject, body, is_system)
SELECT NULL, NULL, 'email', 'follow_up', 'Standard reminder', 'A quick reminder, {{first_name}}',
       'Hi {{first_name}},

I sent you a note a few days ago about leaving {{business_name}} a review.
If you have already done it, thank you, and please ignore this.

If not, the link is here:

{{review_url}}

This is the only reminder you will get from us.

Thanks,
{{business_name}}',
       1
  FROM DUAL
 WHERE NOT EXISTS (
    SELECT 1 FROM templates
     WHERE is_system = 1 AND channel = 'email' AND kind = 'follow_up' AND vertical IS NULL
 );

-- --------------------------------------------------------------------------
-- 5. Finding the work.
--
-- The runner asks one question every few minutes: which requests are due? There
-- is already an index on (status, scheduled_for), which is that query.
-- Nothing more is needed here, and an index that is never read is a write cost
-- for nothing.
-- --------------------------------------------------------------------------
