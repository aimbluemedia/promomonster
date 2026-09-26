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
    <?php /* Three things, and one of them is the point.
             How It Works, Features, Pricing and For Agencies all moved to the
             footer: a bar full of choices is a bar that asks a first-time
             visitor to browse, and browsing is not what we want them to do.
             They are still one click away down there, and still linked from
             the page copy.

             "Login" carries `--always` because a returning customer opening
             the site on a phone must be able to get in; the score link gives
             way first, since it repeats the hero button a few inches below. */ ?>
    <nav class="site-nav">
      <?php foreach ([
        '/members/login' => ['Login', true],
        '/score'         => ['FREE Review Score', false],
      ] as $href => [$label, $always]): ?>
        <a class="site-nav__link<?= $always ? ' site-nav__link--always' : '' ?>"
           href="<?= $href ?>"<?= $current === $href ? ' aria-current="page"' : '' ?>><?= View::e($label) ?></a>
      <?php endforeach; ?>
      <a class="btn btn--primary" href="/members/signup" style="margin-left:.5rem;">Join FREE</a>
    </nav>
  </div>
</header>
