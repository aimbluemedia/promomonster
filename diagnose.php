<?php

declare(strict_types=1);

/**
 * PromoMonster deployment diagnostic.
 *
 * Upload this ONE file into public_html and open it in a browser:
 *     https://your-domain/diagnose.php
 *
 * It reports what is actually on the server, so a 403 or 500 can be traced to
 * a cause instead of guessed at. It prints no passwords.
 *
 * DELETE IT once the site is working.
 */

header('Content-Type: text/html; charset=utf-8');

$checks = [];
$here = __DIR__;
$docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

function add(array &$checks, string $name, string $state, string $detail): void
{
    $checks[] = ['name' => $name, 'state' => $state, 'detail' => $detail];
}

// --- PHP ------------------------------------------------------------------
$phpOk = PHP_VERSION_ID >= 80100;
add($checks, 'PHP version', $phpOk ? 'pass' : 'fail',
    PHP_VERSION . ($phpOk ? '' : ' — PromoMonster needs 8.1 or newer. Change it in hPanel under Advanced → PHP Configuration.'));

foreach (['pdo_mysql' => 'Database access', 'mbstring' => 'Text handling', 'json' => 'JSON'] as $ext => $why) {
    add($checks, "Extension: {$ext}", extension_loaded($ext) ? 'pass' : 'fail',
        extension_loaded($ext) ? $why . ' available' : "Missing — enable {$ext} in hPanel → PHP Configuration.");
}

// --- Where are we? --------------------------------------------------------
add($checks, 'This file is at', 'info', $here);
add($checks, 'DOCUMENT_ROOT is', 'info', $docRoot !== '' ? $docRoot : '(not reported)');

$layout = 'unknown';
if (is_file($here . '/index.php') && is_dir($here . '/assets') && is_file($here . '/../app/bootstrap.php')) {
    $layout = 'public-as-docroot';
} elseif (is_dir($here . '/public') && is_dir($here . '/app')) {
    $layout = 'project-as-docroot';
}

add($checks, 'Detected layout',
    $layout === 'unknown' ? 'fail' : 'pass',
    match ($layout) {
        'public-as-docroot'  => 'Document root points at public/. This is the preferred layout.',
        'project-as-docroot' => 'Whole project is in the web root. Supported via the root .htaccess fallback, but pointing the domain at public/ is better.',
        default => 'Could not find the application. Neither public/index.php nor a sibling app/ directory is here — the files are probably in a subfolder, or the upload is incomplete.',
    });

// --- Files that must exist ------------------------------------------------
$base = $layout === 'project-as-docroot' ? $here : dirname($here);
$required = [
    'app/bootstrap.php'                        => 'Application bootstrap',
    'app/Support/Router.php'                   => 'Router',
    'app/Views/home.php'                       => 'Home page template',
    'public/index.php'                         => 'Front controller',
    'public/assets/css/app.css'                => 'Stylesheet',
    'database/migrations/001_create_waitlist.sql' => 'Phase 0 schema',
];
foreach ($required as $rel => $why) {
    $path = $base . '/' . $rel;
    add($checks, "File: {$rel}", is_file($path) ? 'pass' : 'fail',
        is_file($path) ? $why : "MISSING at {$path}");
}

// --- .htaccess (the usual culprit) ----------------------------------------
foreach ([
    'public/.htaccess' => 'Rewrites pretty URLs to the front controller',
    '.htaccess'        => 'Root fallback (only needed if the whole project is in the web root)',
] as $rel => $why) {
    $path = $base . '/' . $rel;
    $exists = is_file($path);
    $needed = $rel === 'public/.htaccess' || $layout === 'project-as-docroot';
    add($checks, "File: {$rel}", $exists ? 'pass' : ($needed ? 'fail' : 'info'),
        $exists ? $why
                : 'MISSING. FTP clients and file managers hide dotfiles by default — turn on "show hidden files" and upload it.');
}

// --- mod_rewrite ----------------------------------------------------------
$rewrite = null;
if (function_exists('apache_get_modules')) {
    $rewrite = in_array('mod_rewrite', apache_get_modules(), true);
}
add($checks, 'mod_rewrite',
    $rewrite === true ? 'pass' : ($rewrite === false ? 'fail' : 'info'),
    match ($rewrite) {
        true  => 'Enabled — pretty URLs will work.',
        false => 'NOT enabled. Only / will load; every other page will 404.',
        default => 'Cannot detect from PHP (normal on LiteSpeed). Test by opening /business — if it 404s, rewrites are off.',
    });

// --- Permissions ----------------------------------------------------------
$unreadable = [];
foreach ([$base . '/app', $base . '/public', $base . '/public/assets'] as $dir) {
    if (is_dir($dir) && !is_readable($dir)) {
        $unreadable[] = $dir;
    }
}
add($checks, 'Directory permissions', $unreadable === [] ? 'pass' : 'fail',
    $unreadable === [] ? 'Readable.' : 'Not readable by PHP: ' . implode(', ', $unreadable) . ' — set directories to 755 and files to 644.');

// --- Hero image -----------------------------------------------------------
$heroRoots = array_unique(array_filter([
    $layout === 'project-as-docroot' ? $here . '/public' : $here,
    rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') ?: null,
]));
$heroFound = null;
$heroTried = [];
foreach (['hero.png', 'hero.jpg', 'hero.webp'] as $candidate) {
    foreach ($heroRoots as $root) {
        $path = $root . '/assets/img/' . $candidate;
        $heroTried[] = $path;
        if (is_file($path)) { $heroFound = $path; break 2; }
    }
}
add($checks, 'Hero image', $heroFound !== null ? 'pass' : 'info',
    $heroFound !== null
        ? 'Found at ' . $heroFound
        : 'Not found, so the placeholder is shown. Searched: ' . implode('  |  ', $heroTried)
          . ' — filenames are case-sensitive on Linux.');

// --- Config and database --------------------------------------------------
$configPath = $base . '/app/config.php';
if (!is_file($configPath)) {
    add($checks, 'app/config.php', 'fail',
        "MISSING. Copy app/config.example.php to app/config.php and fill in your database details.");
} else {
    add($checks, 'app/config.php', 'pass', 'Present.');
    $config = @require $configPath;
    if (!is_array($config) || !isset($config['db'])) {
        add($checks, 'Config contents', 'fail', 'config.php does not return an array with a "db" key.');
    } else {
        $db = $config['db'];
        add($checks, 'Database target', 'info',
            ($db['username'] ?? '?') . '@' . ($db['host'] ?? '?') . ' / ' . ($db['database'] ?? '?'));
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $db['host'] ?? 'localhost', (int) ($db['port'] ?? 3306), $db['database'] ?? '');
            $pdo = new PDO($dsn, (string) ($db['username'] ?? ''), (string) ($db['password'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            add($checks, 'Database connection', 'pass', 'Connected. Server ' . $pdo->query('SELECT VERSION()')->fetchColumn());

            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            add($checks, 'Tables', in_array('waitlist', $tables, true) ? 'pass' : 'fail',
                $tables === []
                    ? 'No tables. Import database/full-schema.sql through phpMyAdmin.'
                    : count($tables) . ' found: ' . implode(', ', array_slice($tables, 0, 25)));
        } catch (Throwable $e) {
            add($checks, 'Database connection', 'fail', $e->getMessage());
        }
    }
}

$failures = array_values(array_filter($checks, static fn($c) => $c['state'] === 'fail'));
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PromoMonster diagnostic</title>
<style>
 body{font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;margin:0;background:#faf9f6;color:#101418}
 .wrap{max-width:62rem;margin:0 auto;padding:2.5rem 1.25rem}
 h1{font-size:1.6rem;letter-spacing:-.02em;margin:0 0 .25rem}
 .sub{color:#56606d;margin:0 0 2rem}
 table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e3e1da;border-radius:12px;overflow:hidden}
 th,td{text-align:left;padding:.7rem .9rem;border-bottom:1px solid #e3e1da;vertical-align:top}
 th{font-size:.72rem;text-transform:uppercase;letter-spacing:.09em;color:#8a93a0}
 tr:last-child td{border-bottom:0}
 .tag{display:inline-block;min-width:3.4rem;text-align:center;border-radius:6px;padding:.12rem .5rem;font-size:.72rem;font-weight:700}
 .pass{background:#e3f3ec;color:#0b6b5b}.fail{background:#fbe6da;color:#a34a12}.info{background:#eceaf0;color:#56606d}
 td.n{font-weight:600;white-space:nowrap}
 td.d{color:#56606d;word-break:break-word}
 .verdict{border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:2rem}
 .bad{background:#fbe6da;border:1px solid #eab999}.good{background:#e3f3ec;border:1px solid #a6d3c6}
 .verdict h2{margin:0 0 .4rem;font-size:1.05rem}
 .verdict ol,.verdict p{margin:.4rem 0 0}
 code{font-family:ui-monospace,Menlo,monospace;background:#f2f1ec;padding:.08rem .3rem;border-radius:4px;font-size:.86em}
 .warn{margin-top:2rem;color:#a34a12;font-weight:600}
</style></head><body><div class="wrap">
<h1>PromoMonster deployment diagnostic</h1>
<p class="sub"><?= date('Y-m-d H:i:s') ?></p>

<?php if ($failures === []): ?>
  <div class="verdict good">
    <h2>Everything checks out.</h2>
    <p>If the site still shows 403, the document root is pointing somewhere other than these files — check the domain's folder in hPanel.</p>
  </div>
<?php else: ?>
  <div class="verdict bad">
    <h2><?= count($failures) ?> problem<?= count($failures) === 1 ? '' : 's' ?> found</h2>
    <ol>
      <?php foreach ($failures as $f): ?>
        <li><strong><?= htmlspecialchars($f['name'], ENT_QUOTES) ?></strong> — <?= htmlspecialchars($f['detail'], ENT_QUOTES) ?></li>
      <?php endforeach; ?>
    </ol>
  </div>
<?php endif; ?>

<table>
  <tr><th>Check</th><th>Result</th><th>Detail</th></tr>
  <?php foreach ($checks as $c): ?>
    <tr>
      <td class="n"><?= htmlspecialchars($c['name'], ENT_QUOTES) ?></td>
      <td><span class="tag <?= $c['state'] ?>"><?= strtoupper($c['state']) ?></span></td>
      <td class="d"><?= htmlspecialchars($c['detail'], ENT_QUOTES) ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<p class="warn">Delete diagnose.php from the server once the site is working.</p>
</div></body></html>
