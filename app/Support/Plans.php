<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The plan catalogue. One definition, read by the public pricing page, the
 * signup form, the members settings page and the limit checks — so a price can
 * never be right in one place and stale in another.
 *
 * Prices are in whole dollars per month. There is no payment processor yet:
 * picking a paid plan at signup records the request and starts the account on
 * Free. See migration 015.
 */
final class Plans
{
    public const FREE    = 'free';
    public const PRO     = 'pro';
    public const PREMIUM = 'premium';
    public const PARTNER = 'partner';

    /** Plans a member can choose for themselves. Partner is arranged by hand. */
    public const SELECTABLE = [self::FREE, self::PRO, self::PREMIUM];

    /** Plans that cost money, and therefore cannot be switched on without billing. */
    public const PAID = [self::PRO, self::PREMIUM];

    /**
     * Sending limits, in one place.
     *
     * Two windows, not one. `requests_per_month` is the allowance; `burst` over
     * `burst_days` stops it being spent in an afternoon. That matters for more
     * than fairness: a new sending domain that fires a month of mail in one
     * burst is exactly the pattern spam filters are built to catch, so pacing
     * protects deliverability for every account on the platform.
     *
     * These live in a const rather than inside all(), because all() builds its
     * feature bullets from sendingLimit(), which reads these — going through
     * all() would recurse.
     *
     * A null means no limit, and only Partner has one.
     */
    /**
     * How a feature is delivered today, in the same words the features page
     * uses. A plan's bullet list is a promise made before anyone pays, so a
     * thing that is not built yet has to say so there and not only three clicks
     * away.
     *
     * NOW covers anything a customer actually receives, including the pieces we
     * still set up by hand during onboarding. SOON is not available at all.
     * null is for a pointer like "Everything in Free", which is not a feature
     * and gets no tag.
     */
    public const STATE_NOW  = 'now';
    public const STATE_SOON = 'soon';

    public const STATE_LABELS = [
        self::STATE_NOW  => 'Available now',
        self::STATE_SOON => 'In build',
    ];

    private const LIMITS = [
        self::FREE    => ['locations' => 1,    'requests_per_month' => 4,
                          'burst' => 1,  'burst_days' => 7, 'sms' => false],
        self::PRO     => ['locations' => 1,    'requests_per_month' => 60,
                          'burst' => 2,  'burst_days' => 1, 'sms' => true],
        self::PREMIUM => ['locations' => 5,    'requests_per_month' => 300,
                          'burst' => 10, 'burst_days' => 1, 'sms' => true],
        self::PARTNER => ['locations' => null, 'requests_per_month' => null,
                          'burst' => null, 'burst_days' => null, 'sms' => true],
    ];

    /**
     * @return array<string,array{
     *     name:string, price:int, tagline:string, featured:bool,
     *     limits:array{locations:int|null, requests_per_month:int|null,
     *                   burst:int|null, burst_days:int|null, sms:bool},
     *     features:array<int,array{0:string,1:bool,2:string|null}>
     * }>
     */
    public static function all(): array
    {
        return [
            self::FREE => [
                'name'     => 'Free',
                'price'    => 0,
                'tagline'  => 'One ask a week, by email, for one location. Enough to feel it working.',
                'featured' => false,
                'limits'   => self::LIMITS[self::FREE],
                'features' => [
                    ['Google review link and printable QR code', true, self::STATE_NOW],
                    [self::sendingLimit(self::FREE), true, self::STATE_SOON],
                    // Needs Google Business Profile API access, which is not applied for yet.
                    ['Review monitoring and alerts', true, self::STATE_SOON],
                    ['Review Growth Score', true, self::STATE_NOW],
                    ['Sent under your own name, with no PromoMonster footer', false, null],
                    ['SMS review requests', false, null],
                    ['Website review widget', false, null],
                ],
            ],
            self::PRO => [
                'name'     => 'Pro',
                'price'    => 19,
                'tagline'  => 'Two asks a day, SMS included, and the widget that puts reviews on your site.',
                'featured' => true,
                'limits'   => self::LIMITS[self::PRO],
                'features' => [
                    ['Everything in Free', true, null],
                    [self::sendingLimit(self::PRO), true, self::STATE_SOON],
                    ['Sent under your own name, with no PromoMonster footer', true, self::STATE_SOON],
                    ['SMS review requests and reminders', true, self::STATE_SOON],
                    ['Carrier registration handled for you', true, self::STATE_SOON],
                    ['Website review widget', true, self::STATE_SOON],
                    ['AI-drafted replies you approve', true, self::STATE_SOON],
                    ['Multiple locations', false, null],
                ],
            ],
            self::PREMIUM => [
                'name'     => 'Premium',
                'price'    => 49,
                'tagline'  => 'Ten asks a day across up to five locations, each scored separately.',
                'featured' => false,
                'limits'   => self::LIMITS[self::PREMIUM],
                'features' => [
                    ['Everything in Pro', true, null],
                    [self::sendingLimit(self::PREMIUM), true, self::STATE_SOON],
                    ['Up to 5 locations, scored separately', true, self::STATE_SOON],
                    ['Per-team-member reporting', true, self::STATE_SOON],
                    ['Team logins', true, self::STATE_SOON],
                    ['Downloadable and white-label reports', true, self::STATE_SOON],
                    ['Priority support', true, self::STATE_NOW],
                ],
            ],
            self::PARTNER => [
                'name'     => 'Partner',
                'price'    => 0,
                'tagline'  => 'For agencies reselling to their own clients. Arranged directly.',
                'featured' => false,
                'limits'   => self::LIMITS[self::PARTNER],
                'features' => [['Everything in Premium, across client accounts', true, null]],
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? self::all()[self::FREE];
    }

    public static function name(string $key): string
    {
        return (string) self::get($key)['name'];
    }

    public static function price(string $key): int
    {
        return (int) self::get($key)['price'];
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function isPaid(string $key): bool
    {
        return in_array($key, self::PAID, true);
    }

    /** Plans offered on the pricing page and the signup form, in order. */
    public static function selectable(): array
    {
        $out = [];
        foreach (self::SELECTABLE as $key) {
            $out[$key] = self::get($key);
        }

        return $out;
    }

    /** null means no limit. */
    public static function limit(string $key, string $limit): int|bool|null
    {
        return self::LIMITS[$key][$limit] ?? self::LIMITS[self::FREE][$limit] ?? null;
    }

    /**
     * The sending allowance as a sentence, e.g. "4 review requests a month
     * (one a week)".
     *
     * Every page that states the limit calls this. The old numbers were typed
     * out in the plan bullet, the pricing page and twice on the home page, and
     * they had already drifted apart once — so now there is one place to change
     * and nowhere for a stale figure to hide.
     */
    public static function sendingLimit(string $key): string
    {
        $month = self::LIMITS[$key]['requests_per_month'] ?? null;
        if ($month === null) {
            return 'Review requests with no monthly cap';
        }

        $noun = $month === 1 ? 'review request' : 'review requests';
        $out  = $month . ' ' . $noun . ' a month';

        $burst = self::LIMITS[$key]['burst'] ?? null;
        $days  = self::LIMITS[$key]['burst_days'] ?? null;
        if ($burst === null || $days === null) {
            return $out;
        }

        // "one a week" reads better than "1 every 7 days"; everything else is
        // a plain rate.
        if ($burst === 1 && $days === 7) {
            return $out . ' (one a week)';
        }
        if ($days === 1) {
            return $out . ' (' . $burst . ' a day)';
        }

        return $out . ' (' . $burst . ' every ' . $days . ' days)';
    }
}
