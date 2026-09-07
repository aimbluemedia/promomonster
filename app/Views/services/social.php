<?php
use App\Support\View;
$tests = [
  ['Thumb-stop test', 'Your creative is dropped into a mock feed alongside neutral filler and people scroll normally. We measure whether they stop, and for how long. It is the only feed metric that matters, and you cannot fake it.'],
  ['Three-second recall', 'We show the creative for three seconds — real scroll speed — then take it away and ask what they remember. What was it for? Whose brand was it? This is how professional ad recall testing works.'],
  ['Head-to-head', 'Two thumbnails, two titles, two hooks. Which wins, and why, in their words. Usually the reason is something you would never have guessed from looking at it yourself.'],
];
?>
<section class="section section--hero section--hero-bordered">
  <div class="container prose">
    <p class="eyebrow">PromoMonster Social</p>
    <h1>Test the thumbnail before you post it.</h1>
    <p class="lede">Upload your creative — thumbnail, title, first three seconds —
      and we show it to real people at real feed scale. You find out whether it
      stops the scroll and what people think it&rsquo;s about, before you commit
      the post or the ad spend.</p>
    <div class="btn-row"><a class="btn btn--primary" href="/business#start">Test your creative</a></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2>Three tests that tell you something</h2>
    <div class="grid grid--3" style="margin-top:2rem;">
      <?php foreach ($tests as [$title, $body]): ?>
        <div class="card">
          <h3><?= View::e($title) ?></h3>
          <p class="muted" style="margin:.5rem 0 0;font-size:.9375rem;"><?= View::e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--bordered">
  <div class="container prose">
    <h2>What we don&rsquo;t do, and why it protects you</h2>
    <p>We don&rsquo;t sell views, likes, followers or subscribers, and we never
      send people to your live posts. Paid engagement violates every major
      platform&rsquo;s terms, and the penalty lands on <em>your</em> account, not
      ours — scrubbed view counts, suppressed reach, or a channel strike you
      can&rsquo;t appeal.</p>
    <p>So your creative is tested on our platform, not theirs. Nothing touches
      your channel, no metric is inflated, and there is nothing for anyone to
      enforce against. You still learn the thing you actually wanted to know —
      and &ldquo;68% couldn&rsquo;t tell what your video was about&rdquo; is far
      more useful than five hundred views that were never going to watch anyway.</p>
  </div>
</section>
