<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;

/**
 * Telling a business a review has arrived.
 *
 * Until now nothing did. A review landed on the hosted page and sat there
 * until the owner happened to open the dashboard, which for most of them is
 * never -- so the one-star that needed answering the same afternoon was found
 * a fortnight later, if at all.
 *
 * On every plan, deliberately. This is not a feature anybody would pay for; it
 * is the thing that makes the product feel alive, and a Free member who never
 * hears about their reviews has no reason to come back and no reason to
 * upgrade. It is also the trigger for the one thing Premium does sell: the
 * mail about a critical review is where "we can draft you a reply" belongs.
 *
 * Sent by the queue runner rather than inside the request that created the
 * review. The person writing the review is a customer doing the business a
 * favour, and making them watch a spinner while our SMTP wakes up is the
 * wrong place to spend their patience. The runner wakes every five minutes,
 * which is as immediate as this needs to be.
 *
 * alerted_at is set after the mail is away, so a crash mid-batch resumes
 * exactly where it stopped and nobody is told twice.
 */
final class ReviewAlerts
{
    /** At or below this, the mail leads with putting it right. */
    public const POOR = 3;

    /** Per run. The runner has other work and a backlog is not an emergency. */
    public const BATCH = 10;

    private static ?bool $ready = null;

    /** Whether migration 028 has been applied. */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        try {
            Database::first('SELECT alerted_at FROM hosted_reviews LIMIT 1');
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

    /**
     * Reviews that have arrived and not been announced.
     *
     * Only for accounts that want them. alerts_on defaults to 1, so an
     * existing account starts hearing about its reviews without having to go
     * and find the setting first.
     *
     * @return list<array<string,mixed>>
     */
    public static function pending(int $limit = self::BATCH): array
    {
        if (!self::ready()) {
            return [];
        }

        return Database::all(
            'SELECT hr.id, hr.account_id, hr.rating, hr.body, hr.author_name, hr.created_at,
                    a.name AS business, a.plan
               FROM hosted_reviews hr
               JOIN accounts a ON a.id = hr.account_id
              WHERE hr.alerted_at IS NULL
                AND a.alerts_on = 1
              ORDER BY hr.id ASC
              LIMIT ' . max(1, min(100, $limit)),
        );
    }

    /**
     * Who hears about it.
     *
     * Every active user on the account, because a business with an office
     * manager and an owner wants both to know, and we have no way to ask which
     * one. Suppressed and bouncing addresses are left out -- an alert is
     * account mail, but it is still mail, and sending to an address we know is
     * dead costs reputation on the stream that carries password resets.
     *
     * @return list<string>
     */
    public static function recipients(int $accountId): array
    {
        $rows = Database::all(
            'SELECT u.email
               FROM users u
               JOIN account_users au ON au.user_id = u.id
              WHERE au.account_id = :a AND u.status = \'active\'',
            ['a' => $accountId],
        );

        $out = [];
        foreach ($rows as $row) {
            $email = trim((string) $row['email']);
            if ($email !== '' && Mailer::isSendableAddress($email)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * The subject and body for one review.
     *
     * Two shapes, because the two situations are not the same email. A good
     * review is news; a bad one is a job with a clock on it, and burying
     * "this one needs an answer today" under a cheerful subject line is how
     * it gets read on the train and forgotten.
     *
     * The review's own words are in the mail. An alert that says "you have a
     * new 2-star review, log in to read it" makes somebody log in to find out
     * how bad their day is, which is a worse experience than just telling them.
     *
     * @param  array<string,mixed> $review
     * @return array{subject:string, text:string}
     */
    public static function compose(array $review, string $appUrl): array
    {
        $rating   = (int) ($review['rating'] ?? 0);
        $business = trim((string) ($review['business'] ?? 'your business'));
        $who      = ReplyDrafts::firstName((string) ($review['author_name'] ?? '')) ?? 'Someone';
        $body     = trim((string) ($review['body'] ?? ''));
        $poor     = $rating > 0 && $rating <= self::POOR;
        $url      = rtrim($appUrl, '/') . '/members/promomonster-reviews';

        $stars = $rating > 0 ? $rating . ' out of 5' : 'no rating';

        $subject = $poor
            ? 'A ' . $rating . '-star review for ' . $business . ' needs an answer'
            : 'New ' . $stars . ' review for ' . $business;

        $lines = [];
        $lines[] = $poor
            ? $who . ' has left you ' . $stars . ', and this is the kind that is worth '
              . 'answering today rather than next week.'
            : $who . ' has left you ' . $stars . '.';
        $lines[] = '';

        if ($body !== '') {
            // Quoted rather than summarised. It is their words that the
            // owner has to respond to, and a summary would be us putting
            // words in a customer's mouth.
            foreach (explode("\n", wordwrap($body, 68)) as $line) {
                $lines[] = '  ' . $line;
            }
            $lines[] = '';
        }

        $lines[] = $poor
            ? 'Replying well in public is worth more than the review costs you. '
              . 'Acknowledge it, do not argue, and offer to put it right:'
            : 'Reply to it here, and it shows under the review on your page:';
        $lines[] = '  ' . $url;
        $lines[] = '';
        $lines[] = 'To stop these, turn off review alerts in Settings.';

        return ['subject' => $subject, 'text' => implode("\n", $lines)];
    }

    /** Marks one review as announced. Called only after the mail is away. */
    public static function markSent(int $reviewId): void
    {
        if (!self::ready()) {
            return;
        }

        // NOW() from the database, not gmdate() from PHP. PHP runs on
        // America/Phoenix here and MySQL on UTC, and this column is only ever
        // compared against other columns the database wrote.
        Database::run(
            'UPDATE hosted_reviews SET alerted_at = NOW() WHERE id = :id AND alerted_at IS NULL',
            ['id' => $reviewId],
        );
    }

    /**
     * Whether an account wants these.
     *
     * Read through a guard rather than straight off the row: alerts_on arrives
     * with migration 028, and a settings page that fatals before it has been
     * run would take every other setting down with it.
     */
    public static function wanted(array $account): bool
    {
        return !array_key_exists('alerts_on', $account) || (int) $account['alerts_on'] === 1;
    }
}
