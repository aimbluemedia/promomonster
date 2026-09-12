<?php use App\Support\Csrf; use App\Support\View; /** @var ?string $error @var ?string $title */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= View::e($title ?? 'Sign in') ?></title>
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
    <h1>Sign in</h1>
    <p class="muted" style="font-size:.9rem;margin:0 0 1.5rem;">Manage your reviews and reputation.</p>
    <?php if ($error !== null): ?>
      <div class="alert" role="alert" style="margin-bottom:1rem;"><?= View::e($error) ?></div>
    <?php endif; ?>
    <form class="form" method="post" action="/members/login">
      <?= Csrf::field() ?>
      <div><label class="sr-only" for="email">Email</label>
        <input class="field" id="email" name="email" type="email" required
               placeholder="you@yourbusiness.com" autocomplete="username" autofocus></div>
      <div><label class="sr-only" for="password">Password</label>
        <input class="field" id="password" name="password" type="password" required
               placeholder="Password" autocomplete="current-password"></div>
      <button class="btn btn--primary btn--block" type="submit">Sign in</button>
    </form>
    <p class="form__note" style="margin-top:1.25rem;">
      No account yet? <a href="/members/signup" style="color:var(--brand);font-weight:600;">Create one free</a>.
    </p>
  </div>
</div>
</body>
</html>
