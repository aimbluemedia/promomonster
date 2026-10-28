<?php

declare(strict_types=1);

/**
 * Root fallback front controller.
 *
 * The document root SHOULD point at public/. On shared hosting where it cannot
 * be changed, the whole project ends up inside public_html and requests land
 * here instead — so hand off to the real front controller rather than letting
 * Apache find no index and return 403.
 *
 * public/index.php resolves app/ relative to its own parent, so this works
 * unchanged.
 */

require __DIR__ . '/public/index.php';
