<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;
use Throwable;

/**
 * The monthly email answering "am I winning?".
 *
 * /compare already does this work, for anybody, once, for free. The Premium
 * version is the same audit run on a schedule against competitors the member
 * saved, and emailed -- which is the whole difference between a tool somebody
 * remembers to use twice and one that keeps telling them something.
 *
 * Nothing here fetches a competitor automatically. A member types the name and
 * the figures off the competitor's own Google listing, because guessing a
 * rival from a map radius is how you email somebody a report about a business
 * two towns over that they have never heard of.
 *
 * report_next_at is the schedule, on the account. A column rather than "thirty
 * days since the last one" so that turning the report on, changing a
 * competitor, or a run that failed can each move the date without rewriting
 * what already happened.
 */
final class MonthlyReport
{
    /** Two, because the audit takes two and an email about five is not read. */
    public const MAX_COMPETITORS = 2;

    public const EVERY_DAYS = 30;

    /** Reviews of the member's own page handed to the audit. */
    private const SAMPLE = 12;

    private static ?bool $ready = null;

    /** Whether migration 028 has been applied. */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        try {
            Database::first('SELECT 1 FROM report_competitors LIMIT 1');
            Database::first('SELECT report_next_at FROM accounts LIMIT 1');
            return self::$ready = true;
        } catch (PDOException) {
            return self::$ready = false;
        }
    }

    /** Only for tests. */
    public static function forget(): void
    {
        self::$ready = null;
    }

    /** @return list<array<string,mixed>> */
    public static function competitors(int $accountId): array
    {
        if (!self::ready()) {
            return [];
        }

        return Database::all(
            'SELECT id, name, rating, review_count FROM report_competitors
              WHERE account_id = :a ORDER BY id ASC LIMIT ' . self::MAX_COMPETITORS,
            ['a' => $accountId],
        );
    }

    /**
     * Replace the saved competitors, and set the first report running.
     *
     * Replace rather than merge: the form shows both rows at once, so what is
     * submitted IS the list. A merge would leave a competitor the member
     * deleted on screen still in next month's email.
     *
     * @param list<array{name:string, rating:?float, review_count:?int}> $rows
     */
    public static function save(int $accountId, array $rows): int
    {
        if (!self::ready()) {
            return 0;
        }

        $clean = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $clean[] = [
                'name'   => mb_substr($name, 0, 150),
                // A rating outside 1-5 is a typo, not a rating, and feeding it
                // to the audit would produce arithmetic nobody can defend.
                'rating' => self::rating($row['rating'] ?? null),
                'count'  => self::count($row['review_count'] ?? null),
            ];
            if (count($clean) >= self::MAX_COMPETITORS) {
                break;
            }
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM report_competitors WHERE account_id = :a', ['a' => $accountId]);
            foreach ($clean as $row) {
                Database::run(
                    'INSERT INTO report_competitors (account_id, name, rating, review_count)
                     VALUES (:a, :n, :r, :c)',
                    ['a' => $accountId, 'n' => $row['name'], 'r' => $row['rating'], 'c' => $row['count']],
                );
            }

            // Due now if they have just named somebody, never if they cleared
            // the list. A report with nothing to compare against is an email
            // saying "we could not tell you anything", which is worse than no
            // email.
            Database::run(
                'UPDATE accounts SET report_next_at = ' . ($clean === [] ? 'NULL' : 'NOW()')
                . ' WHERE id = :a',
                ['a' => $accountId],
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return count($clean);
    }

    private static function rating(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $n = (float) $value;

        return ($n >= 1.0 && $n <= 5.0) ? round($n, 1) : null;
    }

    private static function count(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $n = (int) $value;

        return $n >= 0 ? min($n, 1_000_000) : null;
    }

    /**
     * One account whose report is due, or null.
     *
     * One, not a batch. Each report is an API call of a few seconds inside a
     * runner that has review requests to send, and a month's worth of accounts
     * coming due on the same morning must not turn one run into a long one.
     * Due is decided by the DATABASE comparing its own column against NOW():
     * PHP runs on America/Phoenix and MySQL on UTC, so a PHP-side comparison
     * would send every report seven hours early or late.
     *
     * @return array<string,mixed>|null
     */
    public static function nextDue(): ?array
    {
        if (!self::ready()) {
            return null;
        }

        return Database::first(
            'SELECT a.id, a.name, a.plan, a.report_next_at
               FROM accounts a
              WHERE a.report_next_at IS NOT NULL
                AND a.report_next_at <= NOW()
                AND EXISTS (SELECT 1 FROM report_competitors c WHERE c.account_id = a.id)
              ORDER BY a.report_next_at ASC
              LIMIT 1',
        );
    }

    /**
     * Move the clock on, whatever happened.
     *
     * Called after a success AND after a failure, which is the point. An
     * account whose audit throws every time would otherwise be picked up on
     * every run forever, spending an API call each time and starving every
     * other account behind it in the queue.
     */
    public static function reschedule(int $accountId, bool $sent): void
    {
        if (!self::ready()) {
            return;
        }

        Database::run(
            'UPDATE accounts
                SET report_next_at = DATE_ADD(NOW(), INTERVAL :days DAY)'
            . ($sent ? ', report_last_at = NOW()' : '')
            . ' WHERE id = :a',
            ['days' => self::EVERY_DAYS, 'a' => $accountId],
        );
    }

    /**
     * Turn the audit into an email.
     *
     * Every figure in here came out of the audit, which was given only the
     * numbers the member typed and the review text from their own page. The
     * data_limits line is carried through verbatim and never trimmed: it is
     * the part that says what the report could not see, and a report that
     * drops its own caveats is the kind people act on wrongly.
     *
     * @param  array<string,mixed> $audit
     * @return array{subject:string, text:string}
     */
    public static function compose(array $audit, string $business, array $competitors, string $appUrl): array
    {
        $names = implode(' and ', array_map(
            static fn (array $c): string => (string) $c['name'],
            $competitors,
        ));

        $standing = (string) ($audit['standing'] ?? 'not_enough_data');
        $subject  = match ($standing) {
            'ahead'  => $business . ' is ahead of ' . $names,
            'behind' => $business . ' is behind ' . $names,
            'level'  => $business . ' is level with ' . $names,
            default  => 'This month: ' . $business . ' and ' . $names,
        };

        $lines = [];
        $lines[] = (string) ($audit['headline'] ?? '');
        $lines[] = '';
        $lines[] = 'THE NUMBERS';
        $lines[] = '  ' . (string) ($audit['rating_gap'] ?? 'Not enough was supplied to compare.');

        $needed = $audit['five_star_reviews_needed'] ?? null;
        if (is_int($needed) && $needed > 0) {
            $lines[] = '  ' . $needed . ' more five-star reviews would close the gap.';
        }

        foreach ([
            'What they say you are good at' => 'their_strengths',
            'What they say the others do better' => 'competitor_strengths',
            'What comes up as a problem' => 'weaknesses',
        ] as $heading => $key) {
            $items = array_values(array_filter((array) ($audit[$key] ?? [])));
            if ($items === []) {
                continue;
            }
            $lines[] = '';
            $lines[] = mb_strtoupper($heading);
            foreach ($items as $item) {
                $lines[] = '  - ' . trim((string) $item);
            }
        }

        $actions = array_values((array) ($audit['actions'] ?? []));
        if ($actions !== []) {
            $lines[] = '';
            $lines[] = 'WHAT TO DO ABOUT IT';
            foreach ($actions as $action) {
                $lines[] = '  - ' . trim((string) ($action['action'] ?? ''));
                $why = trim((string) ($action['why'] ?? ''));
                if ($why !== '') {
                    $lines[] = '    ' . $why;
                }
            }
        }

        // Always, and never shortened. This is the line that says what the
        // report could not see.
        $limits = trim((string) ($audit['data_limits'] ?? ''));
        if ($limits !== '') {
            $lines[] = '';
            $lines[] = 'WHAT THIS COULD NOT SEE';
            foreach (explode("\n", wordwrap($limits, 68)) as $line) {
                $lines[] = '  ' . $line;
            }
        }

        $lines[] = '';
        $lines[] = 'Your reviews: ' . rtrim($appUrl, '/') . '/members/promomonster-reviews';
        $lines[] = 'Change who you are compared against, or stop these: '
                 . rtrim($appUrl, '/') . '/members/settings';

        return ['subject' => $subject, 'text' => implode("\n", $lines)];
    }

    /**
     * Everything the audit needs about the member's own business.
     *
     * @return array<string,mixed>
     */
    public static function subject(int $accountId, string $business): array
    {
        $summary = HostedReviews::ready() ? HostedReviews::summary($accountId) : ['count' => 0, 'average' => null];

        $reviews = [];
        foreach (HostedReviews::ready() ? HostedReviews::forAccount($accountId, self::SAMPLE) : [] as $row) {
            $body = trim((string) ($row['body'] ?? ''));
            if ($body !== '') {
                $reviews[] = $body;
            }
        }

        return [
            'name'         => $business,
            'rating'       => $summary['average'] === null ? null : (float) $summary['average'],
            'review_count' => (int) ($summary['count'] ?? 0),
            'reviews'      => $reviews,
        ];
    }
}
