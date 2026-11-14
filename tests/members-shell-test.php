<?php

declare(strict_types=1);

/**
 * The members shell: the sidebar, the welcome band, and the anchor they both
 * point at.
 *
 * Three things here have gone wrong before and would go wrong silently:
 *
 *   A link in the nav to a route that is not registered. Nobody clicks their
 *   own nav while building, because they already know where everything is.
 *
 *   A class in the markup with no rule in the stylesheet. That is exactly the
 *   bug behind "I am not seeing any design change" -- the page renders, the
 *   markup is right, and the styling is simply absent.
 *
 *   The #plans anchor. Three places in the members area link to
 *   /members#plans. The cards used to be in the layout, so the anchor existed
 *   on every page and the links could not miss. They are on one page now, and
 *   nothing but this test notices if that page stops carrying the id.
 *
 * Source-reading rather than rendering: the sidebar counts an allowance, which
 * needs a database, and a test that silently skips when the database is not
 * there is a test that reports 0 failures while everything is broken.
 */

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

$views   = BASE_PATH . '/app/Views/members';
$sidebar = (string) file_get_contents($views . '/_sidebar.php');
$layout  = (string) file_get_contents($views . '/layout.php');
$welcome = (string) file_get_contents($views . '/_welcome.php');

/**
 * The same file with its comments taken out.
 *
 * Needed for every assertion of the form "this must NOT appear", and for
 * counting an attribute. These views explain themselves at length, and the
 * explanations quote the code they are about -- so a comment saying why the
 * band must not read $me['toutify_sub'] made the test that forbids it fail,
 * and a comment saying the anchor lives here counted as a second anchor.
 * Both of those happened while writing this file, and an earlier test in this
 * suite read its own header comment the same way.
 */
function code(string $source): string
{
    return (string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $source);
}

$sidebarCode = code($sidebar);
$layoutCode  = code($layout);
$welcomeCode = code($welcome);
$css     = (string) file_get_contents(BASE_PATH . '/public/assets/css/app.css');
$index   = (string) file_get_contents(BASE_PATH . '/public/index.php');

ok('the sidebar has a body', strlen($sidebar) > 500);

/* ---- Every link goes somewhere the router answers --------------------- */

$routes = [];
foreach (['get', 'post', 'getToken'] as $verb) {
    if (preg_match_all("/router->{$verb}\('([^']+)'/", $index, $m) > 0) {
        foreach ($m[1] as $path) {
            $routes[] = $path;
        }
    }
}
ok('routes were found to check against', count($routes) > 20);

// The nav's own links live in a PHP array, so they are read out of the source
// rather than out of rendered HTML -- same list, no database needed.
preg_match_all("~'(/members[a-z/-]*)'\s*=> \[~", $sidebar, $navMatch);
$nav = array_values(array_unique($navMatch[1]));

check('the sidebar lists eight screens', count($nav), 8);

foreach ($nav as $href) {
    ok("{$href} is a registered route", in_array($href, $routes, true));
}

// Not one of the eight may go missing in a redesign. These are the links the
// old header row carried; a column that shows all of them at once was the
// whole point of the change, so losing one to a rewrite would be the exact
// opposite of the intent.
foreach ([
    '/members',
    '/members/requests',
    '/members/reviews',
    '/members/promomonster-reviews',
    '/members/templates',
    '/members/playbook',
    '/members/profile',
    '/members/settings',
] as $href) {
    ok("still reachable: {$href}", in_array($href, $nav, true));
}

// The sign-out form and the one primary action.
ok('sign out posts to its route', str_contains($sidebar, 'action="/members/logout"'));
ok('and carries a CSRF field', str_contains($sidebar, 'Csrf::field()'));
ok('the primary action asks for a review', str_contains($sidebar, 'href="/members/requests"'));

/* ---- Active link, by exact match ------------------------------------- */

// Every one of these paths begins with /members, so a prefix test lights
// Dashboard up on all eight screens. It has to be equality.
ok('the active test is equality', str_contains($sidebar, '$here = $path === $href;'));
ok('and not a prefix test', !str_contains($sidebarCode, 'str_starts_with($path'));
ok('the active link is announced', str_contains($sidebar, 'aria-current="page"'));

/* ---- The #plans anchor ----------------------------------------------- */

// Collect every members-area link to the anchor, then prove the page it names
// carries it. The cards moved out of the layout; this is the thing that broke.
$pointers = [];
foreach (glob($views . '/*.php') ?: [] as $file) {
    $body = (string) file_get_contents($file);
    if (preg_match_all('~href="(/members[a-z/-]*)#plans"~', $body, $m) > 0) {
        foreach ($m[1] as $target) {
            $pointers[$target][] = basename($file);
        }
    }
}
ok('something links to the anchor', $pointers !== []);

$overview = (string) file_get_contents($views . '/overview.php');
$pages = ['/members' => $overview];
foreach (array_keys($pointers) as $target) {
    ok("#plans is linked from a page we can check: {$target}", isset($pages[$target]));
    if (isset($pages[$target])) {
        ok("{$target} carries id=\"plans\"", str_contains($pages[$target], 'id="plans"'));
        ok("{$target} renders the plan cards", str_contains($pages[$target], "members/_plan-cards"));
    }
}

// Exactly one, or the browser jumps to whichever comes first and the other is
// dead weight.
check('the dashboard defines the anchor once', substr_count(code($overview), 'id="plans"'), 1);

// And the layout no longer does, or the anchor on the dashboard is shadowed by
// one above it on every page.
ok('the layout does not render the plan cards', !str_contains($layoutCode, '_plan-cards'));
ok('the layout has no plan strip', !str_contains($layoutCode, 'planstrip'));
ok('the stylesheet has no plan strip left', !str_contains(code($css), 'planstrip'));

/* ---- The layout renders the sidebar ---------------------------------- */

ok('the layout renders the sidebar', str_contains($layout, "members/_sidebar"));
ok('and passes it the current path', str_contains($layout, "'path'    => \$path"));
ok('and the account it needs for the plan', str_contains($layout, "'account' => \$account ?? []"));
ok('the skip link has a target', str_contains($layout, 'href="#main"') && str_contains($layout, 'id="main"'));

// The staff screens still use the old header, so its rules have to survive.
ok('superadmin still has its header rules', str_contains($css, '.admin-header{'));
ok('superadmin still has its nav rules', str_contains($css, '.admin-nav a[aria-current="page"]'));

/* ---- Every class in the markup has a rule ---------------------------- */

// The "no design change" bug: markup renders, class name is a typo, nothing
// is styled and nothing complains.
$classes = [];
foreach ([$sidebar, $welcome, $layout] as $source) {
    if (preg_match_all('~class="([^"<>$]+)"~', $source, $m) > 0) {
        foreach ($m[1] as $attr) {
            foreach (preg_split('/\s+/', trim($attr)) ?: [] as $name) {
                if ($name !== '') {
                    $classes[$name] = true;
                }
            }
        }
    }
}
// Classes built by PHP rather than typed in full.
foreach (['side__link', 'is-here', 'hello__btn--solid'] as $name) {
    $classes[$name] = true;
}

ok('classes were found to check', count($classes) > 15);

// Shared with the public pages, defined elsewhere in the stylesheet.
$elsewhere = ['logo', 'logo--stacked', 'logo__text', 'logo__word', 'logo__accent',
    'logo__by', 'icon', 'skip-link', 'flash', 'muted', 'members'];

foreach (array_keys($classes) as $name) {
    if (in_array($name, $elsewhere, true)) {
        continue;
    }
    // A rule for .side__cta, .side__cta:hover, or .side__cta{ -- any of them.
    ok("styled: .{$name}", preg_match('~\.' . preg_quote($name, '~') . '(?![\w-])~', $css) === 1);
}

/* ---- The band says only what it can stand behind --------------------- */

// Telling a direct-signup member their account is "included with your Toutify
// membership" is a sentence about somebody else's account.
ok('the membership line is conditional', str_contains($welcome, 'if ($viaToutify)'));
ok('membership is read from the session', str_contains($welcome, "\$_SESSION['toutify']"));

// Auth::user() selects a fixed column list, and toutify_sub is not in it --
// reading it from the user row would be false for everybody, forever.
$auth = (string) file_get_contents(APP_ROOT . '/Support/Auth.php');
ok('toutify_sub is indeed not selected', !str_contains(code($auth), 'toutify_sub'));
ok('so the band does not read it from the user', !str_contains($welcomeCode, "\$me['toutify_sub']"));

// The Toutify link uses the issuer's own origin. A guessed path on somebody
// else's site is a 404 that only shows up after it ships.
ok('the Toutify link uses the issuer', str_contains($welcome, 'ToutifyAuth::issuer()'));
ok('and only when it is configured', str_contains($welcome, 'ToutifyAuth::configured()'));
foreach (['/dashboard', '/account', '/login'] as $guess) {
    ok("no guessed path: {$guess}", !str_contains($welcomeCode, 'issuer()) ?>' . $guess));
}

// Upgrade buttons only where there is something above the current plan.
ok('the band hides upgrade on the top plan', str_contains($welcome, 'Plans::above($plan) !== []'));
ok('the sidebar does too', str_contains($sidebar, 'Plans::above($plan) !== []'));
check('Premium has nothing above it', App\Support\Plans::above(App\Support\Plans::PREMIUM), []);
ok('Free has something above it', App\Support\Plans::above(App\Support\Plans::FREE) !== []);

/* ---- The allowance is the number the sender enforces ------------------ */

// One source of truth: the figure on screen and the figure that blocks a send
// come from the same call, so they cannot disagree.
ok('the sidebar reads SendLimit', str_contains($sidebar, 'SendLimit::check('));
ok('and the month figures from it', str_contains($sidebar, "\$limit['month_limit']")
    && str_contains($sidebar, "\$limit['month_used']"));

// Unlimited is a word, not a division by zero.
ok('no divide by a null cap', str_contains($sidebar, '$cap === null || $cap === 0'));

/* ---- The sidebar works without a mouse or a script ------------------- */

// The members area loads no JavaScript at all. A menu that needs a script is
// a menu that is shut when the script fails -- and here there is no script to
// fail, so the markup has to stand on its own.
ok('the layout loads no script', !str_contains($layoutCode, '<script'));
ok('the sidebar needs no script', !str_contains($sidebarCode, '<script')
    && !str_contains($sidebarCode, 'onclick'));

// Below 1000px the same markup becomes a bar across the top.
ok('there is a narrow-screen rule', str_contains($css, '@media (max-width:999px){'));
ok('and a wide-screen one', str_contains($css, '@media (min-width:1000px){'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
