<?php
use App\Support\Billing;
use App\Support\Csrf;
use App\Support\Plans;
use App\Support\View;
/**
 * What a section shows instead of itself when the plan does not include it.
 *
 * One partial for all four gates, so a refusal reads the same wherever it
 * happens and the button always points at the cheapest plan that actually
 * grants the thing -- worked out by Plans::cheapestWith() rather than typed,
 * because "Upgrade to Pro" hard-coded next to a feature that later moves to
 * Premium is a promise the checkout will not keep.
 *
 * It never scolds. Somebody reading this came here to do something and has
 * just been told no; the useful content is what it costs and what it unlocks,
 * not a lecture about their plan.
 *
 * @var array   $account
 * @var string  $ability  one of the Plans ability constants
 * @var ?string $blurb    one sentence on what it is for, in the second person
 */
$current = (string) ($account['plan'] ?? Plans::FREE);
$needed  = Plans::cheapestWith($ability);
$blurb   = $blurb ?? null;
$label   = Plans::ABILITY_LABELS[$ability] ?? 'This';
?>
<div class="locked">
  <div class="locked__head">
    <span class="locked__pill"><?= View::e(Plans::name($current)) ?> plan</span>
    <h2 class="locked__title"><?= View::e($label) ?> is not on your plan</h2>
  </div>

  <?php if ($blurb !== null): ?>
    <p class="locked__blurb"><?= View::e($blurb) ?></p>
  <?php endif; ?>

  <?php if ($needed === null): ?>
    <?php /* No purchasable plan grants it. Says so plainly rather than
             offering a button that leads nowhere. */ ?>
    <p class="locked__blurb">This one we arrange directly &mdash; email us and a
      person will sort it.</p>
  <?php else: ?>
    <?php /* Every plan that grants it, not just the cheapest. Naming only the
             cheapest hides the fact that the tier they were already thinking
             about covers it too, and somebody who upgrades to Pro for this and
             then finds they needed Premium for the next thing has been led
             there one plan at a time. */ ?>
    <?php $options = Plans::plansWith($ability); ?>
    <p class="locked__price">
      Upgrade to
      <?php foreach ($options as $i => $key): ?>
        <?php if ($i > 0): ?> or <?php endif; ?>
        <strong><?= View::e(Plans::name($key)) ?></strong><?= $i === 0 ? ',' : '' ?>
        <?= View::e(Plans::money($key)) ?>/month<?= $i === count($options) - 1 ? '.' : '' ?>
      <?php endforeach; ?>
    </p>

    <div class="locked__act">
      <?php if (in_array($current, Plans::SELECTABLE, true) && Billing::canCharge($needed)): ?>
        <form method="post" action="/members/billing/start">
          <?= Csrf::field() ?>
          <input type="hidden" name="plan" value="<?= View::e($needed) ?>">
          <button class="btn btn--primary" type="submit">
            Upgrade to <?= View::e(Plans::name($needed)) ?>
          </button>
        </form>
      <?php elseif (in_array($current, Plans::SELECTABLE, true)): ?>
        <?php /* Card payments are not switched on, so this records a request
                 rather than pretending to charge. */ ?>
        <form method="post" action="/members/plan">
          <?= Csrf::field() ?>
          <input type="hidden" name="plan" value="<?= View::e($needed) ?>">
          <button class="btn btn--primary" type="submit">
            Request <?= View::e(Plans::name($needed)) ?>
          </button>
        </form>
      <?php endif; ?>
      <a class="btn btn--ghost" href="/members">Compare the plans</a>
    </div>
  <?php endif; ?>
</div>
