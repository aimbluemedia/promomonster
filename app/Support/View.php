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

    /**
     * A web-root-relative asset URL with the file's modification time on it.
     *
     * Without this, a browser that has the old stylesheet keeps it: this site
     * deploys by file upload, so nothing sets a cache header worth trusting and
     * nobody gets told to hard-refresh. A visitor then renders new markup
     * against old CSS, which is how an icon with no size rule ends up filling
     * the screen.
     *
     * The mtime is read once per request and never trusted: three deployment
     * layouts are supported and the file is not always under PUBLIC_PATH, so a
     * failed stat simply returns the plain path rather than an error.
     */
    public static function asset(string $path): string
    {
        static $cache = [];

        if (!isset($cache[$path])) {
            $file = (defined('PUBLIC_PATH') ? PUBLIC_PATH : '') . $path;
            $mtime = is_file($file) ? @filemtime($file) : false;
            $cache[$path] = $mtime === false ? $path : $path . '?v=' . $mtime;
        }

        return $cache[$path];
    }
}
