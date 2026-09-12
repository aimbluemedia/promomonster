<?php
use App\Support\Csrf;
use App\Support\View;
/** @var array $plans @var string $chosen @var int $min @var ?string $error @var array $old */
?>
<section class="section">
  <div class="container center" style="max-width:44rem;">
    <p class="eyebrow">Get started</p>
    <h1>Create your account</h1>
    <p class="lede">Start on Free, no card. Move up when the requests are going out
      and you want SMS and the website widget.</p>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container" style="max-width:56rem;">

    <?php if ($error !== null): ?>
      <div class="alert" role="alert" style="margin-bottom:1.5rem;"><?= View::e($error) ?></div>
    <?php endif; ?>

    <form class="form" method="post" action="/members/signup">
      <?= Csrf::field() ?>

      <fieldset class="plan-picker">
        <legend>Choose a plan</legend>
        <div class="plan-picker__grid">
          <?php foreach ($plans as $key => $plan): ?>
            <label class="plan-option<?= $key === $chosen ? ' is-selected' : '' ?>">
              <input type="radio" name="plan" value="<?= View::e($key) ?>"
                     <?= $key === $chosen ? 'checked' : '' ?>>
              <span class="plan-option__head">
                <span class="plan-option__name"><?= View::e($plan['name']) ?></span>
                <span class="plan-option__price">
                  <?= $plan['price'] === 0 ? 'Free' : '$' . (int) $plan['price'] ?>
                  <?php if ($plan['price'] > 0): ?><small>/mo</small><?php endif; ?>
                </span>
              </span>
              <span class="plan-option__blurb"><?= View::e($plan['tagline']) ?></span>
              <ul class="checklist checklist--sm">
                <?php foreach (array_slice($plan['features'], 0, 4) as [$label, $on]): ?>
                  <li<?= $on ? '' : ' class="is-off"' ?>><?= View::e($label) ?></li>
                <?php endforeach; ?>
              </ul>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="form__note" style="margin-top:.75rem;">
          Card payments are not switched on yet. Pick Pro or Premium and your account
          starts on Free while we set the subscription up with you — nothing is
          charged, and nothing happens without you agreeing to it first.
        </p>
      </fieldset>

      <div class="form__row form__row--2">
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
      </div>

      <div class="form__row form__row--2">
        <div>
          <label for="email">Work email</label>
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
      </div>

      <?php /* Honeypot. Hidden from people, irresistible to bots. */ ?>
      <div class="honeypot" aria-hidden="true">
        <label for="website_url">Leave this empty</label>
        <input id="website_url" name="website_url" type="text" tabindex="-1" autocomplete="off">
      </div>

      <label class="consent">
        <input type="checkbox" name="terms" value="1" required>
        <span>I agree to the <a href="/terms">terms</a> and
          <a href="/privacy">privacy policy</a>.</span>
      </label>

      <button class="btn btn--primary btn--block" type="submit">Create my account</button>

      <p class="form__note center" style="margin-top:1rem;">
        Already have an account? <a href="/members/login" style="color:var(--brand);font-weight:600;">Sign in</a>.
      </p>
    </form>
  </div>
</section>
