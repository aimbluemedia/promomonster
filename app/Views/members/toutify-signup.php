<?php
use App\Support\Config;
use App\Support\Toutify;
use App\Support\ToutifyAuth;
use App\Support\View;
/**
 * Sign up, which happens on Toutify.
 *
 * The form is Toutify's, embedded. PromoMonster asks for no password, which is
 * the whole point: one account across every Toutify product, and only Toutify
 * ever sees the password for it.
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

// The siblings, from the one list the footer also reads, so a product added
// there appears here too. PromoMonster is dropped: "gives you PromoMonster
// plus PromoMonster" is how a generated sentence tells you it was generated.
$siblings = [];
foreach (Toutify::products() as $product) {
    if (!Toutify::isSelf($product['key'])) {
        $siblings[] = $product['name'];
    }
}
$last = array_pop($siblings);
$family = $siblings === [] ? $last : implode(', ', $siblings) . ' and ' . $last;
?>
<section class="section signup-wrap">
  <div class="container">
    <div class="signup-card">
      <h1 class="signup-card__title">Create your free account</h1>
      <p class="signup-card__lead">
        One free Toutify account gives you PromoMonster plus
        <?= View::e($family) ?>. No credit card.
      </p>

      <?php if (!ToutifyAuth::configured()): ?>
        <?php /* Said plainly rather than showing a form that cannot work. The
                 old password form is NOT offered as a fallback on purpose:
                 anyone signing up through it would be a member who then has to
                 be migrated to Toutify by hand, which is the exact job this
                 change exists to avoid. */ ?>
        <div class="notice" style="text-align:left;">
          <strong>Sign-up is being connected to Toutify.</strong>
          <p>It is not quite ready. If you already have a Toutify account you
            can <a href="/auth/toutify/login">sign in</a> now.</p>
        </div>
      <?php else: ?>
        <?php /* Toutify's own form drops in here.

                 data-title is set so the embed's heading does not repeat the
                 one above it -- the reference design shows "Create your free
                 account" twice, once from the page and once from the form, and
                 a heading that says the same thing twice reads as a fault. */ ?>
        <div class="signup-card__form"
             data-toutify-signup
             data-client-id="<?= View::e(ToutifyAuth::clientId()) ?>"
             data-login-url="<?= View::e($base . '/auth/toutify/login') ?>"
             data-title="Your details"
             data-button="Join Free Today"
             data-accent="#006cb8"
             <?php if ($email !== null): ?>data-email="<?= View::e($email) ?>"<?php endif; ?>
             <?php if (!empty($name)): ?>data-name="<?= View::e((string) $name) ?>"<?php endif; ?>
             <?php if (!empty($business)): ?>data-business-name="<?= View::e((string) $business) ?>"<?php endif; ?>
             <?php if (!empty($website)): ?>data-website="<?= View::e((string) $website) ?>"<?php endif; ?>
        ></div>
        <script src="https://toutify.com/embed/signup.js" async></script>

        <?php /* Two ways out if the embed never arrives -- a blocked script, a
                 slow network, JavaScript off. Without them this page is a
                 heading and nothing else, which is a dead end at the exact
                 moment somebody is trying to give us their business. */ ?>
        <p class="signup-card__fallback">
          Form not loading?
          <a href="/auth/toutify/login?signup=1">Create your account on Toutify &rarr;</a>
        </p>
        <p class="signup-card__alt">
          Already have an account? <a href="/auth/toutify/login">Log in</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</section>
