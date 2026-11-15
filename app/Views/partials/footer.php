<?php use App\Support\View; ?>
<?php /* The membership band sits above the legal footer, not inside it: it is
         a pitch and the footer is a map, and running them together is how a
         footer stops being somewhere people look things up.

         Not on the sign-up page. The band's own "Start free" button would sit
         directly under the sign-up form, competing with the one thing that
         page exists to do -- and pitching the free account to somebody who is
         already filling in the form for it. */ ?>
<?php if (($current ?? '') !== '/members/signup'): ?>
  <?= View::render('partials/membership') ?>
<?php endif; ?>

<footer class="site-footer">
  <div class="container">
    <div class="site-footer__top">
      <div class="site-footer__brand">
        <?= View::render('partials/logo', ['href' => '/', 'size' => 30]) ?>
        <p>Reviews. Reputation. Growth. PromoMonster helps local businesses ask
           every customer for a review, respond to what comes back, and show it
           off &mdash; without breaking a single platform rule.</p>
      </div>

      <?php /* Grouped by what somebody came looking for. The old footer was
               one run of seven links where "Privacy" sat next to "Features",
               which is a list rather than a map. */ ?>
      <?php foreach ([
        'PromoMonster' => [
          '/how-it-works' => 'How it works',
          '/features'     => 'Features',
          '/pricing'      => 'Pricing',
          '/agencies'     => 'For agencies',
        ],
        'Free tools' => [
          '/audit'   => 'Free review audit',
          '/score'   => 'Review Growth Score',
          '/compare' => 'Compare your reviews',
        ],
        'Your account' => [
          '/members/signup' => 'Create a free account',
          '/members/login'  => 'Log in',
          '/members'        => 'Members dashboard',
        ],
        'Legal' => [
          '/privacy' => 'Privacy',
          '/terms'   => 'Terms',
        ],
      ] as $heading => $links): ?>
        <nav class="site-footer__col" aria-label="<?= View::e($heading) ?>">
          <h2><?= View::e($heading) ?></h2>
          <?php foreach ($links as $href => $label): ?>
            <a href="<?= View::e($href) ?>"><?= View::e($label) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endforeach; ?>
    </div>

    <div class="site-footer__legal">
      <span>&copy; <?= date('Y') ?> PromoMonster by Toutify. All rights reserved.</span>
      <?php /* The promise stays in the footer rather than moving to a policy
               page. It is the thing that separates this product from the ones
               that sell reviews, and it belongs where every page carries it. */ ?>
      <span>We never sell, write or incentivise reviews, and we never filter
        which customers get asked.</span>
    </div>
  </div>
</footer>
