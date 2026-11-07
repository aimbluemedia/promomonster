<?php use App\Support\View; ?>
<footer class="site-footer">
  <div class="container site-footer__grid">
    <div>
      <?= View::render('partials/logo', ['href' => '/', 'size' => 30]) ?>
      <p>Reviews. Reputation. Growth. PromoMonster helps local businesses ask
         every customer for a review, respond to what comes back, and show it
         off — without breaking a single platform rule.</p>
    </div>
    <nav>
      <a href="/how-it-works">How It Works</a>
      <a href="/features">Features</a>
      <a href="/pricing">Pricing</a>
      <a href="/agencies">For Agencies</a>
      <a href="/audit">Free Review Audit</a>
      <a href="/privacy">Privacy</a>
      <a href="/terms">Terms</a>
    </nav>
  </div>
  <div class="container site-footer__legal">
    &copy; <?= date('Y') ?> PromoMonster. We never sell, write or incentivise
    reviews, and we never filter which customers get asked.
  </div>
</footer>
