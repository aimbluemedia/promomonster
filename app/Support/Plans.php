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

    /**
     * Sending limits, in one place.
     *
     * Two windows, not one. `requests_per_month` is the allowance; `burst` over
     * `burst_days` stops it being spent in an afternoon. That matters for more
     * than fairness: a new sending domain that fires a month of mail in one
     * burst is exactly the pattern spam filters are built to catch, so pacing
     * protects deliverability for every account on the platform.
     *
     * A reminder is deliberately NOT counted here. It is the second half of one
     * ask, not a second ask, and charging for it would push people into the one
     * behaviour the playbook tells them to avoid: sending a third message.
     *
     * These live in a const rather than inside all(), because all() builds its
     * feature bullets from sendingLimit(), which reads these — going through
     * all() would recurse.
     *
     * A null means no limit, and only Partner has one.
     */
    private const LIMITS = [
        self::FREE    => ['locations' => 1,    'requests_per_month' => 5,
                          'burst' => 1,  'burst_days' => 7],
        self::PRO     => ['locations' => 1,    'requests_per_month' => 30,
                          'burst' => 2,  'burst_days' => 1],
        self::PREMIUM => ['locations' => 5,    'requests_per_month' => 100,
                          'burst' => 10, 'burst_days' => 1],
        self::PARTNER => ['locations' => null, 'requests_per_month' => null,
                          'burst' => null, 'burst_days' => null],
    ];

    /* ------------------------------------------------------------ abilities */

    /**
     * The things a plan either can or cannot do.
     *
     * Separate from LIMITS because these are not numbers: a limit is "how
     * many", an ability is "at all". Every gate in the product reads one of
     * these four, and so does the card that advertises them -- which is the
     * point. A plan card listing a feature the code does not actually gate,
     * or gating one the card does not mention, is a refund conversation, so
     * the card is GENERATED from this table rather than typed out beside it.
     */
    public const GOOGLE_PAGE   = 'google_page';
    public const ALL_TEMPLATES = 'all_templates';
    public const ADD_REVIEWS   = 'add_reviews';
    public const UNBRANDED     = 'unbranded_widget';

    /** @var list<string> */
    public const ABILITIES = [
        self::GOOGLE_PAGE, self::ALL_TEMPLATES, self::ADD_REVIEWS, self::UNBRANDED,
    ];

    /**
     * What to call each one when it has to be refused, in the second person,
     * because every one of these strings is read by somebody who has just been
     * told they cannot do the thing they came to do.
     */
    public const ABILITY_LABELS = [
        self::GOOGLE_PAGE   => 'Your Google review page',
        self::ALL_TEMPLATES => 'Writing your own email wording',
        self::ADD_REVIEWS   => 'Adding reviews by hand',
        self::UNBRANDED     => 'The website code without our name on it',
    ];

    /** @var array<string,array<string,bool>> */
    private const GRANTS = [
        self::FREE => [
            self::GOOGLE_PAGE   => false,
            self::ALL_TEMPLATES => false,
            self::ADD_REVIEWS   => false,
            self::UNBRANDED     => false,
        ],
        self::PRO => [
            self::GOOGLE_PAGE   => true,
            self::ALL_TEMPLATES => true,
            self::ADD_REVIEWS   => true,
            self::UNBRANDED     => false,
        ],
        self::PREMIUM => [
            self::GOOGLE_PAGE   => true,
            self::ALL_TEMPLATES => true,
            self::ADD_REVIEWS   => true,
            self::UNBRANDED     => true,
        ],
        // Partner resells Premium to its own clients, so it can do everything
        // Premium can. A missing entry here would read as "cannot", and a
        // reseller losing a feature because a plan was added is the kind of
        // bug nobody reports -- they just stop using it.
        self::PARTNER => [
            self::GOOGLE_PAGE   => true,
            self::ALL_TEMPLATES => true,
            self::ADD_REVIEWS   => true,
            self::UNBRANDED     => true,
        ],
    ];

    /**
     * Whether a plan may do something.
     *
     * Fails CLOSED on anything it does not recognise. An unknown plan or a
     * misspelled ability must not open a paid feature -- a typo in a gate
     * should cost a member a feature they can ask us about, never give away
     * one they have not paid for.
     */
    public static function can(string $plan, string $ability): bool
    {
        return self::GRANTS[$plan][$ability] ?? false;
    }

    /**
     * The cheapest plan somebody could buy to get this, or null if none does.
     *
     * Walked in price order rather than hard-coded, so "Upgrade to Pro" never
     * points at the wrong tier after a feature moves between them. Partner is
     * not considered: it is arranged by hand and cannot be bought from a page.
     */
    public static function cheapestWith(string $ability): ?string
    {
        $candidates = [];
        foreach (self::SELECTABLE as $key) {
            if (self::can($key, $ability)) {
                $candidates[$key] = self::price($key);
            }
        }

        if ($candidates === []) {
            return null;
        }

        asort($candidates);

        return (string) array_key_first($candidates);
    }

    /**
     * The plan card, as lines with a yes or a no against each.
     *
     * Built from LIMITS and GRANTS, never typed out. The whole reason this is
     * a function and not a constant is that a card is a promise: if the list
     * said "Add reviews by hand" and the gate disagreed, one of them would be
     * lying to somebody who had paid.
     *
     * @return list<array{text:string, included:bool}>
     */
    public static function card(string $key): array
    {
        $month = self::LIMITS[$key]['requests_per_month'] ?? null;

        return [
            // Nobody is charged for a customer leaving a review. Capping that
            // would mean turning a business's own review page off part way
            // through a month, which is not a plan limit, it is a fault.
            ['text' => 'Unlimited form fills', 'included' => true],
            [
                'text' => $month === null
                    ? 'Emails to customers requesting a review, uncapped'
                    : $month . ' emails to customers requesting a review',
                'included' => true,
            ],
            [
                'text' => self::can($key, self::ALL_TEMPLATES)
                    ? 'Every email template, and your own wording'
                    : 'Standard template only',
                'included' => self::can($key, self::ALL_TEMPLATES),
            ],
            [
                'text'     => 'Your Google review page',
                'included' => self::can($key, self::GOOGLE_PAGE),
            ],
            [
                'text'     => 'Add reviews by hand',
                'included' => self::can($key, self::ADD_REVIEWS),
            ],
            [
                'text' => self::can($key, self::UNBRANDED)
                    ? 'Website code with no PromoMonster name on it'
                    : 'Website code carries the PromoMonster name',
                'included' => self::can($key, self::UNBRANDED),
            ],
        ];
    }

    /**
     * The catalogue.
     *
     * Email only, and nothing here depends on an API we do not have. Review
     * monitoring, the website widget and SMS have all come out: the first two
     * need Google Business Profile access that has not been applied for, and
     * SMS needs a carrier registration paid per business, which a $19 tier
     * cannot carry. Selling any of them before they exist is how a small
     * platform earns its first chargeback.
     *
     * What differentiates the paid tiers instead is all our own data and our
     * own sending: how many you may send, whose name it goes out under,
     * whether you can write your own wording, whether you can see who clicked,
     * and how many locations and people are involved.
     *
     * @return array<string,array{
     *     name:string, price:int, tagline:string, featured:bool,
     *     limits:array{locations:int|null, requests_per_month:int|null,
     *                   burst:int|null, burst_days:int|null},
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
                    ['Unlimited form fills on your own review page', true, self::STATE_NOW],
                    [self::sendingLimit(self::FREE), true, self::STATE_NOW],
                    ['One reminder, three days later', true, self::STATE_NOW],
                    ['Review Growth Score', true, self::STATE_NOW],
                    ['Standard email wording only', true, self::STATE_NOW],
                    ['Your Google review page', false, null],
                    ['Add reviews by hand', false, null],
                    ['Website code with no PromoMonster name on it', false, null],
                ],
            ],
            self::PRO => [
                'name'     => 'Pro',
                'price'    => 19,
                'tagline'  => 'Two asks a day, under your own name, with the reminder handled for you.',
                'featured' => true,
                'limits'   => self::LIMITS[self::PRO],
                'features' => [
                    ['Everything in Free', true, null],
                    [self::sendingLimit(self::PRO), true, self::STATE_NOW],
                    ['Your Google review page', true, self::STATE_NOW],
                    ['Every email template, and your own wording', true, self::STATE_NOW],
                    ['Add reviews by hand', true, self::STATE_NOW],
                    ['See who opened your review link', true, self::STATE_NOW],
                    ['Import your customers from a spreadsheet', true, self::STATE_SOON],
                    ['Website code with no PromoMonster name on it', false, null],
                    ['More than one location', false, null],
                ],
            ],
            self::PREMIUM => [
                'name'     => 'Premium',
                'price'    => 49,
                'tagline'  => 'Ten asks a day across up to five locations, with a link for each of your people.',
                'featured' => false,
                'limits'   => self::LIMITS[self::PREMIUM],
                'features' => [
                    ['Everything in Pro', true, null],
                    [self::sendingLimit(self::PREMIUM), true, self::STATE_NOW],
                    ['Website code with no PromoMonster name on it', true, self::STATE_NOW],
                    ['Up to 5 locations, scored separately', true, self::STATE_SOON],
                    ['A login and an ask link for each of your people', true, self::STATE_SOON],
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

    /**
     * A price as money, always with the cents.
     *
     * "$0.00" rather than "Free", and "$19.00" rather than "$19", because a
     * price column where one row says a word and the next says a number reads
     * as two different kinds of thing. Every price the member sees goes
     * through here, so they cannot drift into different shapes on different
     * screens.
     */
    public static function money(string|int $plan): string
    {
        $dollars = is_int($plan) ? $plan : self::price($plan);

        return '$' . number_format((float) $dollars, 2);
    }

    /**
     * Every plan somebody could buy that grants this, cheapest first.
     *
     * cheapestWith() answers "which button", this answers "what to say": a
     * refusal that names only the cheapest option hides the fact that the plan
     * they were already considering also covers it.
     *
     * @return list<string>
     */
    public static function plansWith(string $ability): array
    {
        $found = [];
        foreach (self::SELECTABLE as $key) {
            if (self::can($key, $ability)) {
                $found[$key] = self::price($key);
            }
        }

        asort($found);

        return array_keys($found);
    }

    /**
     * The plans that cost more than this one, cheapest first.
     *
     * What the bar under the nav offers. Compared by price rather than by
     * position in SELECTABLE, so a tier inserted in the middle cannot leave
     * somebody being offered an "upgrade" to something cheaper.
     *
     * Empty for Partner and anything else off the self-serve ladder: those are
     * arranged by hand and must never be shown a buy button.
     *
     * @return list<string>
     */
    public static function above(string $plan): array
    {
        if (!in_array($plan, self::SELECTABLE, true)) {
            return [];
        }

        $mine  = self::price($plan);
        $found = [];
        foreach (self::SELECTABLE as $key) {
            if (self::price($key) > $mine) {
                $found[$key] = self::price($key);
            }
        }

        asort($found);

        return array_keys($found);
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
