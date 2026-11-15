<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The Toutify family, as the footer advertises it.
 *
 * One constant because the footer appears on every public page and the list is
 * the kind of thing that changes when a sibling product launches or gets
 * renamed. Editing it here changes it everywhere, and there is nowhere else it
 * can drift to.
 *
 * ABOUT THE DESCRIPTIONS: PromoMonster's is written from the product. The
 * other five are read off the LeadCrazy screenshot this footer was modelled
 * on, so they are the siblings' own words as far as a screenshot can be
 * trusted -- correct any that are wrong, they are one line each. Nothing here
 * claims a feature, a price or a figure for a product this codebase cannot
 * see, which is the one thing a cross-sell block must not do.
 *
 * `url` is null on every sibling on purpose. A guessed domain in a live footer
 * is a dead link on every page of the site; a card with no link is merely a
 * card. Fill one in and it becomes a link with no other change.
 */
final class Toutify
{
    /** The product this codebase IS, so the grid can mark it. */
    public const SELF = 'promomonster';

    /**
     * @var list<array{key:string, name:string, what:string, icon:string, url:?string}>
     */
    public const PRODUCTS = [
        [
            'key'  => 'leadcrazy',
            'name' => 'LeadCrazy',
            'what' => 'Turn visitors into quote requests with the Million Dollar Lead Form.',
            'icon' => 'layout',
            'url'  => null,
        ],
        [
            'key'  => 'searchmonster',
            'name' => 'SearchMonster',
            'what' => 'Show up when customers ask AI who to hire.',
            'icon' => 'search',
            'url'  => null,
        ],
        [
            'key'  => 'monsterlist',
            'name' => 'MonsterList',
            'what' => 'Get listed in directories across the web, free.',
            'icon' => 'list',
            'url'  => null,
        ],
        [
            'key'  => self::SELF,
            'name' => 'PromoMonster',
            // Written from the product, not from a screenshot: this is the one
            // we can describe without guessing.
            'what' => 'Ask every customer for a review, reply to what comes back, show it off.',
            'icon' => 'star',
            'url'  => '/',
        ],
        [
            'key'  => 'contentvendor',
            'name' => 'ContentVendor',
            'what' => 'Posts and pages written for you, on a schedule.',
            'icon' => 'megaphone',
            'url'  => null,
        ],
        [
            'key'  => 'directories',
            'name' => 'Official Directories',
            'what' => 'Claim your free spot in official state directories.',
            'icon' => 'shield',
            'url'  => null,
        ],
    ];

    /**
     * What the free account actually gets you, as short claims.
     *
     * Every one of these is a promise made to somebody before they sign up, so
     * each has to be true of the free plan as this codebase enforces it. "Free
     * forever" and "no credit card" are true: Plans::FREE costs 0 and signup
     * never reaches Stripe. "One login, every tool" is the membership's own
     * claim, not a PromoMonster feature, so it is worded as the account rather
     * than as something this product does.
     *
     * @var list<string>
     */
    public const PROMISES = [
        'Free forever',
        'No credit card',
        'One login, every tool',
        'Cancel any time',
    ];

    /** @return list<array{key:string, name:string, what:string, icon:string, url:?string}> */
    public static function products(): array
    {
        return self::PRODUCTS;
    }

    public static function isSelf(string $key): bool
    {
        return $key === self::SELF;
    }
}
