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
<link rel="stylesheet" href="<?= View::e(View::asset('/assets/css/app.css')) ?>">
</head>
<body>
<header class="admin-header">
  <div class="container">
    <div class="admin-header__top">
      <?= View::render('partials/logo', ['href' => '/superadmin']) ?>
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
        '/superadmin'            => 'Dashboard',
        '/superadmin/users'      => 'Users',
        '/superadmin/audits'     => 'Audit requests',
        '/superadmin/scores'     => 'Review scores',
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
