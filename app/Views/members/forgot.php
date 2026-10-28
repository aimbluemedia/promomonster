<?php use App\Support\Csrf; use App\Support\Mailer; use App\Support\PasswordReset; use App\Support\View;
/** @var ?string $error @var ?string $sent @var int $minutes @var ?string $title */

/* Asked here rather than handed in.
 *
 * These two were passed from the controller, which meant two files had to
 * agree about what is true -- and on a host where deploying is uploading files
 * by hand, a current view paired with last week's controller reports whatever
 * last week believed. That is not hypothetical: an older controller asked
 * whether REVIEW REQUESTS could send instead of whether account email could,
 * so this page announced that sending was switched off while a perfectly good
 * mailbox sat configured behind it, and no amount of fixing the config moved
 * it.
 *
 * One source of truth, read at the moment it is displayed. The page cannot now
 * disagree with the code that does the sending.
 */
$sending = Mailer::transactionalIsLive();
$ready   = PasswordReset::ready();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= View::e($title ?? 'Reset your password') ?></title>
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

    <?php if ($sent !== null): ?>
      <?php /* Deliberately not "we have sent you an email". We will not say
               whether that address has an account, because a recovery form that
               does is a free way to check who our customers are. */ ?>
      <h1>Check your email</h1>
      <p class="muted" style="font-size:.9rem;margin:0 0 1.25rem;">
        If <?= $sent === '' ? 'that address' : '<strong>' . View::e($sent) . '</strong>' ?>
        has an account with us, a link to set a new password is on its way. It
        works once and expires in <?= (int) $minutes ?> minutes.
      </p>
      <p class="muted" style="font-size:.86rem;margin:0 0 1.25rem;">
        Nothing yet? Check your spam folder. If you asked more than once, only
        the newest email still works.
      </p>
      <a class="btn btn--primary btn--block" href="/members/login">Back to sign in</a>
      <p class="form__note" style="margin-top:1.25rem;">
        Wrong address? <a href="/members/forgot" style="color:var(--brand);font-weight:600;">Try another</a>.
      </p>

    <?php else: ?>
      <h1>Reset your password</h1>
      <?php /* The promise only holds when there is a form under it. With the
               feature switched off, "we will send you a link" is contradicted
               by the notice two lines below it. */ ?>
      <p class="muted" style="font-size:.9rem;margin:0 0 1.5rem;">
        <?= $ready
          ? 'Enter the email you signed up with and we will send you a link to set a new one.'
          : 'This is the page that sends you a link to set a new password.' ?>
      </p>

      <?php if ($error !== null): ?>
        <div class="alert" role="alert" style="margin-bottom:1rem;"><?= View::e($error) ?></div>
      <?php endif; ?>

      <?php if (!$ready): ?>
        <?php /* The table this feature needs is not there yet, which on this
                 host means the files went up but the migration has not been run.
                 Whoever is reading this is locked out already; a form that
                 cannot work is worse than no form, so it does not get shown. */ ?>
        <div class="notice" style="margin-bottom:1.25rem;border-left-color:var(--star);">
          <strong>Password reset is not switched on yet.</strong>
          <p>Get in touch and we will sort your password out by hand. Sorry about
            this &mdash; it is on us, not on you.</p>
        </div>

      <?php elseif (!$sending): ?>
        <?php /* Same honesty as the Google reviews page. Until the mail provider is
                 connected a reset link cannot reach anybody, and "check your
                 inbox" would send somebody to wait for an email that is sitting
                 in a log file on the server. */ ?>
        <div class="notice" style="margin-bottom:1.25rem;">
          <strong>Email sending is not switched on yet.</strong>
          <p>A reset link cannot reach you until it is. Get in touch and we will
            sort your password out by hand.</p>
        </div>
      <?php endif; ?>

      <?php if ($ready): ?>
      <form class="form" method="post" action="/members/forgot">
        <?= Csrf::field() ?>
        <div>
          <label class="sr-only" for="email">Email</label>
          <input class="field" id="email" name="email" type="email" required
                 placeholder="you@yourbusiness.com" autocomplete="username" autofocus>
        </div>

        <?php /* Honeypot. Hidden from people, irresistible to bots. */ ?>
        <div class="honeypot" aria-hidden="true">
          <label for="website_url">Leave this empty</label>
          <input id="website_url" name="website_url" type="text" tabindex="-1" autocomplete="off">
        </div>

        <button class="btn btn--primary btn--block" type="submit">Send me a link</button>
      </form>
      <?php endif; ?>

      <p class="form__note" style="margin-top:1.25rem;">
        Remembered it? <a href="/members/login" style="color:var(--brand);font-weight:600;">Sign in</a>.
      </p>
      <p class="form__note" style="margin-top:.5rem;">
        No account yet? <a href="/members/signup" style="color:var(--brand);font-weight:600;">Create one free</a>.
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
