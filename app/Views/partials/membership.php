<?php
use App\Support\Icon;
use App\Support\Toutify;
use App\Support\View;
/**
 * The Toutify membership band, above the footer on every public page.
 *
 * Modelled on the LeadCrazy band, in PromoMonster's own blue rather than
 * LeadCrazy's: the same shape, this product's colours, and PromoMonster marked
 * as the one you are already on.
 *
 * It sells the ACCOUNT, not this product -- "you are already signing up for
 * one thing, here is what else it opens" -- which is why it sits between the
 * page's own call to action and the legal footer rather than competing with
 * either.
 */
?>
<section class="membership">
  <div class="container">
    <p class="membership__eyebrow">Toutify membership</p>

    <h2 class="membership__title">
      One free account.
      <span class="membership__accent">Your whole growth toolkit.</span>
    </h2>

    <p class="membership__lead">
      Join Toutify once and PromoMonster comes with it &mdash; plus the rest of
      the tools built for local businesses. No credit card, no trial clock, and
      the free plan stays free.
    </p>

    <div class="membership__act">
      <a class="btn btn--primary btn--xl" href="/members/signup">
        Start free &mdash; takes a minute
      </a>
      <span class="membership__already">
        Already a member? <a href="/members/login">Log in</a>
      </span>
    </div>

    <ul class="membership__promises">
      <?php foreach (Toutify::PROMISES as $promise): ?>
        <li><?= Icon::render('check') ?><?= View::e($promise) ?></li>
      <?php endforeach; ?>
    </ul>

    <p class="membership__label">Included with your free membership</p>

    <div class="membership__grid">
      <?php foreach (Toutify::products() as $product): ?>
        <?php
          $here = Toutify::isSelf($product['key']);
          // A card is a link only where there is a real address to send
          // somebody. A guessed domain would be a dead link on every page of
          // the site; see the note in Toutify.
          $tag  = $product['url'] === null || $here ? 'div' : 'a';
        ?>
        <<?= $tag ?> class="tout<?= $here ? ' tout--here' : '' ?>"
          <?= $tag === 'a' ? 'href="' . View::e((string) $product['url']) . '"' : '' ?>>
          <span class="tout__icon"><?= Icon::render($product['icon']) ?></span>
          <span class="tout__body">
            <span class="tout__name">
              <?= View::e($product['name']) ?>
              <?php if ($here): ?><span class="tout__here">You are here</span><?php endif; ?>
            </span>
            <span class="tout__by">by Toutify</span>
            <span class="tout__what"><?= View::e($product['what']) ?></span>
          </span>
        </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
  </div>
</section>
