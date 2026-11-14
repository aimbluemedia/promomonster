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
$started = (string) file_get_contents($views . '/_getting-started.php');
$promo   = (string) file_get_contents($views . '/promo-reviews.php');

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
$startedCode = code($started);
$promoCode   = code($promo);
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
foreach ([$sidebar, $welcome, $layout, $started, $promo] as $source) {
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
foreach ([
    'side__link', 'is-here', 'hello__btn--solid',
    'gs', 'gs--done', 'gs__card', 'is-next', 'is-done', 'gs__btn--go', 'is-full',
    'stepcard', 'stepcard--locked', 'stepcard__pill--paid',
] as $name) {
    $classes[$name] = true;
}

ok('classes were found to check', count($classes) > 15);

$cssCode = code($css);

// Shared with the public pages, defined elsewhere in the stylesheet.
$elsewhere = ['logo', 'logo--stacked', 'logo__text', 'logo__word', 'logo__accent',
    'logo__by', 'icon', 'skip-link', 'flash', 'muted', 'members'];

foreach (array_keys($classes) as $name) {
    if (in_array($name, $elsewhere, true)) {
        continue;
    }
    // Against the stylesheet with its comments taken out. A rule for
    // .side__cta, .side__cta:hover or .side__cta{ all count -- but a class
    // named only in a comment explaining why it exists does not, which is
    // exactly what this check would otherwise accept. Third time comments
    // have fooled an assertion in this suite.
    ok("styled: .{$name}",
        preg_match('~\.' . preg_quote($name, '~') . '(?![\w-])~', $cssCode) === 1);
}

/* ---- The step cards -------------------------------------------------- */

// One filter, used twice, would be two chances to disagree. The view takes
// the count from progress() rather than counting the cards again, so the
// heading, the bar and the number of boxes on screen cannot drift apart.
ok('the card count comes from progress()', str_contains($startedCode, "\$progress['total']"));
ok('and is not counted a second time', !str_contains($startedCode, '$total    = count($cards)')
    && !str_contains($startedCode, '$total = count($cards)'));

// Cards are the steps that can be finished; the rest go underneath.
ok('cards filter on checkable and unlocked',
    str_contains($startedCode, "\$s['checkable'] && !\$s['locked']"));
ok('and the notes are the complement',
    str_contains($startedCode, "!\$s['checkable'] || \$s['locked']"));

// The locked step and the one we cannot verify must still be on the page.
// Dropping them would leave somebody wondering why a nav item does nothing,
// or never learning the last step exists at all.
ok('the uncountable steps are still rendered', str_contains($startedCode, 'gs__also'));
ok('the locked one names every plan that grants it',
    str_contains($startedCode, 'Plans::plansWith('));
ok('and says what it would cost', str_contains($startedCode, 'Plans::money('));
ok('the unverifiable one says so', str_contains($started, 'We cannot check this one'));

// One card live at a time.
ok('the live card is the one progress() names',
    str_contains($startedCode, "\$progress['next'] === \$step['key']"));
ok('the badges are both there', str_contains($started, 'Do this next')
    && str_contains($started, 'gs__badge--done'));

// A finished card shows what came of it, never its blurb and a chip at once.
ok('chips are an elseif on done', str_contains($startedCode, "elseif ((\$step['facts'] ?? []) !== [])"));
ok('and the blurb is the not-done branch', str_contains($startedCode, "if (!\$step['done']):"));

/* ---- The three steps on the review page ------------------------------ */

// Order matters here and is easy to undo by accident. Adding by hand is the
// only one of the three that a plan can refuse, so it goes last: it sat in
// the middle, between the two everybody can do, which put the one locked
// card in the centre of the row for most of the people reading it.
$order = [];
foreach (['Your review page', 'Put them on your website', 'Add a review yourself'] as $heading) {
    $at = strpos($promo, $heading);
    ok("the row has: {$heading}", $at !== false);
    $order[$heading] = $at === false ? PHP_INT_MAX : $at;
}
ok('the page comes first', $order['Your review page'] < $order['Put them on your website']);
ok('the website second', $order['Put them on your website'] < $order['Add a review yourself']);

ok('they are in one stack', str_contains($promo, 'class="steps"'));

// Two pills say "yours already", one says what it costs. The second half of
// that matters as much as the first: a member on Free should see at a glance
// that two of the three are already theirs.
check('two cards are on every plan',
    substr_count($promo, 'All membership access'), 2);

// The paid one names the plan from the catalogue rather than the word
// "Premium" typed into a view. Move the ability and this follows.
ok('the paid pill is generated',
    str_contains($promoCode, 'Plans::cheapestWith(App\\Support\\Plans::ADD_REVIEWS)'));
ok('and no plan name is hardcoded beside it',
    !str_contains($promoCode, 'Premium access'));

// The form lives inside step 3 again. It was lifted out when the three were
// side by side, because eight fields in a third of a row is a form nobody
// finishes; a full-width card has room for it, so the card that is about
// adding a review is the card that adds one.
ok('step 3 is the anchor', str_contains($promo, 'id="add-review"'));
ok('the form is inside it',
    strpos($promo, 'id="add-review"') < strpos($promo, '/members/promomonster-reviews/add'));

// Rendered on both plans and compared, rather than matched against the text
// of the gate. The first version of this looked for "if ($canAdd):" and broke
// the moment the branch was written the other way round -- a test of how the
// condition is spelled, not of what it does.
App\Support\Config::load([
    'app_url' => 'https://promomonster.test',
    'app_key' => str_repeat('a', 40),
]);
$_SESSION = [];
$_SERVER['REQUEST_URI'] = '/members/promomonster-reviews';

$renderStep3 = static function (string $plan): string {
    return App\Support\View::render('members/promo-reviews', [
        'account'  => ['id' => 1, 'name' => 'Acme Pools', 'plan' => $plan],
        'ready'    => true,
        'slug'     => 'acme-pools',
        'pageUrl'  => 'https://promomonster.test/reviews/acme-pools',
        'widgetJs' => 'https://promomonster.test/widget/acme-pools.js',
        'summary'  => ['count' => 0, 'average' => null],
        'reviews'  => [],
        'requests' => [],
        'error'    => null,
        'canAdd'   => App\Support\Plans::can($plan, App\Support\Plans::ADD_REVIEWS),
        'branded'  => !App\Support\Plans::can($plan, App\Support\Plans::UNBRANDED),
        'drafts'   => [],
        'canDraft' => App\Support\Plans::can($plan, App\Support\Plans::REPLY_DRAFTS),
        'draftMonth' => ['allowed' => false, 'used' => 0, 'limit' => 0, 'reason' => null],
    ]);
};

$asFree    = $renderStep3(App\Support\Plans::FREE);
$asPremium = $renderStep3(App\Support\Plans::PREMIUM);

ok('both render', $asFree !== '' && $asPremium !== '');
ok('Premium gets the form',
    str_contains($asPremium, 'action="/members/promomonster-reviews/add"'));
ok('Free does not',
    !str_contains($asFree, 'action="/members/promomonster-reviews/add"'));
ok('and Free is told the price instead',
    str_contains($asFree, App\Support\Plans::money(App\Support\Plans::PREMIUM)));
ok('Free gets the quiet card', str_contains($asFree, 'stepcard--locked'));
ok('Premium does not', !str_contains($asPremium, 'stepcard--locked'));

// Both see all three steps, whatever the plan. A step that vanished on Free
// would leave somebody unable to learn the feature exists.
foreach (['Your review page', 'Put them on your website', 'Add a review yourself'] as $heading) {
    ok("Free sees: {$heading}", str_contains($asFree, $heading));
    ok("Premium sees: {$heading}", str_contains($asPremium, $heading));
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

// The Toutify link is built from the issuer, never typed. It used to call
// issuer() here and point at the bare origin; it calls membersUrl() now,
// which pins the origin and validates the path -- the two checks below, plus
// the ones in toutify-test.php, cover what this line used to.
ok('the Toutify link is only shown when configured',
    str_contains($welcome, 'ToutifyAuth::configured()'));

// Both ways into Toutify from the band: out to the issuer for a session that
// came through it, and into the sign-in route for one that did not.
ok('a non-Toutify session is offered sign-in',
    str_contains($welcome, 'href="/auth/toutify/login"'));
ok('and that is a route the app registers',
    in_array('/auth/toutify/login', $routes, true));
// In this tab. An authorisation redirect opened in a new tab loses the
// session cookie the callback needs on some browsers.
//
// The whole tag, not the text after href: the first version of this check
// looked only to the right of the href and passed happily when target was
// written before it.
preg_match_all('~<a\s[^>]*>~', $welcome, $anchors);
$signIn = array_values(array_filter(
    $anchors[0],
    static fn (string $tag): bool => str_contains($tag, '/auth/toutify/login'),
));
check('there is one sign-in link', count($signIn), 1);
ok('sign-in does not open a new tab',
    $signIn !== [] && !str_contains($signIn[0], 'target'));
// Out to the issuer, though, is a different site, so it does.
ok('the issuer link opens a new tab and is safe',
    str_contains($welcome, 'target="_blank" rel="noopener"'));

// Through membersUrl(), which pins the origin to the issuer and validates the
// path. A literal would be a guessed path baked into a view, which is what
// this link avoided by pointing at the bare origin in the first place.
ok('the members link is built, not typed',
    str_contains($welcomeCode, 'ToutifyAuth::membersUrl()'));
ok('and no toutify.com is hardcoded in the view',
    !str_contains($welcomeCode, 'toutify.com'));
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
