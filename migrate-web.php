<?php

declare(strict_types=1);

/**
 * Browser-based migration runner, for hosting with no shell access.
 *
 *   1. Open this file and set MIGRATE_TOKEN to a long random string.
 *   2. Upload it into public_html.
 *   3. Visit  https://your-domain/migrate-web.php?token=YOUR-TOKEN
 *      — a GET only shows you what it would do. Nothing changes until you press
 *        the button, which POSTs.
 *   4. DELETE IT from the server afterwards.
 *
 * It will not replay migrations whose tables already exist, and it aborts
 * rather than dropping a table that has rows in it.
 */

const MIGRATE_TOKEN = '';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$here = __DIR__;
$base = is_file($here . '/app/bootstrap.php') ? $here
      : (is_file(dirname($here) . '/app/bootstrap.php') ? dirname($here) : null);

/** Renders a page and stops. */
function page(string $title, string $body, string $tone = 'info'): never
{
    $colour = ['info' => '#0475a3', 'good' => '#1a7f4b', 'bad' => '#b3261e'][$tone] ?? '#0475a3';
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . htmlspecialchars($title) . '</title>'
       . '<style>body{font:15px/1.6 system-ui,-apple-system,Segoe UI,sans-serif;margin:0;background:#f6f8fa;color:#1c2024}'
       . 'main{max-width:820px;margin:0 auto;padding:32px 20px}'
       . 'h1{font-size:22px;margin:0 0 4px;color:' . $colour . '}'
       . 'p.lede{margin:0 0 24px;color:#5b646e}'
       . 'table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #dfe3e8;border-radius:8px;overflow:hidden}'
       . 'td,th{padding:10px 12px;border-bottom:1px solid #eef1f4;text-align:left;vertical-align:top;font-size:14px}'
       . 'th{background:#f0f3f6;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#5b646e}'
       . 'tr:last-child td{border-bottom:0}'
       . 'code,pre{font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace}'
       . 'pre{white-space:pre-wrap;background:#fff;border:1px solid #dfe3e8;border-radius:8px;padding:12px}'
       . '.tag{display:inline-block;padding:2px 8px;border-radius:99px;font-size:12px;font-weight:600}'
       . '.apply{background:#e4f0f7;color:#0a5578}.baseline{background:#eceff2;color:#5b646e}'
       . '.skip{background:#f2f4f6;color:#8a929b}.applied{background:#e3f3ea;color:#166b40}'
       . '.fail{background:#fdeceb;color:#96201a}'
       . 'button{font:600 15px system-ui;background:#0475a3;color:#fff;border:0;border-radius:8px;padding:12px 22px;cursor:pointer;margin-top:20px}'
       . '.note{margin-top:24px;padding:12px 14px;background:#fff8e6;border:1px solid #f0dca8;border-radius:8px;font-size:14px}'
       . '</style><main><h1>' . htmlspecialchars($title) . '</h1>' . $body . '</main>';
    exit;
}

if (MIGRATE_TOKEN === '') {
    page('Set a token first',
        '<p class="lede">This runner is disabled until you give it a password.</p>'
        . '<p>Open <code>migrate-web.php</code>, change the line</p>'
        . '<pre>const MIGRATE_TOKEN = \'\';</pre>'
        . '<p>to a long random string, re-upload it, then visit this page with '
        . '<code>?token=</code> followed by that string.</p>', 'bad');
}

$supplied = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if (!hash_equals(MIGRATE_TOKEN, $supplied)) {
    http_response_code(404);
    page('Not found', '<p class="lede">No such page.</p>', 'bad');
}

if ($base === null) {
    page('Cannot find the application',
        '<p class="lede">Upload this file into <code>public_html</code>, alongside the '
        . '<code>app</code> folder.</p><p>Looked in <code>' . htmlspecialchars($here)
        . '</code> and <code>' . htmlspecialchars(dirname($here)) . '</code>.</p>', 'bad');
}

require $base . '/app/bootstrap.php';

use App\Support\Database;
use App\Support\Migrator;

try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    page('Cannot reach the database',
        '<p class="lede">Check the <code>db</code> settings in <code>app/config.php</code>.</p>'
        . '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>', 'bad');
}

$tokenField = '<input type="hidden" name="token" value="' . htmlspecialchars(MIGRATE_TOKEN) . '">';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $log = Migrator::run($pdo);
    } catch (Throwable $e) {
        page('Stopped', '<p class="lede">Nothing further was changed.</p><pre>'
            . htmlspecialchars($e->getMessage()) . '</pre>', 'bad');
    }

    $failed = false;
    $rows = '';
    foreach ($log as $entry) {
        $failed = $failed || $entry['state'] === 'fail';
        $rows .= '<tr><td><code>' . htmlspecialchars($entry['file']) . '</code></td>'
              . '<td><span class="tag ' . htmlspecialchars($entry['state']) . '">'
              . htmlspecialchars($entry['state']) . '</span></td>'
              . '<td>' . nl2br(htmlspecialchars($entry['detail'])) . '</td></tr>';
    }

    page($failed ? 'A migration failed' : 'Database is up to date',
        '<p class="lede">' . ($failed
            ? 'The run stopped at the first failure. Later migrations were not attempted.'
            : 'Every migration is recorded as applied.') . '</p>'
        . '<table><tr><th>Migration</th><th>Result</th><th>Detail</th></tr>' . $rows . '</table>'
        . ($failed ? '' : '<div class="note"><strong>Now delete this file</strong> from '
            . '<code>public_html</code>, along with <code>diagnose.php</code>.</div>'),
        $failed ? 'bad' : 'good');
}

$plan = Migrator::plan($pdo);
$work = array_filter($plan, static fn (array $s) => $s['action'] !== 'skip');

$rows = '';
foreach ($plan as $step) {
    $rows .= '<tr><td><code>' . htmlspecialchars($step['file']) . '</code></td>'
          . '<td><span class="tag ' . htmlspecialchars($step['action']) . '">'
          . htmlspecialchars($step['action']) . '</span></td>'
          . '<td>' . htmlspecialchars($step['reason']) . '</td></tr>';
}

page('Migration plan',
    '<p class="lede">' . ($work === []
        ? 'Nothing to do — the database is already up to date.'
        : count($work) . ' migration(s) need attention. Nothing has changed yet.') . '</p>'
    . '<table><tr><th>Migration</th><th>Plan</th><th>Why</th></tr>' . $rows . '</table>'
    . '<p style="margin-top:20px;font-size:14px;color:#5b646e"><strong>apply</strong> runs the file. '
    . '<strong>baseline</strong> records it as done without running it, because its tables and '
    . 'columns are already there. <strong>skip</strong> is already recorded.</p>'
    . ($work === [] ? '' : '<form method="post">' . $tokenField
        . '<button type="submit">Run these migrations</button></form>'));
