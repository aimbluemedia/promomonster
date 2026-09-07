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

if ($config['debug'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_name('pm_session');
session_start();

App\Support\Config::load($config);
