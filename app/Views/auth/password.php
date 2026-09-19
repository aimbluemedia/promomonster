<?php use App\Support\Csrf; use App\Support\View;
/** @var string $area @var ?string $error @var int $min @var ?string $title */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= View::e($title ?? 'Choose a password') ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <a class="logo" href="/">
      <svg width="28" height="28" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect width="32" height="32" rx="9" fill="var(--brand)"/>
        <path d="M16 7.5l2.3 4.7 5.2.75-3.75 3.65.9 5.15L16 19.3l-4.65 2.45.9-5.15L8.5 12.95l5.2-.75z" fill="#fff"/>
      </svg>
      <span class="logo__word">Promo<span class="logo__accent">Monster</span></span>
    </a>
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
               autocomplete="new-password" autofocus>
      </div>
      <div>
        <label class="sr-only" for="password_confirm">Repeat new password</label>
        <input class="field" id="password_confirm" name="password_confirm" type="password" required
               minlength="<?= (int) $min ?>" placeholder="Repeat new password"
               autocomplete="new-password">
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
</body>
</html>
