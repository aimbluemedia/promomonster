<?php

declare(strict_types=1);

namespace App\Support;

/**
 * How many review requests an account may still send.
 *
 * Two windows, both from the plan catalogue: a monthly allowance, and a burst
 * cap over a short window so the month cannot be spent in an afternoon. The
 * second one is not bureaucracy — a young sending domain that fires a month of
 * mail in one go is the exact shape spam filters are built to catch, and the
 * cost of that lands on every other account sending through us.
 *
 * Counted from `review_requests` itself rather than a separate counter table.
 * One source of truth means the number on screen and the number enforced can
 * never disagree, and there is no counter to drift when a row is deleted.
 *
 * Two things deliberately do not count:
 *
 *   Reminders. A follow-up is the second half of one ask, not a second ask.
 *   Charging for it would push people towards the one behaviour the playbook
 *   tells them to avoid, which is sending a third message.
 *
 *   Failures. An address that bounced consumed no attention from anyone. An
 *   earlier version of the rate limiter here charged for failed attempts and
 *   locked people out with nothing to show for it; that mistake is not worth
 *   making twice.
 */
final class SendLimit
{
    /**
     * @return array{
     *     allowed:bool, reason:?string,
     *     month_used:int, month_limit:?int, month_left:?int,
     *     burst_used:int, burst_limit:?int, burst_left:?int, burst_days:?int
     * }
     */
    public static function check(int $accountId, string $plan): array
    {
        $monthLimit = self::intOrNull(Plans::limit($plan, 'requests_per_month'));
        $burstLimit = self::intOrNull(Plans::limit($plan, 'burst'));
        $burstDays  = self::intOrNull(Plans::limit($plan, 'burst_days'));

        $monthUsed = self::countSince($accountId, self::monthStart());
        $burstUsed = ($burstLimit === null || $burstDays === null)
            ? 0
            : self::countSince($accountId, self::daysAgo($burstDays));

        $monthLeft = $monthLimit === null ? null : max(0, $monthLimit - $monthUsed);
        $burstLeft = $burstLimit === null ? null : max(0, $burstLimit - $burstUsed);

        $reason = null;
        if ($monthLimit !== null && $monthUsed >= $monthLimit) {
            $reason = sprintf(
                'That is this month\'s %d on the %s plan. The count resets on the 1st.',
                $monthLimit,
                Plans::name($plan),
            );
        } elseif ($burstLimit !== null && $burstDays !== null && $burstUsed >= $burstLimit) {
            $reason = self::burstReason($burstLimit, $burstDays);
        }

        return [
            'allowed'     => $reason === null,
            'reason'      => $reason,
            'month_used'  => $monthUsed,
            'month_limit' => $monthLimit,
            'month_left'  => $monthLeft,
            'burst_used'  => $burstUsed,
            'burst_limit' => $burstLimit,
            'burst_left'  => $burstLeft,
            'burst_days'  => $burstDays,
        ];
    }

    /**
     * Requests that count against the allowance, since a moment in time.
     *
     * Joined through locations because the allowance belongs to the account and
     * a request belongs to a location. Cancelled and failed rows are excluded:
     * see the class note.
     */
    public static function countSince(int $accountId, string $since): int
    {
        $row = Database::first(
            'SELECT COUNT(*) AS n
               FROM review_requests r
               JOIN locations l ON l.id = r.location_id
              WHERE l.account_id = :account
                AND r.is_follow_up = 0
                AND r.status NOT IN (\'failed\', \'cancelled\')
                AND r.created_at >= :since',
            ['account' => $accountId, 'since' => $since],
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * The first instant of the current calendar month.
     *
     * Calendar, not rolling: "4 a month" has to mean something an owner can
     * predict, and "it resets on the 1st" is a sentence they already
     * understand. A rolling 30-day window is fairer on paper and impossible to
     * explain on the phone.
     */
    public static function monthStart(): string
    {
        return date('Y-m-01 00:00:00');
    }

    public static function daysAgo(int $days): string
    {
        return date('Y-m-d H:i:s', time() - ($days * 86400));
    }

    private static function burstReason(int $limit, int $days): string
    {
        if ($limit === 1 && $days === 7) {
            return 'Free is one ask a week. The next one is available seven days after the last.';
        }

        $per = $days === 1 ? 'a day' : 'every ' . $days . ' days';

        return sprintf(
            'That is %d %s, which is the pace on this plan. Sending a month of requests at once is what gets email filtered.',
            $limit,
            $per,
        );
    }

    private static function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
