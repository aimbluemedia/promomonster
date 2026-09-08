<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\View;

final class PageController
{
    private const PAGES = [
        'home' => [
            'home',
            'PromoMonster — Reviews. Reputation. Growth.',
            'Ask every customer for a review, respond to what comes back, and put it to work. Reputation management for local businesses, from $39/month.',
        ],
        'howItWorks' => [
            'how-it-works',
            'How it works · PromoMonster',
            'Ask everyone, reply to everyone, show the result. A review process built for your kind of business, running on its own.',
        ],
        'features' => [
            'features',
            'Features · PromoMonster',
            'Collect reviews by SMS, email and QR code. Monitor and reply with AI-drafted responses. Show them on your site. Track every location.',
        ],
        'pricing' => [
            'pricing',
            'Pricing · PromoMonster',
            'From $39/month. No setup fee, no contract, no sales call. Competitors charge $300-$600 for this.',
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

    public function __call(string $name, array $arguments): void
    {
        $page = self::PAGES[$name] ?? null;
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
