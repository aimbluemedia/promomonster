<?php

declare(strict_types=1);

/**
 * The footer's links, against the routes that actually exist.
 *
 * The footer is on every public page, so one dead link in it is a dead link
 * site-wide -- and it is exactly the kind of thing nobody clicks while
 * building, because you already know where everything is. Renaming a route is
 * the normal way this breaks.
 *
 * Reads the rendered markup and the router's own registrations, so neither
 * side can be edited without the other noticing.
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
use App\Support\Toutify;
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

$html = View::render('partials/footer');
ok('the footer renders', $html !== '');

// --- Every internal link must be a route the app registers ---------------
$routes = [];
foreach (explode("\n", (string) file_get_contents(BASE_PATH . '/public/index.php')) as $line) {
    if (preg_match("/router->get\('([^']+)'/", $line, $m) === 1) {
        $routes[] = $m[1];
    }
    // getToken('/reviews') serves /reviews/{slug}; record the prefix.
    if (preg_match("/router->getToken\('([^']+)'/", $line, $m) === 1) {
        $routes[] = $m[1];
    }
}
ok('routes were found to check against', count($routes) > 10);

preg_match_all('~href="(/[^"#?]*)~', $html, $found);
$links = array_values(array_unique($found[1]));

ok('the footer has links', count($links) >= 10);

foreach ($links as $href) {
    $path = rtrim($href, '/') ?: '/';
    ok("{$href} is a real route", in_array($path, $routes, true));
}

// --- Nothing points off-site without meaning to -------------------------
// A guessed domain for a sibling product would be a dead link on every page.
preg_match_all('~href="(https?://[^"]+)~', $html, $external);
check('no external links yet', $external[1], []);

// --- The cross-sell says what it can stand behind ------------------------
check('six products', count(Toutify::products()), 6);

$selves = array_filter(Toutify::products(), static fn (array $p): bool => Toutify::isSelf($p['key']));
check('exactly one is marked as this product', count($selves), 1);
check('and it is PromoMonster', array_values($selves)[0]['name'], 'PromoMonster');
ok('the band marks it on screen', str_contains($html, 'You are here'));

foreach (Toutify::products() as $product) {
    ok("{$product['name']}: has a description", trim($product['what']) !== '');
    ok("{$product['name']}: is named in the footer", str_contains($html, $product['name']));
    // A sibling with no address is drawn as a card, never as a link: a guessed
    // domain is worse than no link at all.
    if ($product['url'] === null) {
        ok("{$product['name']}: is not linked anywhere",
            !str_contains($html, 'href="' . $product['name'] . '"'));
    }
}

// No price, no figure, no claim we cannot source -- the same rule the
// playbooks seed follows, for the same reason.
$bandText = strip_tags($html);
ok('no percentages in the cross-sell', preg_match('/\d+\s?%/', $bandText) === 0);
ok('no invented multiples', preg_match('/\b\d+\s?(x|times)\s+(more|faster|better)/i', $bandText) === 0);

// --- Promises the free plan actually keeps -------------------------------
require_once APP_ROOT . '/Support/Plans.php';
check('Free really costs nothing', App\Support\Plans::price(App\Support\Plans::FREE), 0);
foreach (Toutify::PROMISES as $promise) {
    ok("promise has words: {$promise}", trim($promise) !== '');
    ok("promise is on screen: {$promise}", str_contains($html, $promise));
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
