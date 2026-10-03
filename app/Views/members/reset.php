<?php use App\Support\Csrf; use App\Support\View;
/** @var ?array $reset @var string $token @var ?string $error @var int $min @var ?string $title */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php /* The token is in this page's own URL, so nothing about this page should
         carry that URL anywhere else. No referrer means a stylesheet, an image
         or a link out cannot hand the reset link to whatever it loads. */ ?>
<meta name="referrer" content="no-referrer">
<title><?= View::e($title ?? 'Choose a new password') ?></title>
<link rel="stylesheet" href="<?= View::e(View::asset('/assets/css/app.css')) ?>">
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

    <?php if ($reset === null): ?>
      <?php /* One message for expired, already used, and never real. Saying
               which would tell somebody holding a guessed token that they had
               guessed a real one. */ ?>
      <h1>That link no longer works</h1>
      <?php /* Naming the commonest cause first, because it is the one nobody
               guesses. "Expired" sends people to look at the clock; the real
               reason is almost always that they asked again and are now
               clicking the older of two emails, which reads as broken rather
               than as intended. */ ?>
      <p class="muted" style="font-size:.9rem;margin:0 0 1rem;">
        <strong>If you asked for more than one link, only the newest email
        works.</strong> Check for a more recent one before asking again.
      </p>
      <p class="muted" style="font-size:.9rem;margin:0 0 1.5rem;">
        Otherwise: links work once, and expire an hour after they are sent.
      </p>
      <a class="btn btn--primary btn--block" href="/members/forgot">Send me a new link</a>
      <p class="form__note" style="margin-top:1.25rem;">
        <a href="/members/login" style="color:var(--brand);font-weight:600;">Back to sign in</a>
      </p>

    <?php else: ?>
      <h1>Choose a new password</h1>
      <p class="muted" style="font-size:.9rem;margin:0 0 1.5rem;">
        Setting one for <strong><?= View::e($reset['email']) ?></strong>. This
        signs you in straight afterwards.
      </p>

      <?php if ($error !== null): ?>
        <div class="alert" role="alert" style="margin-bottom:1rem;"><?= View::e($error) ?></div>
      <?php endif; ?>

      <form class="form" method="post" action="/members/reset">
        <?= Csrf::field() ?>
        <?php /* In the body rather than the action, so the POST does not put the
                 token in a request line that a web server writes to its access
                 log. */ ?>
        <input type="hidden" name="token" value="<?= View::e($token) ?>">
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
        <button class="btn btn--primary btn--block" type="submit">Save and sign in</button>
        <p class="form__note">At least <?= (int) $min ?> characters. A passphrase from
          your password manager is ideal.</p>
      </form>
    <?php endif; ?>
  </div>
</div>
<script src="<?= View::e(View::asset('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
