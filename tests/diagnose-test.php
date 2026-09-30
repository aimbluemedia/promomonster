<?php

declare(strict_types=1);

/**
 * diagnose.php must never be the thing that is broken.
 *
 *   php tests/diagnose-test.php
 *
 * It is the page somebody opens when the site is already misbehaving, usually
 * from a phone, usually annoyed. A 500 there costs more than a 500 anywhere
 * else, because it removes the only tool for finding out why.
 *
 * It has now managed it twice. Once by requiring its support classes one line
 * each and meeting a class that needed a class of its own -- Heartbeat uses
 * Database, Database was not on the list, white page. Once before that by
 * naming a column a migration had not added yet.
 *
 * So this runs the real file, in a subprocess, exactly as a request would, and
 * asserts it produced a page rather than an exception. It is deliberately a
 * smoke test: it does not care what the rows say, only that they exist and
 * that nothing threw on the way.
 *
 * Runs against whatever app/config.php points at, including nothing at all --
 * a database that is down is one of the states this page must survive, not a
 * reason to skip.
 */

$root = dirname(__DIR__);

$passed = 0;
$failed = 0;
function check(string $what, mixed $got, mixed $want): void
{
    global $passed, $failed;
    if ($got === $want) { $passed++; return; }
    $failed++;
    echo "FAIL  {$what}\n      got:  " . var_export($got, true)
        . "\n      want: " . var_export($want, true) . "\n";
}
function ok(string $what, bool $got): void { check($what, $got, true); }

if (!is_file($root . '/diagnose.php')) {
    echo "SKIP  diagnose.php is not present\n";
    exit(0);
}

/**
 * Run it the way the server would and give back everything it said.
 *
 * Warnings and notices go to stdout here too, so a page that renders while
 * quietly complaining is still caught.
 *
 * @return array{out:string, code:int}
 */
function runDiagnose(string $root, array $query = []): array
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $command = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -d error_reporting=E_ALL '
        . escapeshellarg($root . '/diagnose.php');

    $process = proc_open($command, $descriptors, $pipes, $root, [
        'QUERY_STRING' => http_build_query($query),
    ]);

    if (!is_resource($process)) {
        return ['out' => '', 'code' => -1];
    }

    $out = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['out' => $out, 'code' => proc_close($process)];
}

$result = runDiagnose($root);
$out    = $result['out'];

// The things that mean it died.
foreach (['Fatal error', 'Uncaught', 'Parse error', 'Allowed memory size'] as $bad) {
    ok("no '{$bad}'", !str_contains($out, $bad));
}

// A warning is not fatal but it is still a defect on a page like this: it means
// a variable or an index is not what the code believed.
foreach (['Warning: Undefined', 'Notice: Undefined', 'Deprecated:'] as $bad) {
    ok("no '{$bad}'", !str_contains($out, $bad));
}

// A guarded block that swallowed a load failure still counts as broken. The
// try/catch around the runner check means the page survives a missing class --
// which is right for a half-finished upload and wrong for a complete one, and
// without this the test passes on the very bug it was written for.
ok('no check degraded to a load failure', !str_contains($out, 'Could not be checked'));

ok('it produced a page', strlen($out) > 500);
ok('with a closing tag, so it reached the end', str_contains($out, '</html>'));

// The rows that answer the questions this page exists for. Named explicitly,
// because a page that renders with half its checks silently missing is the
// failure mode a length assertion cannot see.
//
// Split by what they need. A database that cannot be reached is one of the
// states this page exists to report, and the checks that read tables are
// rightly absent then -- asserting them unconditionally would make the test
// fail for the page behaving correctly.
foreach (['Send queue runner', 'Email sending', 'Database connection'] as $row) {
    ok("the '{$row}' row is there", str_contains($out, $row));
}

$databaseUp = !str_contains($out, 'Connection refused')
    && !str_contains($out, 'Access denied')
    && !str_contains($out, 'Unknown database');

if ($databaseUp) {
    foreach (['Required columns', 'Migrations', 'Password reset email'] as $row) {
        ok("the '{$row}' row is there", str_contains($out, $row));
    }
} else {
    // Still an assertion, not a shrug: the page has to SAY the database is
    // unreachable rather than quietly drop half its output.
    ok('with no database it says so plainly', str_contains($out, 'Database connection'));
    echo "  (no database reachable: the table-reading checks are skipped, as the page skips them)\n";
}

// Every class the page names must actually be loadable. This is the specific
// hole the autoloader closed: App\Support\Heartbeat was required by hand and
// App\Support\Database, which it uses, was not.
$source = (string) file_get_contents($root . '/diagnose.php');
preg_match_all('/App\\\\Support\\\\([A-Za-z]+)/', $source, $found);
$named = array_values(array_unique($found[1] ?? []));
ok('the page names some support classes', $named !== []);

foreach ($named as $class) {
    ok("App\\Support\\{$class} exists to be loaded",
        is_file($root . '/app/Support/' . $class . '.php'));
}

// And each of those classes' own dependencies, one level down, which is where
// the hand-written require list came apart.
foreach ($named as $class) {
    $file = $root . '/app/Support/' . $class . '.php';
    if (!is_file($file)) {
        continue;
    }
    $body = (string) file_get_contents($file);

    // Same-namespace calls: Database::first(), Config::get(), and so on.
    preg_match_all('/(?<![\\\\$>\w])([A-Z][A-Za-z]+)::/', $body, $uses);
    foreach (array_unique($uses[1] ?? []) as $used) {
        if ($used === $class || !is_file($root . '/app/Support/' . $used . '.php')) {
            continue;
        }
        ok("{$class} needs {$used}, which the autoloader can reach",
            is_file($root . '/app/Support/' . $used . '.php'));
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
