<?php
use App\Support\View;
$questions = [
  ['Comprehension', 'In one sentence, what was this article about? If readers cannot answer, nothing else in it matters.'],
  ['Drop-off', 'Where did you lose interest? Pinpoints the paragraph that is costing you the rest of the page.'],
  ['Credibility', 'Did this feel written by someone who knows the subject? The answer to this is usually blunt and useful.'],
  ['Takeaway', 'What is the one thing you would remember tomorrow? Tells you whether your actual point survived the draft.'],
  ['Next action', 'What would you do after reading this? Reveals whether your call to action is doing anything at all.'],
  ['Headline test', 'Two titles, head to head, with reasons. Cheapest possible way to double a click-through rate.'],
];
?>
<section class="section section--hero section--hero-bordered">
  <div class="container prose">
    <p class="eyebrow">PromoMonster Content</p>
    <h1>You wrote it. Does it land?</h1>
    <p class="lede">Publishing an article and watching the traffic number is not
      feedback. Send it to 50 real readers and find out what they actually took
      away, where they stopped caring, and whether they&rsquo;d trust the
      business behind it.</p>
    <div class="btn-row"><a class="btn btn--primary" href="/business#start">Test a piece of content</a></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2>The questions worth asking</h2>
    <div class="grid grid--3" style="margin-top:2rem;">
      <?php foreach ($questions as [$title, $body]): ?>
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
    <h2>Why not just buy traffic?</h2>
    <p>Because a visit tells you nothing. A thousand people landing on your
      article and leaving produces one number and no explanation, and it quietly
      pollutes your analytics while it does it.</p>
    <p>Fifty people telling you that your opening paragraph buries the point,
      and that three of them thought you were selling something you don&rsquo;t
      sell, is worth more than any traffic number — and it costs less.</p>
  </div>
</section>
