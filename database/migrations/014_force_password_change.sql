-- Lets an account be handed over with a temporary password that cannot be used
-- for anything until it is replaced.

ALTER TABLE users
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN password_changed_at DATETIME NULL AFTER must_change_password;
