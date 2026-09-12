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
     * @return array<string,array{
     *     name:string, price:int, tagline:string, featured:bool,
     *     limits:array{locations:int|null, requests_per_month:int|null, sms:bool},
     *     features:array<int,array{0:string,1:bool}>
     * }>
     */
    public static function all(): array
    {
        return [
            self::FREE => [
                'name'     => 'Free',
                'price'    => 0,
                'tagline'  => 'Ask properly, by email, for one location. Enough to start moving a rating.',
                'featured' => false,
                'limits'   => ['locations' => 1, 'requests_per_month' => 25, 'sms' => false],
                'features' => [
                    ['Google review link and printable QR code', true],
                    ['25 email review requests a month', true],
                    ['Review monitoring and alerts', true],
                    ['Review Growth Score', true],
                    ['Unlimited email requests', false],
                    ['SMS review requests', false],
                    ['Website review widget', false],
                ],
            ],
            self::PRO => [
                'name'     => 'Pro',
                'price'    => 19,
                'tagline'  => 'Unlimited asking, SMS included, and the widget that puts reviews on your site.',
                'featured' => true,
                'limits'   => ['locations' => 1, 'requests_per_month' => null, 'sms' => true],
                'features' => [
                    ['Everything in Free', true],
                    ['Unlimited email review requests', true],
                    ['SMS review requests and reminders', true],
                    ['Carrier registration handled for you', true],
                    ['Website review widget', true],
                    ['AI-drafted replies you approve', true],
                    ['Multiple locations', false],
                ],
            ],
            self::PREMIUM => [
                'name'     => 'Premium',
                'price'    => 49,
                'tagline'  => 'Up to five locations, each scored separately, with reports you can hand to a client.',
                'featured' => false,
                'limits'   => ['locations' => 5, 'requests_per_month' => null, 'sms' => true],
                'features' => [
                    ['Everything in Pro', true],
                    ['Up to 5 locations, scored separately', true],
                    ['Per-team-member reporting', true],
                    ['Team logins', true],
                    ['Downloadable and white-label reports', true],
                    ['Priority support', true],
                ],
            ],
            self::PARTNER => [
                'name'     => 'Partner',
                'price'    => 0,
                'tagline'  => 'For agencies reselling to their own clients. Arranged directly.',
                'featured' => false,
                'limits'   => ['locations' => null, 'requests_per_month' => null, 'sms' => true],
                'features' => [['Everything in Premium, across client accounts', true]],
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
        return self::get($key)['limits'][$limit] ?? null;
    }
}
