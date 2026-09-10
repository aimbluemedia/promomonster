<?php

declare(strict_types=1);

namespace App\Support;

final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = []): string
    {
        $file = APP_ROOT . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /** Renders a view inside the site layout. @param array<string,mixed> $data */
    public static function page(string $template, array $data = []): string
    {
        $content = self::render($template, $data);
        return self::render('layout', $data + [
            'content' => $content,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'current' => $data['current'] ?? null,
        ]);
    }

    /** Renders a view inside the admin layout. @param array<string,mixed> $data */
    public static function superadmin(string $template, array $data = []): string
    {
        $content = self::render($template, $data);
        return self::render('superadmin/layout', $data + [
            'content' => $content,
            'title' => $data['title'] ?? 'Superadmin',
        ]);
    }

    /** Renders a view inside the members layout. @param array<string,mixed> $data */
    public static function members(string $template, array $data = []): string
    {
        $content = self::render($template, $data);
        return self::render('members/layout', $data + [
            'content' => $content,
            'title' => $data['title'] ?? 'PromoMonster',
        ]);
    }

    /** Escape for HTML output. Every dynamic value in a template goes through this. */
    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
