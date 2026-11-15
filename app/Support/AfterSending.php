<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * The work the queue runner does once the queue is empty.
 *
 * Two jobs live here: telling a business a review has arrived, and the
 * monthly report on how they compare. Both are periodic, and neither has its
 * own cron job on purpose.
 *
 * Shared hosting is why. A cron job on Hostinger is created by hand in a
 * control panel, and every one of them is a thing that can be forgotten, typed
 * wrong, or silently dropped when a plan changes. There is one cron job in
 * this product, it already wakes every five minutes, and it already holds the
 * lock that stops two copies running at once -- so a second periodic job
 * belongs inside it rather than beside it.
 *
 * The cost of that decision: this must never be slow and must never throw.
 * The runner's real work is sending review requests, and a report that hangs
 * on an API call would hold up mail somebody is waiting on. So: a small batch
 * of alerts, at most ONE report per run, and every failure caught, logged and
 * stepped over.
 */
final class AfterSending
{
    /**
     * @return array{alerts:int, report:?string, notes:list<string>}
     */
    public static function run(string $appUrl): array
    {
        $notes = [];

        $alerts = 0;
        try {
            $alerts = self::alerts($appUrl, $notes);
        } catch (Throwable $e) {
            $notes[] = 'alerts failed: ' . $e->getMessage();
            error_log('Review alerts failed: ' . $e->getMessage());
        }

        $report = null;
        try {
            $report = self::report($appUrl, $notes);
        } catch (Throwable $e) {
            $notes[] = 'report failed: ' . $e->getMessage();
            error_log('Monthly report failed: ' . $e->getMessage());
        }

        return ['alerts' => $alerts, 'report' => $report, 'notes' => $notes];
    }

    /** @param list<string> $notes */
    private static function alerts(string $appUrl, array &$notes): int
    {
        $sent = 0;

        foreach (ReviewAlerts::pending() as $review) {
            $recipients = ReviewAlerts::recipients((int) $review['account_id']);
            if ($recipients === []) {
                // Nobody to tell. Marked anyway, or this review is looked at
                // again on every run for the life of the account.
                ReviewAlerts::markSent((int) $review['id']);
                $notes[] = 'review #' . (int) $review['id'] . ': nobody to tell';
                continue;
            }

            $mail = ReviewAlerts::compose($review, $appUrl);
            $any  = false;

            foreach ($recipients as $to) {
                $result = Mailer::send([
                    'to'      => $to,
                    'subject' => $mail['subject'],
                    'text'    => $mail['text'],
                    'tag'     => 'review-alert',
                    // The transactional stream, not the bulk one. This is
                    // account mail: it must not ride on the reputation that
                    // a business's review requests build or spend.
                    'from_name' => Mailer::transactionalHeader(),
                    'from'      => Mailer::transactionalFrom(),
                    'stream'    => Mailer::transactionalStream(),
                    'driver'    => Mailer::transactionalDriver(),
                ]);
                $any = $any || (bool) $result['ok'];
                if (!$result['ok']) {
                    $notes[] = 'alert to ' . $to . ' failed: ' . (string) $result['error'];
                }
            }

            // Only once something got through. A provider having a bad minute
            // should mean trying again in five, not a review nobody is ever
            // told about.
            if ($any) {
                ReviewAlerts::markSent((int) $review['id']);
                $sent++;
            }
        }

        return $sent;
    }

    /** @param list<string> $notes */
    private static function report(string $appUrl, array &$notes): ?string
    {
        $account = MonthlyReport::nextDue();
        if ($account === null) {
            return null;
        }

        $id   = (int) $account['id'];
        $name = trim((string) ($account['name'] ?? 'your business'));

        // Checked here as well as when it was scheduled. A plan can lapse
        // between the two, and a report is a paid API call: a downgrade must
        // stop it, not merely stop advertising it.
        if (!Plans::can((string) ($account['plan'] ?? Plans::FREE), Plans::MONTHLY_REPORT)) {
            MonthlyReport::reschedule($id, false);
            $notes[] = 'report for #' . $id . ' skipped: not on a plan that includes it';
            return null;
        }

        if (!Claude::isConfigured()) {
            MonthlyReport::reschedule($id, false);
            $notes[] = 'report for #' . $id . ' skipped: no API key';
            return null;
        }

        $competitors = MonthlyReport::competitors($id);
        if ($competitors === []) {
            MonthlyReport::reschedule($id, false);
            return null;
        }

        try {
            $audit = ReviewComparison::run(
                MonthlyReport::subject($id, $name),
                array_map(static fn (array $c): array => [
                    'name'         => (string) $c['name'],
                    'rating'       => $c['rating'] === null ? null : (float) $c['rating'],
                    'review_count' => $c['review_count'] === null ? null : (int) $c['review_count'],
                    'reviews'      => [],
                ], $competitors),
            );
        } catch (Throwable $e) {
            // Rescheduled on the way out, so one account whose audit keeps
            // failing cannot be picked up again on the very next run and
            // starve everybody behind it.
            MonthlyReport::reschedule($id, false);
            $notes[] = 'report for #' . $id . ' failed: ' . $e->getMessage();
            return null;
        }

        $mail = MonthlyReport::compose($audit, $name, $competitors, $appUrl);
        $any  = false;

        foreach (ReviewAlerts::recipients($id) as $to) {
            $result = Mailer::send([
                'to'        => $to,
                'subject'   => $mail['subject'],
                'text'      => $mail['text'],
                'tag'       => 'monthly-report',
                'from_name' => Mailer::transactionalHeader(),
                'from'      => Mailer::transactionalFrom(),
                'stream'    => Mailer::transactionalStream(),
                'driver'    => Mailer::transactionalDriver(),
            ]);
            $any = $any || (bool) $result['ok'];
            if (!$result['ok']) {
                $notes[] = 'report to ' . $to . ' failed: ' . (string) $result['error'];
            }
        }

        MonthlyReport::reschedule($id, $any);

        return $any ? $name : null;
    }
}
