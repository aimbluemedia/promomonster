<?php

declare(strict_types=1);

/**
 * The seeded playbooks, read out of the migration itself.
 *
 * Parsed rather than queried, deliberately: the thing worth catching is drift
 * between App\Support\Trades and the seed, and that is visible in the file
 * without a database. A trade with no playbook row is an account that sets its
 * trade and still sees an empty page -- which is the exact failure this seed
 * was written to end, so it is the one worth a test.
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

$path = dirname(__DIR__) . '/database/migrations/026_seed_playbooks.sql';
$sql  = (string) file_get_contents($path);

ok('the seed exists', $sql !== '');

// --- phpMyAdmin safety, the same rules every migration here follows ------
foreach (explode("\n", $sql) as $i => $line) {
    if (str_starts_with(trim($line), '--')) {
        continue;
    }
    ok('line ' . ($i + 1) . ' has balanced quotes', substr_count($line, "'") % 2 === 0);
}
check('ASCII only', preg_match('/[^\x00-\x7F]/', $sql), 0);

// Every check from here about what the file SAYS reads the statements only.
// Three of them first failed against this file's own header comments -- the
// one warning against inventing a percentage contains a percentage, and the
// one explaining why this is not an upsert contains the words "ON DUPLICATE
// KEY UPDATE". A test that cannot tell a rule from an example of breaking it
// is worse than no test.
$body = (string) preg_replace('/^\s*--.*$/m', '', $sql);

// Safe to paste twice, and must never clobber wording somebody has tuned --
// migration 009 says these rows are data so they CAN be edited.
ok('inserts without overwriting edits', str_contains($sql, 'INSERT IGNORE INTO playbooks'));
ok('does not overwrite on duplicate',  !str_contains(strtoupper($body), 'ON DUPLICATE KEY UPDATE'));

// --- Pull the rows out ---------------------------------------------------
preg_match('/VALUES\s*(.+?);/s', $body, $m);
ok('the VALUES block parses', isset($m[1]));

/** @var list<list<string>> $rows */
$rows = [];
foreach (explode("),\n(", trim((string) ($m[1] ?? ''), "();\n")) as $tuple) {
    // Split on commas that sit between quoted values, leaving quoted commas be.
    preg_match_all("/'((?:[^']|'')*)'|(\d+)|NULL/", $tuple, $vals, PREG_SET_ORDER);
    $rows[] = array_map(
        // $v[0] decides, not $v[1]. A group that did not take part comes back
        // as an empty STRING, not absent, so `$v[1] ?? $v[2]` quietly read
        // every number as "" and the whole follow_up_days column vanished.
        static fn (array $v): string => str_starts_with($v[0], "'")
            ? str_replace("''", "'", $v[1])
            : trim($v[0]),
        $vals,
    );
}

check('one row per trade', count($rows), count(Trades::ALL));

// --- Every trade has one, and nothing has one that is not a trade --------
$seeded = array_column($rows, 0);

foreach (array_keys(Trades::ALL) as $key) {
    ok("{$key} has a playbook", in_array($key, $seeded, true));
}
foreach ($seeded as $key) {
    ok("{$key} is a real trade", Trades::has($key));
}
check('no trade seeded twice', count(array_unique($seeded)), count($seeded));

// --- What each row says --------------------------------------------------
foreach ($rows as $row) {
    $trade = $row[0];

    // vertical, display_name, trigger_moment, who_asks, primary_channel,
    // verbal_ask, follow_up_days, offline_method, objection
    check("{$trade}: has every column", count($row), 9);

    [$v, $name, $when, $who, $channel, $say, $days, $offline, $objection] = $row;

    // The migration is ASCII by house rule; the PHP label is not ("cafe"
    // carries an accent in Trades). Both are right in their own file, so this
    // folds the accent rather than forcing either one to be wrong. It still
    // catches the drift it is here for: a renamed trade.
    $fold = static fn (string $t): string => strtr($t, ['é' => 'e', 'è' => 'e', 'ü' => 'u']);
    ok("{$trade}: the name matches the trade list", $fold($name) === $fold(Trades::label($v)));
    foreach (['when' => $when, 'who' => $who, 'say' => $say,
              'offline' => $offline, 'objection' => $objection] as $field => $value) {
        ok("{$trade}: {$field} says something", trim($value) !== '');
    }

    // The ENUM allows sms and qr. Neither is built: SMS needs a carrier
    // registration billed per business and was cut from the plans for it, and
    // nothing in this product makes a QR code. A playbook telling somebody to
    // send an SMS through software that cannot send one is the lie this seed
    // exists to remove.
    check("{$trade}: asks by email", $channel, 'email');

    // TINYINT, and advice rather than automation -- but a chase a fortnight
    // later is not a chase, it is a cold email.
    ok("{$trade}: the chase is within a week",
        ctype_digit($days) && (int) $days >= 1 && (int) $days <= 7);

    // An objection is written as the objection, then what to do about it.
    ok("{$trade}: the objection has an answer", str_contains($objection, '--'));
}

// --- Nothing anybody has to take on trust -------------------------------
//
// A playbook invites exactly the sentence this product must not print: "asking
// at the door lifts response 40%". Nothing here can source a figure like that,
// and a business that reorganises its day around an invented one has been
// misled by us rather than by the internet.
ok('no percentages', preg_match('/\d+\s?%/', $body) === 0);
ok('no "x times more"', preg_match('/\b\d+\s?(times|x)\s+(more|likely|higher)/i', $body) === 0);
ok('no "studies show"', stripos($body, 'studies show') === false);
ok('no "research shows"', stripos($body, 'research shows') === false);

// --- The two regulated trades say to go and look ------------------------
//
// Dental and legal advertising rules differ by country and by state. A
// confident wrong summary here is worse for the member than being told to
// check, so the notes point at the regulator rather than stating the rule.
foreach (['dental', 'legal'] as $regulated) {
    ok("{$regulated}: carries a compliance note",
        str_contains($sql, "WHERE vertical = '{$regulated}'"));
}
ok('the dental note names HIPAA',       stripos($sql, 'HIPAA') !== false);
ok('the dental note says to check',     preg_match('/Check your own regulator/i', $sql) === 1);
ok('the legal note says to check',      preg_match('/Check your own bar/i', $sql) === 1);
// Told to verify, never told what the rule is.
ok('neither note states the rule as settled',
    stripos($sql, 'the law says') === false && stripos($sql, 'you are required to') === false);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
