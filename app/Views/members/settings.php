<?php use App\Support\Csrf; use App\Support\Plans; use App\Support\View;
/** @var array $account @var array $user @var array $team @var array $plans */
$current   = (string) ($account['plan'] ?? Plans::FREE);
$requested = $account['requested_plan'] ?? null;
?>
<div class="admin-title"><h1>Settings</h1></div>

<div class="plan-strip" style="margin-bottom:1.5rem;">
  <div>
    <span class="plan-strip__now">
      <?= View::e(Plans::name($current)) ?>
      <?php if (Plans::price($current) > 0): ?>
        &middot; $<?= (int) Plans::price($current) ?>/mo
      <?php endif; ?>
    </span>
    <p class="plan-strip__meta">
      <?php if ($requested !== null): ?>
        <?= View::e(Plans::name((string) $requested)) ?> requested &mdash; we will be in
        touch. Nothing is charged until you agree to it.
      <?php else: ?>
        Your current plan.
      <?php endif; ?>
    </p>
  </div>
</div>

<div class="card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1.05rem;margin-bottom:.9rem;">Change plan</h2>
<?php if (!in_array($current, Plans::SELECTABLE, true)): ?>
  <p class="muted" style="font-size:.9rem;margin:0;">
    Your account is on <?= View::e(Plans::name($current)) ?>, which we arrange with you
    directly rather than through the self-serve plans. Email us for any change.
  </p>
<?php else: ?>
  <div class="plan-picker__grid">
    <?php foreach ($plans as $key => $plan): ?>
      <div class="plan-option<?= $key === $current ? ' is-selected' : '' ?>">
        <span class="plan-option__head">
          <span class="plan-option__name"><?= View::e($plan['name']) ?></span>
          <span class="plan-option__price">
            <?= $plan['price'] === 0 ? 'Free' : '$' . (int) $plan['price'] ?>
            <?php if ($plan['price'] > 0): ?><small>/mo</small><?php endif; ?>
          </span>
        </span>
        <span class="plan-option__blurb"><?= View::e($plan['tagline']) ?></span>
        <?php if ($key === $current): ?>
          <p class="form__note" style="margin-top:.9rem;"><strong>Current plan</strong></p>
        <?php elseif ((string) $requested === (string) $key): ?>
          <p class="form__note" style="margin-top:.9rem;">Requested &mdash; awaiting setup</p>
        <?php else: ?>
          <form method="post" action="/members/plan" style="margin-top:.9rem;">
            <?= Csrf::field() ?>
            <input type="hidden" name="plan" value="<?= View::e($key) ?>">
            <button class="btn <?= Plans::isPaid($key) ? 'btn--primary' : 'btn--ghost' ?> btn--block"
                    type="submit">
              <?= Plans::isPaid($key) ? 'Request ' . View::e($plan['name']) : 'Move to Free' ?>
            </button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="form__note" style="margin-top:1rem;">
    Card payments are not switched on yet, so moving up is a request rather than a
    purchase. Moving down to Free takes effect straight away.
  </p>
<?php endif; ?>
</div>

<div class="grid grid--2">
  <div class="card">
    <h2 style="font-size:1.05rem;margin-bottom:.9rem;">Account</h2>
    <ul class="checklist">
      <li><strong>Business</strong> <?= View::e($account['name'] ?? '—') ?></li>
      <li><strong>Plan</strong> <?= View::e(Plans::name($current)) ?></li>
      <li><strong>Status</strong> <?= View::e(ucfirst((string) ($account['status'] ?? '—'))) ?></li>
      <li><strong>Signed in as</strong> <?= View::e($user['email'] ?? '') ?></li>
    </ul>
    <p class="muted" style="margin-top:1.1rem;font-size:.88rem;">
      Change your plan above. Billing runs by hand until card payments are
      switched on.</p>
  </div>

  <div class="card">
    <h2 style="font-size:1.05rem;margin-bottom:.9rem;">Your team</h2>
    <?php if ($team === []): ?>
      <p class="muted" style="font-size:.9rem;margin:0;">Just you for now.</p>
    <?php else: ?>
      <ul class="checklist">
        <?php foreach ($team as $m): ?>
          <li><strong><?= View::e(trim($m['first_name'] . ' ' . $m['last_name'])) ?></strong>
            <?= View::e($m['email']) ?> · <?= View::e($m['role']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p class="muted" style="margin-top:1.1rem;font-size:.88rem;">
      Adding teammates ships with the sending release — the people who ask
      customers need their own logins.</p>
  </div>
</div>
