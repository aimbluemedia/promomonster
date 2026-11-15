<?php
use App\Support\Csrf;
use App\Support\View;
/**
 * The Free Review Score form. Shared by the homepage section and /score so the
 * two can never drift apart.
 *
 * @var array $old
 * @var bool  $compact  true on the homepage, where the surrounding section
 *                      already carries the heading and the reassurance.
 */
$old = $old ?? [];
$compact = $compact ?? false;
?>
<form class="score-form" method="post" action="/score">
  <?= Csrf::field() ?>

  <div class="score-form__fields">
    <div class="score-form__field">
      <label for="score-url<?= $compact ? '' : '-page' ?>">Your website</label>
      <input class="field field--xl" id="score-url<?= $compact ? '' : '-page' ?>" name="url"
             type="text" inputmode="url" required maxlength="255"
             value="<?= View::e((string) ($old['url'] ?? '')) ?>"
             placeholder="yourbusiness.com" autocomplete="url">
    </div>
    <div class="score-form__field">
      <label for="score-email<?= $compact ? '' : '-page' ?>">Your email</label>
      <input class="field field--xl" id="score-email<?= $compact ? '' : '-page' ?>" name="email"
             type="email" required
             value="<?= View::e((string) ($old['email'] ?? '')) ?>"
             placeholder="you@yourbusiness.com" autocomplete="email">
    </div>
  </div>

  <div class="honeypot" aria-hidden="true">
    <label for="website_url<?= $compact ? '' : '-page' ?>">Leave this field empty</label>
    <input id="website_url<?= $compact ? '' : '-page' ?>" name="website_url" type="text"
           tabindex="-1" autocomplete="off">
  </div>

  <button class="btn btn--primary btn--xl btn--block" type="submit">Free Review Score</button>

  <p class="score-form__note">
    No credit card. No sales call. We read your page and score it in about ten seconds.
  </p>
</form>
