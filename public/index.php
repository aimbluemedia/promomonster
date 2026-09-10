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
    // Only ever hand back known static assets. Anything else -- above all a
    // .php file -- must fall through so it is executed, never emitted as
    // source. Returning the raw bytes of a stray config.php would leak
    // credentials.
    $types = [
        'css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'txt' => 'text/plain',
    ];
    $ext = strtolower(pathinfo((string) $candidate, PATHINFO_EXTENSION));

    if ($candidate !== false
        && is_file($candidate)
        && str_starts_with($candidate, __DIR__ . DIRECTORY_SEPARATOR)
        && isset($types[$ext])
    ) {
        // Emitted directly rather than via `return false`, because the built-in
        // server resolves that against ITS document root, which is the project
        // root under the shared-hosting fallback layout.
        header('Content-Type: ' . $types[$ext]);
        readfile($candidate);
        return true;
    }

    // A real .php file under public/ (diagnose.php, say) is handed back to the
    // server so it EXECUTES it. `return false` is what makes that happen —
    // reading the bytes ourselves would emit the source instead.
    if ($candidate !== false
        && is_file($candidate)
        && str_starts_with($candidate, __DIR__ . DIRECTORY_SEPARATOR)
        && $ext === 'php'
        && $candidate !== __FILE__
    ) {
        return false;
    }
}

/**
 * Absolute path to the web root. Resolved from this file rather than from the
 * project root, because in the recommended deployment the contents of public/
 * are copied into public_html and no `public` directory exists on the server.
 */
define('PUBLIC_PATH', __DIR__);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\LeadController;
use App\Controllers\PageController;
use App\Support\Router;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

$router = new Router();
$pages = new PageController();

$router->get('/',             [$pages, 'home']);
$router->get('/how-it-works', [$pages, 'howItWorks']);
$router->get('/features',     [$pages, 'features']);
$router->get('/pricing',      [$pages, 'pricing']);
$router->get('/agencies',     [$pages, 'agencies']);
$router->get('/audit',        [$pages, 'audit']);
$router->get('/privacy',      [$pages, 'privacy']);
$router->get('/terms',        [$pages, 'terms']);

$router->post('/leads', [new LeadController(), 'store']);

// Admin. AdminController's constructor calls Auth::requireAdmin(), so it is
// instantiated lazily inside each closure — building it eagerly would redirect
// every public request to the login form.
$auth = new AuthController();
$router->get('/admin/login',  [$auth, 'showLogin']);
$router->post('/admin/login', [$auth, 'login']);
$router->post('/admin/logout', [$auth, 'logout']);

$router->get('/admin',                  static fn () => (new AdminController())->overview());
$router->get('/admin/audits',           static fn () => (new AdminController())->audits());
$router->post('/admin/audits/update',   static fn () => (new AdminController())->updateAudit());
$router->get('/admin/leads',            static fn () => (new AdminController())->leads());
$router->post('/admin/leads/update',    static fn () => (new AdminController())->updateLead());
$router->get('/admin/compliance',       static fn () => (new AdminController())->compliance());
$router->get('/admin/activity',         static fn () => (new AdminController())->activity());

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/',
);
