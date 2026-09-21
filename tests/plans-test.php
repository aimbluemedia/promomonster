<?php

declare(strict_types=1);

/**
 * Plan catalogue and sending limits.
 *
 * The numbers here are a pricing promise on a public page, so they are pinned
 * by test: a typo in the catalogue is a refund conversation, not a bug report.
 */

require __DIR__ . '/../app/Support/Plans.php';

use App\Support\Plans;

$passed = 0;
$failed = 0;

function check(string $what, mixed $got, mixed $want): void
{
    global $passed, $failed;
    if ($got === $want) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL  {$what}\n      got:  " . var_export($got, true)
        . "\n      want: " . var_export($want, true) . "\n";
}

// --- The agreed ladder ---------------------------------------------------
check('free month',     Plans::limit(Plans::FREE, 'requests_per_month'), 4);
check('free burst',     Plans::limit(Plans::FREE, 'burst'), 1);
check('free window',    Plans::limit(Plans::FREE, 'burst_days'), 7);
check('pro month',      Plans::limit(Plans::PRO, 'requests_per_month'), 60);
check('pro burst',      Plans::limit(Plans::PRO, 'burst'), 2);
check('pro window',     Plans::limit(Plans::PRO, 'burst_days'), 1);
check('premium month',  Plans::limit(Plans::PREMIUM, 'requests_per_month'), 300);
check('premium burst',  Plans::limit(Plans::PREMIUM, 'burst'), 10);
check('premium window', Plans::limit(Plans::PREMIUM, 'burst_days'), 1);

// A month's allowance must be reachable inside a month, or the headline number
// is a lie: 4 at 1 per 7 days needs 22 days, 60 at 2 a day needs 30.
foreach ([Plans::FREE, Plans::PRO, Plans::PREMIUM] as $plan) {
    $month  = (int) Plans::limit($plan, 'requests_per_month');
    $burst  = (int) Plans::limit($plan, 'burst');
    $days   = (int) Plans::limit($plan, 'burst_days');
    $needed = (int) ceil($month / $burst) * $days - ($days - 1);
    check("{$plan}: allowance reachable within 31 days (needs {$needed})",
        $needed <= 31, true);
}

// --- Prices --------------------------------------------------------------
check('free price',    Plans::price(Plans::FREE), 0);
check('pro price',     Plans::price(Plans::PRO), 19);
check('premium price', Plans::price(Plans::PREMIUM), 49);

// --- SMS is a paid capability -------------------------------------------
check('free has no sms',    Plans::limit(Plans::FREE, 'sms'), false);
check('pro has sms',        Plans::limit(Plans::PRO, 'sms'), true);
check('premium has sms',    Plans::limit(Plans::PREMIUM, 'sms'), true);

// --- Locations -----------------------------------------------------------
check('free locations',    Plans::limit(Plans::FREE, 'locations'), 1);
check('pro locations',     Plans::limit(Plans::PRO, 'locations'), 1);
check('premium locations', Plans::limit(Plans::PREMIUM, 'locations'), 5);

// --- The sentence every page prints -------------------------------------
check('free sentence',    Plans::sendingLimit(Plans::FREE), '4 review requests a month (one a week)');
check('pro sentence',     Plans::sendingLimit(Plans::PRO), '60 review requests a month (2 a day)');
check('premium sentence', Plans::sendingLimit(Plans::PREMIUM), '300 review requests a month (10 a day)');
check('partner sentence', Plans::sendingLimit(Plans::PARTNER), 'Review requests with no monthly cap');

// --- The ladder has to go up, or the upgrade makes no sense -------------
check('pro beats free',     Plans::limit(Plans::PRO, 'requests_per_month') > Plans::limit(Plans::FREE, 'requests_per_month'), true);
check('premium beats pro',  Plans::limit(Plans::PREMIUM, 'requests_per_month') > Plans::limit(Plans::PRO, 'requests_per_month'), true);
check('price rises too',    Plans::price(Plans::PREMIUM) > Plans::price(Plans::PRO), true);

// --- No plan may still claim "unlimited" --------------------------------
foreach (Plans::selectable() as $key => $plan) {
    $text = $plan['tagline'] . ' ' . implode(' ', array_column($plan['features'], 0));
    check("{$key} does not say unlimited", stripos($text, 'unlimited') === false, true);
}

// --- Every selectable plan states its allowance in its bullets ----------
foreach (Plans::selectable() as $key => $plan) {
    $bullets = array_column(array_filter($plan['features'], fn ($f) => $f[1] === true), 0);
    check("{$key} states its allowance",
        in_array(Plans::sendingLimit($key), $bullets, true), true);
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
