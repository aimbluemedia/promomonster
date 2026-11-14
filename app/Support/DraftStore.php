<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;

/**
 * Drafted replies: kept, counted, and capped.
 *
 * The table is both the record and the meter. A draft costs a paid API call,
 * so the number written this month IS the usage -- counted from the rows the
 * way SendLimit counts requests from review_requests, rather than from a
 * counter column that drifts the first time a row is deleted.
 *
 * Keeping the draft matters for more than audit. A member presses the button,
 * reads the draft, then reloads the page or opens the review again. Without a
 * stored draft that is a second API call, a different answer, and a second
 * deduction from their month for asking the same question once.
 *
 * Every method tolerates the table not existing. Migration 028 adds it, and a
 * members page that fatals because a migration has not been run yet is a worse
 * failure than a button that is quietly unavailable.
 */
final class DraftStore
{
    private static ?bool $ready = null;

    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        try {
            Database::first('SELECT 1 FROM review_reply_drafts LIMIT 1');
            return self::$ready = true;
        } catch (PDOException) {
            return self::$ready = false;
        }
    }

    /** Only for tests, which build and drop the table between cases. */
    public static function forget(): void
    {
        self::$ready = null;
    }

    /**
     * How the month stands.
     *
     * Counted by the DATABASE, against a column the database wrote. PHP runs
     * on America/Phoenix here and MySQL on UTC -- seven hours apart -- so a
     * PHP-side "first of the month" would start counting on the wrong day
     * twice a year and be wrong by seven hours every other day.
     *
     * @return array{allowed:bool, used:int, limit:int|null, left:int|null, reason:?string}
     */
    public static function month(int $accountId, string $plan): array
    {
        $limit = Plans::limit($plan, 'reply_drafts_per_month');
        $limit = $limit === null ? null : (int) $limit;

        if (!Plans::can($plan, Plans::REPLY_DRAFTS)) {
            return [
                'allowed' => false, 'used' => 0, 'limit' => 0, 'left' => 0,
                'reason'  => 'Drafted replies are on the ' . Plans::name(
                    (string) Plans::cheapestWith(Plans::REPLY_DRAFTS)
                ) . ' plan.',
            ];
        }

        $used = self::ready() ? self::countThisMonth($accountId) : 0;
        $left = $limit === null ? null : max(0, $limit - $used);

        $reason = null;
        if ($limit !== null && $used >= $limit) {
            $reason = sprintf(
                'That is this month\'s %d drafted replies. The count resets on the 1st, '
                . 'and you can still write a reply yourself.',
                $limit,
            );
        }

        return [
            'allowed' => $reason === null,
            'used'    => $used,
            'limit'   => $limit,
            'left'    => $left,
            'reason'  => $reason,
        ];
    }

    private static function countThisMonth(int $accountId): int
    {
        $row = Database::first(
            'SELECT COUNT(*) AS n FROM review_reply_drafts
              WHERE account_id = :a
                AND created_at >= DATE_FORMAT(NOW(), \'%Y-%m-01 00:00:00\')',
            ['a' => $accountId],
        );

        return (int) ($row['n'] ?? 0);
    }

    /** The newest draft for one review, or null. */
    public static function latest(int $accountId, int $reviewId): ?string
    {
        if (!self::ready()) {
            return null;
        }

        $row = Database::first(
            'SELECT draft FROM review_reply_drafts
              WHERE account_id = :a AND hosted_review_id = :r
              ORDER BY id DESC LIMIT 1',
            ['a' => $accountId, 'r' => $reviewId],
        );

        return $row === null ? null : (string) $row['draft'];
    }

    /**
     * Every review's newest draft, in one query.
     *
     * The list screen shows a dozen reviews. Asking per review is a dozen
     * round trips to render one page, which is how a screen that was fine in
     * testing becomes slow for the member with the most reviews -- the one
     * who is getting the most value and would notice first.
     *
     * @param  list<int> $reviewIds
     * @return array<int,string>  review id => newest draft
     */
    public static function forReviews(int $accountId, array $reviewIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $reviewIds)));
        if ($ids === [] || !self::ready()) {
            return [];
        }

        // Built from integers that have just been cast, never from input. A
        // placeholder list would be the alternative and PDO cannot bind an
        // array, so the cast is what makes this safe and the cast is explicit.
        $in = implode(',', $ids);

        $rows = Database::all(
            'SELECT d.hosted_review_id AS rid, d.draft
               FROM review_reply_drafts d
               JOIN (SELECT hosted_review_id, MAX(id) AS newest
                       FROM review_reply_drafts
                      WHERE account_id = :a AND hosted_review_id IN (' . $in . ')
                      GROUP BY hosted_review_id) latest
                 ON latest.newest = d.id
              WHERE d.account_id = :a2',
            ['a' => $accountId, 'a2' => $accountId],
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['rid']] = (string) $row['draft'];
        }

        return $out;
    }

    /** Records a draft. Returns false if the table is not there yet. */
    public static function save(int $accountId, int $reviewId, string $draft, string $model): bool
    {
        if (!self::ready() || trim($draft) === '') {
            return false;
        }

        Database::run(
            'INSERT INTO review_reply_drafts (account_id, hosted_review_id, draft, model)
             VALUES (:a, :r, :d, :m)',
            ['a' => $accountId, 'r' => $reviewId, 'd' => $draft, 'm' => $model],
        );

        return true;
    }
}
