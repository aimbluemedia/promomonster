<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Turns a bare 500 into something diagnosable.
 *
 * Every uncaught error is written in full to storage/logs/error.log with a
 * short reference. In production the visitor sees only that reference; with
 * debug on, the detail is printed. Either way the error stops being invisible,
 * which is the whole problem with a default 500 on shared hosting.
 */
final class ErrorHandler
{
    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            // Respect the current error_reporting level (the @ operator).
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            self::handle(new \ErrorException(
                $error['message'], 0, $error['type'], $error['file'], $error['line'],
            ));
        });
    }

    public static function handle(Throwable $e): void
    {
        $reference = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        self::write($reference, $e);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        if (self::$debug) {
            echo '<pre style="font:13px ui-monospace,Menlo,monospace;padding:1.5rem;'
                . 'background:#fbe6da;color:#7a2d12;white-space:pre-wrap;">';
            echo 'Reference ' . $reference . "\n\n";
            echo htmlspecialchars(get_class($e) . ': ' . $e->getMessage(), ENT_QUOTES) . "\n";
            echo htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES) . "\n\n";
            echo htmlspecialchars($e->getTraceAsString(), ENT_QUOTES);
            echo '</pre>';
            return;
        }

        echo '<!doctype html><meta charset="utf-8">'
            . '<title>Something went wrong</title>'
            . '<div style="font:16px system-ui,sans-serif;max-width:34rem;margin:12vh auto;padding:0 1.5rem;">'
            . '<h1 style="font-size:1.4rem;">Something went wrong</h1>'
            . '<p style="color:#5a6b7c;">We have logged it. If you are the site owner, '
            . 'open <code>/diagnose.php</code> and look for reference '
            . '<strong>' . $reference . '</strong>.</p></div>';
    }

    private static function write(string $reference, Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s  %s: %s  in %s:%d%s%s%s",
            date('Y-m-d H:i:s'),
            $reference,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL,
            $e->getTraceAsString(),
            PHP_EOL . PHP_EOL,
        );

        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // Fall back to the server's own log if storage is not writable, so the
        // detail is never simply lost.
        if (@file_put_contents($dir . '/error.log', $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('promomonster ' . $reference . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
