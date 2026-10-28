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
