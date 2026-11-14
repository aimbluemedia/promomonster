<?php
use App\Support\Config;
use App\Support\ToutifyAuth;
use App\Support\View;
/**
 * Sign up, which happens on Toutify.
 *
 * The form is Toutify's, embedded. PromoMonster asks for no password, which
 * is the whole point: there is one account across every Toutify product and
 * only Toutify ever sees the password for it.
 *
 * The visitor fills this in, Toutify emails them a link to choose a password,
 * and that link brings them back to /auth/toutify/initiate already signed in.
 *
 * @var ?string $email    prefill, locked when present
 * @var ?string $name
 * @var ?string $business
 * @var ?string $website
 */
$base  = rtrim((string) Config::get('app_url', 'https://promomonster.com'), '/');
$email = $email ?? null;
?>
<section class="section">
  <div class="container center" style="max-width:40rem;">
    <h1>Start free</h1>
    <p class="lede">One account for PromoMonster and every other Toutify tool.
      No card, no trial clock, and the free plan stays free.</p>

    <?php if (!ToutifyAuth::configured()): ?>
      <?php /* Said plainly rather than showing a form that cannot work. The
               old password form is NOT offered as a fallback on purpose:
               anyone who signed up through it would be a member who then has
               to be migrated to Toutify by hand, which is the exact job this
               change exists to avoid. */ ?>
      <div class="notice" style="margin-top:2rem;text-align:left;">
        <strong>Sign-up is being connected to Toutify.</strong>
        <p>It is not quite ready. If you already have a Toutify account you can
          <a href="/auth/toutify/login">sign in</a> now.</p>
      </div>
    <?php else: ?>
      <?php /* Toutify's own form. The attributes are the whole configuration:
               the client it belongs to, and where to send somebody who turns
               out to have an account already. */ ?>
      <div style="margin-top:2rem;text-align:left;"
           data-toutify-signup
           data-client-id="<?= View::e(ToutifyAuth::clientId()) ?>"
           data-login-url="<?= View::e($base . '/auth/toutify/login') ?>"
           data-accent="#006cb8"
           <?php if ($email !== null): ?>data-email="<?= View::e($email) ?>"<?php endif; ?>
           <?php if (!empty($name)): ?>data-name="<?= View::e((string) $name) ?>"<?php endif; ?>
           <?php if (!empty($business)): ?>data-business-name="<?= View::e((string) $business) ?>"<?php endif; ?>
           <?php if (!empty($website)): ?>data-website="<?= View::e((string) $website) ?>"<?php endif; ?>
      ></div>
      <script src="https://toutify.com/embed/signup.js" async></script>

      <?php /* The form needs JavaScript. Without this, somebody with it turned
               off sees an empty page and no way forward. */ ?>
      <noscript>
        <div class="notice" style="margin-top:1.5rem;text-align:left;">
          <strong>The sign-up form needs JavaScript.</strong>
          <p>Turn it on and reload, or
            <a href="/auth/toutify/login?signup=1">sign up on Toutify directly</a>.</p>
        </div>
      </noscript>

      <p class="form__note" style="margin-top:1.5rem;">
        Already have a Toutify account?
        <a href="/auth/toutify/login">Log in</a>.
      </p>
    <?php endif; ?>
  </div>
</section>
