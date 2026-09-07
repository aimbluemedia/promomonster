<?php

declare(strict_types=1);

/**
 * Front controller. Every request that is not a real file on disk is rewritten
 * here by public/.htaccess.
 */

/**
 * The PHP built-in server routes every request through this script, static
 * files included, so hand real files back to it. Apache never reaches this
 * branch — public/.htaccess already excludes existing files from the rewrite.
 */
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $candidate = realpath(__DIR__ . urldecode($path));
    if ($candidate !== false
        && is_file($candidate)
        && str_starts_with($candidate, __DIR__ . DIRECTORY_SEPARATOR)
    ) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\PageController;
use App\Controllers\WaitlistController;
use App\Support\Router;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

$router = new Router();
$pages = new PageController();

$router->get('/',                  [$pages, 'home']);
$router->get('/business',          [$pages, 'business']);
$router->get('/earn',              [$pages, 'earn']);
$router->get('/services/content',  [$pages, 'content']);
$router->get('/services/social',   [$pages, 'social']);

$router->post('/waitlist', [new WaitlistController(), 'store']);

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/',
);
