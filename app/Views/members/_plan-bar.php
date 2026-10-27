<?php
use App\Support\Plans;
use App\Support\View;
/**
 * The line under the nav that says what plan this is, on every page.
 *
 * It exists because the plan now decides whether three of the screens in the
 * nav above it work. Without it, a member meets a locked section with no idea
 * why, and "why can't I do this" is a support email rather than a sale.
 *
 * Names the plans above theirs in plain text rather than only linking to them:
 * somebody who has not clicked anything should still be able to read what the
 * next tier costs. On the top plan it says so and offers nothing, and on
 * Partner it offers nothing at all -- that one is arranged by hand and must
 * never be shown a buy button.
 *
 * @var array $account
 */
$current = (string) ($account['plan'] ?? Plans::FREE);
$higher  = Plans::above($current);

// Only a plan somebody could buy has a price worth printing. Partner's entry
// in the catalogue is 0 because it is negotiated, not because it is free, and
// telling a reseller their plan costs $0.00/month is worse than saying
// nothing.
$onLadder = in_array($current, Plans::SELECTABLE, true);
?>
<div class="planbar">
  <div class="container planbar__in">
    <span class="planbar__mine">
      <span class="planbar__label">Your plan</span>
      <strong><?= View::e(Plans::name($current)) ?></strong>
      <?php if ($onLadder): ?>
        <span class="planbar__price"><?= View::e(Plans::money($current)) ?>/month</span>
      <?php else: ?>
        <span class="planbar__price">arranged with you directly</span>
      <?php endif; ?>
    </span>

    <?php if ($higher !== []): ?>
      <span class="planbar__more">
        <span class="planbar__label">Upgrade</span>
        <?php foreach ($higher as $i => $key): ?>
          <?php if ($i > 0): ?><span class="planbar__or">or</span><?php endif; ?>
          <a href="/members#plans">
            <?= View::e(Plans::name($key)) ?>
            <?= View::e(Plans::money($key)) ?>/month
          </a>
        <?php endforeach; ?>
      </span>
    <?php elseif ($onLadder): ?>
      <span class="planbar__top">Everything is included on this plan.</span>
    <?php endif; ?>
  </div>
</div>
