<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\View;

final class PageController
{
    public function home(): void
    {
        echo View::page('home', [
            'title' => 'PromoMonster — Real People. Real Answers.',
            'description' => 'Find out what real people think of your website, your content and your ad creative. Studies from 50 to 500 real respondents, usually back the same day.',
        ]);
    }

    public function business(): void
    {
        echo View::page('business', [
            'title' => 'Find out what real people think of your site · PromoMonster',
            'description' => 'Studies from 50 to 500 real US respondents. First impressions, head-to-head tests, competitor comparisons and ad creative testing.',
        ]);
    }

    public function earn(): void
    {
        echo View::page('earn', [
            'title' => 'Get paid to share your opinion · PromoMonster',
            'description' => 'Look at a website, answer a few honest questions, get paid. Around $8–$16 an hour, cash out at $10, work whenever you want.',
        ]);
    }

    public function content(): void
    {
        echo View::page('services/content', [
            'title' => 'Content testing · PromoMonster',
            'description' => 'Find out whether your article, landing page or guide actually lands.',
        ]);
    }

    public function social(): void
    {
        echo View::page('services/social', [
            'title' => 'Social creative testing · PromoMonster',
            'description' => 'Test your thumbnail, title and hook on real people before you post.',
        ]);
    }
}
