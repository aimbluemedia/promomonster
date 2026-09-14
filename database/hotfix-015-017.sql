-- HOTFIX: migrations 015, 016 and 017 have not been applied.
--
-- Symptom: the Free Review Score form returns "Something went wrong", and the
-- log says
--   PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'source'
--
-- Paste this WHOLE file into phpMyAdmin -> your database -> SQL, and press Go.
-- Run it as it is, top to bottom. The order matters: the last block changes a
-- column the second block creates.
--
-- Some statements may report
--   #1060 Duplicate column name   /   #1061 Duplicate key name
-- if part of this was already applied. Those are safe to ignore — the column is
-- there, which is all that matters. Any OTHER error is real; send it to me.
--
-- Your data is kept. The plan values are converted, not reset:
--   trial -> free    starter -> pro    growth -> pro    pro -> premium

-- =====================================================================
-- 015: member plans (Free / Pro / Premium)
-- =====================================================================

-- Widen to text first so the old values can be rewritten, then narrow back to
-- an enum. Changing one enum straight to another would reject the old values.
ALTER TABLE accounts MODIFY COLUMN plan VARCHAR(20) NOT NULL DEFAULT 'free';

UPDATE accounts SET plan = CASE plan
    WHEN 'trial'   THEN 'free'
    WHEN 'starter' THEN 'pro'
    WHEN 'growth'  THEN 'pro'
    WHEN 'pro'     THEN 'premium'
    WHEN 'partner' THEN 'partner'
    ELSE 'free'
END;

ALTER TABLE accounts MODIFY COLUMN plan ENUM('free','pro','premium','partner') NOT NULL DEFAULT 'free';

ALTER TABLE accounts ADD COLUMN requested_plan ENUM('pro','premium') NULL AFTER plan;
ALTER TABLE accounts ADD COLUMN requested_plan_at DATETIME NULL AFTER requested_plan;
ALTER TABLE accounts ADD COLUMN plan_changed_at DATETIME NULL AFTER requested_plan_at;
ALTER TABLE accounts ADD COLUMN signup_ip VARCHAR(45) NULL AFTER plan_changed_at;
ALTER TABLE accounts ADD KEY accounts_requested_plan_index (requested_plan, requested_plan_at);

-- =====================================================================
-- 016: the public comparison at /compare  <-- this is the one crashing
-- =====================================================================

ALTER TABLE audits ADD COLUMN source ENUM('audit_form','self_serve') NOT NULL DEFAULT 'audit_form' AFTER vertical;
ALTER TABLE audits ADD COLUMN results_generated_at DATETIME NULL AFTER results;
ALTER TABLE audits ADD KEY audits_source_index (source, created_at);
ALTER TABLE audits ADD KEY audits_email_source_index (email, source);

-- =====================================================================
-- 017: the Free Review Score
-- =====================================================================

ALTER TABLE audits MODIFY COLUMN source ENUM('audit_form','self_serve','score') NOT NULL DEFAULT 'audit_form';
ALTER TABLE audits ADD COLUMN website VARCHAR(255) NULL AFTER business_name;
ALTER TABLE audits ADD COLUMN score TINYINT UNSIGNED NULL AFTER results_generated_at;

-- =====================================================================
-- Record all three, so diagnose.php stops reporting them as pending and
-- migrate-web.php will not try to run them again.
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
