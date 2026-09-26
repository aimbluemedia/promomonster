<?php

declare(strict_types=1);

/**
 * The queue runner. Sends review requests that have come due.
 *
 * Hostinger cron, every five minutes:
 *
 *   /usr/bin/php /home/USER/domains/promomonster.com/bin/send-due.php >> \
 *       /home/USER/domains/promomonster.com/storage/logs/cron.log 2>&1
 *
 * Why a cron and a queue rather than sending inside the web request: a send
 * that waits on a third party makes the person who clicked "Send" watch a
 * spinner, and a provider having a slow minute turns into a page timeout and a
 * row nobody is sure about. Here a failure is a row with a reason on it.
 *
 * CLI only. It is under the project root rather than the web root, but the
 * check is here too because on one of the three supported deployment layouts
 * the project root IS the web root.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Support\Database;
use App\Support\Mailer;
use App\Support\ReviewRequests;

/** How many to send per run. Five minutes apart, this is 3,000 an hour. */
const BATCH = 25;

/**
 * A pause between sends.
 *
 * Not politeness — pacing. A burst of identical mail from a young domain is
 * the shape filters are built to catch, and the whole batch arriving in two
 * seconds is worth nothing to anyone.
 */
const PAUSE_MICROSECONDS = 250_000;

$startedAt = microtime(true);
$lockName  = 'promomonster_send_due';

/**
 * One runner at a time.
 *
 * Cron will happily start a second copy while the first is still going — a slow
 * provider is all it takes — and two runners reading the same due rows would
 * email the same customer twice. GET_LOCK is held by the MySQL session and
 * released when this script's connection closes, including on a fatal error,
 * which is exactly the behaviour a lock file does not have.
 */
$lock = Database::first('SELECT GET_LOCK(:name, 0) AS got', ['name' => $lockName]);

if ((int) ($lock['got'] ?? 0) !== 1) {
    echo line('Another run is still going. Nothing to do.');
    exit(0);
}

try {
    $due = ReviewRequests::due(BATCH);

    if ($due === []) {
        echo line('Nothing due.');
        exit(0);
    }

    echo line(sprintf('%d due, driver=%s', count($due), Mailer::driver()));

    $sent = 0;
    $failed = 0;
    $skipped = 0;

    foreach ($due as $i => $row) {
        $result = ReviewRequests::send($row);

        if ($result['ok']) {
            $sent++;
            echo line(sprintf('  #%d sent', (int) $row['id']));
        } elseif ($result['error'] === 'Opted out.') {
            $skipped++;
            echo line(sprintf('  #%d cancelled — opted out', (int) $row['id']));
        } else {
            $failed++;
            echo line(sprintf('  #%d failed — %s', (int) $row['id'], (string) $result['error']));
        }

        if ($i < count($due) - 1) {
            usleep(PAUSE_MICROSECONDS);
        }
    }

    echo line(sprintf(
        'Done: %d sent, %d failed, %d cancelled, in %.1fs',
        $sent,
        $failed,
        $skipped,
        microtime(true) - $startedAt,
    ));
} finally {
    // Explicit, even though closing the connection would do it. A future
    // version that keeps running after this block would otherwise hold the
    // lock and quietly stop every later run.
    Database::run('SELECT RELEASE_LOCK(:name)', ['name' => $lockName]);
}

function line(string $message): string
{
    return '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}
