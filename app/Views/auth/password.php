<?php use App\Support\Csrf; use App\Support\View;
/** @var string $area @var ?string $error @var int $min @var ?string $title */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= View::e($title ?? 'Choose a password') ?></title>
<link rel="stylesheet" href="<?= View::e(View::asset('/assets/css/app.css')) ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <?= View::render('partials/logo', ['href' => '/', 'size' => 28]) ?>
    <h1>Choose a password</h1>
    <p class="muted" style="font-size:.9rem;margin:0 0 1.5rem;">
      You signed in with a temporary password. Pick your own before continuing —
      the temporary one stops working straight away.</p>

    <?php if ($error !== null): ?>
      <div class="alert" role="alert" style="margin-bottom:1rem;"><?= View::e($error) ?></div>
    <?php endif; ?>

    <form class="form" method="post" action="/<?= View::e($area) ?>/password">
      <?= Csrf::field() ?>
      <div>
        <label class="sr-only" for="password">New password</label>
        <input class="field" id="password" name="password" type="password" required
               minlength="<?= (int) $min ?>" placeholder="New password"
               autocomplete="new-password" autofocus data-eye>
      </div>
      <div>
        <label class="sr-only" for="password_confirm">Repeat new password</label>
        <input class="field" id="password_confirm" name="password_confirm" type="password" required
               minlength="<?= (int) $min ?>" placeholder="Repeat new password"
               autocomplete="new-password" data-eye>
      </div>
      <button class="btn btn--primary btn--block" type="submit">Save and continue</button>
      <p class="form__note">At least <?= (int) $min ?> characters. A passphrase from
        your password manager is ideal.</p>
    </form>

    <form method="post" action="/<?= View::e($area) ?>/logout" style="margin-top:1.25rem;">
      <?= Csrf::field() ?>
      <button type="submit" style="background:none;border:0;padding:0;color:var(--muted);
        font:inherit;font-size:.85rem;cursor:pointer;text-decoration:underline;">Sign out instead</button>
    </form>
  </div>
</div>
<script src="<?= View::e(View::asset('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
