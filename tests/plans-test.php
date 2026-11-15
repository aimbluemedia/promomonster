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
// One, on every self-serve plan. A location row is created once at signup and
// no screen adds a second, so a number above 1 would be a limit on something
// nobody can reach -- which is what Premium's "up to 5 locations" rested on.
check('premium locations', Plans::limit(Plans::PREMIUM, 'locations'), 1);

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
check('the Google page is live on Premium',
    stateOf(Plans::PREMIUM, 'Your Google review page'), Plans::STATE_NOW);
check('own wording is live on Pro',
    stateOf(Plans::PRO, 'Every email template, and your own wording'), Plans::STATE_NOW);
check('adding by hand is live on Pro',
    stateOf(Plans::PREMIUM, 'Add reviews by hand'), Plans::STATE_NOW);
check('white-label is live on Premium',
    stateOf(Plans::PREMIUM, 'Website code with no PromoMonster name on it'), Plans::STATE_NOW);

// And the things genuinely not built still say so.
check('spreadsheet import is still in build',
    stateOf(Plans::PRO, 'Import your customers from a spreadsheet'), Plans::STATE_SOON);
// Multi-location is gone from the catalogue rather than tagged "in build":
// every plan is one business, and that is now said rather than implied.

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

// Premium's now. Pro keeps the allowance and its own wording; the saved
// Google link, and everything that happens after a review arrives, is the
// tier above.
check('pro: no Google page',       Plans::can(Plans::PRO, Plans::GOOGLE_PAGE), false);
check('pro: all wording',          Plans::can(Plans::PRO, Plans::ALL_TEMPLATES), true);
// Premium's, not Pro's. Pro buys the Google link and your own wording; the
// page you curate by hand is the next tier up.
check('pro: no adding by hand',    Plans::can(Plans::PRO, Plans::ADD_REVIEWS), false);
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
check('Google page is cheapest on Premium',
    Plans::cheapestWith(Plans::GOOGLE_PAGE), Plans::PREMIUM);
check('wording is cheapest on Pro',      Plans::cheapestWith(Plans::ALL_TEMPLATES), Plans::PRO);
check('adding by hand is cheapest on Premium',
    Plans::cheapestWith(Plans::ADD_REVIEWS), Plans::PREMIUM);
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

    check("{$plan}: card has eight lines", count($card), 8);

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

// =====================================================================
// How a price is printed, and what sits above a plan
// =====================================================================
//
// Every price the member sees goes through money(). A column where one row
// says a word and the next says a number reads as two different things, and
// the word is the one people skip.

check('free is a number, not a word', Plans::money(Plans::FREE), '$0.00');
check('pro carries cents',            Plans::money(Plans::PRO), '$19.00');
check('premium carries cents',        Plans::money(Plans::PREMIUM), '$49.00');
check('a bare amount formats too',    Plans::money(7), '$7.00');

foreach (Plans::SELECTABLE as $key) {
    ok("{$key}: the price is money, not prose",
        preg_match('/^\$[0-9,]+\.[0-9]{2}$/', Plans::money($key)) === 1);
}

// --- What the bar under the nav offers ----------------------------------
check('free is offered both paid plans', Plans::above(Plans::FREE), [Plans::PRO, Plans::PREMIUM]);
check('pro is offered premium',          Plans::above(Plans::PRO), [Plans::PREMIUM]);
check('premium is offered nothing',      Plans::above(Plans::PREMIUM), []);
// Partner is arranged by hand. Offering it a buy button would let a stray
// click replace a reseller agreement with a $19 subscription.
check('partner is offered nothing',      Plans::above(Plans::PARTNER), []);
check('an unknown plan is offered nothing', Plans::above('enterprise'), []);

// Everything offered as an upgrade must actually cost more, and be buyable.
foreach (Plans::SELECTABLE as $key) {
    foreach (Plans::above($key) as $up) {
        ok("{$key} -> {$up} really is dearer", Plans::price($up) > Plans::price($key));
        ok("{$key} -> {$up} can be bought",    in_array($up, Plans::SELECTABLE, true));
    }
}

// --- What a refusal names -----------------------------------------------
// Every plan that grants it, cheapest first, not just the cheapest: somebody
// who upgrades to Pro for one thing and then needs Premium for the next has
// been led there one plan at a time.
check('the Google page names Premium alone',
    Plans::plansWith(Plans::GOOGLE_PAGE), [Plans::PREMIUM]);
// Still one ability where more than one plan qualifies, so the "names every
// plan that grants it" path is exercised by something.
check('the templates name both paid plans',
    Plans::plansWith(Plans::ALL_TEMPLATES), [Plans::PRO, Plans::PREMIUM]);
check('white-label names Premium alone',
    Plans::plansWith(Plans::UNBRANDED), [Plans::PREMIUM]);
check('an ability nobody sells names nobody', Plans::plansWith('teleportation'), []);

foreach (Plans::ABILITIES as $ability) {
    $named = Plans::plansWith($ability);
    ok("{$ability}: something grants it", $named !== []);
    ok("{$ability}: the cheapest named is the one the button uses",
        $named[0] === Plans::cheapestWith($ability));
    foreach ($named as $key) {
        ok("{$ability}: {$key} really grants it", Plans::can($key, $ability));
    }
}

// =====================================================================
// What comes with every plan
// =====================================================================
//
// The directories are not metered and not gated, so they are not in GRANTS.
// They are still a promise on a card, which is the only reason they need
// pinning: a plan quietly losing one is a thing somebody chose us for.

check('two directories are bundled', count(Plans::BUNDLED), 2);
check('SearchMonster is one',  in_array('SearchMonster Directory', Plans::BUNDLED, true), true);
check('MonsterList is the other', in_array('MonsterList Directory', Plans::BUNDLED, true), true);

foreach ([Plans::FREE, Plans::PRO, Plans::PREMIUM, Plans::PARTNER] as $plan) {
    $block = Plans::includes($plan);

    // The heading names the plan it is on, so three cards side by side do not
    // all read "Includes Free Plan".
    check("{$plan}: the heading names the plan",
        $block['heading'], 'Includes ' . Plans::name($plan) . ' Plan');

    // Every plan carries the lot. The whole point of listing them on Free is
    // that somebody weighing the tiers can see they are not an upsell.
    check("{$plan}: carries every directory", $block['items'], Plans::BUNDLED);

    foreach ($block['items'] as $item) {
        ok("{$plan}: \"{$item}\" has words", trim($item) !== '');
    }
}

// A directory must never collide with a gated line, or the same words would
// appear once with a tick and once with a cross on one card.
foreach ([Plans::FREE, Plans::PRO, Plans::PREMIUM] as $plan) {
    $onCard = array_column(Plans::card($plan), 'text');
    foreach (Plans::BUNDLED as $item) {
        ok("{$plan}: \"{$item}\" is not also a gated line",
            !in_array($item, $onCard, true));
    }
}

// =====================================================================
// Nothing anywhere sells a second location
// =====================================================================
//
// It was a headline on Premium and a card on the features page, resting on a
// number nothing read. Taking it out of one place and leaving it in another is
// how it comes back.

foreach (Plans::SELECTABLE as $plan) {
    check("{$plan}: one business", Plans::limit($plan, 'locations'), 1);

    $prose = Plans::get($plan)['tagline'] . ' ';
    foreach (Plans::get($plan)['features'] as [$label, , ]) {
        $prose .= $label . ' ';
    }
    foreach (Plans::card($plan) as $line) {
        $prose .= $line['text'] . ' ';
    }

    ok("{$plan}: no location count is advertised",
        preg_match('/\b(\d+|two|three|four|five|multiple)\s+locations?\b/i', $prose) !== 1);
    ok("{$plan}: no multi-location wording",
        preg_match('/multi-?location/i', $prose) !== 1);
}

// =====================================================================
// The prose on the public pages agrees with the catalogue
// =====================================================================
//
// The pricing page carried a sentence saying Free sent four a month, Pro sixty
// and Premium three hundred. The real numbers were 5, 30 and 100, and had been
// for a while -- three wrong figures on a pricing page, with the right ones in
// a table a few lines below them. Nobody reads their own pricing page.

define('APP_ROOT', dirname(__DIR__) . '/app');
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = APP_ROOT . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

App\Support\Config::load(['app_url' => 'https://promomonster.test', 'app_key' => str_repeat('a', 40)]);
$_SESSION = [];
$_SERVER['REQUEST_URI'] = '/pricing';

$page = App\Support\View::render('pricing');
ok('the pricing page renders', $page !== '');

$allowances = [];
foreach (Plans::SELECTABLE as $plan) {
    $allowances[] = (string) Plans::limit($plan, 'requests_per_month');
}

// Every "<Plan> sends N" on the page has to be the N we actually grant, and
// no other number may appear in that position. Pinning the phrasing to the
// catalogue is the whole point: hardcode a figure here and this fails on the
// next change to an allowance, which is exactly what did not happen last time.
// $pageText, not $text. The first version of this block called it $text, and
// two loops below destructure a feature row into [$text, , ] -- so by the time
// the last check ran, "the page" was the final feature label of the final
// plan, and the check passed on every mutation. A test that cannot fail is
// worse than no test: it reports the thing is guarded.
$pageText = strip_tags($page);
foreach (Plans::SELECTABLE as $plan) {
    $name = Plans::name($plan);
    $n    = (string) Plans::limit($plan, 'requests_per_month');
    ok("the page says {$name} sends {$n}", str_contains($pageText, "{$name} sends {$n}"));

    if (preg_match_all('/' . preg_quote($name, '/') . '\s+sends\s+(\d[\d,]*)/i', $pageText, $m) > 0) {
        foreach (array_unique($m[1]) as $quoted) {
            check("{$name}: the only figure quoted is the real one",
                str_replace(',', '', $quoted), $n);
        }
    }
}

// Not one of them is a number we no longer grant, however it is written.
foreach (['four a month', 'sixty a month', 'three hundred'] as $stale) {
    ok("the old allowance is gone: \"{$stale}\"", !str_contains(strtolower($pageText), $stale));
}

ok('the pricing page sells no second location',
    preg_match('/\b(\d+|two|three|four|five|multiple)\s+locations?\b/i', $pageText) !== 1);

$features = App\Support\View::render('features');
ok('the features page renders', $features !== '');
ok('and sells no second location',
    preg_match('/multi-?location|\b(\d+|two|three|four|five)\s+locations?\b/i', strip_tags($features)) !== 1);

// =====================================================================
// The public feature list cannot contradict the gate either
// =====================================================================
//
// card() is generated from GRANTS, so the members-area card and the gate can
// never disagree. all()['features'] -- the list on the public pricing page --
// is hand-written, and nothing tied it to anything. Moving "add reviews by
// hand" from Pro to Premium changed the gate and the card together and left
// the pricing page free to keep selling it to Pro, which is a refund
// conversation with a receipt attached.

$sold = [
    'Your Google review page'                      => Plans::GOOGLE_PAGE,
    'Add reviews by hand'                          => Plans::ADD_REVIEWS,
    'Website code with no PromoMonster name on it'  => Plans::UNBRANDED,
    'Every email template, and your own wording'    => Plans::ALL_TEMPLATES,
    'Replies drafted for you'                      => Plans::REPLY_DRAFTS,
    'A monthly report on how you compare'          => Plans::MONTHLY_REPORT,
];

foreach (Plans::SELECTABLE as $plan) {
    foreach (Plans::get($plan)['features'] as [$label, $included, ]) {
        if (!isset($sold[$label])) {
            continue;
        }
        check("{$plan}: the pricing page's \"{$label}\" matches the gate",
            (bool) $included, Plans::can($plan, $sold[$label]));
    }
}

// And a plan that HAS an ability has to say so somewhere on that list, or the
// page is selling less than the member gets -- which is nobody's complaint,
// but it is still the two drifting apart.
foreach (Plans::SELECTABLE as $plan) {
    $labels = array_column(Plans::get($plan)['features'], 0);
    $inherits = in_array('Everything in Free', $labels, true)
        || in_array('Everything in Pro', $labels, true);

    foreach ($sold as $label => $ability) {
        if (!Plans::can($plan, $ability) || $inherits) {
            continue;
        }
        ok("{$plan}: the page mentions \"{$label}\", which it grants",
            in_array($label, $labels, true));
    }
}

// =====================================================================
// Nothing sells whose name the email goes out under
// =====================================================================
//
// The mail leaves a PromoMonster domain. Gmail and Outlook add their own
// "via promomonster.com" from the envelope, whatever we write in the From
// name -- so "your emails go out under your own business name" describes
// something the recipient never sees. ReviewRequests does still drop our
// suffix and our footer for paid plans, but that is a quieter email, not a
// change of sender, and it is not sold as one anywhere.

foreach (Plans::SELECTABLE as $plan) {
    $prose = Plans::get($plan)['tagline'] . ' ';
    foreach (Plans::get($plan)['features'] as [$label, , ]) {
        $prose .= $label . ' ';
    }
    foreach (Plans::card($plan) as $line) {
        $prose .= $line['text'] . ' ';
    }

    ok("{$plan}: no claim about the sender's name",
        preg_match('/(under|as)\s+(your|their)\s+own\s+(business\s+)?name/i', $prose) !== 1);
    ok("{$plan}: does not mention the via line either way",
        !str_contains(strtolower($prose), 'via promomonster'));
}

ok('the pricing page makes no sender-name promise',
    preg_match('/(under|as)\s+(your|their)\s+own\s+(business\s+)?name/i', $pageText) !== 1);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
