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

// Every plan that grants it, cheapest first.
$options = $needed === null ? [] : Plans::plansWith($ability);

/*
 * The callout at the top, and it has to be true.
 *
 * "Premium only" is the right words when Premium really is the only plan
 * that grants the thing. It is the wrong words on a screen the Pro plan
 * opens -- and two of the three gates that render this panel are Pro gates,
 * so a hardcoded "Premium only" would be telling a member the cheaper plan
 * will not do, which is both false and expensive for them.
 *
 * So it is generated: one plan grants it and the badge says "Premium only";
 * more than one and it says "Pro and above", which is the same promise
 * without the lie. Move an ability between plans and this follows.
 */
$only  = count($options) === 1;
$badge = $needed === null
    ? 'Arranged with us'
    : ($only
        ? Plans::name($options[0]) . ' only'
        : Plans::name($options[0]) . ' and above');

// Amber when a single plan holds it, the brand blue when more than one do.
// The same two accents the steps on the review page use, meaning the same
// two things, so a member learns the colour once.
$tone = $needed === null ? '' : ($only ? ' locked--only' : ' locked--up');
?>
<div class="locked<?= $tone ?>">
  <div class="locked__band">
    <p class="locked__badge"><?= View::e($badge) ?></p>
    <p class="locked__on">
      You are on <strong><?= View::e(Plans::name($current)) ?></strong>
    </p>
  </div>

  <div class="locked__body">
    <h2 class="locked__title"><?= View::e($label) ?> is not on your plan</h2>

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
      <p class="locked__price">
        <?php foreach ($options as $i => $key): ?>
          <?php if ($i > 0): ?><span class="locked__or">or</span><?php endif; ?>
          <span class="locked__plan">
            <strong><?= View::e(Plans::name($key)) ?></strong>
            <?= View::e(Plans::money($key)) ?>/month
          </span>
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
        <a class="btn btn--ghost" href="/members#plans">Compare the plans</a>
      </div>
    <?php endif; ?>
  </div>
</div>
