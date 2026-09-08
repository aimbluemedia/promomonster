<?php use App\Support\View; $current = $current ?? ''; ?>
<header class="site-header">
  <div class="container site-header__inner">
    <a class="logo" href="/" aria-label="PromoMonster home">
      <svg width="30" height="30" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect width="32" height="32" rx="9" fill="var(--brand)"/>
        <path d="M16 7.5l2.3 4.7 5.2.75-3.75 3.65.9 5.15L16 19.3l-4.65 2.45.9-5.15L8.5 12.95l5.2-.75z" fill="#fff"/>
      </svg>
      <span class="logo__word">Promo<span class="logo__accent">Monster</span></span>
    </a>
    <nav class="site-nav">
      <?php foreach ([
        '/how-it-works' => 'How It Works',
        '/features'     => 'Features',
        '/pricing'      => 'Pricing',
        '/agencies'     => 'For Agencies',
      ] as $href => $label): ?>
        <a class="site-nav__link" href="<?= $href ?>"<?= $current === $href ? ' aria-current="page"' : '' ?>><?= View::e($label) ?></a>
      <?php endforeach; ?>
      <a class="btn btn--primary" href="/audit" style="margin-left:.5rem;">Free Review Audit</a>
    </nav>
  </div>
</header>
