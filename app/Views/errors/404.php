<?php use App\Support\View; ?>
<section class="section section--hero">
  <div class="container prose">
    <h1><?= View::e($title ?? 'Page not found') ?></h1>
    <p class="lede">That page isn&rsquo;t here. Try the
      <a href="/">homepage</a>, or read about
      <a href="/business">running a study</a> or
      <a href="/earn">joining the panel</a>.</p>
  </div>
</section>
