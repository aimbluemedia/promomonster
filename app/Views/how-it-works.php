<?php use App\Support\Icon; use App\Support\View; ?>
<section class="section">
  <div class="container">
    <div class="prose">
      <p class="eyebrow">How it works</p>
      <h1>Ask everyone. Reply to everyone. Show the result.</h1>
      <p class="lede">Most businesses get reviews by accident. The ones with
        hundreds have a process. This is that process, running on its own.</p>
    </div>
  </div>
</section>

<section class="section section--wash" style="padding-top:0;">
  <div class="container">
    <div class="grid grid--3">
      <?php foreach ([
        ['01','users','Add your customers','Import a list, paste them in, or add each one as the job finishes. We check every number and address, and anyone who has opted out is filtered before a single message goes anywhere.'],
        ['02','send','The ask goes out','At the moment your vertical converts best — for a landscaper that is the final walkthrough, for a dentist it is checkout. One reminder a few days later if they did not act. Never a third.'],
        ['03','star','Reviews come in','They appear in your dashboard as they land. Reply with a drafted response, publish them to your website, and watch the score move.'],
      ] as [$num,$icon,$title,$body]): ?>
        <div class="step" style="text-align:left;">
          <?= Icon::chip($icon) ?>
          <div class="step__num"><?= $num ?></div>
          <h3><?= View::e($title) ?></h3>
          <p><?= View::e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container split">
    <div>
      <p class="eyebrow">The part nobody else does</p>
      <h2>A plan built for your kind of business</h2>
      <p class="lede">Generic tools tell you to "send a request". That is not
        advice. We tell you the moment to ask, who on your team should ask, what
        they should say out loud first, and the exact message that follows.</p>
      <p class="muted" style="margin-top:1rem;font-size:.95rem;">
        The single biggest lift in review rates is not the software. It is the
        person saying <em>"I'll text you a link — it takes thirty seconds"</em>
        before the text arrives. We tell your team exactly when to say it.</p>
    </div>
    <div class="card">
      <p class="eyebrow" style="margin-bottom:.6rem;">Landscaping playbook</p>
      <ul class="checklist">
        <li><strong>Ask at</strong> the final walkthrough, on the finished work</li>
        <li><strong>Who asks</strong> the crew lead who did the job</li>
        <li><strong>Channel</strong> SMS — they are outdoors, not in an inbox</li>
        <li><strong>Reminder</strong> once, three days later, in the morning</li>
        <li><strong>Offline</strong> QR card clipped to the invoice</li>
      </ul>
      <p style="margin:1.3rem 0 0;padding-top:1.1rem;border-top:1px solid var(--line);font-size:.92rem;color:var(--body);">
        <strong style="color:var(--ink);">They say:</strong> &ldquo;If you're happy
        with how it turned out, I'll text you a link — takes about thirty
        seconds and it really helps us.&rdquo;</p>
    </div>
  </div>
</section>

<section class="section section--wash">
  <div class="container center">
    <h2>Find out where you stand</h2>
    <p class="lede">The free audit shows your rating, your pace, what is going
      unanswered, and how you compare to the businesses you compete with.</p>
    <div class="btn-row" style="justify-content:center;">
      <a class="btn btn--primary" href="/audit">Get your free review audit</a>
    </div>
  </div>
</section>
