<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Scores how well a website is set up to collect and use customer reviews.
 *
 * The score is DETERMINISTIC and comes entirely from things present or absent
 * in the page we fetched. That is the point: a visitor can open their own
 * source, search for the same thing, and find it. A model asked to "score this
 * business" from a URL would produce a confident number that means nothing, and
 * the first customer to check would catch it.
 *
 * What this cannot see, and the report says so plainly:
 *   - The business's actual Google rating or review count. That needs the
 *     Places API and a place ID, not a homepage.
 *   - Anything behind a login, rendered only by JavaScript, or on a page other
 *     than the one given.
 *
 * Claude's role here is narrative only — turning the findings into a paragraph
 * and a priority. It never sets the number.
 */
final class ReviewScore
{
    /**
     * Each check: weight, what a pass means, and what a fail costs you.
     * The weights total 100 and are ordered by how much they actually move a
     * rating — asking is worth more than displaying.
     */
    private const CHECKS = [
        'google_link' => [
            'weight' => 25,
            'label'  => 'A direct link to leave a Google review',
            'pass'   => 'Customers can reach your Google review form from your site.',
            'fail'   => 'Nothing on this page links to your Google review form, so a '
                      . 'customer who wants to leave one has to go and find it.',
        ],
        'structured_data' => [
            'weight' => 20,
            'label'  => 'Review structured data',
            'pass'   => 'Search engines can read your ratings from the markup.',
            'fail'   => 'No review or rating markup, so search engines cannot show '
                      . 'stars against your listing.',
        ],
        'reviews_shown' => [
            'weight' => 20,
            'label'  => 'Customer reviews on the page',
            'pass'   => 'Real customer words appear on the page.',
            'fail'   => 'No customer reviews or testimonials found on this page.',
        ],
        'review_widget' => [
            'weight' => 15,
            'label'  => 'A review platform connected',
            'pass'   => 'A review tool is already embedded.',
            'fail'   => 'No review platform detected, so anything shown here is manual.',
        ],
        'stars_visible' => [
            'weight' => 10,
            'label'  => 'A star rating on show',
            'pass'   => 'A star rating is visible to visitors.',
            'fail'   => 'No star rating on show. It is the first thing people look for.',
        ],
        'https' => [
            'weight' => 5,
            'label'  => 'Served over HTTPS',
            'pass'   => 'The page is secure.',
            'fail'   => 'This page is not served over HTTPS, which browsers warn about.',
        ],
        'mobile' => [
            'weight' => 5,
            'label'  => 'Built for phones',
            'pass'   => 'The page sets a mobile viewport.',
            'fail'   => 'No mobile viewport set — most review links are opened on a phone.',
        ],
    ];

    /** Review platforms we can recognise from the markup they inject. */
    private const PLATFORMS = [
        'trustpilot'  => 'Trustpilot',
        'birdeye'     => 'Birdeye',
        'podium'      => 'Podium',
        'yotpo'       => 'Yotpo',
        'reviews.io'  => 'Reviews.io',
        'grade.us'    => 'Grade.us',
        'nicejob'     => 'NiceJob',
        'swellcx'     => 'Swell',
        'reputation.com' => 'Reputation.com',
        'shopperapproved' => 'Shopper Approved',
        'elfsight'    => 'Elfsight',
        'trustindex'  => 'Trustindex',
    ];

    /**
     * @return array{
     *   score:int, band:string, url:string, checks:array<int,array<string,mixed>>,
     *   passed:array<int,string>, failed:array<int,string>, platform:?string
     * }
     */
    public static function analyse(string $html, string $url): array
    {
        $lower = mb_strtolower($html);
        $platform = self::detectPlatform($lower);

        $results = [
            'google_link'     => self::hasGoogleReviewLink($lower),
            'structured_data' => self::hasReviewMarkup($lower),
            'reviews_shown'   => self::hasReviewsOnPage($lower),
            'review_widget'   => $platform !== null,
            'stars_visible'   => self::hasStars($lower),
            'https'           => str_starts_with(strtolower($url), 'https://'),
            'mobile'          => str_contains($lower, 'name="viewport"') || str_contains($lower, "name='viewport'"),
        ];

        $score = 0;
        $checks = [];
        $passed = [];
        $failed = [];

        foreach (self::CHECKS as $key => $check) {
            $ok = $results[$key];
            if ($ok) {
                $score += $check['weight'];
                $passed[] = $check['label'];
            } else {
                $failed[] = $check['fail'];
            }

            $checks[] = [
                'key'    => $key,
                'label'  => $check['label'],
                'weight' => $check['weight'],
                'passed' => $ok,
                'detail' => $ok ? $check['pass'] : $check['fail'],
            ];
        }

        return [
            'score'    => $score,
            'band'     => self::band($score),
            'url'      => $url,
            'checks'   => $checks,
            'passed'   => $passed,
            'failed'   => $failed,
            'platform' => $platform,
        ];
    }

    public static function band(int $score): string
    {
        if ($score >= 75) {
            return 'Strong';
        }
        if ($score >= 45) {
            return 'Getting there';
        }

        return 'Needs work';
    }

    private static function hasGoogleReviewLink(string $html): bool
    {
        foreach ([
            'g.page/',
            'maps.app.goo.gl',
            'goo.gl/maps',
            'google.com/maps',
            'search.google.com/local/writereview',
            'writereview?placeid',
            '/writereview',
        ] as $needle) {
            if (str_contains($html, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function hasReviewMarkup(string $html): bool
    {
        foreach ([
            '"aggregaterating"',
            "'aggregaterating'",
            'itemprop="aggregaterating"',
            'itemtype="https://schema.org/review"',
            'itemtype="http://schema.org/review"',
            '"@type":"review"',
            '"@type": "review"',
            '"reviewrating"',
        ] as $needle) {
            if (str_contains($html, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Heuristic, and described as one in the report: a page that says
     * "testimonials" or shows several review-shaped blocks is very likely
     * showing customer words, but we are reading markup, not meaning.
     */
    private static function hasReviewsOnPage(string $html): bool
    {
        foreach (['testimonial', 'what our customers say', 'what our clients say',
                  'customer reviews', 'client reviews', 'read our reviews'] as $needle) {
            if (str_contains($html, $needle)) {
                return true;
            }
        }

        // Several separate "review" class hooks is a stronger signal than the
        // word appearing once in a nav link.
        return preg_match_all('/class="[^"]*\breview[s]?\b[^"]*"/', $html) >= 2;
    }

    private static function hasStars(string $html): bool
    {
        if (str_contains($html, '★') || str_contains($html, '&#9733;') || str_contains($html, '&starf;')) {
            return true;
        }

        return (bool) preg_match('/class="[^"]*\b(star|stars|rating|star-rating)\b[^"]*"/', $html);
    }

    private static function detectPlatform(string $html): ?string
    {
        foreach (self::PLATFORMS as $needle => $name) {
            if (str_contains($html, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
