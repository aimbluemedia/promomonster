<?php

declare(strict_types=1);

namespace App\Support;

final class Request
{
    public static function ip(): string
    {
        // Shared hosts and Cloudflare put the real client address in a header.
        // Trusted here only because the app sits behind the host's proxy; on a
        // setup where clients can reach PHP directly this must not be trusted.
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = $_SERVER[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $first = trim(explode(',', $value)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }
        return 'unknown';
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    public static function referer(): ?string
    {
        $value = $_SERVER['HTTP_REFERER'] ?? null;
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 500) : null;
    }

    public static function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
