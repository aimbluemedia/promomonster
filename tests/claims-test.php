<?php

declare(strict_types=1);

/**
 * What the public pages claim.
 *
 * Every page here is read by somebody deciding whether to trust us with their
 * Google profile, so a number on one of them is a promise. The rule is simple
 * and absolute: a figure may appear only if this codebase can produce it.
 *
 * Three claims have already had to be taken off these pages:
 *
 *   "Competitors charge $300-$600 a month for this", on the pricing page and
 *   in its meta description, which travels into search results and shared
 *   links. Nothing sourced it.
 *
 *   "A service you can sell for $199-$399 a month", on the agencies page.
 *   That one is an earnings claim made to a prospective partner, about
 *   revenue no agency has yet earned, which is the version a regulator reads
 *   back to you.
 *
 *   "Up to 5 locations", which rested on a limit nothing enforced.
 *
 * None of them was found by a test. They were found by reading the pages,
 * which is exactly the thing nobody does to their own marketing.
 */

define('APP_ROOT', dirname(__DIR__) . '/app');
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', dirname(__DIR__) . '/public');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = APP_ROOT . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Support\Config;
use App\Support\Plans;
use App\Support\View;

Config::load(['app_url' => 'https://promomonster.test', 'app_key' => str_repeat('a', 40)]);
$_SESSION = [];
$_SERVER['REQUEST_URI'] = '/';

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

function ok(string $what, bool $got): void
{
    check($what, $got, true);
}

/* ---- The pages, rendered ---------------------------------------------- */

// Pages that take no controller data. The rest are left out rather than
// faked: a page rendered with invented data is not the page anybody sees.
$pages = ['home', 'pricing', 'features', 'agencies', 'how-it-works'];
$text  = [];

foreach ($pages as $page) {
    $html = View::render($page);
    ok("{$page} renders", $html !== '');
    // Entities first, or "$300&ndash;$600" reads as two separate figures and
    // the dash that joins them disappears from the dash check below.
    $text[$page] = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/* ---- Every dollar figure is one of ours -------------------------------- */

// Our own prices, from the catalogue. Nothing else may carry a $ in front of
// it: not a competitor's price, not what an agency might charge, not a figure
// from an article somebody half remembers.
$ours = [];
foreach (array_keys(Plans::all()) as $plan) {
    $ours[] = (string) Plans::price((string) $plan);
}
$ours = array_values(array_unique($ours));
ok('our own prices were found', count($ours) > 1);

foreach ($text as $page => $body) {
    preg_match_all('/\$\s?([0-9][0-9,]*)(?:\.[0-9]{2})?/', $body, $found);
    foreach (array_unique($found[1]) as $figure) {
        $plain = str_replace(',', '', $figure);
        ok("{$page}: \${$figure} is a price we charge", in_array($plain, $ours, true));
    }
}

/* ---- And no range of someone else's prices ----------------------------- */

foreach ($text as $page => $body) {
    ok("{$page}: no price range",
        preg_match('/\$\s?[0-9][0-9,]*\s?[-\x{2013}\x{2014}]\s?\$?[0-9]/u', $body) !== 1);
    ok("{$page}: nobody else's charges are quoted",
        preg_match('/(competitors?|others|rivals|most tools|agencies)\s+(charge|cost|bill)/i', $body) !== 1);
    ok("{$page}: no earnings promise",
        preg_match('/(sell|charge|bill|resell)\s+(it\s+)?for\s+\$/i', $body) !== 1);
}

/* ---- The calculator's one assumption, in three places ------------------ */

// The home page carries a percentage: "at a 25% response rate". That is not a
// statistic about the world, it is the figure the calculator divides by, said
// out loud -- and a calculator with a hidden assumption would be the worse of
// the two. So it stays, and instead the three copies of it are pinned
// together: the sentence a visitor reads, the arithmetic PHP does on first
// render, and the constant the script uses once they move a slider. Change
// one and the page tells a visitor one thing while doing another.

$calc = (string) file_get_contents(APP_ROOT . '/Views/partials/calculator.php');
$js   = (string) file_get_contents(PUBLIC_PATH . '/assets/js/app.js');

ok('the script declares a response rate',
    preg_match('/RESPONSE_RATE\s*=\s*(0?\.[0-9]+)/', $js, $m) === 1);
$rate = isset($m[1]) ? (float) $m[1] : 0.0;
ok('and it is a fraction between 0 and 1', $rate > 0 && $rate < 1);

$shown = (int) round($rate * 100);
ok("the hint says {$shown}%",
    preg_match('/\b' . $shown . '%\s+response rate/', $calc) === 1);

// Every divisor in the first-render maths is the same number.
preg_match_all('~/\s*(0?\.[0-9]+)~', $calc, $divisors);
ok('the PHP divides by something', $divisors[1] !== []);
foreach (array_unique($divisors[1]) as $divisor) {
    check("the PHP divides by the declared rate", (float) $divisor, $rate);
}

// And it is the ONLY percentage anywhere on these pages.
$allowed = $shown . '%';

/* ---- The same rule for statistics -------------------------------------- */

// The footer suite already holds this line for the cross-sell band. These are
// the pages above it.
foreach ($text as $page => $body) {
    preg_match_all('/\b\d+(?:\.\d+)?\s?%/', $body, $percents);
    foreach (array_unique($percents[0]) as $percent) {
        check("{$page}: \"{$percent}\" is the calculator's own assumption",
            str_replace(' ', '', $percent), $allowed);
    }
    ok("{$page}: no unsourced multiple",
        preg_match('/\b\d+\s?(x|times)\s+(more|fewer|faster|better|likely)/i', $body) !== 1);
    ok("{$page}: no appeal to research we cannot name",
        preg_match('/\b(studies show|research shows|surveys show|on average,)/i', $body) !== 1);
}

/* ---- Meta descriptions travel further than the page -------------------- */

// They show up in search results and in a shared link, where nobody can see
// the page that would qualify them. The competitor price was in one.
$source = (string) file_get_contents(APP_ROOT . '/Controllers/PageController.php');
$source = (string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $source);

ok('no competitor price in a meta description',
    preg_match('/(competitors?|others)\s+(charge|cost)/i', $source) !== 1);
ok('no price range in a meta description',
    preg_match('/\$\s?[0-9][0-9,]*\s?[-\x{2013}\x{2014}]\s?\$?[0-9]/u', $source) !== 1);

/* ---- What the pages must still say ------------------------------------- */

// The checks above all pass on an empty page, so here is the floor: the
// pricing page still has to name every plan and its price.
foreach (Plans::SELECTABLE as $plan) {
    ok("pricing names {$plan}", str_contains($text['pricing'], Plans::name($plan)));
    ok("pricing gives {$plan}'s price",
        str_contains($text['pricing'], (string) Plans::price($plan)));
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
