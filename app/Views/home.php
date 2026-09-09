<?php use App\Support\Icon; use App\Support\View; ?>

<section class="section">
  <div class="container hero">
    <div class="hero__media">
      <!-- Served straight from the web root at /assets/img/hero.png. No
           server-side existence check: it silently swallowed a wrong path
           twice, where a plain 404 in the network tab says exactly what is
           wrong. -->
      <img src="/assets/img/hero.png" width="1000" height="800" fetchpriority="high"
           alt="A home service professional finishing a job at a customer&rsquo;s home.">
      <div class="hero__float">
        <span class="stars" aria-hidden="true">★★★★★</span>
        <span>
          <strong>+38 reviews in 90 days</strong>
          <p>Acme Pools &middot; Mesa, AZ</p>
        </span>
      </div>
    </div>

    <div>
      <p class="eyebrow">Reputation Management</p>
      <h1>Reviews. Reputation. Growth.</h1>
      <p class="lede">Ask every customer for a review, reply to what comes back,
        and put it to work on your website — automatically. Built for local
        businesses that get chosen, or skipped, on their star rating.</p>

      <div class="hero__cards">
        <?php foreach ([
          ['01','users','Collect','Every customer asked, at the right moment.'],
          ['02','sparkle','Respond','Drafted replies, sent in a click by you.'],
          ['03','chart','Grow','Reviews on your site, working for you.'],
        ] as [$num,$icon,$title,$body]): ?>
          <div class="step step--sm">
            <?= Icon::chip($icon) ?>
            <div class="step__num"><?= $num ?></div>
            <h3><?= View::e($title) ?></h3>
            <p><?= View::e($body) ?></p>
          </div>
        <?php endforeach; ?>
      </div>

      <ul class="pills">
        <li>Google</li><li>SMS &amp; email</li><li>QR codes</li><li>AI replies</li>
      </ul>

      <p class="hero__note">Everything compliant. We never gate, incentivise or
        write reviews.</p>

      <div class="btn-row">
        <a class="btn btn--primary" href="/audit">Get your free review audit</a>
        <a class="btn btn--ghost" href="/how-it-works">See how it works</a>
      </div>
    </div>
  </div>
</section>

<section class="section section--wash">
  <div class="container">
    <div class="center" style="max-width:44rem;margin-inline:auto;">
      <p class="eyebrow">How it works</p>
      <h2>Three steps, then it runs itself</h2>
      <p class="lede">Set it up once. After that the only thing you do is reply
        — and we draft those too.</p>
    </div>
    <div class="grid grid--3" style="margin-top:2.75rem;">
      <?php foreach ([
        ['01','users','Add customers','Import a list, paste them in, or add one after each job.'],
        ['02','send','We ask, automatically','A text or email at the right moment, then one reminder. Never more.'],
        ['03','star','Reviews arrive','Watch them land, reply in a click, and show them on your site.'],
      ] as [$num,$icon,$title,$body]): ?>
        <div class="step">
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
      <p class="eyebrow">Review Growth Score</p>
      <h2>Know exactly what to fix next</h2>
      <p class="lede">One number for how well your reputation engine is running,
        and one clear next step. No dashboards to interpret.</p>
      <div class="btn-row"><a class="btn btn--primary" href="/audit">Score my business free</a></div>
    </div>
    <div class="card">
      <div class="score"><span class="score__val">62</span><span class="score__max">/ 100</span></div>
      <div class="meter"><span style="width:62%"></span></div>
      <ul class="checklist">
        <li>Google review link configured</li>
        <li>QR code created</li>
        <li>Review requests sending</li>
        <li class="is-off">Follow-up reminder off</li>
        <li class="is-off">Team not trained yet</li>
        <li>Replying within 48 hours</li>
        <li class="is-off">Website widget not installed</li>
      </ul>
      <p style="margin:1.4rem 0 0;font-weight:700;color:var(--ink);">
        Next best action → turn on the 3-day reminder
      </p>
    </div>
  </div>
</section>

<section class="section section--wash">
  <div class="container">
    <div class="center" style="max-width:46rem;margin-inline:auto;">
      <p class="eyebrow">Built the right way</p>
      <h2>We ask all your customers — not just the ones you think will be nice</h2>
      <p class="lede">Filtering who gets asked is called review gating. Google
        prohibits it and the FTC treats it as deceptive, with penalties over
        $50,000 per violation. Plenty of tools still quietly do it. We never
        will, and that protects the profile you have spent years building.</p>
    </div>
    <div class="grid grid--3" style="margin-top:2.75rem;">
      <?php foreach ([
        ['shield','No gating, ever','Every customer gets the same message and the same link. That is the only compliant way to do this.'],
        ['star','No incentives','Offering anything for a review breaks Google policy outright. Our templates make the compliant path the easy one.'],
        ['search','No fake reviews','We never write, buy or sell reviews. Everything you see came from a real customer of yours.'],
      ] as [$icon,$title,$body]): ?>
        <div class="card feature">
          <?= Icon::chip($icon) ?>
          <h3><?= View::e($title) ?></h3>
          <p><?= View::e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container center">
    <h2>See where you stand in about a minute</h2>
    <p class="lede">We will show you your rating, your review velocity, how many
      go unanswered, and how you compare to the three nearest businesses like
      yours. Free, and no card.</p>
    <div class="btn-row" style="justify-content:center;">
      <a class="btn btn--primary" href="/audit">Get your free review audit</a>
    </div>
  </div>
</section>
