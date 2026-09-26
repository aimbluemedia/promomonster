-- Self-serve signup needs plans people can actually pick: Free, Pro and Premium.
--
-- The old enum (trial/starter/growth/pro/partner) came from the pre-pivot
-- pricing. Existing rows are mapped onto the nearest new plan rather than being
-- reset, so nobody silently loses what they are paying for. Note that the old
-- 'pro' maps to 'premium': it was the top self-serve tier and still is.

ALTER TABLE accounts MODIFY COLUMN plan VARCHAR(20) NOT NULL DEFAULT 'free';

UPDATE accounts SET plan = CASE plan
    WHEN 'trial'   THEN 'free'
    WHEN 'starter' THEN 'pro'
    WHEN 'growth'  THEN 'pro'
    WHEN 'pro'     THEN 'premium'
    WHEN 'partner' THEN 'partner'
    ELSE 'free'
END;

ALTER TABLE accounts
    MODIFY COLUMN plan ENUM('free','pro','premium','partner') NOT NULL DEFAULT 'free';

-- There is no payment processor yet, so a paid signup cannot be charged. Rather
-- than pretend otherwise, the account starts on Free and records what it asked
-- for; superadmin works that queue by hand, exactly as audits are handled now.
-- When billing lands, this column is what it reconciles against.
ALTER TABLE accounts
    ADD COLUMN requested_plan ENUM('pro','premium') NULL AFTER plan,
    ADD COLUMN requested_plan_at DATETIME NULL AFTER requested_plan,
    ADD COLUMN plan_changed_at DATETIME NULL AFTER requested_plan_at,
    ADD KEY accounts_requested_plan_index (requested_plan, requested_plan_at);

-- Who signed up, from where. The audit trail for self-serve accounts.
ALTER TABLE accounts
    ADD COLUMN signup_ip VARCHAR(45) NULL AFTER plan_changed_at;
