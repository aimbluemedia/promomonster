-- PromoMonster: the migrations not yet applied to the live database.
--
-- 018, then 021 to 028, in that order. 019 and 020 are already on the live
-- database, which is why they are not here.
--
-- HOW TO RUN IT
--   phpMyAdmin -> your database -> SQL tab -> paste the whole file -> Go.
--   Export the database first. It is thirty seconds and it is the only undo.
--
-- WHAT IT DOES
--   7 CREATE TABLE, 24 ADD COLUMN, 7 CREATE INDEX, 1 MODIFY, 3 INSERT and
--   2 UPDATE on seeded playbook rows. No DROP, no DELETE, no TRUNCATE, no
--   RENAME. Nothing touches a member, an account, a contact or a review.
--
--   The one MODIFY makes users.password_hash nullable, which only WIDENS
--   what the column accepts. Existing password hashes are untouched, so all
--   four accounts keep working.
--
-- SAFE TO PASTE TWICE
--   Every statement is guarded: CREATE TABLE IF NOT EXISTS, and every ALTER
--   sits behind a check of information_schema. Running it again does nothing.
--
-- AFTERWARDS
--   phpMyAdmin does not update the `migrations` table, so migrate-web.php
--   will still list these as pending. It is safe to let it run them -- the
--   guards mean it changes nothing the second time -- and that is also how
--   the table catches up.


-- ============================================================
-- 018_email_sending.sql
-- ============================================================
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


-- ============================================================
-- 021_email_templates.sql
-- ============================================================
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


-- ============================================================
-- 022_hosted_review_columns.sql
-- ============================================================
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


-- ============================================================
-- 023_request_destination.sql
-- ============================================================
-- Where a review request sends the customer.
--
-- Until now there was one answer and it was not written down: every request
-- pointed at locations.google_review_url, because Google was the only place a
-- review could be left. A business can now host its own reviews here, so the
-- same email has two possible destinations and the row has to say which -- the
-- click handler resolves the link at click time, days after the send, and it
-- cannot guess.
--
-- DEFAULT 'google' is not a preference, it is the truth about every row that
-- already exists: they were all sent before this column existed and they all
-- pointed at Google. The form defaults to PromoMonster, which is a separate
-- decision made in the view.
--
-- Written for phpMyAdmin: every quoted string on one line, one ALTER per
-- column, each behind its own guard so the file can be pasted twice.

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'review_requests' AND COLUMN_NAME = 'destination'), 'DO 0', 'ALTER TABLE review_requests ADD COLUMN destination ENUM(''google'', ''promomonster'') NOT NULL DEFAULT ''google'' AFTER channel'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The lookup all three pages make: one location's requests of one destination,
-- newest first. Without it the Google page and the PromoMonster page both scan
-- every request the account has ever sent to show their own ten.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'review_requests' AND INDEX_NAME = 'requests_destination_index'), 'DO 0', 'CREATE INDEX requests_destination_index ON review_requests (location_id, destination, created_at)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ============================================================
-- 024_cron_runs.sql
-- ============================================================
-- A trace of every time the queue runner ran.
--
-- The send queue and the thing that drains it have never been connected by
-- anything a page could read. "Queued" meant "waiting for a process we cannot
-- see, on a schedule we were told about once" -- so a cron job that was never
-- set up, or that stopped, looked exactly like one that was about to run. Two
-- requests sat at "queued" for days and the screen could only shrug.
--
-- Nothing in PHP can read the crontab on shared hosting, and no API on
-- Hostinger will say what is scheduled. What CAN be known is what actually
-- happened: if the runner writes a row every time it wakes, the gap between
-- those rows IS the schedule, measured rather than assumed. A run that records
-- nothing to do is the useful one -- it is the proof the job is alive.
--
-- Kept small on purpose. The runner prunes to the last few hundred rows, which
-- on a five-minute schedule is about a day, and a day is all anybody looks at.
--
-- Written for phpMyAdmin: every quoted string on one line, ASCII only, and safe
-- to paste twice.

CREATE TABLE IF NOT EXISTS cron_runs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Which job. One table for all of them, because a second scheduled task
    -- will want exactly this and a second table would want exactly this code.
    job           VARCHAR(60) NOT NULL,

    -- Written by the database, like every other timestamp here. A time from
    -- PHP would be on the site's timezone and compared against MySQL's, which
    -- on this host is seven hours out -- enough to report a runner that last
    -- ran two minutes ago as having stopped this morning.
    started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at   DATETIME NULL,

    -- What it found and what it did with it.
    due_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    sent_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    failed_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    skipped_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    -- ok, locked (another run was still going), or error. A row with
    -- finished_at still NULL is a run that died part way, which is its own
    -- answer and the reason finished_at is separate from started_at.
    outcome       VARCHAR(40) NOT NULL DEFAULT 'ok',

    PRIMARY KEY (id),
    KEY cron_runs_job_index (job, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 025_stripe_subscriptions.sql
-- ============================================================
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


-- ============================================================
-- 026_seed_playbooks.sql
-- ============================================================
-- The per-trade playbooks, which have never existed.
--
-- The playbooks table has been in the schema since migration 009 and no row
-- has ever been written to it: there is no INSERT INTO playbooks anywhere in
-- the repository. So every account, whatever its trade, saw "we build your
-- playbook during setup" forever, and the Your playbook page has been an empty
-- promise since the day it shipped.
--
-- One row per trade in App\Support\Trades, keyed by the same slugs. Those keys
-- are already in locations.vertical on live rows, so they are data: renaming
-- one here orphans every account on it.
--
-- primary_channel is 'email' on every row. The ENUM also allows 'sms' and
-- 'qr'; neither is built. SMS needs a carrier registration billed per business
-- and was cut from the plans for that reason, and nothing in this product
-- generates a QR code. A playbook telling somebody to send an SMS through
-- software that cannot send one is the same lie this file exists to remove.
--
-- No figures anywhere. "Asking at the door lifts response 40%" is the kind of
-- sentence a playbook invites and this one cannot source, and a business that
-- reorganises its day around an invented number has been misled by us.
--
-- The two compliance notes point at the regulator rather than stating the
-- rule. Dental and legal advertising rules differ by country and by state, and
-- a confident wrong summary here is worse for the member than being told to go
-- and look.
--
-- INSERT IGNORE, not ON DUPLICATE KEY UPDATE: the table comment in 009 says
-- these are data so they can be edited, and a re-paste must not silently
-- overwrite wording somebody has tuned. Safe to run twice; it will not undo
-- your edits.
--
-- Written for phpMyAdmin: every quoted string on ONE line, ASCII only.

INSERT IGNORE INTO playbooks (vertical, display_name, trigger_moment, who_asks, primary_channel, verbal_ask, follow_up_days, offline_method, objection) VALUES
('landscaping', 'Landscaping / lawn care', 'The last visit of the month, while the garden still looks freshly done', 'The crew lead who actually did the work', 'email', 'If the garden looks how you wanted it, would you mind saying so online? I will send you the link before I drive off.', 2, 'A card through the door with the review link printed on it, left on the last visit', 'I would not know how. -- Tell them the link opens the box for them and it takes a minute. Never offer to write it for them.'),
('hvac', 'HVAC / plumbing / electrical', 'Once the system is running again and they have felt it working', 'The engineer who fixed it, before packing the van', 'email', 'Glad that is sorted. If you have two minutes, a review helps the next person find us when their heating packs up. I will send the link now.', 2, 'The review link printed on the invoice, and on the van', 'That was expensive. -- Settle the price conversation before you ask. An unhappy customer asked for a review leaves one.'),
('roofing', 'Roofing / exteriors', 'On the final walk-round, once the skip has gone and the job looks finished', 'The site foreman, standing with the owner in front of the work', 'email', 'Before I go, would you leave us a review? People spend a lot on a roof and they want to hear from somebody who has had one done.', 3, 'A photo of the finished roof, emailed with the link underneath it', 'I will do it later. -- Later is where reviews go to die. Ask while you are still packing up.'),
('dental', 'Dental / medical', 'At the desk, after treatment is finished and the next appointment is booked', 'The receptionist, not the clinician who treated them', 'email', 'If you were happy with how today went, would you leave us a review? It helps people who are nervous about coming in.', 3, 'A card handed over with the appointment reminder', 'I do not want my treatment discussed publicly. -- Tell them to review the practice and the people, not the procedure.'),
('veterinary', 'Veterinary', 'A day or two after the visit, once the animal is clearly on the mend', 'The nurse who handled the discharge', 'email', 'How is she doing? If you are happy with how we looked after her, a review would mean a lot to us.', 3, 'The review link on the discharge notes', 'A poor outcome. -- Do not ask at all. A grieving owner asked for a review writes a complaint, and they are right to.'),
('restaurant', 'Restaurant / cafe', 'With the bill, while they are still at the table and still full', 'The server who looked after them', 'email', 'Hope you enjoyed it. If you have a second, a review helps us more than you would think.', 2, 'A card on the saucer with the bill, link printed on it', 'They are in a hurry. -- Hand the card over and say nothing else. Do not chase somebody who is putting a coat on.'),
('salon', 'Salon / barber / spa', 'At the chair, while they are still looking in the mirror and pleased with it', 'The stylist who did the work', 'email', 'If you love it, would you say so online? I will send you the link while you pay.', 2, 'The link printed on the back of the appointment card', 'I hate how I look in photos. -- Nobody has to post one. It is words.'),
('auto', 'Auto repair', 'At handover, with the keys in their hand and the car ready', 'Whoever hands the keys back', 'email', 'All done. If we did right by you, a review helps a lot -- most people pick a garage from them.', 2, 'The link on the invoice and on the counter', 'Shock at the bill. -- Explain the invoice first. Never ask on top of a bill somebody is unhappy about.'),
('legal', 'Law firm', 'At the end of the matter, when the file closes and the result is known', 'The fee earner who ran the matter, not the office', 'email', 'Now that is behind you, would you be willing to leave a review? You do not need to say anything about the case itself.', 5, 'A line in the closing letter, with the link', 'Confidentiality. -- They can review the service without any of the facts. Say so plainly and they usually will.'),
('realestate', 'Real estate', 'On completion day, at the handover of the keys', 'The agent who ran the sale, by name', 'email', 'Congratulations. Reviews follow me rather than the office, so if today went well it would really help me.', 3, 'The link in the completion email', 'The chain was stressful. -- Acknowledge it first, then ask about how you handled it rather than how it went.'),
('other', 'Something else', 'The day the work is done, while they still remember your name', 'The person who did the work, by name', 'email', 'If we did a good job, would you leave us a review? I will send you the link now.', 3, 'A card with the link on it, handed over at the end', 'I will do it later. -- Ask while you are still in front of them. Nothing else on this page matters as much as that.');

-- The two trades with a regulator looking over their shoulder. Set separately
-- so the long note stays on its own line and the VALUES list above stays
-- readable. Each UPDATE is harmless if the row is not there.
UPDATE playbooks SET compliance_note = 'Never put a patient name, condition, treatment or appointment in a review request, and never confirm in a public reply that somebody is a patient. In the US that is a HIPAA disclosure whether or not the review named them first. Check your own regulator before you start asking.' WHERE vertical = 'dental' AND compliance_note IS NULL;
UPDATE playbooks SET compliance_note = 'Most bars restrict what may be published about a client and what a testimonial may imply about results. Do not ask a client to mention the outcome, and do not answer a review with anything about the matter. Check your own bar admission rules on advertising and testimonials before you start asking.' WHERE vertical = 'legal' AND compliance_note IS NULL;


-- ============================================================
-- 027_toutify.sql
-- ============================================================
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


-- ============================================================
-- 028_premium_tools.sql
-- ============================================================
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

