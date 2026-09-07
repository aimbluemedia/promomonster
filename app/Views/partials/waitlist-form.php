<?php
/** @var string $role  @var string $source  @var string $cta  @var string $note */
use App\Support\Csrf;
use App\Support\View;

$isBusiness = $role === 'business';
$done  = !empty($_SESSION['waitlist_done']);
$error = $_SESSION['waitlist_error'] ?? null;
unset($_SESSION['waitlist_done'], $_SESSION['waitlist_error']);
?>
<?php if ($done): ?>
  <div class="card">
    <h3>You&rsquo;re on the list.</h3>
    <p class="muted" style="margin:.5rem 0 0;font-size:.9375rem;">
      <?= $isBusiness
        ? 'We&rsquo;ll email you within a day or two to set up your first study. Early studies are run hands-on, so you&rsquo;ll be talking to a person, not a form.'
        : 'We open spots in batches so there&rsquo;s always work available when you log in. You&rsquo;ll get an email as soon as yours is ready.' ?>
    </p>
  </div>
<?php else: ?>
  <?php if ($error !== null): ?>
    <div class="alert alert--error" role="alert" style="margin-bottom:.75rem;"><?= View::e($error) ?></div>
  <?php endif; ?>

  <form class="form" method="post" action="/waitlist">
    <?= Csrf::field() ?>
    <input type="hidden" name="role" value="<?= View::e($role) ?>">
    <input type="hidden" name="source" value="<?= View::e($source) ?>">

    <div class="honeypot" aria-hidden="true">
      <label for="website_url_<?= View::e($role) ?>">Leave this field empty</label>
      <input id="website_url_<?= View::e($role) ?>" type="text" name="website_url" tabindex="-1" autocomplete="off">
    </div>

    <div class="form__row form__row--2">
      <div>
        <label class="sr-only" for="name_<?= View::e($role) ?>">Name</label>
        <input class="field" id="name_<?= View::e($role) ?>" name="name" placeholder="Your name" autocomplete="name">
      </div>
      <div>
        <label class="sr-only" for="email_<?= View::e($role) ?>">Email</label>
        <input class="field" id="email_<?= View::e($role) ?>" name="email" type="email" required
               placeholder="you@example.com" autocomplete="email">
      </div>
    </div>

    <?php if ($isBusiness): ?>
      <div class="form__row form__row--2">
        <div>
          <label class="sr-only" for="company">Company</label>
          <input class="field" id="company" name="company" placeholder="Company" autocomplete="organization">
        </div>
        <div>
          <label class="sr-only" for="website">Website</label>
          <input class="field" id="website" name="website" placeholder="yoursite.com" autocomplete="url">
        </div>
      </div>
      <div>
        <label class="sr-only" for="goal">What do you want to find out?</label>
        <textarea class="field" id="goal" name="goal" rows="3"
          placeholder="What do you want to find out? (e.g. why visitors leave my pricing page)"></textarea>
      </div>
    <?php else: ?>
      <div class="form__row form__row--2">
        <div>
          <label class="sr-only" for="region">State</label>
          <input class="field" id="region" name="region" placeholder="State" autocomplete="address-level1">
        </div>
        <div>
          <label class="sr-only" for="postalCode">ZIP code</label>
          <input class="field" id="postalCode" name="postalCode" placeholder="ZIP code"
                 autocomplete="postal-code" inputmode="numeric">
        </div>
      </div>
    <?php endif; ?>

    <button class="btn <?= $isBusiness ? 'btn--primary' : 'btn--earn' ?> btn--block" type="submit">
      <?= View::e($cta) ?>
    </button>
    <p class="form__note"><?= View::e($note) ?></p>
  </form>
<?php endif; ?>
