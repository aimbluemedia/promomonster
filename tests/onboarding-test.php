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

[$s, ] = run(['plan' => Plans::PRO, 'hasPage' => true, 'hasGoogleLink' => true,
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
check('and names the plan that would do it', $g['needs'], Plans::PRO);

// The step still SHOWS. Hiding it would leave somebody wondering why the
// Google page in the nav does nothing.
ok('but it is still listed', step($free, 'google') !== []);

check('Free counts three steps, not four', $freeProgress['total'], 3);
check('Pro counts four',                   Onboarding::progress(Onboarding::steps(FRESH_PRO))['total'], 4);

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
// The bar
// =====================================================================

[, $p] = run(FRESH_PRO);
check('one of four is 25%', $p['percent'], 25);
check('and not complete',   $p['complete'], false);

[, $p] = run(['plan' => Plans::PRO, 'hasPage' => true, 'hasGoogleLink' => true,
              'requestsSent' => 1, 'reviewsCollected' => 0]);
check('three of four is 75%', $p['percent'], 75);

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

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
