-- The homepage Free Review Score writes into `audits` like the other two entry
-- points, so the operator works one queue. 'score' distinguishes it, and is what
-- the per-email and daily spend limits count.

ALTER TABLE audits
    MODIFY COLUMN source ENUM('audit_form','self_serve','score') NOT NULL DEFAULT 'audit_form';

-- The URL that was scored. Kept separate from results so it can be looked up
-- and indexed without parsing JSON.
ALTER TABLE audits
    ADD COLUMN website VARCHAR(255) NULL AFTER business_name,
    ADD COLUMN score TINYINT UNSIGNED NULL AFTER results_generated_at;
