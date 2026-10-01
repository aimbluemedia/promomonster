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

// Hand off to the support dump, before a byte of HTML is written.
//
// support.php cannot be opened directly in this layout: the root .htaccess
// rewrites every unrecognised root file into public/, and its exemption list
// named three tools by hand. A fourth was added without being added to it, so
// the URL answered with the site's own 404 page. The .htaccess is fixed, but
// that is a dotfile, and asking somebody to upload a hidden file to reach the
// tool that diagnoses their upload problems is a poor trade.
//
// Requiring it is a filesystem read, not a request, so no rewrite rule is
// involved and this works the moment the file is on disk -- reachable through
// diagnose.php, which is already exempt and already uploaded.
if (isset($_GET['support'])) {
    $dump = __DIR__ . '/support.php';
    if (is_file($dump)) {
        require $dump;
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo "support.php is not on the server yet. Upload it beside this file and reload.\n";
    exit;
}

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

/**
 * Load App\ classes on demand, rather than naming each one by hand.
 *
 * This page used to require the handful of classes it touches, one line each.
 * That works until one of those classes needs a class of its own: Heartbeat
 * uses Database, Database was not on the list, and the page whose entire job is
 * to work when the site does not became a 500 with no output at all. A hand
 * list only ever covers what somebody remembered.
 *
 * Deliberately not app/bootstrap.php, which loads the config and dies if it is
 * missing -- that is one of the things this page exists to report on.
 */
spl_autoload_register(static function (string $class) use ($base): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = $base . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
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

// --- The deny rules that keep secrets off the web -------------------------
//
// app/, storage/ and database/ each ship an .htaccess that refuses every
// request. They only matter when those folders are inside the web root -- which
// is this installation's layout -- and they are dotfiles, which FTP clients and
// file managers hide and therefore skip by default. Uploading "the app folder"
// and silently leaving its .htaccess behind is the ordinary way this goes
// wrong, and nothing was checking for it.
//
// What each one is holding back is worth stating plainly, because "a missing
// .htaccess" does not sound like anything:
$denyDirs = [
    'app'      => 'app/config.php, which holds your database password and your mailbox password',
    'storage'  => 'storage/logs/mail.log, which holds live password-reset links and customer addresses',
    'database' => 'the migration files, which map out the whole schema',
];

$undefended = [];
foreach ($denyDirs as $dir => $holds) {
    if (is_dir($base . '/' . $dir) && !is_file($base . '/' . $dir . '/.htaccess')) {
        $undefended[$dir] = $holds;
    }
}

if ($undefended === []) {
    add($checks, 'Sensitive folders', 'pass',
        'app/, storage/ and database/ each have the .htaccess that refuses web requests.');
} elseif ($layout !== 'public-as-docroot') {
    // Anything but the safe layout, including 'unknown': if we cannot prove the
    // folders are outside the web root, assume they are inside it.
    add($checks, 'Sensitive folders', 'fail',
        'EXPOSED: ' . implode(', ', array_map(
            static fn (string $d, string $h): string => $d . '/.htaccess is missing, which was denying ' . $h,
            array_keys($undefended),
            $undefended,
        ))
        . '. These folders sit inside the web root on this server, so without those files anyone '
        . 'can fetch them over HTTP. Turn on "show hidden files" in File Manager and upload them. '
        . 'Test one: open https://' . (string) ($_SERVER['HTTP_HOST'] ?? 'your-domain')
        . '/storage/logs/mail.log — you should get 403 or 404, never a page of email.');
} else {
    add($checks, 'Sensitive folders', 'todo',
        'Missing .htaccess in: ' . implode(', ', array_keys($undefended))
        . '. The document root points at public/ on this server, so these folders are outside it '
        . 'and nothing is exposed today. '
        . 'Upload them anyway: the layout is one hPanel setting away from changing.');
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
    $here,
    $here . '/public',
    rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') ?: null,
]));

// Listing what IS there beats listing what was looked for: it catches 'hero,png',
// 'Hero.png' and 'hero.png.jpg' at a glance, which guessing at paths never does.
$heroFound = null;
$heroDirs = [];
foreach ($heroRoots as $root) {
    $dir = $root . '/assets/img';
    if (!is_dir($dir)) {
        $heroDirs[] = $dir . '  →  no such folder';
        continue;
    }

    $names = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
    $heroDirs[] = $dir . '  →  ' . ($names === [] ? 'empty' : implode(', ', $names));

    foreach ($names as $name) {
        if ($heroFound === null
            && preg_match('/^hero\.(png|jpe?g|webp)$/', $name)
            && is_file($dir . '/' . $name)) {
            $heroFound = $dir . '/' . $name;
        }
    }
}

add($checks, 'Hero image', $heroFound !== null ? 'pass' : 'fail',
    $heroFound !== null
        ? 'Found at ' . $heroFound . ' — served as /assets/img/' . basename($heroFound)
        : 'No hero.png / .jpg / .webp found, so the placeholder shows instead. '
          . 'Filenames are case-sensitive on Linux, and the extension must be a dot, not a comma. '
          . 'What is actually in each image folder: ' . implode('   |   ', $heroDirs));

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

            // --- Pending migrations -------------------------------------
            // The usual cause of "login works, next page is a 500": a column
            // the app selects has not been added yet.
            $files = glob($base . '/database/migrations/*.sql') ?: [];
            sort($files);
            $names = array_map('basename', $files);

            $applied = [];
            if (in_array('migrations', $tables, true)) {
                $applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
            }
            $pending = array_values(array_diff($names, $applied));

            if ($names === []) {
                add($checks, 'Migrations', 'info',
                    'No migration files found at ' . $base . '/database/migrations — upload that folder to check.');
            } elseif (!in_array('migrations', $tables, true)) {
                add($checks, 'Migrations', 'fail',
                    'No migrations table, so nothing has been tracked. Set a token in migrate-web.php, '
                    . 'upload it and open /migrate-web.php?token=... — it records what is already here '
                    . 'instead of replaying it. Do NOT paste the migration files in by hand: '
                    . '006_drop_panel_schema.sql drops the users table.');
            } elseif ($pending !== []) {
                add($checks, 'Migrations', 'fail',
                    count($pending) . ' NOT applied: ' . implode(', ', $pending)
                    . ' — run /migrate-web.php?token=... to apply them. This is the usual cause of a '
                    . '500 after signing in.');
            } else {
                add($checks, 'Migrations', 'pass', count($applied) . ' applied, none pending.');
            }

            // --- Columns the app selects --------------------------------
            // Every column any page SELECTs, with the migration that adds it,
            // so a missing one names its own fix.
            //
            // This list went stale once: it stopped at migration 014 while the
            // app moved on to 017, so it reported "All present" while the
            // Review scores page was returning a 500 on a column it was not
            // watching. Whenever a migration adds a column the app reads, it
            // belongs here in the same commit.
            $required = [
                'users.is_admin'                   => '013',
                'users.must_change_password'       => '014',
                'users.password_changed_at'        => '014',
                'audits.status'                    => '013',
                'audits.notes'                     => '013',
                'audits.handled_by_user_id'        => '013',
                'login_attempts.email'             => '013',
                'accounts.plan'                    => '015',
                'accounts.requested_plan'          => '015',
                'accounts.requested_plan_at'       => '015',
                'accounts.plan_changed_at'         => '015',
                'accounts.signup_ip'               => '015',
                'audits.source'                    => '016',
                'audits.results_generated_at'      => '016',
                'audits.website'                   => '017',
                'audits.score'                     => '017',
                'locations.reply_to_email'         => '018',
                'review_requests.attempts'         => '018',
                'review_requests.provider_ref'     => '018',
                'review_requests.sent_subject'     => '018',
                'review_requests.sent_body'        => '018',
                'password_resets.token_hash'       => '019',
                'accounts.public_slug'             => '020 or 022',
                'hosted_reviews.source_url'        => '020 or 022',
                // The one that got away. 020 was already recorded as applied
                // when this column was added to it, and the runner never
                // replays a recorded name -- so the table existed, the code
                // wrote the column, and adding a review was a 500. 022 exists
                // to add it to a database in that state, and this row is what
                // would have named it in seconds.
                'hosted_reviews.author_city'       => '020 or 022',
                'templates.is_default'             => '021',
                'templates.updated_at'             => '021',
            ];

            $columnCache = [];
            $missing = [];
            $blame = [];
            foreach ($required as $path => $migration) {
                [$table, $column] = explode('.', $path, 2);

                if (!in_array($table, $tables, true)) {
                    $missing[] = $table . ' (whole table missing)';
                    $blame[$migration] = true;
                    continue;
                }
                if (!isset($columnCache[$table])) {
                    $columnCache[$table] = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')
                        ->fetchAll(PDO::FETCH_COLUMN);
                }
                if (!in_array($column, $columnCache[$table], true)) {
                    $missing[] = $path;
                    $blame[$migration] = true;
                }
            }

            add($checks, 'Required columns', $missing === [] ? 'pass' : 'fail',
                $missing === []
                    ? count($required) . ' columns checked, all present.'
                    // Comma-joined rather than "and": one of these entries is
                    // itself "020 or 022", and "020 or 022 and 021" parses as
                    // nothing anybody would want to read at this moment.
                    : 'MISSING: ' . implode(', ', $missing)
                      . ' — added by migration ' . implode(', ', array_keys($blame))
                      . '. Pages that read them return a 500 until it is applied. '
                      . 'If the migrations row above says none are pending, the file was '
                      . 'edited after it ran: the runner records a name and never replays '
                      . 'it, so paste that migration into phpMyAdmin by hand. Every one of '
                      . 'them is guarded and safe to run twice.');
        } catch (Throwable $e) {
            add($checks, 'Database connection', 'fail', $e->getMessage());
        }
    }
}

// --- Claude / competitor comparison ----------------------------------------
// Three separate things can be wrong here and they need different fixes: the
// code is not uploaded, the key is not in config.php, or the key is wrong.
$claudeFiles = [
    'app/Support/Claude.php'           => 'API client',
    'app/Support/ReviewComparison.php' => 'Comparison engine',
    'app/Views/superadmin/audit.php'   => 'Audit screen',
];
$missingClaude = [];
foreach ($claudeFiles as $rel => $what) {
    if (!is_file($base . '/' . $rel)) {
        $missingClaude[] = $rel;
    }
}

if ($missingClaude !== []) {
    add($checks, 'Competitor comparison', 'fail',
        'NOT UPLOADED. Missing: ' . implode(', ', $missingClaude)
        . ' — upload the app/ folder again. Saving the API key alone does nothing '
        . 'until these files are on the server.');
} else {
    $apiKey = '';
    if (isset($config) && is_array($config)) {
        $apiKey = trim((string) ($config['anthropic']['api_key'] ?? ''));
    }

    if ($apiKey === '') {
        // Not switched on rather than broken. This feature is optional and
        // costs money to run, so never configuring it is a decision, not a
        // fault -- and listing a decision under "problems found" is how a
        // report trains you to stop reading it.
        add($checks, 'Competitor comparison', 'todo',
            "No 'anthropic' => ['api_key' => '...'] entry in app/config.php, so the competitor "
            . 'comparison stays switched off. Nothing else depends on it, and it is the only '
            . 'feature here that costs money per use.');
    } elseif (!extension_loaded('curl')) {
        add($checks, 'Competitor comparison', 'fail',
            'A key is set, but the curl PHP extension is off. Enable it in hPanel → PHP Configuration.');
    } else {
        add($checks, 'Competitor comparison', 'pass',
            'Code uploaded and a key is configured (' . strlen($apiKey) . ' characters, '
            . 'ending ' . substr($apiKey, -4) . '). Model: '
            . (string) ($config['anthropic']['model'] ?? 'claude-sonnet-5 (default)')
            . '. Add ?claude=1 to this URL to spend about $0.001 checking the key works.');

        // Opt-in, because every run of this costs money. Tiny and cheap.
        if (isset($_GET['claude'])) {
            $payload = json_encode([
                'model'      => (string) ($config['anthropic']['model'] ?? 'claude-sonnet-5'),
                'max_tokens' => 16,
                'messages'   => [['role' => 'user', 'content' => 'Reply with the single word: ready']],
            ]);
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_HTTPHEADER     => [
                    'content-type: application/json',
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                ],
                CURLOPT_POSTFIELDS => $payload,
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                add($checks, 'Claude live check', 'fail',
                    'Could not reach api.anthropic.com: ' . $curlErr
                    . ' — Hostinger may be blocking outbound HTTPS on this plan.');
            } elseif ($code === 401) {
                add($checks, 'Claude live check', 'fail',
                    'The key was rejected (401). Check for a stray space or a truncated paste.');
            } elseif ($code === 200) {
                add($checks, 'Claude live check', 'pass',
                    'Claude answered. The comparison tool is ready to use.');
            } else {
                $body = json_decode((string) $raw, true);
                add($checks, 'Claude live check', 'fail',
                    'HTTP ' . $code . ': ' . ($body['error']['message'] ?? 'no detail given'));
            }
        }
    }
}

// --- Email sending --------------------------------------------------------
// Four separate things have to be true, and "it is not working" gives no clue
// which one is missing. Each gets its own line, so the answer is read rather
// than deduced.
$mailFiles = [];
foreach ([
    'app/Support/Mailer.php',
    'app/Support/Tokens.php',
    'app/Support/ReviewRequests.php',
    'app/Support/ReviewLink.php',
    'bin/send-due.php',
] as $needed) {
    if (!is_file($base . '/' . $needed)) {
        $mailFiles[] = $needed;
    }
}

$mailCfg   = (isset($config) && is_array($config)) ? ($config['mail'] ?? []) : [];
$mailToken = trim((string) ($mailCfg['token'] ?? ''));
$mailFrom  = trim((string) ($mailCfg['from'] ?? ''));
$loginFrom = trim((string) ($mailCfg['transactional_from'] ?? ''));
$appKey    = (isset($config) && is_array($config)) ? trim((string) ($config['app_key'] ?? '')) : '';
// Asked of Mailer rather than worked out here. This block used to re-derive the
// rule from the config keys, which is exactly the drift the comment further down
// warns about: the bulk lane learned to send through a mailbox and this page
// carried on reporting "no mail.token is set, nothing is being delivered" at a
// server that was delivering. One source for the decision, two readers.
if ($mailFiles === []) {
    require_once $base . '/app/Support/Config.php';
    require_once $base . '/app/Support/Mailer.php';
    require_once $base . '/app/Support/Smtp.php';
    App\Support\Config::load(is_array($config) ? $config : []);
    $driver = App\Support\Mailer::driver();
} else {
    $driver = trim((string) ($mailCfg['driver'] ?? '')) ?: ($mailToken === '' ? 'log' : 'postmark');
}

if ($mailFiles !== []) {
    add($checks, 'Email sending', 'fail',
        'NOT UPLOADED. Missing: ' . implode(', ', $mailFiles)
        . ' — upload the app/ and bin/ folders again.');
} else {
    // 1. The signing key. Without it every send fails when it tries to build
    //    the unsubscribe link, which is a confusing place to discover it.
    if ($appKey === '') {
        // Generated here, on this server, by this server's PHP. Telling
        // somebody on shared hosting to run a command line is telling them to
        // use a shell they do not have -- and the alternative most people
        // reach for is a random string typed by hand, or one pasted from a
        // website, neither of which is a key. A fresh one every page load, so
        // this page cannot be used to learn which one was taken.
        add($checks, 'Signing key (app_key)', 'fail',
            "app/config.php has no 'app_key'. Unsubscribe links cannot be signed without one, "
            . 'so every review request will fail to send. It goes at the TOP level of the file, '
            . 'next to app_name -- not inside the mail section. Copy this line in, once, and '
            . 'never change it: '
            . "'app_key' => '" . bin2hex(random_bytes(32)) . "',");
    } elseif (strlen($appKey) < 32) {
        add($checks, 'Signing key (app_key)', 'fail',
            'app_key is only ' . strlen($appKey) . ' characters. Use at least 32 — '
            . 'this signs links that stop us emailing someone who asked us not to.');
    } else {
        add($checks, 'Signing key (app_key)', 'pass',
            strlen($appKey) . ' characters. Never change it: every unsubscribe link '
            . 'already sitting in an inbox is signed with this one.');
    }

    // 2. The provider, in Mailer's own words and with the same verdict the
    //    Google reviews page reaches from the same function.
    //    A driver name that is not one of the four is a typo in config.php and
    //    fails every send, so it is a failure rather than something still to do.
    $bulkLive = App\Support\Mailer::isLive();
    $bulkState = match (true) {
        $bulkLive => 'pass',
        in_array($driver, ['postmark', 'smtp', 'log', 'null'], true) => 'todo',
        default => 'fail',
    };
    add($checks, 'Email sending', $bulkState,
        App\Support\Mailer::status()
        . ' So /members/reviews '
        . ($bulkLive
            ? 'sends review requests -- as long as the scheduled job below is running. '
              . 'Use the test-send form on this page to put a real message through it.'
            : 'shows "Email sending is not switched on yet" and queues them instead.'));

    // 3. The from address, which has to be on a domain verified with the
    //    provider or every send is rejected.
    if ($mailFrom === '' || filter_var($mailFrom, FILTER_VALIDATE_EMAIL) === false) {
        // Only fatal once the bulk lane can actually send. Before Postmark is
        // connected, review requests are not going out for a reason this row
        // has nothing to do with, and reporting it as a failure puts a problem
        // on the list that cannot be fixed into a working state yet -- which
        // trains you to skim the list.
        add($checks, 'From address', $driver === 'postmark' ? 'fail' : 'todo',
            'mail.from is missing or not an address'
            . ($driver === 'postmark'
                ? ', so every review request will be rejected. Set it to something on a domain '
                  . 'you have verified with Postmark.'
                : '. This one is only used for review requests, which cannot send yet anyway '
                  . '(no mail.token). Set it when you connect Postmark; password resets do not '
                  . 'use it.'));
    } else {
        $domain = substr($mailFrom, strpos($mailFrom, '@') + 1);
        add($checks, 'From address', 'pass',
            $mailFrom . ' — this exact domain (' . $domain . ') must be verified in '
            . 'Postmark, with its DKIM and Return-Path records added to your DNS, '
            . 'or every send is rejected.');
    }

    // 2b. Are the password-reset files on the server, and current?
    //
    //     This is the check whose absence cost the most. Every other row here
    //     reads app/config.php and Mailer, so a server holding an OLD copy of
    //     PasswordReset.php reports a perfect setup: the notice goes away, the
    //     lane resolves to SMTP, the test send passes -- and the actual reset
    //     still goes out through whatever the old file asked for, which is the
    //     bulk driver, which with no Postmark token is the log driver. Written
    //     to a file, reported as success, no error anywhere, no email.
    //
    //     Uploading one file at a time is the normal way to deploy here, so
    //     "some of these are from last week" is the normal failure, not an
    //     exotic one. The marker is a string that only exists in the current
    //     version of each file: cruder than a checksum and it needs no
    //     manifest to go stale in its own right.
    $resetFiles = [
        'app/Support/PasswordReset.php'            => 'transactionalDriver',
        'app/Support/Mailer.php'                   => 'transactionalIsLive',
        'app/Support/Smtp.php'                     => 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT',
        'app/Support/ErrorHandler.php'             => 'function note',
        'app/Support/Auth.php'                     => 'setTemporaryPassword',
        'app/Controllers/PasswordResetController.php' => 'transactionalIsLive',
        'app/Views/members/forgot.php'             => 'ready',
        'app/Views/members/reset.php'              => 'data-eye',
    ];

    $missingReset = [];
    $staleReset   = [];
    foreach ($resetFiles as $rel => $marker) {
        $full = $base . '/' . $rel;
        if (!is_file($full)) {
            $missingReset[] = $rel;
        } elseif (!str_contains((string) @file_get_contents($full), $marker)) {
            $staleReset[] = $rel;
        }
    }

    if ($missingReset !== [] || $staleReset !== []) {
        add($checks, 'Password reset files', 'fail',
            ($missingReset !== [] ? 'NOT UPLOADED: ' . implode(', ', $missingReset) . '. ' : '')
            . ($staleReset !== [] ? 'OUT OF DATE (an older copy is on the server): '
                . implode(', ', $staleReset) . '. ' : '')
            . 'Everything else on this page can pass while these are wrong, because the rest '
            . 'reads your config rather than these files -- an old PasswordReset.php sends '
            . 'through the bulk driver instead of your mailbox, which writes the email to '
            . 'storage/logs/mail.log and reports success. Upload the whole app/ folder again.');
    } else {
        add($checks, 'Password reset files', 'pass',
            count($resetFiles) . ' files present and current.');
    }

    // 3. Does the forgot-password page think it can send?
    //
    //    Its own words, from the same function the page calls, because "that
    //    notice is still showing" is a question about a decision and not about
    //    a setting. Re-deriving the rule here is how a diagnostic ends up
    //    confidently disagreeing with the page it is diagnosing.
    require_once $base . '/app/Support/Config.php';
    require_once $base . '/app/Support/Mailer.php';
    require_once $base . '/app/Support/Smtp.php';
    App\Support\Config::load(is_array($config) ? $config : []);

    // --- Is the scheduled job running? ---------------------------------
    // The one question no configuration check can answer, and the one that
    // decides whether anything ever leaves. Measured from the runner's own
    // rows, so it reports what happened rather than what was intended.
    try {
    if (!App\Support\Heartbeat::ready()) {
        add($checks, 'Send queue runner', 'todo',
            'Not tracked yet: migration 024 adds the cron_runs table the runner writes to. '
            . 'Until it is applied there is no way to tell a cron job that was never created '
            . 'from one that is about to fire.');
    } else {
        $beat = App\Support\Heartbeat::status(App\Support\Heartbeat::SEND_QUEUE);
        $every = App\Support\Heartbeat::inWords($beat['every_seconds']);
        $ago   = App\Support\Heartbeat::inWords($beat['ago_seconds']);

        if ($beat['state'] === 'never') {
            // Everything needed to create the job, with this server's own
            // paths filled in, so none of it has to be matched up by eye.
            // The log line is the important half: if cron fires at all, even
            // with the wrong PHP binary, the shell's error lands in that file
            // and names the problem outright.
            $logDirOk = is_dir($base . '/storage/logs') && is_writable($base . '/storage/logs');

            add($checks, 'Send queue runner', 'fail',
                'HAS NEVER RUN. A request now sends on the click, so this is not what stands '
                . 'between pressing Send and an email arriving -- it is the reminder three days '
                . 'later, and the retry for anything that failed first time. Both are off until '
                . 'it runs. In hPanel: Advanced -> Cron Jobs, '
                . 'every 5 minutes, with this as the command: '
                . '/usr/bin/php ' . $base . '/bin/send-due.php >> '
                . $base . '/storage/logs/cron.log 2>&1'
                . '  ||  If hPanel rejects /usr/bin/php, try the PHP selector it offers, or '
                . 'php instead of the full path. This server reports its own binary as '
                . PHP_BINARY . ' (that is the web one, so the CLI path may differ).'
                . '  ||  Once the job exists, read ' . $base . '/storage/logs/cron.log: '
                . 'content means it ran, an empty or absent file means it did not fire at all.'
                . ($logDirOk
                    ? ''
                    : '  ||  WARNING: ' . $base . '/storage/logs is missing or not writable, '
                      . 'so the >> redirect will fail and you will get no log to read. '
                      . 'Create it and make it writable first.'));
        } elseif ($beat['state'] === 'late') {
            add($checks, 'Send queue runner', 'fail',
                'STOPPED. Last run ' . $beat['last_at'] . ' (' . $ago . ' ago)'
                . ($beat['every_seconds'] === null ? '' : ', having run about every ' . $every)
                . '. Check the cron job still exists in hPanel.');
        } else {
            add($checks, 'Send queue runner', 'pass',
                'Running about every ' . $every . '. Last run ' . $beat['last_at']
                . ' (' . $ago . ' ago), outcome "' . $beat['last_outcome'] . '", '
                . $beat['last_sent'] . ' sent that run'
                . ($beat['next_at'] === null ? '.' : '. Next expected ' . $beat['next_at'] . '.'));
        }
    }
    } catch (Throwable $e) {
        // Never fatal. Every other row on this page is still worth reading,
        // and "this one check could not run" is itself a useful answer.
        add($checks, 'Send queue runner', 'fail',
            'Could not be checked: ' . get_class($e) . ': ' . $e->getMessage()
            . ' -- most likely app/Support/Heartbeat.php or a class it uses was not uploaded.');
    }

    $accountLive = App\Support\Mailer::transactionalIsLive();
    add($checks, 'Password reset email', $accountLive ? 'pass' : 'todo',
        App\Support\Mailer::transactionalStatus()
        . ' So /members/forgot '
        . ($accountLive
            ? 'shows the form and will send a link.'
            : 'shows "Email sending is not switched on yet" and will not send a link.'));

    // Has anybody actually asked for one?
    //
    // Every other row here is about configuration. None of them can tell the
    // difference between "the form was never submitted" and "it was submitted
    // and the mail went out fine" -- and with a quiet error log those two look
    // identical from the outside, which is precisely where "still no email"
    // gets stuck. A reset row is written before the send is attempted, so its
    // presence proves the request reached the code, and its absence proves it
    // did not.
    if (isset($pdo) && $pdo instanceof PDO && in_array('password_resets', $tables ?? [], true)) {
        try {
            $r = $pdo->query(
                'SELECT COUNT(*) AS n, MAX(created_at) AS newest,
                        SUM(used_at IS NOT NULL) AS used
                   FROM password_resets'
            )->fetch(PDO::FETCH_ASSOC) ?: [];

            $n = (int) ($r['n'] ?? 0);

            if ($n === 0) {
                add($checks, 'Reset links issued', 'todo',
                    'None, ever. Nobody has successfully submitted /members/forgot on this site — '
                    . 'so if you are waiting on an email, the request never got as far as sending '
                    . 'one. The usual reasons are the address not belonging to an active, '
                    . 'non-staff member account, or too many attempts in the last hour '
                    . '(three per address, twelve per IP). Both are written to the error log.');
            } else {
                $ago = strtotime((string) $r['newest']);
                $mins = $ago === false ? null : max(0, (int) round((time() - $ago) / 60));
                add($checks, 'Reset links issued', 'pass',
                    $n . ' link' . ($n === 1 ? '' : 's') . ' issued, '
                    . (int) ($r['used'] ?? 0) . ' used, most recent '
                    . ($mins === null ? 'at ' . (string) $r['newest']
                        : ($mins < 60 ? $mins . ' minutes ago' : (int) round($mins / 60) . ' hours ago'))
                    . '. The request reached the code and a link was created, so if no email '
                    . 'arrived the failure is in delivery, not in the form — check the error log '
                    . 'for a "Password reset not sent" line, and check the spam folder.');
            }
        } catch (PDOException $e) {
            // Not worth a row of its own; the table check above covers it.
        }
    }

    // 3a. The account-email lane, which can be a different provider entirely.
    //     Worth its own row because "review requests are sending" and "a
    //     locked-out customer can get back in" are now two separate switches.
    // Asked of Mailer, not re-derived from the config key. A complete smtp
    // block now means SMTP without transactional_driver being set, so reading
    // that key directly skipped every SMTP check on exactly the setup this was
    // written for -- the same "a diagnostic that reimplements the rule
    // eventually disagrees with the page" mistake, made two commits after
    // warning about it.
    $loginDriver = App\Support\Mailer::transactionalDriver();
    $smtp        = is_array($mailCfg['smtp'] ?? null) ? $mailCfg['smtp'] : [];

    // The address a reset would ACTUALLY be sent from, which is not the same
    // question as what is written in the config: transactional_from falls back
    // to mail.from, and mail.from to a built-in default. Kept separate from
    // $loginFrom, which is the raw key and is what the rows after this one are
    // asking about.
    $loginFromUsed = App\Support\Mailer::transactionalFrom();

    if ($loginDriver === 'smtp') {
        $missing = [];
        foreach (['host', 'username', 'password'] as $key) {
            if (trim((string) ($smtp[$key] ?? '')) === '') {
                $missing[] = 'mail.smtp.' . $key;
            }
        }

        $port = (int) ($smtp['port'] ?? 465);
        $enc  = trim((string) ($smtp['encryption'] ?? '')) ?: ($port === 587 ? 'tls' : 'ssl');

        if ($missing !== []) {
            add($checks, 'Account email', 'fail',
                'Set to send password resets over SMTP, but missing: ' . implode(', ', $missing)
                . '. Your mailbox password goes here, from hPanel under Emails, '
                . 'Mailboxes, Connect apps and devices.');
        } elseif (strcasecmp((string) $smtp['username'], $loginFromUsed) !== 0) {
            // Compared against the address that will actually be used, which
            // falls back to mail.from and then to a built-in default when
            // transactional_from is unset -- so leaving it blank does not mean
            // "the same as the mailbox", it means some other address entirely.
            add($checks, 'Account email', 'fail',
                'The mailbox is ' . (string) $smtp['username'] . ' but resets would be sent from '
                . $loginFromUsed . ', and most hosts refuse a message posted as anything but the '
                . 'mailbox that authenticated. Set mail.transactional_from to '
                . (string) $smtp['username'] . '.');
        } else {
            add($checks, 'Account email', 'pass',
                'SMTP via ' . (string) $smtp['host'] . ':' . $port . ' (' . $enc . ') as '
                . (string) $smtp['username'] . '. Password resets go out through this mailbox; '
                . 'review requests still use the provider above. Add ?mail=you@example.com to '
                . 'this URL to send a real test message.');
        }
    }

    // 3b. The account-email address. Password resets must not share a
    //     reputation with bulk review requests: a blocklisted review domain
    //     would take the one email a locked-out customer needs with it.
    // The rows below are about the provider lane's from-address. On SMTP the
    // address is the mailbox, which the check above already reported against,
    // so saying it twice would only muddy it.
    if ($loginDriver === 'smtp') {
        // Nothing further to say.
    } elseif ($loginFrom === '') {
        add($checks, 'Account email', 'todo',
            'No mail.transactional_from, so password resets go out from ' . ($mailFrom ?: 'mail.from')
            . ' — the same address as the review requests. They will send, but every spam '
            . 'complaint a business collects lands on the domain you need to log people in from. '
            . 'Set mail.transactional_from to something like logins@ on a domain verified in '
            . 'Postmark, on its own stream.');
    } elseif (filter_var($loginFrom, FILTER_VALIDATE_EMAIL) === false) {
        add($checks, 'Account email', 'fail',
            'mail.transactional_from is not an address.');
    } elseif (strcasecmp($loginFrom, $mailFrom) === 0) {
        add($checks, 'Account email', 'todo',
            'mail.transactional_from is the same address as mail.from, which is the thing '
            . 'it exists to avoid. Use a different subdomain for password resets.');
    } else {
        $loginDomain = substr($loginFrom, strpos($loginFrom, '@') + 1);
        add($checks, 'Account email', 'pass',
            $loginFrom . ' on stream "'
            . (trim((string) ($mailCfg['transactional_stream'] ?? '')) ?: 'outbound')
            . '". This domain (' . $loginDomain . ') needs verifying in Postmark too — it is '
            . 'separate from the review-request one on purpose.');
    }

    // 4. The webhook secret. Not fatal, but without it bounces and complaints
    //    never come back, and a complaint nobody records is one nobody acts on.
    if (trim((string) ($mailCfg['webhook_secret'] ?? '')) === '') {
        $suggested = bin2hex(random_bytes(16));
        add($checks, 'Delivery webhook', 'todo',
            'No mail.webhook_secret, so the bounce and complaint endpoint refuses everything. '
            . "Add \"'webhook_secret' => '" . $suggested . "',\" inside the mail section, then "
            . 'point Postmark at https://' . (string) ($_SERVER['HTTP_HOST'] ?? 'your-domain')
            . '/webhooks/email/' . $suggested);
    } else {
        add($checks, 'Delivery webhook', 'pass',
            'Secret set. Point Postmark at /webhooks/email/YOUR-SECRET for bounces and complaints.');
    }

    // 5. Is the cron actually running? The log's timestamp answers it, and
    //    this is the single most likely reason a member presses Send and
    //    nothing ever happens.
    $cronLog = $base . '/storage/logs/cron.log';
    if (!is_file($cronLog)) {
        add($checks, 'Scheduled sending', 'todo',
            'storage/logs/cron.log does not exist, so the cron job has probably never run. '
            . 'Queued requests will sit there until it does. See DEPLOYMENT.md section 6b.');
    } else {
        $age = time() - (int) @filemtime($cronLog);
        add($checks, 'Scheduled sending', $age < 900 ? 'pass' : 'todo',
            $age < 900
                ? 'Last ran ' . max(0, (int) round($age / 60)) . ' minutes ago.'
                : 'Last ran ' . (int) round($age / 60) . ' minutes ago, which is too long for a '
                  . 'five-minute schedule. Check the cron job in hPanel → Advanced → Cron Jobs.');
    }

    // --- Opt-in live send -------------------------------------------------
    // Sends a real message, so it only happens when asked and only to an
    // address typed into the URL by whoever is holding this page.
    if (isset($_GET['mail']) && $mailFiles === []) {
        $to = trim((string) $_GET['mail']);

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            add($checks, 'Test send', 'fail',
                'Add a real address: ?mail=you@example.com');
        } else {
            // Config and Mailer are already loaded by the lane check above.

            // BOTH lanes, separately. They can be different providers, and for
            // a while this only ever tested the bulk one -- so somebody could
            // configure SMTP for password resets, press the test button, watch
            // it pass, and still have no resets arriving. The lane that was
            // never exercised is exactly the lane that was broken.
            $lanes = [
                'Test send (review requests)' => [
                    'driver'    => App\Support\Mailer::driver(),
                    'from'      => App\Support\Mailer::from(),
                    'from_name' => App\Support\Mailer::fromHeader(null, false),
                    'stream'    => (string) ($mailCfg['stream'] ?? 'outbound'),
                    'note'      => 'a 401 means the token is wrong; "sender signature" means the '
                                 . 'From domain is not verified in Postmark yet.',
                ],
                'Test send (password resets)' => [
                    'driver'    => App\Support\Mailer::transactionalDriver(),
                    'from'      => App\Support\Mailer::transactionalFrom(),
                    'from_name' => App\Support\Mailer::transactionalHeader(),
                    'stream'    => App\Support\Mailer::transactionalStream(),
                    'note'      => 'on SMTP, "refused authentication" means the mailbox password is '
                                 . 'wrong; "Could not reach" means the host or port is wrong, or '
                                 . 'your server does not allow outbound connections on it.',
                ],
            ];

            foreach ($lanes as $label => $lane) {
                $result = App\Support\Mailer::send([
                    'to'        => $to,
                    'subject'   => 'PromoMonster test send',
                    'text'      => "This is a test from diagnose.php.\n\n"
                                 . "Lane: " . $label . "\n"
                                 . "Driver: " . $lane['driver'] . "\n"
                                 . "From: " . $lane['from'] . "\n",
                    'from_name' => $lane['from_name'],
                    'from'      => $lane['from'],
                    'driver'    => $lane['driver'],
                    'stream'    => $lane['stream'],
                    'tag'       => 'diagnostic',
                ]);

                if ($result['ok']) {
                    add($checks, $label, 'pass',
                        'Accepted by the "' . $result['driver'] . '" driver, from ' . $lane['from']
                        . ($result['driver'] === 'log'
                            ? ' — which means it was written to storage/logs/mail.log, NOT delivered.'
                            : ', id ' . (string) $result['id']
                              . '. Check the inbox, and check the spam folder before celebrating.'));
                } else {
                    add($checks, $label, 'fail',
                        'Driver "' . $result['driver'] . '" refused it: ' . (string) $result['error']
                        . ' — ' . $lane['note']);
                }
            }
        }
    }
}

// --- Recent errors --------------------------------------------------------
// The whole point: a 500 should never again be a dead end.
//
// The newest error's first line goes INTO the summary row, not just into the
// section below. People copy the summary — three times running, the detail was
// left behind and the cause had to be guessed at. A detail nobody copies is a
// detail nobody has.
$logDir = $base . '/storage/logs';
$logPath = $logDir . '/error.log';
$recentErrors = [];
if (is_file($logPath)) {
    $raw = (string) @file_get_contents($logPath);
    // Entries start with "[date] REFERENCE  Class: message".
    $blocks = preg_split('/\n(?=\[\d{4}-)/', trim($raw)) ?: [];

    // Drop the empties. preg_split on an empty string returns one empty
    // element, not none -- so a log file that had been emptied rather than
    // deleted was reported as "1 error(s)" with a blank message and no date,
    // which then could not be aged and so came out as a hard failure. Telling
    // somebody to clear the log and having that create a phantom error is a
    // special kind of unhelpful.
    $recentErrors = array_slice(
        array_values(array_filter(array_reverse($blocks), static fn (string $b): bool => trim($b) !== '')),
        0,
        5,
    );
}

// If the log cannot be written, every reference is a dead end and the visitor
// is told to look somewhere with nothing in it. Say so loudly.
$logWritable = is_dir($logDir) ? is_writable($logDir) : is_writable($base . '/storage');
if (!$logWritable) {
    add($checks, 'Error log writable', 'fail',
        $logDir . ' is not writable, so crash details go to the server error log '
        . 'instead of here. Set that folder to 755 in hPanel → File Manager, or read '
        . 'hPanel → Advanced → PHP Error Log for entries starting "promomonster".');
}

if ($recentErrors === []) {
    add($checks, 'Error log', 'pass',
        is_file($logPath) ? 'Empty — nothing has crashed.' : 'No log yet — nothing has crashed.');
} else {
    // First line is "[date] REF  Class: message"; second is the top frame.
    $lines = explode("\n", trim($recentErrors[0]));
    $headline = trim($lines[0] ?? '');
    $frame = '';
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#0 ')) { $frame = trim($line); break; }
    }

    // How old the newest one is, which is most of what you want to know.
    //
    // Without it a crash from two days ago, already fixed, keeps being reported
    // as a current problem for ever -- so the report says "2 problems found"
    // when nothing is wrong, and the one time something IS wrong it does not
    // stand out. An entry nobody can date is an entry nobody can dismiss.
    $age = null;
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $headline, $m) === 1) {
        $when = strtotime($m[1]);
        $age = $when === false ? null : max(0, time() - $when);
    }

    $ago = static function (int $n, string $unit): string {
        return ' (' . $n . ' ' . $unit . ($n === 1 ? '' : 's') . ' ago)';
    };

    $howLongAgo = match (true) {
        $age === null => '',
        $age < 3600   => $ago(max(1, (int) round($age / 60)), 'minute'),
        $age < 86400  => $ago((int) round($age / 3600), 'hour'),
        default       => $ago((int) round($age / 86400), 'day'),
    };

    // Older than a day and nothing since: worth reading, not worth alarm.
    $stale = $age !== null && $age > 86400;

    add($checks, 'Error log', $stale ? 'todo' : 'fail',
        count($recentErrors) . ' error(s) in the log, newest' . $howLongAgo
        . ($stale
            ? '. Nothing has gone wrong since, so this is history rather than a live problem '
              . '— delete storage/logs/error.log to clear it. NEWEST: '
            : '. NEWEST: ')
        . $headline
        . ($frame !== '' ? '  |  ' . $frame : '')
        . '  — copy this whole line when asking for help.');
}

// Look up one reference directly: /diagnose.php?ref=850B649D
$refLookup = null;
$wantedRef = strtoupper(trim((string) ($_GET['ref'] ?? '')));
if ($wantedRef !== '' && preg_match('/^[0-9A-F]{4,16}$/', $wantedRef) && is_file($logPath)) {
    $raw = (string) @file_get_contents($logPath);
    foreach (preg_split('/\n(?=\[\d{4}-)/', trim($raw)) ?: [] as $block) {
        if (str_contains($block, $wantedRef)) { $refLookup = $block; }
    }
    add($checks, 'Reference ' . $wantedRef, $refLookup === null ? 'fail' : 'info',
        $refLookup === null
            ? 'Not found in this log. Either it predates the log being cleared, or the '
              . 'log is not writable — see the row above.'
            : 'Found. The full entry is printed below.');
}

$failures = array_values(array_filter($checks, static fn($c) => $c['state'] === 'fail'));
$todos = array_values(array_filter($checks, static fn($c) => $c['state'] === 'todo'));
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
 .tag.todo{background:#fff4e0;color:#8a5a12}
 td.n{font-weight:600;white-space:nowrap}
 td.d{color:#56606d;word-break:break-word}
 .verdict{border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:2rem}
 .bad{background:#fbe6da;border:1px solid #eab999}.good{background:#e3f3ec;border:1px solid #a6d3c6}
 .verdict h2{margin:0 0 .4rem;font-size:1.05rem}
 .verdict ol,.verdict p{margin:.4rem 0 0}
 code{font-family:ui-monospace,Menlo,monospace;background:#f2f1ec;padding:.08rem .3rem;border-radius:4px;font-size:.86em}
 .warn{margin-top:2rem;color:#a34a12;font-weight:600}
 .sendbox{background:#fff;border:1px solid #e3e1da;border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:2rem}
 .sendbox strong{display:block;font-size:1.05rem}
 .sendbox p{margin:.35rem 0 0;color:#56606d;font-size:.92rem}
 .sendbox__row{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.85rem}
 .sendbox input{flex:1 1 16rem;padding:.6rem .75rem;border:1px solid #cfccc3;border-radius:8px;font:inherit}
 .sendbox button{padding:.6rem 1.1rem;border:0;border-radius:8px;background:#0475a3;color:#fff;
   font:inherit;font-weight:700;cursor:pointer}
 .sendbox button:hover{background:#005694}
 .sendbox__note{font-size:.84rem}
</style></head><body><div class="wrap">
<h1>PromoMonster deployment diagnostic</h1>
<p class="sub"><?= date('Y-m-d H:i:s') ?></p>

<?php /* A box, not a URL to hand-edit.

         Every check on this page can pass while no email actually leaves the
         building -- they test settings, and this tests delivery. It was
         reachable only by typing ?mail=you@example.com into the address bar,
         which meant the one thing worth doing was the one thing nobody did.
         GET, because it changes nothing here and a refresh should just send
         another. */ ?>
<form method="get" class="sendbox">
  <strong>Send a real test email</strong>
  <p>Nothing else on this page proves mail can leave the server. This does.</p>
  <div class="sendbox__row">
    <input type="email" name="mail" required placeholder="you@example.com"
           value="<?= htmlspecialchars((string) ($_GET['mail'] ?? ''), ENT_QUOTES) ?>">
    <button type="submit">Send test</button>
  </div>
  <p class="sendbox__note">Sends through both lanes &mdash; the review-request
    provider and the password-reset mailbox &mdash; and reports each separately.
    Look for the two &ldquo;Test send&rdquo; rows below.</p>
</form>

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

<?php if ($todos !== []): ?>
  <div class="verdict" style="background:#fff8ec;border:1px solid #e8cfa0;">
    <h2><?= count($todos) ?> thing<?= count($todos) === 1 ? '' : 's' ?> still to switch on</h2>
    <p>The site works without <?= count($todos) === 1 ? 'this' : 'these' ?>. <?= count($todos) === 1 ? 'It is' : 'They are' ?> why a feature is sitting quiet.</p>
    <ol>
      <?php foreach ($todos as $t): ?>
        <li><strong><?= htmlspecialchars($t['name'], ENT_QUOTES) ?>:</strong>
          <?= htmlspecialchars($t['detail'], ENT_QUOTES) ?></li>
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

<?php if ($refLookup !== null): ?>
  <h2 style="font-size:1.05rem;margin:2.25rem 0 .5rem;">Reference <?= htmlspecialchars($wantedRef, ENT_QUOTES) ?></h2>
  <pre style="background:#fff;border:2px solid #b3261e;border-radius:10px;padding:1rem;
    overflow-x:auto;font:12px ui-monospace,Menlo,monospace;white-space:pre-wrap;
    color:#7a2d12;margin:0 0 1.5rem;"><?= htmlspecialchars(
      implode("\n", array_slice(explode("\n", $refLookup), 0, 16)), ENT_QUOTES) ?></pre>
<?php endif; ?>

<?php if ($recentErrors !== []): ?>
  <h2 style="font-size:1.05rem;margin:2.25rem 0 .5rem;">Most recent errors</h2>
  <p style="color:#56606d;margin:0 0 1rem;font-size:.92rem;">
    Newest first. The first line of each carries the reference shown on the error page.</p>
  <?php foreach ($recentErrors as $block): ?>
    <pre style="background:#fff;border:1px solid #e3e1da;border-radius:10px;padding:1rem;
      overflow-x:auto;font:12px ui-monospace,Menlo,monospace;white-space:pre-wrap;
      color:#7a2d12;margin:0 0 .75rem;"><?= htmlspecialchars(
        implode("\n", array_slice(explode("\n", $block), 0, 12)), ENT_QUOTES) ?></pre>
  <?php endforeach; ?>
<?php endif; ?>

<p class="warn">Delete diagnose.php from the server once the site is working.</p>
</div></body></html>
