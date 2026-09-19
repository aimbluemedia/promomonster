<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Inline SVG icons. Inline rather than a sprite or icon font so they inherit
 * currentColor, need no extra request, and add no build step.
 */
final class Icon
{
    private const PATHS = [
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'send'      => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'message'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
        'qr'        => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 20h1M20 14h1M14 20h1"/>',
        'list'      => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'bell'      => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'sparkle'   => '<path d="M12 3l1.9 4.6L18.5 9.5 13.9 11.4 12 16l-1.9-4.6L5.5 9.5l4.6-1.9Z"/><path d="M19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8Z"/>',
        'layout'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
        'pin'       => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'share'     => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
        'megaphone' => '<path d="m3 11 15-6v14L3 13Z"/><path d="M3 11v2a2 2 0 0 0 2 2h1"/><path d="M7 15v4a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-3"/>',
        'star'      => '<path d="m12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.4L12 17.8 6.2 20.8l1.1-6.4L2.6 9.8l6.5-.9Z"/>',
        'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="m7 15 3-4 3 3 5-7"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    ];

    public static function render(string $name, string $class = ''): string
    {
        $body = self::PATHS[$name] ?? self::PATHS['star'];

        // Every icon carries `icon`, which is where the stroke-not-fill
        // presentation lives. These paths are outlines: without it they render
        // as solid black blobs, which is exactly what happened the first time
        // one was used outside a .chip.
        $cls = 'icon' . ($class !== '' ? ' ' . View::e($class) : '');

        return '<svg class="' . $cls . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . $body . '</svg>';
    }

    public static function chip(string $name): string
    {
        return '<span class="chip">' . self::render($name) . '</span>';
    }
}
