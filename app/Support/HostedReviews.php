<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reviews PromoMonster hosts itself.
 *
 * A business gets a page of its own, a link it can send anybody, and a widget
 * that puts the result on its own website. Three ways in, all landing in one
 * table, each recorded with where it came from.
 *
 * The rule the whole thing is built around: a review that is published stays
 * published. There is no hide, no approve, no "pending". A business answers a
 * review it does not like -- publicly, under the review -- and that is the only
 * lever it has. That is not squeamishness: a page showing only the reviews a
 * business approved is what the FTC's rule on consumer reviews exists to stop,
 * and it is also worthless to the customer reading it, which makes the widget
 * worthless to the business displaying it.
 *
 * The second rule: never let one kind of review look like another. A review
 * typed in by the business is labelled as such wherever it appears. Only one
 * that arrived through a link we emailed a named customer is called verified.
 */
final class HostedReviews
{
    /** Anything longer is a paragraph somebody pasted by accident. */
    private const MAX_BODY = 4000;
    private const MAX_NAME = 120;
    private const MAX_CITY = 120;

    /** Public submissions allowed from one address in an hour. */
    private const PER_IP = 5;
    private const WINDOW = 3600;

    /** @var array<string,bool>|null */
    private static ?bool $ready = null;

    /** Whether migration 020 has been run. Same pattern as PasswordReset. */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        try {
            $row = Database::first(
                'SELECT 1 AS present FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'hosted_reviews\' LIMIT 1',
            );
        } catch (\PDOException $e) {
            return self::$ready = false;
        }

        return self::$ready = $row !== null;
    }

    // -- The public address --------------------------------------------------

    /**
     * The account's page slug, creating one on first use.
     *
     * Derived from the business name because a customer should recognise the
     * address before they click it, and a stranger seeing acme-pools in a URL
     * learns nothing they could not read on the sign outside. A numeric suffix
     * settles collisions rather than a random string, so the common case stays
     * readable.
     */
    public static function slug(int $accountId, string $businessName): ?string
    {
        if (!self::ready()) {
            return null;
        }

        $row = Database::first('SELECT public_slug FROM accounts WHERE id = :id', ['id' => $accountId]);
        $existing = trim((string) ($row['public_slug'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $base = self::slugify($businessName);
        for ($suffix = 0; $suffix < 50; $suffix++) {
            $candidate = $suffix === 0 ? $base : $base . '-' . $suffix;

            $taken = Database::first(
                'SELECT 1 AS t FROM accounts WHERE public_slug = :s LIMIT 1',
                ['s' => $candidate],
            );
            if ($taken !== null) {
                continue;
            }

            // The unique index is the real arbiter; this can still lose a race,
            // in which case the next loop picks the next suffix.
            try {
                Database::run(
                    'UPDATE accounts SET public_slug = :s WHERE id = :id AND public_slug IS NULL',
                    ['s' => $candidate, 'id' => $accountId],
                );
            } catch (\PDOException $e) {
                continue;
            }

            $row = Database::first('SELECT public_slug FROM accounts WHERE id = :id', ['id' => $accountId]);
            $now = trim((string) ($row['public_slug'] ?? ''));
            if ($now !== '') {
                return $now;
            }
        }

        return null;
    }

    /** Lowercase, hyphens, nothing else. Never empty. */
    public static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 60);

        return $slug === '' ? 'business' : $slug;
    }

    /** @return array<string,mixed>|null The account a public slug belongs to. */
    public static function bySlug(string $slug): ?array
    {
        if (!self::ready() || $slug === '' || preg_match('/^[a-z0-9-]{1,80}$/', $slug) !== 1) {
            return null;
        }

        return Database::first(
            'SELECT id, name, public_slug FROM accounts
              WHERE public_slug = :s AND status = \'active\' LIMIT 1',
            ['s' => $slug],
        );
    }

    // -- Reading -------------------------------------------------------------

    /** @return array<int,array<string,mixed>> Newest first. */
    public static function forAccount(int $accountId, int $limit = 50): array
    {
        if (!self::ready()) {
            return [];
        }

        return Database::all(
            'SELECT r.*, c.first_name AS contact_first
               FROM hosted_reviews r
          LEFT JOIN contacts c ON c.id = r.contact_id
              WHERE r.account_id = :id
           ORDER BY r.created_at DESC, r.id DESC
              LIMIT ' . max(1, min(200, $limit)),
            ['id' => $accountId],
        );
    }

    /**
     * Count and average.
     *
     * The average is over everything, because an average over a selection is
     * not an average. Rounded to one decimal for display only.
     *
     * @return array{count:int, average:?float, stars:array<int,int>}
     */
    public static function summary(int $accountId): array
    {
        $empty = ['count' => 0, 'average' => null, 'stars' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
        if (!self::ready()) {
            return $empty;
        }

        $row = Database::first(
            'SELECT COUNT(*) AS n, AVG(rating) AS avg FROM hosted_reviews WHERE account_id = :id',
            ['id' => $accountId],
        );

        $count = (int) ($row['n'] ?? 0);
        if ($count === 0) {
            return $empty;
        }

        $stars = $empty['stars'];
        foreach (Database::all(
            'SELECT rating, COUNT(*) AS n FROM hosted_reviews WHERE account_id = :id GROUP BY rating',
            ['id' => $accountId],
        ) as $band) {
            $stars[(int) $band['rating']] = (int) $band['n'];
        }

        return [
            'count'   => $count,
            'average' => round((float) $row['avg'], 1),
            'stars'   => $stars,
        ];
    }

    // -- Writing -------------------------------------------------------------

    /**
     * Records a review.
     *
     * @param array{account:int, location?:?int, contact?:?int, source:string,
     *              name:string, email?:?string, city:string, rating:int,
     *              body:string, source_url?:?string} $review
     * @return array{ok:bool, error:?string}
     */
    public static function add(array $review): array
    {
        if (!self::ready()) {
            return ['ok' => false, 'error' => 'Reviews are not switched on yet.'];
        }

        $name = trim($review['name']);
        $city = trim((string) ($review['city'] ?? ''));
        $body = trim($review['body']);
        $rating = (int) $review['rating'];

        if ($name === '') {
            return ['ok' => false, 'error' => 'Please give a name to put on the review.'];
        }
        if (mb_strlen($name) > self::MAX_NAME) {
            return ['ok' => false, 'error' => 'That name is too long.'];
        }
        if ($city === '') {
            return ['ok' => false, 'error' => 'Please add the city and state.'];
        }
        if (mb_strlen($city) > self::MAX_CITY) {
            return ['ok' => false, 'error' => 'That city and state is too long.'];
        }
        if ($rating < 1 || $rating > 5) {
            return ['ok' => false, 'error' => 'Choose a rating from one to five stars.'];
        }
        if ($body === '') {
            return ['ok' => false, 'error' => 'Please write a few words about what happened.'];
        }
        if (mb_strlen($body) > self::MAX_BODY) {
            return ['ok' => false, 'error' => 'That review is longer than we can store. Please shorten it.'];
        }

        $source = in_array(
            $review['source'],
            ['invited', 'public_link', 'entered_by_business', 'google'],
            true,
        ) ? $review['source'] : 'public_link';

        $email = trim((string) ($review['email'] ?? ''));

        // Only a Google link, and only on a Google row. It is shown to the
        // public as "read it on Google", so it must not be able to point
        // anywhere else -- the same open-redirect reasoning as ReviewLink.
        $sourceUrl = null;
        if ($source === 'google') {
            $checked = ReviewLink::check(trim((string) ($review['source_url'] ?? '')));
            if (($review['source_url'] ?? '') !== '' && !$checked['ok']) {
                return ['ok' => false, 'error' => (string) $checked['error']];
            }
            $sourceUrl = $checked['ok'] ? $checked['url'] : null;
        }

        Database::run(
            'INSERT INTO hosted_reviews
                (account_id, location_id, contact_id, source, source_url, author_name,
                 author_city, author_email, rating, body, submitted_ip)
             VALUES
                (:account, :location, :contact, :source, :url, :name, :city, :email,
                 :rating, :body, :ip)',
            [
                'account'  => (int) $review['account'],
                'location' => $review['location'] ?? null,
                'contact'  => $review['contact'] ?? null,
                'source'   => $source,
                'url'      => $sourceUrl,
                'name'     => $name,
                'city'     => $city,
                'email'    => $email === '' ? null : $email,
                'rating'   => $rating,
                'body'     => $body,
                'ip'       => Request::ip(),
            ],
        );

        return ['ok' => true, 'error' => null];
    }

    /**
     * The business's public answer to a review.
     *
     * Replacing an existing reply is allowed; removing the review is not, which
     * is the point. An empty reply clears the answer, not the review.
     */
    public static function reply(int $accountId, int $reviewId, string $body): bool
    {
        if (!self::ready()) {
            return false;
        }

        $body = trim($body);
        if (mb_strlen($body) > self::MAX_BODY) {
            return false;
        }

        // account_id in the WHERE, so one business cannot answer another's.
        $done = Database::run(
            'UPDATE hosted_reviews
                SET reply_body = :body, replied_at = ' . ($body === '' ? 'NULL' : 'NOW()') . '
              WHERE id = :id AND account_id = :account',
            [
                'body'    => $body === '' ? null : $body,
                'id'      => $reviewId,
                'account' => $accountId,
            ],
        );

        return $done->rowCount() >= 0;
    }

    /** True when this address has posted too many public reviews lately. */
    public static function throttled(): bool
    {
        return RateLimiter::tooManyAttempts('hosted-review:' . Request::ip(), self::PER_IP, self::WINDOW);
    }

    /**
     * How a source reads on screen. Never left as the raw enum.
     *
     * Only 'invited' says verified, because only 'invited' arrived through a
     * link we addressed to a named customer. The other three are the business's
     * word, and say so -- including the Google ones, which we have no way to
     * check and must not dress up as though we had.
     */
    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            'invited'             => 'Verified customer',
            'entered_by_business' => 'Added by the business',
            'google'              => 'From Google, added by the business',
            default               => 'Left on the review page',
        };
    }

    /** The short badge beside a review. Empty for the ordinary cases. */
    public static function sourceBadge(string $source): string
    {
        return match ($source) {
            'invited' => 'Verified',
            'google'  => 'Google',
            default   => '',
        };
    }
}
