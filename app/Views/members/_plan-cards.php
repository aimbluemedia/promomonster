<?php
use App\Support\Billing;
use App\Support\Csrf;
use App\Support\Plans;
use App\Support\View;
/**
 * The three plans, with the member's own one marked.
 *
 * A partial rather than part of the dashboard, because Settings wants the same
 * three cards and two copies would drift the moment a price moved.
 *
 * Every line inside a card comes from Plans::card(), which is generated from
 * the same table the gates read. Nothing here is typed out: a card that said
 * "Add reviews by hand" while the gate disagreed would be lying to somebody
 * who had paid, so the two cannot be edited apart.
 *
 * @var array $account
 */
$current  = (string) ($account['plan'] ?? Plans::FREE);
$onLadder = in_array($current, Plans::SELECTABLE, true);
?>
<div class="plan-cards">
  <?php foreach (Plans::selectable() as $key => $plan): ?>
    <?php
      $isCurrent = $key === $current;
      $lines     = Plans::card((string) $key);
      // Partner sits off the self-serve ladder, so nothing is offered to it:
      // a reseller agreement must not be replaceable by a stray click on a
      // $19 button.
      $canBuy    = $onLadder && !$isCurrent && Plans::isPaid((string) $key);
    ?>
    <div class="plan-card<?= $isCurrent ? ' is-current' : '' ?>">
      <?php if ($isCurrent): ?>
        <span class="plan-card__flag">Current</span>
      <?php endif; ?>

      <span class="plan-card__name"><?= View::e($plan['name']) ?></span>
      <?php /* "$0.00", not "Free". A price column where one row says a word
               and the next says a number reads as two different kinds of
               thing, and the word is the one people skip over. */ ?>
      <span class="plan-card__price">
        <?= View::e(Plans::money((string) $key)) ?><small>/month</small>
      </span>

      <?php /* A details element rather than a script. It needs no JavaScript,
               it opens on its own when somebody searches the page in their
               browser, and it keeps working in an email-client preview and
               with a screen reader -- none of which a div and a click handler
               would do. */ ?>
      <?php /* Closed by default, current plan included. These sit above the
               content of every page now, so an open panel is three screens of
               plan detail between the nav and the thing somebody came to do.
               One click opens it, and the summary says what is behind it. */ ?>
      <details class="plan-card__more">
        <summary>What is included</summary>
        <p class="plan-card__heading">Includes monthly</p>
        <ul class="plan-card__list">
          <?php foreach ($lines as $line): ?>
            <li class="<?= $line['included'] ? 'yes' : 'no' ?>">
              <span aria-hidden="true"><?= $line['included'] ? '&#10003;' : '&#10005;' ?></span>
              <span><?= View::e($line['text']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php /* What comes with every plan, listed on every plan. The same two
                 lines on Free as on Premium is the point: somebody weighing
                 the tiers should see they get these either way, rather than
                 wondering whether the cheap one leaves them out. */ ?>
        <?php $extra = Plans::includes((string) $key); ?>
        <p class="plan-card__heading plan-card__heading--extra">
          <?= View::e($extra['heading']) ?>
        </p>
        <ul class="plan-card__list">
          <?php foreach ($extra['items'] as $item): ?>
            <li class="yes">
              <span aria-hidden="true">&#10003;</span>
              <span><strong><?= View::e($item) ?></strong></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>

      <div class="plan-card__act">
        <?php if ($isCurrent): ?>
          <span class="plan-card__on">You are on this plan</span>
        <?php elseif (!$onLadder): ?>
          <span class="plan-card__on">Arranged with you directly</span>
        <?php elseif ($canBuy && Billing::canCharge((string) $key)): ?>
          <?php /* POST with a token, not a link: a GET that creates a Stripe
                   customer and a checkout session is one an image tag on
                   another site can fire. */ ?>
          <form method="post" action="/members/billing/start">
            <?= Csrf::field() ?>
            <input type="hidden" name="plan" value="<?= View::e((string) $key) ?>">
            <button class="btn btn--primary btn--block" type="submit">
              <?= Plans::isPaid($current)
                  ? 'Change plan on Stripe'
                  : 'Upgrade &mdash; ' . View::e(Plans::money((string) $key)) . '/month' ?>
            </button>
          </form>
        <?php elseif ($canBuy): ?>
          <form method="post" action="/members/plan">
            <?= Csrf::field() ?>
            <input type="hidden" name="plan" value="<?= View::e((string) $key) ?>">
            <button class="btn btn--primary btn--block" type="submit">
              Request <?= View::e($plan['name']) ?>
            </button>
          </form>
        <?php else: ?>
          <?php /* Moving DOWN to Free. Routed through /members/plan, which
                   refuses it outright while Stripe is still collecting --
                   dropping the plan here would leave a business on the Free
                   allowance with the card still being charged. */ ?>
          <form method="post" action="/members/plan">
            <?= Csrf::field() ?>
            <input type="hidden" name="plan" value="<?= View::e((string) $key) ?>">
            <button class="btn btn--ghost btn--block" type="submit">Move to Free</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
