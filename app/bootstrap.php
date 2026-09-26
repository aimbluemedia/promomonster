<?php

declare(strict_types=1);

/**
 * Application bootstrap. Loaded by public/index.php.
 *
 * No Composer: shared hosting often has no shell access, so the app is a plain
 * upload with a hand-rolled PSR-4 autoloader. Nothing here requires a build
 * step for the same reason.
 */

define('APP_ROOT', __DIR__);
define('BASE_PATH', dirname(__DIR__));

// public/index.php defines this before loading us. CLI entry points (migrate.php)
// do not, so fall back to the repository layout for them.
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4));
    $file = APP_ROOT . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration missing. Copy app/config.example.php to app/config.php and fill it in.');
}

/** @var array<string,mixed> $config */
$config = require $configFile;

date_default_timezone_set($config['timezone'] ?? 'UTC');

error_reporting(E_ALL);
ini_set('display_errors', '0');   // ErrorHandler decides what the visitor sees
ini_set('log_errors', '1');

App\Support\ErrorHandler::register((bool) ($config['debug'] ?? false));

/**
 * No session for the links inside a sent email.
 *
 * /r/, /u/ and /webhooks/ are reached by somebody who has never heard of this
 * site: a customer of a customer, clicking a link in a review request. Setting
 * a cookie on them buys us nothing — there is no form to protect and no login
 * to keep — and it means a review request quietly drops a first-party cookie on
 * a stranger, which is a thing a privacy policy then has to declare.
 *
 * It also keeps the redirect fast, which is the entire job of /r/.
 */
$sessionless = ['/r/', '/u/', '/webhooks/'];
$requestPath = '/' . ltrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if (str_starts_with($requestPath, '/public/')) {
    $requestPath = substr($requestPath, 7);
}

$needsSession = PHP_SAPI !== 'cli';
foreach ($sessionless as $prefix) {
    if (str_starts_with($requestPath, $prefix)) {
        $needsSession = false;
        break;
    }
}

if ($needsSession) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('pm_session');
    session_start();
}

App\Support\Config::load($config);
