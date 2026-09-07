-- Phase 1: campaigns, questions, task slots and responses.

CREATE TABLE IF NOT EXISTS campaigns (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id       BIGINT UNSIGNED NOT NULL,
    name              VARCHAR(160) NOT NULL,
    type              ENUM('site_feedback','head_to_head','serp_test','creative_test',
                           'verified_visit','targeted_visit','content_read','unverified_visit')
                      NOT NULL,
    status            ENUM('draft','pending_review','approved','active','paused',
                           'completed','rejected','cancelled')
                      NOT NULL DEFAULT 'draft',
    urls              JSON NOT NULL,               -- 1 url, or 2+ for head-to-head
    min_dwell_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    targeting         JSON NULL,                   -- geo, device, profile predicates
    min_trust_score   SMALLINT NOT NULL DEFAULT 50,
    slots_total       INT UNSIGNED NOT NULL,
    slots_completed   INT UNSIGNED NOT NULL DEFAULT 0,
    credits_per_slot  DECIMAL(6,2) NOT NULL,
    credits_reserved  DECIMAL(12,2) NOT NULL,
    payout_cents      BIGINT NOT NULL,             -- per completed slot
    daily_slot_cap    INT UNSIGNED NULL,
    starts_at         DATETIME NULL,
    ends_at           DATETIME NULL,
    reviewed_by       BIGINT UNSIGNED NULL,
    reviewed_at       DATETIME NULL,
    rejection_reason  VARCHAR(500) NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY campaigns_business_index (business_id),
    KEY campaigns_status_type_index (status, type),
    CONSTRAINT campaigns_business_fk FOREIGN KEY (business_id)
        REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT campaigns_reviewer_fk FOREIGN KEY (reviewed_by)
        REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT campaigns_slots_positive CHECK (slots_total > 0),
    CONSTRAINT campaigns_payout_positive CHECK (payout_cents >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_questions (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id        BIGINT UNSIGNED NOT NULL,
    position           SMALLINT UNSIGNED NOT NULL,
    kind               ENUM('short_text','long_text','single','multi','rating','yes_no') NOT NULL,
    prompt             VARCHAR(500) NOT NULL,
    options            JSON NULL,
    required           TINYINT(1) NOT NULL DEFAULT 1,
    is_attention_check TINYINT(1) NOT NULL DEFAULT 0,
    expected_answer    VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY questions_campaign_position_unique (campaign_id, position),
    CONSTRAINT questions_campaign_fk FOREIGN KEY (campaign_id)
        REFERENCES campaigns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Slots are LEASED, never assigned. Claim with:
--
--   SELECT id FROM task_slots
--    WHERE campaign_id = ? AND state = 'open'
--    ORDER BY id LIMIT 1
--    FOR UPDATE SKIP LOCKED;
--
-- SKIP LOCKED requires MySQL 8.0+ or MariaDB 10.6+. On an older server it is a
-- syntax error, not a silent fallback -- check the version before deploying,
-- because without it concurrent claims serialise and, if the clause is simply
-- dropped, two panelists can be handed the same slot.
CREATE TABLE IF NOT EXISTS task_slots (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id   BIGINT UNSIGNED NOT NULL,
    state         ENUM('open','leased','submitted','approved','rejected','expired','stalled')
                  NOT NULL DEFAULT 'open',
    panelist_id   BIGINT UNSIGNED NULL,
    token         CHAR(36) NOT NULL,               -- app-generated UUID; the ?pm_t= value
    leased_at     DATETIME NULL,
    leased_until  DATETIME NULL,
    submitted_at  DATETIME NULL,
    resolved_at   DATETIME NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    dwell_seconds INT UNSIGNED NULL,
    scroll_depth  TINYINT UNSIGNED NULL,
    payout_cents  BIGINT NOT NULL,
    reject_reason VARCHAR(255) NULL,
    quality_flags JSON NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slots_token_unique (token),
    -- One attempt per panelist per campaign, ever. In MySQL a UNIQUE key treats
    -- NULLs as distinct, so unclaimed slots (panelist_id NULL) coexist freely
    -- while a second claim by the same panelist is rejected by the database.
    -- This is the same behaviour Postgres gets from a partial unique index.
    UNIQUE KEY slots_campaign_panelist_unique (campaign_id, panelist_id),
    KEY slots_claim_index (campaign_id, state, id),
    KEY slots_sweeper_index (state, leased_until),
    CONSTRAINT slots_campaign_fk FOREIGN KEY (campaign_id)
        REFERENCES campaigns (id) ON DELETE CASCADE,
    CONSTRAINT slots_panelist_fk FOREIGN KEY (panelist_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS responses (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slot_id       BIGINT UNSIGNED NOT NULL,
    question_id   BIGINT UNSIGNED NOT NULL,
    answer_text   TEXT NULL,
    answer_choice JSON NULL,
    answer_number SMALLINT NULL,
    ms_to_answer  INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY responses_slot_question_unique (slot_id, question_id),
    KEY responses_question_index (question_id),
    CONSTRAINT responses_slot_fk FOREIGN KEY (slot_id)
        REFERENCES task_slots (id) ON DELETE CASCADE,
    CONSTRAINT responses_question_fk FOREIGN KEY (question_id)
        REFERENCES campaign_questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
