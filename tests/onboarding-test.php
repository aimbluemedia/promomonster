<?php

declare(strict_types=1);

/**
 * The Get started checklist.
 *
 * Needs no database: Onboarding is a pure function of state the controller has
 * already read, which is exactly why it was written that way. Every judgement
 * worth arguing about -- which step is next, what a plan hides, what counts
 * toward the bar -- is decided here and asserted here.
 */

require __DIR__ . '/../app/Support/Plans.php';
require __DIR__ . '/../app/Support/Onboarding.php';

use App\Support\Onboarding;
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

function ok(string $what, bool $got): void
{
    check($what, $got, true);
}

/** @param array<string,mixed> $state */
function run(array $state): array
{
    $steps = Onboarding::steps($state);

    return [$steps, Onboarding::progress($steps)];
}

/** @param list<array<string,mixed>> $steps */
function step(array $steps, string $key): array
{
    foreach ($steps as $s) {
        if ($s['key'] === $key) {
            return $s;
        }
    }

    return [];
}

const FRESH_PRO = ['plan' => Plans::PRO, 'hasPage' => true, 'hasGoogleLink' => false,
                   'requestsSent' => 0, 'reviewsCollected' => 0];

// =====================================================================
// The shape of the list
// =====================================================================

[$steps, ] = run(FRESH_PRO);

check('there are five steps', count($steps), 5);
check('in the order somebody does them', array_column($steps, 'key'),
    ['page', 'google', 'send', 'collect', 'display']);

foreach ($steps as $s) {
    foreach (['key', 'title', 'what', 'done', 'locked', 'checkable', 'href', 'cta'] as $field) {
        ok("{$s['key']}: has {$field}", array_key_exists($field, $s));
    }
    ok("{$s['key']}: the title says something", trim($s['title']) !== '');
    ok("{$s['key']}: the blurb says something", trim($s['what']) !== '');
    // Every link has to go to a page that exists, or the checklist sends
    // somebody to a 404 on their first visit.
    ok("{$s['key']}: links into the members area", str_starts_with($s['href'], '/members'));
}

// =====================================================================
// Nothing is ticked that cannot be seen
// =====================================================================
//
// The snippet is served from here, but nothing records who fetched it. A tick
// we guessed at would tell somebody their website is live when it is not, so
// this step is never done and never counted -- and it says so on screen.

$display = step($steps, 'display');
check('the website step is never ticked', $display['done'], false);
check('and is marked unverifiable',       $display['checkable'], false);

foreach ([0, 1, 50] as $n) {
    [$s2, ] = run(['plan' => Plans::PREMIUM, 'hasPage' => true, 'hasGoogleLink' => true,
                   'requestsSent' => $n, 'reviewsCollected' => $n]);
    check("still unticked with {$n} reviews", step($s2, 'display')['done'], false);
}

// =====================================================================
// What each signal turns on
// =====================================================================

[$s, ] = run(['plan' => Plans::PRO, 'hasPage' => false, 'hasGoogleLink' => false,
              'requestsSent' => 0, 'reviewsCollected' => 0]);
check('no page, no tick', step($s, 'page')['done'], false);

[$s, ] = run(FRESH_PRO);
check('a page ticks the first step', step($s, 'page')['done'], true);
check('no link, no tick',            step($s, 'google')['done'], false);
check('nothing sent, no tick',       step($s, 'send')['done'], false);
check('nothing collected, no tick',  step($s, 'collect')['done'], false);

// Premium, because that is the cheapest plan the Google step is on now. A
// saved link on a plan that cannot use it must not tick anything.
[$s, ] = run(['plan' => Plans::PREMIUM, 'hasPage' => true, 'hasGoogleLink' => true,
              'requestsSent' => 1, 'reviewsCollected' => 1]);
check('a saved link ticks it',   step($s, 'google')['done'], true);
check('one request ticks it',    step($s, 'send')['done'], true);
check('one review ticks it',     step($s, 'collect')['done'], true);

// Counts must never be taken on trust. A negative from a bad query would
// otherwise read as "done" through a loose comparison.
[$s, ] = run(['plan' => Plans::PRO, 'hasPage' => true, 'hasGoogleLink' => true,
              'requestsSent' => -5, 'reviewsCollected' => -5]);
check('a negative count is not done (send)',    step($s, 'send')['done'], false);
check('a negative count is not done (collect)', step($s, 'collect')['done'], false);

// Missing keys must read as "not done", never as done.
[$s, $p] = run([]);
check('an empty state ticks nothing',
    array_filter(array_column($s, 'done')), []);
ok('and still names a next step', $p['next'] !== null);

// =====================================================================
// A plan that cannot do a thing is not nagged about it
// =====================================================================

[$free, $freeProgress] = run(['plan' => Plans::FREE, 'hasPage' => true, 'hasGoogleLink' => false,
                              'requestsSent' => 0, 'reviewsCollected' => 0]);
$g = step($free, 'google');
check('on Free the Google step is locked', $g['locked'], true);
check('and is not counted',                $g['checkable'], false);
check('and names the plan that would do it', $g['needs'], Plans::PREMIUM);

// The step still SHOWS. Hiding it would leave somebody wondering why the
// Google page in the nav does nothing.
ok('but it is still listed', step($free, 'google') !== []);

// Three on both Free and Pro, because the Google step is locked on both now.
// Premium is the one with four. The number is read from progress() rather
// than typed into the view, so the heading follows whichever it is.
check('Free counts three steps, not four', $freeProgress['total'], 3);
check('Pro counts three as well',
    Onboarding::progress(Onboarding::steps(FRESH_PRO))['total'], 3);
check('Premium counts four', Onboarding::progress(Onboarding::steps(
    ['plan' => Plans::PREMIUM, 'hasPage' => true]))['total'], 4);

// The one that matters: a Free account must be able to REACH the end. Leaving
// the locked step in the denominator would park them at "3 of 4" forever,
// reading as a job half done rather than a plan working as sold.
[, $done] = run(['plan' => Plans::FREE, 'hasPage' => true, 'hasGoogleLink' => false,
                 'requestsSent' => 3, 'reviewsCollected' => 2]);
check('Free can finish',        $done['complete'], true);
check('Free reaches 100%',      $done['percent'], 100);
check('Free reads 3 of 3',      [$done['done'], $done['total']], [3, 3]);

// A locked step must never be the thing it tells them to do next.
foreach ([Plans::FREE, Plans::PRO, Plans::PREMIUM] as $plan) {
    foreach ([[false, 0, 0], [true, 0, 0], [true, 2, 0], [true, 2, 2]] as [$link, $sent, $got]) {
        [$list, $prog] = run(['plan' => $plan, 'hasPage' => true, 'hasGoogleLink' => $link,
                              'requestsSent' => $sent, 'reviewsCollected' => $got]);
        if ($prog['next'] === null) {
            continue;
        }
        $next = step($list, $prog['next']);
        ok("{$plan}: next step is not locked",   !$next['locked']);
        ok("{$plan}: next step is not done",     !$next['done']);
    }
}

// progress() is tested directly here, on a list steps() does not currently
// produce: one that is both locked AND checkable.
//
// Found by mutation. Removing the "&& !locked" guard from progress() changed
// nothing, because steps() happens to set checkable=false whenever it locks
// the Google step -- so the guard was untested and the suite was quietly
// relying on that coupling. The guard is defence for the day a step is added
// that can be both, and defence nothing tests is decoration.
$synthetic = [
    ['key' => 'a', 'done' => true,  'locked' => false, 'checkable' => true],
    ['key' => 'b', 'done' => false, 'locked' => true,  'checkable' => true],
    ['key' => 'c', 'done' => false, 'locked' => false, 'checkable' => true],
];
$sp = Onboarding::progress($synthetic);
check('a locked-but-checkable step is out of the denominator', $sp['total'], 2);
check('and out of the numerator',                              $sp['done'], 1);
check('and is never the next thing to do',                     $sp['next'], 'c');

// With the locked one finished elsewhere, the account still completes.
$synthetic[2]['done'] = true;
$sp = Onboarding::progress($synthetic);
check('completion ignores the locked step', $sp['complete'], true);
check('and reads 100%',                     $sp['percent'], 100);

// An all-locked list must not divide by zero.
$sp = Onboarding::progress([['key' => 'x', 'done' => false, 'locked' => true, 'checkable' => true]]);
check('nothing countable does not divide by zero', $sp['percent'], 100);
check('and is not called complete',                $sp['complete'], false);
check('an empty list is safe',  Onboarding::progress([])['percent'], 100);

// =====================================================================
// A locked step names every plan that would unlock it
// =====================================================================
//
// It used to name only the cheapest. Somebody already weighing Premium was
// told about Pro and left to wonder whether the dearer plan covered it too --
// and the locked SECTIONS have named both all along, so the dashboard was the
// odd one out. The step carries the ability so the screen can ask Plans, which
// is the one place that knows.

[$free, ] = run(['plan' => Plans::FREE, 'hasPage' => true]);
$locked = step($free, 'google');

check('the locked step carries its ability', $locked['ability'], Plans::GOOGLE_PAGE);

$grants = Plans::plansWith((string) $locked['ability']);
// One, since the Google link moved to Premium. The point of carrying the
// ability rather than a plan name is that the screen asks Plans and gets
// whatever is true today -- which is what this is checking.
ok('at least one plan grants it', count($grants) >= 1);
check('and it is Premium', $grants, [Plans::PREMIUM]);

// The cheapest stays on the step too: it is what decides which plan is named
// first, and the two must not disagree.
check('the cheapest is the first named', $grants[0], $locked['needs']);

// Nothing ungated claims an ability, or every step would sprout a price.
foreach ($free as $s) {
    if ($s['key'] === 'google') {
        continue;
    }
    check("{$s['key']}: carries no ability", $s['ability'], null);
    check("{$s['key']}: carries no plan",    $s['needs'], null);
}

// On a plan that already has it, the step is not locked and names nothing.
// Premium, since that is now the only plan the Google step is on.
[$prem, ] = run(['plan' => Plans::PREMIUM, 'hasPage' => true]);
$open = step($prem, 'google');
check('on Premium it is not locked', $open['locked'], false);
check('and names no ability',        $open['ability'], null);
check('and names no plan',           $open['needs'], null);

// And on Pro it IS locked now, which is the change itself.
[$pro, ] = run(['plan' => Plans::PRO, 'hasPage' => true]);
$shut = step($pro, 'google');
check('on Pro it is locked',    $shut['locked'], true);
check('and names Premium',      $shut['needs'], Plans::PREMIUM);

// Whatever is named has to actually grant the thing, on every plan, or the
// upgrade button sends somebody to a tier that will refuse them again.
foreach ([Plans::FREE, Plans::PRO, Plans::PREMIUM] as $plan) {
    [$list, ] = run(['plan' => $plan, 'hasPage' => true]);
    foreach ($list as $s) {
        if ($s['ability'] === null) {
            continue;
        }
        foreach (Plans::plansWith((string) $s['ability']) as $named) {
            ok("{$plan}/{$s['key']}: {$named} really grants it",
                Plans::can($named, (string) $s['ability']));
            ok("{$plan}/{$s['key']}: {$named} can be bought",
                in_array($named, Plans::SELECTABLE, true));
        }
    }
}

// =====================================================================
// The bar
// =====================================================================

// Premium, because it is the plan with four countable steps now -- Pro has
// three, the Google one having moved up. Quarters are what make a rounding
// mistake visible, so the arithmetic is checked where quarters exist.
const FRESH_PREMIUM = ['plan' => Plans::PREMIUM, 'hasPage' => true, 'hasGoogleLink' => false,
                       'requestsSent' => 0, 'reviewsCollected' => 0];

[, $p] = run(FRESH_PREMIUM);
check('one of four is 25%', $p['percent'], 25);
check('and not complete',   $p['complete'], false);

[, $p] = run(['plan' => Plans::PREMIUM, 'hasPage' => true, 'hasGoogleLink' => true,
              'requestsSent' => 1, 'reviewsCollected' => 0]);
check('three of four is 75%', $p['percent'], 75);

// And thirds, on Pro, where three is the count. 1 of 3 rounds to 33.
[, $p] = run(FRESH_PRO);
check('one of three is 33%', $p['percent'], 33);
check('and still not complete', $p['complete'], false);

// Percent must stay inside the rails whatever is thrown at it.
foreach ([
    ['plan' => Plans::FREE, 'hasPage' => false],
    ['plan' => Plans::PREMIUM, 'hasPage' => true, 'hasGoogleLink' => true,
     'requestsSent' => 99, 'reviewsCollected' => 99],
    [],
] as $state) {
    [, $prog] = run($state);
    ok('percent is 0-100', $prog['percent'] >= 0 && $prog['percent'] <= 100);
    ok('done never exceeds total', $prog['done'] <= $prog['total']);
}

// Once everything checkable is done, the next thing really is the website --
// the one step we cannot tick. It is excluded from the BAR, not from the walk.
[, $p] = run(['plan' => Plans::PREMIUM, 'hasPage' => true, 'hasGoogleLink' => true,
              'requestsSent' => 1, 'reviewsCollected' => 1]);
check('complete, and still pointing at the website', [$p['complete'], $p['next']],
    [true, 'display']);

// =====================================================================
// "Complete" never means "nothing left"
// =====================================================================
//
// The dashboard printed "You are set up / 3 of 3" directly above an unticked
// step and a padlock. Three things on one screen disagreeing about whether the
// job was done, which to anybody who has not read the code means the screen is
// broken.
//
// The cause is by design: the website step can never be ticked, so complete
// has always meant "nothing WE CAN CHECK is left". The heading now says that,
// and it reads $progress['next'] to decide -- so this pins the invariant it
// leans on: there is always a next step, complete or not.

foreach ([Plans::FREE, Plans::PRO, Plans::PREMIUM] as $plan) {
    foreach ([[false, 0, 0], [true, 0, 0], [true, 9, 0], [true, 9, 9]] as [$link, $sent, $got]) {
        [, $prog] = run(['plan' => $plan, 'hasPage' => true, 'hasGoogleLink' => $link,
                         'requestsSent' => $sent, 'reviewsCollected' => $got]);

        ok("{$plan}: there is always something next", $prog['next'] !== null);

        // And when the bar is full, the thing still outstanding is the one we
        // cannot check -- never a real task left silently undone.
        if ($prog['complete']) {
            check("{$plan}: a full bar leaves only the website step", $prog['next'], 'display');
        }
    }
}

// =====================================================================
// The sentence that counts the steps
// =====================================================================

check('three has a word', Onboarding::inWords(3), 'three');
check('four has a word',  Onboarding::inWords(4), 'four');
// Rather than inventing one it does not have.
check('a big number falls back to the digit', Onboarding::inWords(99), '99');

// =====================================================================
// The chips on a finished card
// =====================================================================
//
// They sit where a finished step's blurb used to be, so the cards in a row
// come out the same height. Everything they say has to be read out of the
// same state that decided the step was done -- a chip is the one place on
// this screen where a number appears without a label beside it, so a wrong
// one is a number nobody can check.

foreach ([
    ['free', ['hasPage' => false, 'hasGoogleLink' => false, 'requestsSent' => 0,  'reviewsCollected' => 0]],
    ['free', ['hasPage' => true,  'hasGoogleLink' => true,  'requestsSent' => 1,  'reviewsCollected' => 1]],
    ['free', ['hasPage' => true,  'hasGoogleLink' => true,  'requestsSent' => 12, 'reviewsCollected' => 7]],
    ['pro',  ['hasPage' => true,  'hasGoogleLink' => true,  'requestsSent' => 3,  'reviewsCollected' => 0]],
    ['pro',  ['hasPage' => false, 'hasGoogleLink' => false, 'requestsSent' => 0,  'reviewsCollected' => 0]],
] as [$plan, $state]) {
    $steps = Onboarding::steps($state + ['plan' => $plan]);
    $prog  = Onboarding::progress($steps);

    foreach ($steps as $step) {
        ok("{$plan}/{$step['key']}: has a facts list", array_key_exists('facts', $step));
        ok("{$plan}/{$step['key']}: facts is a list", is_array($step['facts']));

        // Nothing to show for a step that is not finished. A chip on an
        // unfinished card would contradict the card it is sitting on.
        if (!$step['done']) {
            check("{$plan}/{$step['key']}: unfinished says nothing", $step['facts'], []);
        }

        foreach ($step['facts'] as $fact) {
            ok("{$plan}/{$step['key']}: the chip has words", trim((string) $fact) !== '');
            // Every digit in a chip must be a number we were handed.
            if (preg_match('/\d+/', (string) $fact, $m) === 1) {
                $n = (int) $m[0];
                $knows = in_array($n, [
                    (int) $state['requestsSent'],
                    (int) $state['reviewsCollected'],
                ], true);
                ok("{$plan}/{$step['key']}: '{$fact}' counts something real", $knows);
            }
        }
    }

    // The locked step must never carry a chip, whatever the state says: on
    // Free, hasGoogleLink is true here and the step is still not theirs.
    $google = null;
    foreach ($steps as $step) {
        if ($step['key'] === 'google') {
            $google = $step;
        }
    }
    if ($google !== null && $google['locked']) {
        check("{$plan}: the locked step shows no chip", $google['facts'], []);
    }

    // What the dashboard draws as cards, and what progress() counts, are the
    // same set. The heading says "Finish these N steps", the bar reads N, and
    // N boxes are on screen -- one filter, or they drift.
    $cards = array_values(array_filter(
        $steps,
        static fn (array $s): bool => $s['checkable'] && !$s['locked'],
    ));
    check("{$plan}: cards and the counter agree", count($cards), $prog['total']);
}

// Singular and plural, because "1 reviews in" is the kind of thing that stays
// on a screen for a year.
$one = Onboarding::steps(['plan' => 'free', 'hasPage' => true, 'requestsSent' => 1, 'reviewsCollected' => 1]);
$many = Onboarding::steps(['plan' => 'free', 'hasPage' => true, 'requestsSent' => 2, 'reviewsCollected' => 2]);
$factOf = static function (array $steps, string $key): array {
    foreach ($steps as $s) {
        if ($s['key'] === $key) {
            return $s['facts'];
        }
    }
    return [];
};
check('one review reads as one', $factOf($one, 'collect'), ['1 review in']);
check('two read as two',        $factOf($many, 'collect'), ['2 reviews in']);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
