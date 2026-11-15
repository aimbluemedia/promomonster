<?php

declare(strict_types=1);

/**
 * The trade list.
 *
 * Small, and pinned anyway, because three things key off it: the public lead
 * form, the members picker, and the playbook lookup. The keys are also live
 * data -- they sit in locations.vertical and audits.vertical on real rows --
 * so renaming one orphans every account on it.
 */

require __DIR__ . '/../app/Support/Trades.php';

use App\Support\Trades;

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

// --- The keys are data, not labels --------------------------------------
// Every one of these is already written into live rows. A rename is not a
// rewording, it is an account whose playbook stops matching.
foreach (['landscaping', 'hvac', 'roofing', 'dental', 'veterinary', 'restaurant',
          'salon', 'auto', 'legal', 'realestate', 'other'] as $key) {
    ok("{$key} is still a trade", Trades::has($key));
}

check('nothing has been added unannounced', count(Trades::ALL), 11);

foreach (Trades::ALL as $key => $label) {
    // The column is VARCHAR(60) and the value is a lookup key, so it has to
    // stay short and plain.
    ok("{$key}: fits the column", strlen($key) <= 60);
    ok("{$key}: is a safe key", preg_match('/^[a-z][a-z0-9]*$/', $key) === 1);
    ok("{$key}: has a label", trim($label) !== '');
    ok("{$key}: the label is not the key", $label !== $key);
}

// --- Validation ----------------------------------------------------------
check('a known trade is kept',       Trades::clean('hvac'), 'hvac');
check('case does not matter',        Trades::clean('HVAC'), 'hvac');
check('whitespace does not matter',  Trades::clean('  roofing  '), 'roofing');

// Refused, not written. A free-typed value would match no playbook and read as
// the playbook being broken rather than the form being wrong.
check('an invented trade is refused', Trades::clean('time travel'), null);
check('an empty trade is refused',    Trades::clean(''), null);
check('spaces alone are refused',     Trades::clean('   '), null);
// The label is not an accepted input; only the key is.
check('the label is not a key',       Trades::clean('Roofing / exteriors'), null);

// --- Labels --------------------------------------------------------------
check('a known key reads as its label', Trades::label('auto'), 'Auto repair');
// Rows predating this list, and anything staff typed during onboarding, must
// still read as something rather than vanishing from the member's dashboard.
check('an unknown value reads as itself', Trades::label('mobile locksmith'), 'mobile locksmith');
check('an empty value reads as empty',    Trades::label(''), '');

// "Something else" is a real option on purpose: a business that does not fit
// should be able to say so once, not be asked again on every screen.
ok('there is an option for not fitting', Trades::has('other'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
