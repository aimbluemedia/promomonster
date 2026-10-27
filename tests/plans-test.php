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

function ok(string $what, bool $got): void
{
    check($what, $got, true);
}

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
check('free month',     Plans::limit(Plans::FREE, 'requests_per_month'), 5);
check('free burst',     Plans::limit(Plans::FREE, 'burst'), 1);
check('free window',    Plans::limit(Plans::FREE, 'burst_days'), 7);
check('pro month',      Plans::limit(Plans::PRO, 'requests_per_month'), 30);
check('pro burst',      Plans::limit(Plans::PRO, 'burst'), 2);
check('pro window',     Plans::limit(Plans::PRO, 'burst_days'), 1);
check('premium month',  Plans::limit(Plans::PREMIUM, 'requests_per_month'), 100);
check('premium burst',  Plans::limit(Plans::PREMIUM, 'burst'), 10);
check('premium window', Plans::limit(Plans::PREMIUM, 'burst_days'), 1);

// A month's allowance must be reachable inside a month, or the headline number
// is a lie: 5 at 1 per 7 days needs 29 days, 30 at 2 a day needs 15.
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

// --- The product is email only ------------------------------------------
// SMS is out: it needs a carrier registration billed per business, which a $19
// tier cannot carry. Nothing in the catalogue may offer it again by accident.
foreach (Plans::all() as $key => $plan) {
    $text = $plan['tagline'] . ' ' . implode(' ', array_column($plan['features'], 0));
    check("{$key} does not sell SMS", stripos($text, 'sms') === false, true);
    check("{$key} does not sell a widget", stripos($text, 'widget') === false, true);
    // Monitoring and the widget both need Google Business Profile API access,
    // which has not been applied for.
    check("{$key} does not sell monitoring", stripos($text, 'monitoring') === false, true);
}

// --- Locations -----------------------------------------------------------
check('free locations',    Plans::limit(Plans::FREE, 'locations'), 1);
check('pro locations',     Plans::limit(Plans::PRO, 'locations'), 1);
check('premium locations', Plans::limit(Plans::PREMIUM, 'locations'), 5);

// --- The sentence every page prints -------------------------------------
check('free sentence',    Plans::sendingLimit(Plans::FREE), '5 review requests a month (one a week)');
check('pro sentence',     Plans::sendingLimit(Plans::PRO), '30 review requests a month (2 a day)');
check('premium sentence', Plans::sendingLimit(Plans::PREMIUM), '100 review requests a month (10 a day)');
check('partner sentence', Plans::sendingLimit(Plans::PARTNER), 'Review requests with no monthly cap');

// --- The ladder has to go up, or the upgrade makes no sense -------------
check('pro beats free',     Plans::limit(Plans::PRO, 'requests_per_month') > Plans::limit(Plans::FREE, 'requests_per_month'), true);
check('premium beats pro',  Plans::limit(Plans::PREMIUM, 'requests_per_month') > Plans::limit(Plans::PRO, 'requests_per_month'), true);
check('price rises too',    Plans::price(Plans::PREMIUM) > Plans::price(Plans::PRO), true);

// --- No plan may claim unlimited SENDING --------------------------------
//
// This used to ban the word outright. It cannot any more: form fills really
// are uncapped -- a customer leaving a review on a business's own page costs
// us a row, and cutting that off part way through a month would be a fault,
// not a plan limit. So the ban narrows to the thing that IS metered. Every
// use of the word has to be about form fills and nothing else.
foreach (Plans::selectable() as $key => $plan) {
    $text = $plan['tagline'] . ' ' . implode(' ', array_column($plan['features'], 0));

    foreach (['unlimited review', 'unlimited email', 'unlimited request', 'unlimited sending'] as $claim) {
        check("{$key} does not claim {$claim}", stripos($text, $claim) === false, true);
    }

    // Wherever it does appear, it is about form fills.
    if (preg_match_all('/unlimited[^.,;|]*/i', $text, $m) === 1 || $m[0] !== []) {
        foreach ($m[0] as $phrase) {
            ok("{$key}: \"" . trim($phrase) . "\" is about form fills",
                stripos($phrase, 'form fill') !== false);
        }
    }

}

// --- Every selectable plan states its allowance in its bullets ----------
foreach (Plans::selectable() as $key => $plan) {
    $bullets = array_column(array_filter($plan['features'], fn ($f) => $f[1] === true), 0);
    check("{$key} states its allowance",
        in_array(Plans::sendingLimit($key), $bullets, true), true);
}

// --- Nothing may be promised as delivered when it is not ----------------
// The pricing page ticks these before anyone pays, so a feature that is not
// built has to carry a tag. Review monitoring in particular needs Google
// Business Profile API access, which has not been applied for.
$states = [Plans::STATE_NOW, Plans::STATE_SOON];
foreach (Plans::all() as $key => $plan) {
    foreach ($plan['features'] as $i => $feature) {
        check("{$key} feature {$i} is a triple", count($feature) === 3, true);
        [$label, $on, $state] = $feature;
        if ($on && $state !== null) {
            check("{$key}: '{$label}' has a known state",
                in_array($state, $states, true), true);
        }
    }
}

/** @return string|null */
function stateOf(string $plan, string $label): ?string
{
    foreach (Plans::get($plan)['features'] as [$l, , $s]) {
        if ($l === $label) {
            return $s;
        }
    }
    return 'MISSING';
}

// AI-drafted replies came off the catalogue entirely rather than being
// re-tagged. Nothing builds them, and a bullet with a "coming" badge is still
// a bullet somebody reads before paying.
foreach (Plans::all() as $key => $plan) {
    $text = implode(' ', array_column($plan['features'], 0));
    ok("{$key} does not sell AI replies", stripos($text, 'AI-drafted') === false);
}

// Sending used to be tagged "in build" here, and that assertion was pinned so
// the pricing page could not overclaim it. It is built: a request now goes out
// on the click. Leaving it as SOON would have been the same fault in the other
// direction -- a page understating what a customer gets.
check('sending IS live',
    stateOf(Plans::FREE, Plans::sendingLimit(Plans::FREE)), Plans::STATE_NOW);
check('the score IS live',
    stateOf(Plans::FREE, 'Review Growth Score'), Plans::STATE_NOW);

// The four gated abilities are all built, so wherever a plan grants one the
// catalogue must not tag it "in build" -- that would be selling a thing the
// product would then refuse to let them not have.
check('the Google page is live on Pro',
    stateOf(Plans::PRO, 'Your Google review page'), Plans::STATE_NOW);
check('own wording is live on Pro',
    stateOf(Plans::PRO, 'Every email template, and your own wording'), Plans::STATE_NOW);
check('adding by hand is live on Pro',
    stateOf(Plans::PRO, 'Add reviews by hand'), Plans::STATE_NOW);
check('white-label is live on Premium',
    stateOf(Plans::PREMIUM, 'Website code with no PromoMonster name on it'), Plans::STATE_NOW);

// And the things genuinely not built still say so.
check('spreadsheet import is still in build',
    stateOf(Plans::PRO, 'Import your customers from a spreadsheet'), Plans::STATE_SOON);
check('multi-location is still in build',
    stateOf(Plans::PREMIUM, 'Up to 5 locations, scored separately'), Plans::STATE_SOON);

// Both words the pages print must exist, since two views index this map.
check('now has a label',  isset(Plans::STATE_LABELS[Plans::STATE_NOW]), true);
check('soon has a label', isset(Plans::STATE_LABELS[Plans::STATE_SOON]), true);

// =====================================================================
// What each plan may actually do
// =====================================================================
//
// These four decide whether a screen works, so they are pinned here. A plan
// quietly gaining an ability is a feature given away; quietly losing one is a
// customer who paid for it and cannot use it.

check('free: no Google page',      Plans::can(Plans::FREE, Plans::GOOGLE_PAGE), false);
check('free: standard wording',    Plans::can(Plans::FREE, Plans::ALL_TEMPLATES), false);
check('free: no adding by hand',   Plans::can(Plans::FREE, Plans::ADD_REVIEWS), false);
check('free: branded website code', Plans::can(Plans::FREE, Plans::UNBRANDED), false);

check('pro: Google page',          Plans::can(Plans::PRO, Plans::GOOGLE_PAGE), true);
check('pro: all wording',          Plans::can(Plans::PRO, Plans::ALL_TEMPLATES), true);
check('pro: adding by hand',       Plans::can(Plans::PRO, Plans::ADD_REVIEWS), true);
// The one thing Pro does not get, and the only reason to go to Premium on this
// list. If this ever reads true, Premium has nothing to sell.
check('pro: still branded',        Plans::can(Plans::PRO, Plans::UNBRANDED), false);

foreach (Plans::ABILITIES as $ability) {
    check("premium: {$ability}", Plans::can(Plans::PREMIUM, $ability), true);
    // Partner resells Premium, so it can never have less than Premium.
    check("partner: {$ability}", Plans::can(Plans::PARTNER, $ability), true);
}

// Fails closed. A typo in a gate must cost a member a feature they can ask us
// about, never hand one over that was not paid for.
check('an unknown plan can do nothing', Plans::can('enterprise', Plans::UNBRANDED), false);
check('an unknown ability is refused',  Plans::can(Plans::PREMIUM, 'teleportation'), false);
check('an empty plan is refused',       Plans::can('', Plans::GOOGLE_PAGE), false);

// --- Where an upgrade button points -------------------------------------
// Worked out by price rather than hard-coded, so a feature moving tier cannot
// leave a button pointing at a plan that does not grant it.
check('Google page is cheapest on Pro',  Plans::cheapestWith(Plans::GOOGLE_PAGE), Plans::PRO);
check('wording is cheapest on Pro',      Plans::cheapestWith(Plans::ALL_TEMPLATES), Plans::PRO);
check('adding by hand is cheapest on Pro', Plans::cheapestWith(Plans::ADD_REVIEWS), Plans::PRO);
check('white-label needs Premium',       Plans::cheapestWith(Plans::UNBRANDED), Plans::PREMIUM);
check('an ability nobody sells is null', Plans::cheapestWith('teleportation'), null);

// Whatever it names must actually grant it, and must be buyable. Partner is
// arranged by hand and must never be offered by a button.
foreach (Plans::ABILITIES as $ability) {
    $needed = Plans::cheapestWith($ability);
    ok("{$ability}: the plan named grants it", $needed !== null && Plans::can($needed, $ability));
    ok("{$ability}: the plan named can be bought",
        $needed !== null && in_array($needed, Plans::SELECTABLE, true));
}

// --- The card cannot disagree with the gate -----------------------------
// The whole reason card() is generated. If these two could be edited apart,
// one of them would be lying to somebody who had paid.
$claims = [
    'Your Google review page' => Plans::GOOGLE_PAGE,
    'Add reviews by hand'     => Plans::ADD_REVIEWS,
];

foreach ([Plans::FREE, Plans::PRO, Plans::PREMIUM] as $plan) {
    $card = Plans::card($plan);

    check("{$plan}: card has six lines", count($card), 6);

    foreach ($card as $line) {
        ok("{$plan}: every line has words", trim($line['text']) !== '');
    }

    foreach ($claims as $text => $ability) {
        foreach ($card as $line) {
            if ($line['text'] === $text) {
                check("{$plan}: \"{$text}\" matches the gate",
                    $line['included'], Plans::can($plan, $ability));
            }
        }
    }

    // The monthly figure on the card is the one the sender enforces.
    $month = (int) Plans::limit($plan, 'requests_per_month');
    ok("{$plan}: the card states the real monthly allowance",
        str_contains($card[1]['text'], (string) $month));

    // Free is the only plan whose website code carries our name AND whose
    // wording cannot be changed, so the card must say so in those words.
    $all = implode(' | ', array_column($card, 'text'));
    ok("{$plan}: says something about PromoMonster on the website code",
        str_contains($all, 'PromoMonster'));
    ok("{$plan}: offers unlimited form fills", str_contains($all, 'Unlimited form fills'));
}

check('free card: standard template only',
    Plans::card(Plans::FREE)[2]['text'], 'Standard template only');
check('premium card: no logo',
    Plans::card(Plans::PREMIUM)[5]['text'], 'Website code with no PromoMonster name on it');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
