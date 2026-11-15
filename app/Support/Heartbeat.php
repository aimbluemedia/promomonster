<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;

/**
 * Whether the scheduled job is actually running, and how often.
 *
 * The queue and the process that drains it have never been connected by
 * anything a page could read. A request said "queued" and the screen could not
 * tell a cron job that runs in four minutes from one that was never set up.
 * Both look identical from the database, and the second one is the common case
 * on shared hosting, where the job is created by hand in a control panel and it
 * is entirely possible to think you did it.
 *
 * Nothing here can read the crontab -- PHP on shared hosting cannot, and
 * Hostinger exposes no API that would say. So this does not ask what is
 * scheduled. It records what happened, and works the schedule out from that:
 *
 *   - the runner writes a row every time it wakes, including the runs with
 *     nothing to do, because those are the ones that prove it is alive
 *   - the gap between consecutive rows is the real interval, measured
 *   - the next run is the last one plus that interval
 *
 * A measured interval is better than a configured one even when both exist. The
 * crontab can say every five minutes while the host quietly runs it every
 * fifteen under load, and it is the fifteen a customer is waiting through.
 *
 * Every comparison is made by the database against columns the database wrote.
 * PHP runs on America/Phoenix here and MySQL on UTC: a PHP-side comparison
 * would report a runner that last ran two minutes ago as having stopped seven
 * hours ago, which is the exact opposite of what this is for.
 */
final class Heartbeat
{
    public const SEND_QUEUE = 'send-due';

    /** Runs kept. At five minutes apart this is about a day, which is all anybody reads. */
    private const KEEP = 400;

    /**
     * How many intervals may pass before the runner counts as stopped.
     *
     * Not one: a five-minute job legitimately arrives at 5:03 under load, and a
     * page that cried wolf every third refresh would be ignored by the time it
     * mattered. Not ten either, which is half a morning of silence.
     */
    private const LATE_AFTER = 3;

    /** With no measured interval yet, silence this long is still worth saying. */
    private const UNKNOWN_LATE_SECONDS = 1800;

    private static ?bool $ready = null;

    /** Whether migration 024 has been applied. */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        try {
            $row = Database::first(
                'SELECT COUNT(*) AS n FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
                ['t' => 'cron_runs'],
            );

            return self::$ready = ((int) ($row['n'] ?? 0)) === 1;
        } catch (PDOException) {
            return self::$ready = false;
        }
    }

    /** For the tests, which take the table away and put it back. */
    public static function forget(): void
    {
        self::$ready = null;
    }

    // -- Writing, from the runner ----------------------------------------

    /**
     * Mark the start of a run. Returns the row id, or null if it could not.
     *
     * Never throws. A heartbeat that breaks the job it is watching would be
     * worse than no heartbeat at all -- the whole point is that sending keeps
     * working whether or not this table exists.
     */
    public static function start(string $job): ?int
    {
        if (!self::ready()) {
            return null;
        }

        try {
            Database::run('INSERT INTO cron_runs (job) VALUES (:job)', ['job' => $job]);

            return (int) Database::connection()->lastInsertId();
        } catch (PDOException) {
            return null;
        }
    }

    /** Close the run off with what it did. Never throws, for the same reason. */
    public static function finish(
        ?int $id,
        string $outcome = 'ok',
        int $due = 0,
        int $sent = 0,
        int $failed = 0,
        int $skipped = 0,
    ): void {
        if ($id === null || !self::ready()) {
            return;
        }

        try {
            Database::run(
                'UPDATE cron_runs
                    SET finished_at = NOW(), outcome = :outcome, due_count = :due,
                        sent_count = :sent, failed_count = :failed, skipped_count = :skipped
                  WHERE id = :id',
                ['outcome' => $outcome, 'due' => $due, 'sent' => $sent,
                 'failed' => $failed, 'skipped' => $skipped, 'id' => $id],
            );
        } catch (PDOException) {
            // Nothing to do about it, and nothing worth failing a send over.
        }
    }

    /** Keep the table small. Cheap, and it runs on a schedule already. */
    public static function prune(string $job): void
    {
        if (!self::ready()) {
            return;
        }

        try {
            // The id below the cut, then one delete. A correlated LIMIT inside
            // a DELETE subquery is refused by MySQL.
            $row = Database::first(
                'SELECT id FROM cron_runs WHERE job = :job
                  ORDER BY id DESC LIMIT 1 OFFSET ' . self::KEEP,
                ['job' => $job],
            );

            if ($row !== null) {
                Database::run(
                    'DELETE FROM cron_runs WHERE job = :job AND id <= :id',
                    ['job' => $job, 'id' => (int) $row['id']],
                );
            }
        } catch (PDOException) {
            // Housekeeping. Never worth failing a run over.
        }
    }

    // -- Reading, from a page ---------------------------------------------

    /**
     * What the runner is doing, in terms a screen can use.
     *
     * state is one of:
     *   'unknown'  the table is not there, so nothing can be said
     *   'never'    it has not run once. The usual cause: no cron job exists
     *   'ok'       it is running, and next_at is when to expect it again
     *   'late'     it ran before, and has been quiet for too long
     *
     * every_seconds is null until there are two runs to measure between.
     *
     * @return array{state:string, last_at:?string, ago_seconds:?int,
     *               every_seconds:?int, next_at:?string, due_seconds:?int,
     *               last_outcome:?string, last_sent:?int}
     */
    public static function status(string $job): array
    {
        $blank = ['state' => 'unknown', 'last_at' => null, 'ago_seconds' => null,
                  'every_seconds' => null, 'next_at' => null, 'due_seconds' => null,
                  'last_outcome' => null, 'last_sent' => null];

        if (!self::ready()) {
            return $blank;
        }

        try {
            // TIMESTAMPDIFF against NOW(), both inside the database, so the
            // answer does not depend on PHP agreeing about the time.
            $last = Database::first(
                'SELECT started_at, outcome, sent_count, finished_at,
                        TIMESTAMPDIFF(SECOND, started_at, NOW()) AS ago
                   FROM cron_runs WHERE job = :job
               ORDER BY started_at DESC, id DESC LIMIT 1',
                ['job' => $job],
            );
        } catch (PDOException) {
            return $blank;
        }

        if ($last === null) {
            return array_merge($blank, ['state' => 'never']);
        }

        $ago   = (int) $last['ago'];
        $every = self::interval($job);

        $late = $every === null
            ? $ago > self::UNKNOWN_LATE_SECONDS
            : $ago > $every * self::LATE_AFTER;

        $nextAt = null;
        $dueIn  = null;
        if ($every !== null) {
            $nextAt = self::shift((string) $last['started_at'], $every);
            $dueIn  = $every - $ago;
        }

        return [
            'state'         => $late ? 'late' : 'ok',
            'last_at'       => (string) $last['started_at'],
            'ago_seconds'   => $ago,
            'every_seconds' => $every,
            'next_at'       => $nextAt,
            'due_seconds'   => $dueIn,
            'last_outcome'  => (string) $last['outcome'],
            'last_sent'     => (int) $last['sent_count'],
        ];
    }

    /**
     * The measured gap between runs, in seconds, or null if unmeasurable.
     *
     * The MEDIAN of recent gaps, not the mean. One missed run doubles a gap,
     * and a mean would fold that into the estimate and quietly tell everybody
     * the job runs half as often as it does. A median ignores it, which is the
     * right treatment for an outlier that has already been reported elsewhere
     * as lateness.
     */
    public static function interval(string $job, int $sample = 12): ?int
    {
        if (!self::ready()) {
            return null;
        }

        try {
            $rows = Database::all(
                'SELECT started_at FROM cron_runs WHERE job = :job
              ORDER BY started_at DESC, id DESC LIMIT ' . max(2, min(60, $sample)),
                ['job' => $job],
            );
        } catch (PDOException) {
            return null;
        }

        if (count($rows) < 2) {
            return null;
        }

        $gaps = [];
        for ($i = 0; $i < count($rows) - 1; $i++) {
            $newer = strtotime((string) $rows[$i]['started_at']);
            $older = strtotime((string) $rows[$i + 1]['started_at']);
            if ($newer === false || $older === false) {
                continue;
            }
            // Both timestamps come from the same clock, so the DIFFERENCE is
            // sound whatever timezone PHP thinks they are in. That is why this
            // one subtraction is safe where comparing one of them to time()
            // would not be.
            $gap = $newer - $older;
            if ($gap > 0) {
                $gaps[] = $gap;
            }
        }

        if ($gaps === []) {
            return null;
        }

        sort($gaps);
        $middle = intdiv(count($gaps), 2);

        return count($gaps) % 2 === 1
            ? $gaps[$middle]
            : (int) round(($gaps[$middle - 1] + $gaps[$middle]) / 2);
    }

    /** A stored datetime plus n seconds, computed by the database. */
    private static function shift(string $from, int $seconds): ?string
    {
        try {
            $row = Database::first(
                'SELECT :from + INTERVAL ' . (int) $seconds . ' SECOND AS t',
                ['from' => $from],
            );

            return $row === null ? null : (string) $row['t'];
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * "about 4 minutes", for putting in a sentence.
     *
     * Rounded, because a runner that arrives at 4:58 rather than 5:00 is not
     * worth a number that precise and precision here reads as a promise.
     */
    public static function inWords(?int $seconds): string
    {
        if ($seconds === null) {
            return 'an unknown time';
        }

        $seconds = abs($seconds);

        if ($seconds < 90) {
            return 'less than a minute';
        }
        if ($seconds < 3600) {
            $minutes = (int) round($seconds / 60);

            return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        }
        if ($seconds < 86400) {
            $hours = (int) round($seconds / 3600);

            return $hours . ' hour' . ($hours === 1 ? '' : 's');
        }

        $days = (int) round($seconds / 86400);

        return $days . ' day' . ($days === 1 ? '' : 's');
    }
}
