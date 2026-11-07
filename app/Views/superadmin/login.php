<?php
use App\Support\Csrf;
use App\Support\View;
/** @var ?string $error @var ?string $title */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= View::e($title ?? 'Sign in') ?></title>
<link rel="stylesheet" href="<?= View::e(View::asset('/assets/css/app.css')) ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <?= View::render('partials/logo', ['href' => '/', 'size' => 28]) ?>
    <h1>Sign in</h1>
    <p class="muted" style="font-size:.9rem;margin:0 0 1.5rem;">Staff access only.</p>

    <?php if ($error !== null): ?>
      <div class="alert" role="alert" style="margin-bottom:1rem;"><?= View::e($error) ?></div>
    <?php endif; ?>

    <form class="form" method="post" action="/superadmin/login">
      <?= Csrf::field() ?>
      <div>
        <label class="sr-only" for="email">Email</label>
        <input class="field" id="email" name="email" type="email" required
               placeholder="you@promomonster.com" autocomplete="username" autofocus>
      </div>
      <div>
        <label class="sr-only" for="password">Password</label>
        <input class="field" id="password" name="password" type="password" required
               placeholder="Password" autocomplete="current-password" data-eye>
      </div>
      <button class="btn btn--primary btn--block" type="submit">Sign in</button>
    </form>
  </div>
</div>
<script src="<?= View::e(View::asset('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
