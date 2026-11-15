<?php use App\Support\View; $current = $current ?? ''; ?>
<header class="site-header">
  <div class="container site-header__inner">
    <?= View::render('partials/logo', ['href' => '/', 'size' => 30]) ?>
    <?php /* Three things, and one of them is the point.
             How It Works, Features, Pricing and For Agencies all moved to the
             footer: a bar full of choices is a bar that asks a first-time
             visitor to browse, and browsing is not what we want them to do.
             They are still one click away down there, and still linked from
             the page copy.

             "Login" carries `--always` because a returning customer opening
             the site on a phone must be able to get in. The score link gives
             way first whatever the order, since it repeats the hero button a
             few inches below. */ ?>
    <nav class="site-nav">
      <?php foreach ([
        '/score'         => ['Free Review Score', false],
        '/members/login' => ['Login', true],
      ] as $href => [$label, $always]): ?>
        <a class="site-nav__link<?= $always ? ' site-nav__link--always' : '' ?>"
           href="<?= $href ?>"<?= $current === $href ? ' aria-current="page"' : '' ?>><?= View::e($label) ?></a>
      <?php endforeach; ?>
      <a class="btn btn--primary" href="/members/signup" style="margin-left:.5rem;">Join FREE</a>
    </nav>
  </div>
</header>
