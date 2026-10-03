<?php use App\Support\Billing; use App\Support\Csrf; use App\Support\Plans; use App\Support\View;
/** @var array $account @var array $user @var array $team @var array $plans */
$current   = (string) ($account['plan'] ?? Plans::FREE);
$requested = $account['requested_plan'] ?? null;

// Whether a card can be taken at all. Everything below reads this rather than
// assuming: with Stripe unconfigured the page has to go back to being the
// request queue it was, not offer a button that leads nowhere.
$canPay    = Billing::live();
$customer  = trim((string) ($account['stripe_customer_id'] ?? ''));
$subState  = (string) ($account['stripe_status'] ?? '');
$renews    = $account['subscription_renews_at'] ?? null;
$pastDue   = ($account['status'] ?? '') === 'past_due';
// A card Stripe is still charging. The Free button must not be a plain
// downgrade while this is true -- that would drop the plan and keep billing.
$onCard    = Billing::hasLiveSubscription($account);
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
      <?php if ($pastDue): ?>
        <strong>Your last payment did not go through.</strong> Nothing has stopped
        yet &mdash; Stripe retries for a few weeks. Update your card below and it
        clears itself.
      <?php elseif ($requested !== null): ?>
        <?= View::e(Plans::name((string) $requested)) ?> requested &mdash; we will be in
        touch. Nothing is charged until you agree to it.
      <?php elseif (!empty($renews) && Plans::isPaid($current)): ?>
        <?php /* The date comes out of the database, which runs on UTC, and is
                 shown as a day rather than a time -- "renews 2 Nov" is the whole
                 of what anybody wants from it, and a time would be wrong by
                 seven hours. */ ?>
        Renews <?= View::e(date('j M Y', strtotime((string) $renews))) ?>.
        <?php if ($subState === 'trialing'): ?>On trial until then.<?php endif; ?>
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
        <?php elseif ($key === Plans::FREE && $onCard): ?>
          <?php /* Cancelling happens at Stripe, because that is where the
                   charge is. requestPlan() refuses this case too -- the view is
                   not the authority on it -- but a button that leads to a
                   refusal is a worse screen than one that leads to the right
                   place. */ ?>
          <form method="post" action="/members/billing/manage" style="margin-top:.9rem;">
            <?= Csrf::field() ?>
            <button class="btn btn--ghost btn--block" type="submit">Cancel on Stripe</button>
          </form>
        <?php elseif (Billing::canCharge((string) $key)): ?>
          <?php /* A real purchase. Posts rather than links, with a token: a GET
                   that creates a Stripe customer and a checkout session is one
                   an image tag on somebody else's page can fire. */ ?>
          <form method="post" action="/members/billing/start" style="margin-top:.9rem;">
            <?= Csrf::field() ?>
            <input type="hidden" name="plan" value="<?= View::e($key) ?>">
            <?php /* Already paying? The button goes to Stripe's portal, not to a
                     second checkout -- start() sends it there, so that Stripe
                     prorates the change rather than us charging twice for one
                     month. The label says so, because landing on an invoice
                     screen after pressing "Subscribe" reads as a mistake. */ ?>
            <button class="btn btn--primary btn--block" type="submit">
              <?= Plans::isPaid($current)
                  ? 'Change plan on Stripe'
                  : 'Subscribe &mdash; $' . (int) $plan['price'] . '/mo' ?>
            </button>
          </form>
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
  <?php if ($canPay): ?>
    <p class="form__note" style="margin-top:1rem;">
      Payment is taken by Stripe on their own page &mdash; your card details never
      reach us. Cancel whenever you like from
      <?= $customer === '' ? 'this page' : '<strong>Manage billing</strong> below' ?>,
      and you keep everything you have collected.
      <?php if (Billing::testMode()): ?>
        <br><strong>Test mode:</strong> these buttons will not take real money.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <p class="form__note" style="margin-top:1rem;">
      Card payments are not switched on yet, so moving up is a request rather than a
      purchase. Moving down to Free takes effect straight away.
    </p>
  <?php endif; ?>
<?php endif; ?>
</div>

<?php if ($customer !== '' && $canPay): ?>
  <?php /* Card, invoices, receipts and cancelling all live on Stripe's own
           portal. Building them here would mean building proration, dunning
           and invoice history to go with them, and a subtly wrong invoice is a
           dispute rather than a bug report. */ ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:1.05rem;margin-bottom:.4rem;">Billing</h2>
    <p class="muted" style="font-size:.9rem;margin:0 0 1rem;">
      Your card, your receipts, and cancelling &mdash; all on Stripe, where the
      card lives.
      <?php if ($subState !== ''): ?>
        Stripe currently has this subscription as
        <strong><?= View::e(str_replace('_', ' ', $subState)) ?></strong>.
      <?php endif; ?>
    </p>
    <form method="post" action="/members/billing/manage">
      <?= Csrf::field() ?>
      <button class="btn btn--ghost" type="submit">Manage billing</button>
    </form>
  </div>
<?php endif; ?>

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
      Change your plan above.
      <?= $canPay
          ? 'Payments are taken by Stripe.'
          : 'Billing runs by hand until card payments are switched on.' ?></p>
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
