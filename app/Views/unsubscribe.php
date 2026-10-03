<?php use App\Support\View;
/** @var string $token @var string $business @var bool $already @var bool $done */
?>
<section class="section">
  <div class="container center" style="max-width:34rem;">

    <?php if ($done || $already): ?>
      <p class="eyebrow">Unsubscribed</p>
      <h1 style="font-size:1.6rem;">That is done.</h1>
      <p class="lede" style="margin-inline:auto;">
        You will not get another review request at this address
        <?php if ($business !== ''): ?>from <?= View::e($business) ?><?php endif; ?>,
        or from any other business using PromoMonster.
      </p>
      <p class="muted" style="margin-top:1.25rem;font-size:.92rem;">
        Nothing else was changed, and nobody will follow up about it. If you
        still want to leave a review one day, you can always do it directly on
        the business&rsquo;s Google listing.
      </p>

    <?php else: ?>
      <p class="eyebrow">Unsubscribe</p>
      <h1 style="font-size:1.6rem;">Stop these emails?</h1>
      <p class="lede" style="margin-inline:auto;">
        <?php if ($business !== ''): ?>
          <?= View::e($business) ?> will stop asking you for a review, and so will
          every other business that uses PromoMonster.
        <?php else: ?>
          You will stop getting review requests from any business that uses
          PromoMonster.
        <?php endif; ?>
      </p>

      <?php /* A POST, because mail scanners follow links. One click on a GET
               would unsubscribe half a customer list before a human read a
               word. No CSRF token: there is no session here, the signed link is
               the authorisation, and requiring one would break the unsubscribe
               button Gmail renders from the List-Unsubscribe header. */ ?>
      <form method="post" action="/u/<?= View::e($token) ?>" style="margin-top:2rem;">
        <button class="btn btn--primary" type="submit">Yes, stop emailing me</button>
      </form>

      <p class="muted" style="margin-top:1.5rem;font-size:.92rem;">
        Close this page if you landed here by accident &mdash; nothing changes
        until you press the button.
      </p>
    <?php endif; ?>

  </div>
</section>
