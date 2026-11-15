-- Phase 0 schema: waitlist capture and the rate-limit bucket it needs.
--
-- The full platform schema (users, campaigns, task_slots, the double-entry
-- ledger, payouts, fraud tables) is specified in docs/04-data-model.md and
-- lands in Phase 1. Deliberately not built yet -- docs/00-execution-plan.md
-- gates platform work behind 20 hand-sold studies.

CREATE TABLE IF NOT EXISTS waitlist (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email          VARCHAR(254)    NOT NULL,
    role           ENUM('business','panelist') NOT NULL,
    name           VARCHAR(120)    NULL,

    -- business side
    company        VARCHAR(160)    NULL,
    website        VARCHAR(300)    NULL,
    goal           TEXT            NULL,

    -- panel side
    country        CHAR(2)         NULL,
    region         VARCHAR(80)     NULL,
    postal_code    VARCHAR(16)     NULL,

    -- provenance
    source         VARCHAR(80)     NULL,
    referrer       VARCHAR(500)    NULL,
    ip             VARCHAR(45)     NULL,
    user_agent     VARCHAR(500)    NULL,

    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- One signup per email per side. A repeat submission is treated as success
    -- rather than an error, so the visitor never sees a failure for it.
    UNIQUE KEY waitlist_email_role_unique (email, role),
    KEY waitlist_created_at_index (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket_key        CHAR(64)  NOT NULL,
    attempts          INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME  NOT NULL,
    PRIMARY KEY (bucket_key),
    KEY rate_limits_window_index (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
