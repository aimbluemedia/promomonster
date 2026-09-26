<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Plans;
use App\Support\View;

final class PageController
{
    /**
     * Title and meta description per page.
     *
     * A method rather than a const so the prices come from the plan catalogue.
     * These strings are what Google indexes and what a link preview shows, and
     * they had gone stale in the worst way: the home page was still advertising
     * "from $39/month" from a pricing ladder that no longer exists, and the
     * features page was selling SMS, monitoring and a website widget that the
     * product does not have.
     *
     * @return array<string,array{0:string,1:string,2:string}>
     */
    private static function pages(): array
    {
        $pro = '$' . Plans::price(Plans::PRO);

        return [
            'home' => [
                'home',
                'PromoMonster — Reviews. Reputation. Growth.',
                'Email every customer your Google review link on the day of the job, '
                    . 'and remind them once. Review requests for local service businesses, '
                    . 'free to start and ' . $pro . '/month after that.',
            ],
            'howItWorks' => [
                'how-it-works',
                'How it works · PromoMonster',
                'Ask everyone, reply to everyone, show the result. A review process built for your kind of business, running on its own.',
            ],
            'features' => [
                'features',
                'Features · PromoMonster',
                'Ask by email and QR code, with your Google review link in every message, '
                    . 'one reminder and no gating. Nothing here needs a platform to grant '
                    . 'us permission first.',
            ],
            'pricing' => [
                'pricing',
                'Pricing · PromoMonster',
                'Free to start, then ' . $pro . '/month. No setup fee, no contract, no '
                    . 'sales call. Competitors charge $300-$600 for this.',
            ],
            'agencies' => [
                'agencies',
                'For agencies · PromoMonster',
                'Add reputation management to what you already sell. White-label reports, one login for every client, revenue share.',
            ],
            'privacy' => [
                'privacy',
                'Privacy policy · PromoMonster',
                'What we collect, what we do with it, and how to have it deleted.',
            ],
            'terms' => [
                'terms',
                'Terms of use · PromoMonster',
                'The terms covering this website, and what we will and will not do.',
            ],
            'audit' => [
                'audit',
                'Free review audit · PromoMonster',
                'See your rating, your review velocity, what is going unanswered, and how you compare to your three nearest competitors. Free.',
            ],
        ];
    }

    public function __call(string $name, array $arguments): void
    {
        $page = self::pages()[$name] ?? null;
        if ($page === null) {
            throw new \BadMethodCallException("Unknown page: {$name}");
        }
        [$template, $title, $description] = $page;

        echo View::page($template, [
            'title'       => $title,
            'description' => $description,
            'current'     => $template === 'home' ? '/' : '/' . $template,
        ]);
    }
}
