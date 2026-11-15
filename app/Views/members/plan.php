<?php
use App\Support\Billing;
use App\Support\Csrf;
use App\Support\Plans;
use App\Support\View;
/**
 * The plan page.
 *
 * Three questions, in the order somebody asks them: what am I on, what have
 * I used, and what would the others give me. The banner answers the first,
 * the two tiles the second, the cards the third.
 *
 * The ladder across the banner exists because a price list does not show
 * somebody where they are. A row of dots with one filled in does, in less
 * space than a sentence, and it makes "what is above me" a glance rather
 * than a comparison.
 *
 * Every figure comes from the catalogue or from the counters that enforce
 * it. Nothing on this page is typed twice.
 *
 * @var array  $account
 * @var array  $plans    Plans::selectable()
 * @var string $current
 * @var array  $sending  SendLimit::check()
 * @var array  $drafts   DraftStore::month()
 */
$name  = trim((string) ($account['name'] ?? ''));
$above = Plans::above($current);
$ladder = array_keys($plans);
?>

<?php /* ---- Where you are ------------------------------------------------ */ ?>
<section class="planhero">
  <div class="planhero__top">
    <div>
      <p class="planhero__eyebrow">Your membership</p>
      <h1 class="planhero__h">
        <?= View::e(Plans::name($current)) ?>, and what the rest would add
      </h1>
      <?php if ($name !== ''): ?>
        <p class="planhero__who"><?= View::e($name) ?></p>
      <?php endif; ?>
    </div>

    <div class="planhero__now">
      <p class="planhero__nowlabel">Current plan</p>
      <p class="planhero__nowname"><?= View::e(Plans::name($current)) ?></p>
      <p class="planhero__nowprice"><?= View::e(Plans::money($current)) ?> <span>/month</span></p>
    </div>
  </div>

  <?php /* The ladder. Every step is a real plan from the catalogue, so a tier
           added or removed changes this without anybody editing a view. */ ?>
  <ol class="ladder">
    <?php $passed = true; ?>
    <?php foreach ($ladder as $key): ?>
      <?php
        $here = $key === $current;
        $done = $passed && !$here;
        if ($here) { $passed = false; }
      ?>
      <li class="ladder__step<?= $here ? ' is-here' : ($done ? ' is-done' : '') ?>">
        <span class="ladder__dot" aria-hidden="true"><?= $done || $here ? '&#10003;' : '' ?></span>
        <span class="ladder__name"><?= View::e(Plans::name($key)) ?></span>
        <span class="ladder__price"><?= View::e(Plans::money($key)) ?>/month</span>
        <?php if ($here): ?>
          <span class="ladder__you">You are here</span>
        <?php elseif (in_array($key, $above, true)): ?>
          <a class="ladder__go" href="#compare">Compare &rarr;</a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <p class="planhero__foot">
    <strong><?= Plans::featureCount($current) ?> of <?= count(Plans::ABILITIES) ?></strong>
    features are on your plan
  </p>
</section>

<?php /* ---- What you have used --------------------------------------- */ ?>
<div class="planuse">
  <div class="planuse__tile">
    <p class="planuse__label">Review requests this month</p>
    <p class="planuse__n">
      <?= (int) ($sending['month_used'] ?? 0) ?>
      <span>/ <?= $sending['month_limit'] === null ? '&infin;' : (int) $sending['month_limit'] ?></span>
    </p>
  </div>
  <div class="planuse__tile">
    <p class="planuse__label">Replies drafted this month</p>
    <?php if (Plans::can($current, Plans::REPLY_DRAFTS)): ?>
      <p class="planuse__n">
        <?= (int) ($drafts['used'] ?? 0) ?>
        <span>/ <?= $drafts['limit'] === null ? '&infin;' : (int) ($drafts['limit'] ?? 0) ?></span>
      </p>
    <?php else: ?>
      <?php /* "0 / 0" reads as a counter that broke. A plan that does not
               include drafting should say so, and name the plans that do
               rather than a number nobody can move. */ ?>
      <?php $withDrafts = array_map(
              static fn (string $k): string => Plans::name($k),
              Plans::plansWith(Plans::REPLY_DRAFTS)
            ); ?>
      <p class="planuse__n planuse__n--off">&mdash;</p>
      <p class="planuse__off">
        <?= View::e(implode(' or ', $withDrafts)) ?>
        <?= count($withDrafts) === 1 ? 'drafts replies' : 'draft replies' ?> for you
      </p>
    <?php endif; ?>
  </div>
</div>

<?php /* ---- What the others would add ---------------------------------- */ ?>
<div class="admin-title" id="compare">
  <div>
    <p class="eyebrow">Compare plans</p>
    <h2 class="plans__h">Pick the one that fits how often you ask</h2>
    <p class="muted plans__sub">
      Every plan is part of your Toutify membership. Move up or down whenever
      you like &mdash; nothing changes until you say so.
    </p>
  </div>
</div>

<div class="plangrid">
  <?php foreach ($plans as $key => $plan): ?>
    <?php
      $isCurrent = $key === $current;
      $cadence   = Plans::cadence((string) $key);
      $canBuy    = !$isCurrent && Plans::isPaid((string) $key)
                   && in_array($current, Plans::SELECTABLE, true);
    ?>
    <section class="plancard<?= $isCurrent ? ' plancard--current' : '' ?>">
      <?php if ($isCurrent): ?>
        <p class="plancard__flag">Your plan</p>
      <?php endif; ?>

      <p class="plancard__eyebrow"><?= View::e(strtoupper(Plans::name((string) $key))) ?></p>
      <p class="plancard__price">
        <?= View::e(Plans::money((string) $key)) ?><span>/month</span>
      </p>
      <p class="plancard__for">
        <strong>Best for:</strong> <?= View::e(Plans::bestFor((string) $key)) ?>
      </p>

      <?php /* The rhythm, not a time saving. We have no data for "saves you
               ten hours a month" and will not print one; the allowance under
               it is a figure the sender actually enforces. */ ?>
      <div class="plancard__rhythm">
        <p class="plancard__every"><?= View::e($cadence['every']) ?></p>
        <p class="plancard__what"><?= View::e($cadence['what']) ?></p>
        <p class="plancard__count"><?= View::e($cadence['count']) ?></p>
      </div>

      <?php $extra = Plans::includes((string) $key); ?>
      <p class="plancard__heading"><?= View::e(Plans::listHeading((string) $key)) ?></p>

      <ul class="plancard__list">
        <?php foreach (Plans::card((string) $key) as $line): ?>
          <li class="<?= $line['included'] ? 'yes' : 'no' ?>">
            <span aria-hidden="true"><?= $line['included'] ? '&#10003;' : '&#215;' ?></span>
            <span><?= View::e($line['text']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($extra['items'] !== []): ?>
        <p class="plancard__heading plancard__heading--extra">Comes with every plan</p>
        <ul class="plancard__list">
          <?php foreach ($extra['items'] as $item): ?>
            <li class="yes"><span aria-hidden="true">&#10003;</span><span><?= View::e($item) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="plancard__act">
        <?php if ($isCurrent): ?>
          <p class="plancard__on">&#10003; Your current plan</p>
        <?php elseif ($canBuy && Billing::canCharge((string) $key)): ?>
          <form method="post" action="/members/billing/start">
            <?= Csrf::field() ?>
            <input type="hidden" name="plan" value="<?= View::e((string) $key) ?>">
            <button class="btn btn--primary" type="submit">
              Upgrade to <?= View::e(Plans::name((string) $key)) ?> &rarr;
            </button>
          </form>
        <?php elseif (in_array($current, Plans::SELECTABLE, true)): ?>
          <?php /* Card payments are not switched on, so this records a request
                   rather than pretending to charge. Moving DOWN to Free takes
                   effect at once: refusing it would be holding an account
                   hostage over a plan somebody no longer wants. */ ?>
          <form method="post" action="/members/plan">
            <?= Csrf::field() ?>
            <input type="hidden" name="plan" value="<?= View::e((string) $key) ?>">
            <button class="btn <?= Plans::isPaid((string) $key) ? 'btn--primary' : 'btn--ghost' ?>"
                    type="submit">
              <?= Plans::isPaid((string) $key)
                  ? 'Request ' . View::e(Plans::name((string) $key)) . ' &rarr;'
                  : 'Move to ' . View::e(Plans::name((string) $key)) ?>
            </button>
          </form>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<p class="muted" style="margin-top:1.5rem;font-size:.88rem;">
  Card payments are not switched on yet, so moving up records a request and we
  arrange it with you. Moving down to Free takes effect straight away.
</p>
