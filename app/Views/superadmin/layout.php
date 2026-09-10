<?php
/** @var string $content @var ?string $title */
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\View;
$me = Auth::user();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/superadmin', PHP_URL_PATH) ?: '/superadmin';
$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= View::e($title ?? 'Admin') ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="admin-header">
  <div class="container">
    <div class="admin-header__top">
      <a class="logo" href="/superadmin">
        <svg width="26" height="26" viewBox="0 0 32 32" fill="none" aria-hidden="true">
          <rect width="32" height="32" rx="9" fill="var(--brand)"/>
          <path d="M16 7.5l2.3 4.7 5.2.75-3.75 3.65.9 5.15L16 19.3l-4.65 2.45.9-5.15L8.5 12.95l5.2-.75z" fill="#fff"/>
        </svg>
        <span class="logo__word">Promo<span class="logo__accent">Monster</span></span>
      </a>
      <div class="admin-header__who">
        <span><?= View::e(trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''))) ?></span>
        <form method="post" action="/superadmin/logout" style="display:inline;">
          <?= Csrf::field() ?>
          <button type="submit" style="background:none;border:0;padding:0;color:var(--brand);
            font:inherit;font-size:.88rem;font-weight:700;cursor:pointer;">Sign out</button>
        </form>
      </div>
    </div>
    <nav class="admin-nav">
      <?php foreach ([
        '/superadmin'            => 'Overview',
        '/superadmin/audits'     => 'Audit requests',
        '/superadmin/leads'      => 'Agencies',
        '/superadmin/compliance' => 'Compliance',
        '/superadmin/activity'   => 'Activity',
      ] as $href => $label): ?>
        <a href="<?= $href ?>"<?= $path === $href ? ' aria-current="page"' : '' ?>><?= View::e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<main class="admin-main">
  <div class="container">
    <?php if ($flash !== null): ?>
      <div class="flash" role="status"><?= View::e($flash) ?></div>
    <?php endif; ?>
    <?= $content ?>
  </div>
</main>
</body>
</html>
