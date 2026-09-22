<?php
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Plans;
use App\Support\View;
/** @var int $min @var ?string $error @var array $old */
?>
<?php /* One plan, so no plan picker.

         The paid tiers came off this page deliberately. Asking somebody to
         pick a tier before they have seen the product is asking for a decision
         they have no way to make, and every extra choice on a signup form
         costs signups. The upgrade lives in members settings, which is the
         right place for it: you can only sensibly ask for more of a thing once
         you have used it.

         The important half of this change is in SignupController, not here.
         Deleting the radio buttons does not delete the server-side path they
         fed, so the controller no longer reads a plan from the request at all
         -- a hand-crafted POST cannot log an upgrade request nobody made. */ ?>
<section class="section" style="padding-bottom:0;">
  <div class="container center" style="max-width:40rem;">
    <p class="eyebrow">Get started</p>
    <h1>Create your free account</h1>
    <p class="lede" style="margin-inline:auto;">Get your review link and your QR
      code, and start asking your customers properly. Takes about a minute.</p>

    <ul class="signup-facts">
      <?php foreach ([
        'No subscription',
        'No credit card',
        // The headline figure only. The pacing is spelled out on the pricing
        // page; here it made the third tick wrap onto two lines and unbalance
        // a row whose whole job is to look effortless.
        Plans::limit(Plans::FREE, 'requests_per_month') . ' review requests a month',
      ] as $fact): ?>
        <li><?= Icon::render('check') ?><?= View::e($fact) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?php /* Tight against the block above it: the reassurances and the form are one
         thought, and a full section gap between them reads as two pages. */ ?>
<section class="section" style="padding-top:2.5rem;">
  <div class="container" style="max-width:34rem;">

    <?php if ($error !== null): ?>
      <div class="alert" role="alert" style="margin-bottom:1.5rem;"><?= View::e($error) ?></div>
    <?php endif; ?>

    <form class="form card" method="post" action="/members/signup" style="padding:1.75rem;">
      <?= Csrf::field() ?>

      <div>
        <label for="business">Business name</label>
        <input class="field" id="business" name="business" type="text" required maxlength="160"
               value="<?= View::e($old['business'] ?? '') ?>"
               placeholder="Acme Pools" autocomplete="organization">
      </div>

      <div>
        <label for="name">Your name</label>
        <input class="field" id="name" name="name" type="text" required maxlength="120"
               value="<?= View::e($old['name'] ?? '') ?>"
               placeholder="Dana Okafor" autocomplete="name">
      </div>

      <div>
        <label for="email">Email</label>
        <input class="field" id="email" name="email" type="email" required
               value="<?= View::e($old['email'] ?? '') ?>"
               placeholder="you@yourbusiness.com" autocomplete="username">
      </div>

      <div>
        <label for="password">Password</label>
        <input class="field" id="password" name="password" type="password" required
               minlength="<?= (int) $min ?>" placeholder="At least <?= (int) $min ?> characters"
               autocomplete="new-password">
      </div>

      <?php /* Honeypot. Hidden from people, irresistible to bots. */ ?>
      <div class="honeypot" aria-hidden="true">
        <label for="website_url">Leave this empty</label>
        <input id="website_url" name="website_url" type="text" tabindex="-1" autocomplete="off">
      </div>

      <button class="btn btn--primary btn--xl btn--block" type="submit">Sign Up FREE</button>

      <?php /* The tick box is gone -- it was one more thing to do before the
               button, and nobody reads it anyway. The sentence stays. Terms are
               only worth having if somebody was shown them at the moment they
               agreed, and a privacy notice at the point of collection is a
               legal requirement rather than a nicety. This is how Stripe,
               GitHub and Google all do it: consent implied by the act, with the
               links right there. */ ?>
      <p class="form__note center" style="margin-top:.9rem;">
        Free is a plan, not a trial. Nothing to cancel, and we never ask for a card.
      </p>
      <?php /* Its own line, and quieter: reassurance and fine print do different
               jobs, and running them together makes the reassurance read like
               small print too. */ ?>
      <p class="form__note center" style="margin-top:.5rem;color:var(--muted);font-size:.78rem;">
        By signing up you agree to our <a href="/terms">terms</a> and
        <a href="/privacy">privacy policy</a>.
      </p>
    </form>

    <p class="form__note center" style="margin-top:1.5rem;">
      Already have an account? <a href="/members/login" style="color:var(--brand);font-weight:600;">Sign in</a>.
    </p>

    <?php /* Pricing rather than a second signup route: every account starts on
             Free whichever button is pressed, and that page carries Premium,
             the Pro tier and the link on to agencies. One destination, and no
             way to end up on a form that promises something it cannot do. */ ?>
    <p class="form__note center" style="margin-top:.5rem;">
      Need Premium or an agency plan?
      <a href="/pricing" style="color:var(--brand);font-weight:600;">View options</a>.
    </p>
  </div>
</section>
