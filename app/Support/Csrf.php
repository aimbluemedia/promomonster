<?php

declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . View::e(self::token()) . '">';
    }

    /** Constant-time comparison — never compare tokens with ==. */
    public static function check(?string $candidate): bool
    {
        $expected = $_SESSION[self::KEY] ?? null;
        if (!is_string($expected) || !is_string($candidate)) {
            return false;
        }
        return hash_equals($expected, $candidate);
    }
}
