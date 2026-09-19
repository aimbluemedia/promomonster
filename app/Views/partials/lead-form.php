<?php
/** @var string $role  @var string $source  @var string $cta  @var string $note */
use App\Support\Csrf;
use App\Support\View;

$done  = !empty($_SESSION['lead_done']);
$error = $_SESSION['lead_error'] ?? null;
unset($_SESSION['lead_done'], $_SESSION['lead_error']);
?>
<?php if ($done): ?>
  <div style="border:1px solid var(--line);border-radius:var(--radius-sm);padding:1.25rem;background:var(--tint);">
    <h3 style="margin-bottom:.4rem;">Got it — check your email.</h3>
    <p class="muted" style="margin:0;font-size:.94rem;">
      We&rsquo;ll be in touch within a day, from a real person, not an
      autoresponder.</p>
  </div>
<?php else: ?>
  <?php if ($error !== null): ?>
    <div class="alert" role="alert" style="margin-bottom:.8rem;"><?= View::e($error) ?></div>
  <?php endif; ?>

  <form class="form" method="post" action="/leads">
    <?= Csrf::field() ?>
    <input type="hidden" name="role" value="<?= View::e($role) ?>">
    <input type="hidden" name="source" value="<?= View::e($source) ?>">

    <div class="honeypot" aria-hidden="true">
      <label for="website_url_<?= View::e($role) ?>">Leave this field empty</label>
      <input id="website_url_<?= View::e($role) ?>" type="text" name="website_url" tabindex="-1" autocomplete="off">
    </div>

    <div class="form__row form__row--2">
      <div>
        <label class="sr-only" for="name_<?= View::e($role) ?>">Your name</label>
        <input class="field" id="name_<?= View::e($role) ?>" name="name" placeholder="Your name" autocomplete="name">
      </div>
      <div>
        <label class="sr-only" for="email_<?= View::e($role) ?>">Email</label>
        <input class="field" id="email_<?= View::e($role) ?>" name="email" type="email" required
               placeholder="you@example.com" autocomplete="email">
      </div>
    </div>

    <div class="form__row form__row--2">
      <div>
        <label class="sr-only" for="company_<?= View::e($role) ?>">Business name</label>
        <input class="field" id="company_<?= View::e($role) ?>" name="company"
               placeholder="<?= $role === 'agency' ? 'Agency name' : 'Business name' ?>" autocomplete="organization">
      </div>
      <div>
        <label class="sr-only" for="website_<?= View::e($role) ?>">Website</label>
        <input class="field" id="website_<?= View::e($role) ?>" name="website" placeholder="yoursite.com" autocomplete="url">
      </div>
    </div>

    <?php if ($role === 'business'): ?>
      <div>
        <label class="sr-only" for="vertical">What kind of business?</label>
        <select class="field" id="vertical" name="vertical">
          <option value="">What kind of business?</option>
          <?php foreach ([
            'landscaping'=>'Landscaping / lawn care','hvac'=>'HVAC / plumbing / electrical',
            'roofing'=>'Roofing / exteriors','dental'=>'Dental / medical','veterinary'=>'Veterinary',
            'restaurant'=>'Restaurant / café','salon'=>'Salon / barber / spa',
            'auto'=>'Auto repair','legal'=>'Law firm','realestate'=>'Real estate','other'=>'Something else',
          ] as $value => $label): ?>
            <option value="<?= View::e($value) ?>"><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php else: ?>
      <div>
        <label class="sr-only" for="goal_agency">How many clients?</label>
        <textarea class="field" id="goal_agency" name="goal" rows="3"
          placeholder="How many clients would this be for, and what do you sell them today?"></textarea>
      </div>
    <?php endif; ?>

    <button class="btn btn--primary btn--block" type="submit"><?= View::e($cta) ?></button>
    <p class="form__note"><?= View::e($note) ?></p>
  </form>
<?php endif; ?>
