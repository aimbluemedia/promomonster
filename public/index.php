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

use App\Controllers\SuperadminController;
use App\Controllers\AuthController;
use App\Controllers\LeadController;
use App\Controllers\MembersController;
use App\Controllers\PasswordController;
use App\Controllers\SignupController;
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

// Superadmin (PromoMonster staff). SuperadminController's constructor calls
// Auth::requireStaff(), so it is built lazily inside each closure — creating it
// eagerly would redirect every public request to the login form.
$auth = new AuthController();
$router->get('/superadmin/login',   static fn () => $auth->showLogin('superadmin'));
$router->post('/superadmin/login',  static fn () => $auth->login('superadmin'));
$router->post('/superadmin/logout', static fn () => $auth->logout('superadmin'));
$router->get('/superadmin/password',  static fn () => (new PasswordController())->show('superadmin'));
$router->post('/superadmin/password', static fn () => (new PasswordController())->update('superadmin'));

$router->get('/superadmin',                  static fn () => (new SuperadminController())->overview());
$router->get('/superadmin/audits',           static fn () => (new SuperadminController())->audits());
$router->get('/superadmin/audit',            static fn () => (new SuperadminController())->audit());
$router->post('/superadmin/audits/compare',  static fn () => (new SuperadminController())->compareAudit());
$router->post('/superadmin/audits/update',   static fn () => (new SuperadminController())->updateAudit());
$router->get('/superadmin/leads',            static fn () => (new SuperadminController())->leads());
$router->post('/superadmin/leads/update',    static fn () => (new SuperadminController())->updateLead());
$router->get('/superadmin/compliance',       static fn () => (new SuperadminController())->compliance());
$router->get('/superadmin/activity',         static fn () => (new SuperadminController())->activity());
$router->post('/superadmin/accounts/plan',   static fn () => (new SuperadminController())->updatePlan());

// Members (customers). Same lazy-construction reason as above.
$router->get('/members/login',   static fn () => $auth->showLogin('members'));
$router->post('/members/login',  static fn () => $auth->login('members'));
$router->post('/members/logout', static fn () => $auth->logout('members'));
$router->get('/members/password',  static fn () => (new PasswordController())->show('members'));
$router->post('/members/password', static fn () => (new PasswordController())->update('members'));

// Signup is public: SignupController has no guard in its constructor.
$router->get('/members/signup',  static fn () => (new SignupController())->show());
$router->post('/members/signup', static fn () => (new SignupController())->store());

$router->get('/members',          static fn () => (new MembersController())->overview());
$router->get('/members/reviews',  static fn () => (new MembersController())->reviews());
$router->get('/members/requests', static fn () => (new MembersController())->requests());
$router->get('/members/playbook', static fn () => (new MembersController())->playbook());
$router->get('/members/settings', static fn () => (new MembersController())->settings());
$router->post('/members/plan',   static fn () => (new MembersController())->requestPlan());

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/',
);
