-- HOTFIX: applies migrations 015, 016 and 017, whatever state you are in.
--
-- Paste this WHOLE file into phpMyAdmin -> your database -> SQL, and press Go.
--
-- It is SAFE TO RUN MORE THAN ONCE and safe to run when some of it has already
-- been applied. Every step checks first and skips itself if the work is done, so
-- it runs start to finish with no errors. phpMyAdmin stops at the first error,
-- which is how a previous version of this file left a database half-migrated.
--
-- Your data is kept. Old plan values are converted once and only once:
--   trial -> free    starter -> pro    growth -> pro    pro -> premium

-- =====================================================================
-- 015: member plans (Free / Pro / Premium)
-- =====================================================================

-- The conversion runs ONLY while the column still holds the old values. Running
-- it a second time would read an already-converted 'pro' as the old 'pro' and
-- push it to 'premium', and 'premium' to 'free'. Guarding it is the difference
-- between an idempotent script and silent data loss.
SET @old_plan := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts'
       AND COLUMN_NAME = 'plan' AND COLUMN_TYPE LIKE '%trial%'
);

SET @sql := IF(@old_plan > 0,
    'ALTER TABLE accounts MODIFY COLUMN plan VARCHAR(20) NOT NULL DEFAULT ''free''',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- On one line, even though it is long: phpMyAdmin parses the file before it
-- sends it and cannot follow a string literal that spans lines. It answers
-- "Ending quote ' was expected" and refuses the statement, which is exactly
-- how migration 018 failed on a live database.
SET @sql := IF(@old_plan > 0, 'UPDATE accounts SET plan = CASE plan WHEN ''trial'' THEN ''free'' WHEN ''starter'' THEN ''pro'' WHEN ''growth'' THEN ''pro'' WHEN ''pro'' THEN ''premium'' WHEN ''partner'' THEN ''partner'' ELSE ''free'' END', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(@old_plan > 0,
    'ALTER TABLE accounts MODIFY COLUMN plan ENUM(''free'',''pro'',''premium'',''partner'') NOT NULL DEFAULT ''free''',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Columns: added only if absent.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'requested_plan') = 0,
    'ALTER TABLE accounts ADD COLUMN requested_plan ENUM(''pro'',''premium'') NULL AFTER plan', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'requested_plan_at') = 0,
    'ALTER TABLE accounts ADD COLUMN requested_plan_at DATETIME NULL AFTER requested_plan', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'plan_changed_at') = 0,
    'ALTER TABLE accounts ADD COLUMN plan_changed_at DATETIME NULL AFTER requested_plan_at', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'signup_ip') = 0,
    'ALTER TABLE accounts ADD COLUMN signup_ip VARCHAR(45) NULL AFTER plan_changed_at', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'accounts' AND INDEX_NAME = 'accounts_requested_plan_index') = 0,
    'ALTER TABLE accounts ADD KEY accounts_requested_plan_index (requested_plan, requested_plan_at)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================================
-- 016: the public comparison  <-- the missing `source` column lives here
-- =====================================================================

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'audits' AND COLUMN_NAME = 'source') = 0,
    'ALTER TABLE audits ADD COLUMN source ENUM(''audit_form'',''self_serve'') NOT NULL DEFAULT ''audit_form'' AFTER vertical', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'audits' AND COLUMN_NAME = 'results_generated_at') = 0,
    'ALTER TABLE audits ADD COLUMN results_generated_at DATETIME NULL AFTER results', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'audits' AND INDEX_NAME = 'audits_source_index') = 0,
    'ALTER TABLE audits ADD KEY audits_source_index (source, created_at)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'audits' AND INDEX_NAME = 'audits_email_source_index') = 0,
    'ALTER TABLE audits ADD KEY audits_email_source_index (email, source)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================================
-- 017: the Free Review Score
-- =====================================================================

-- Widening an enum to include 'score' is safe to repeat; it only runs when the
-- value is missing so a re-run costs nothing on a large table.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'audits' AND COLUMN_NAME = 'source' AND COLUMN_TYPE LIKE '%score%') = 0,
    'ALTER TABLE audits MODIFY COLUMN source ENUM(''audit_form'',''self_serve'',''score'') NOT NULL DEFAULT ''audit_form''', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'audits' AND COLUMN_NAME = 'website') = 0,
    'ALTER TABLE audits ADD COLUMN website VARCHAR(255) NULL AFTER business_name', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'audits' AND COLUMN_NAME = 'score') = 0,
    'ALTER TABLE audits ADD COLUMN score TINYINT UNSIGNED NULL AFTER results_generated_at', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================================
-- Record all three so diagnose.php stops reporting them pending.
-- =====================================================================

CREATE TABLE IF NOT EXISTS migrations (
    filename   VARCHAR(191) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (filename) VALUES
    ('015_member_plans.sql'),
    ('016_self_serve_comparison.sql'),
    ('017_review_score.sql');

-- Check what you have. Plans should read free / pro / premium / partner.
SELECT id, name, plan, requested_plan FROM accounts ORDER BY id;
