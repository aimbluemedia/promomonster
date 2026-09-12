<?php use App\Support\Icon; use App\Support\Plans; use App\Support\View; ?>

<section class="section">
  <div class="container hero">
    <div class="hero__media">
      <!-- Served straight from the web root at /assets/img/hero.jpg. No
           server-side existence check: it silently swallowed a wrong path
           twice, where a plain 404 in the network tab says exactly what is
           wrong.

           width/height are the file's real pixels (portrait), so the browser
           reserves the right box and the page does not jump as it loads. The
           JPEG is the same photograph as hero.png at a ninetieth of the weight;
           this is the largest asset on the page, so that matters. -->
      <img src="/assets/img/hero.jpg" width="1122" height="1402" fetchpriority="high"
           alt="A home service professional outside a customer&rsquo;s home, holding a tablet.">
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
      <h1>Reviews. Reputation. <em>Growth.</em></h1>
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


<?php /* Ticker strips break the page into chapters the way the reference does,
         and carry the compliance line past anyone who only skims. */ ?>
<?php require APP_ROOT . '/Views/partials/ticker.php'; ?>

<section class="section" style="padding-block:2.5rem;">
  <div class="container">
    <div class="benefits" data-reveal>
      <?php foreach ([
        ['clock',   'Live in a day',      'Not a project'],
        ['shield',  'Never gates',        'Google-safe'],
        ['send',    'SMS and email',      'Both included'],
        ['sparkle', 'Replies drafted',    'You approve'],
        ['layout',  'Widget for my site', 'One line of code'],
        ['star',    'Starts free',        'No card'],
      ] as [$icon, $title, $sub]): ?>
        <div class="benefit">
          <?= Icon::render($icon) ?>
          <strong><?= View::e($title) ?></strong>
          <span><?= View::e($sub) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php /* ---- The calculator: the centrepiece of the page. ----------------- */ ?>
<section class="section section--wash" id="calculator">
  <div class="container">
    <div class="center" style="max-width:46rem;margin-inline:auto;" data-reveal>
      <p class="eyebrow">Review calculator</p>
      <h2 class="display display--tight" style="margin-inline:auto;">
        How many five-star reviews does it <em>actually</em> take?
      </h2>
      <p class="lede">Move the sliders. The maths is exact: a rating is an
        average, and an average takes a knowable number of reviews to shift.</p>
    </div>
    <div style="margin-top:2.5rem;" data-reveal data-reveal-delay="1">
      <?php require APP_ROOT . '/Views/partials/calculator.php'; ?>
    </div>
  </div>
</section>

<?php require APP_ROOT . '/Views/partials/ticker.php'; ?>

<section class="section">
  <div class="container">
    <div class="center" style="max-width:44rem;margin-inline:auto;" data-reveal>
      <p class="eyebrow">How it works</p>
      <h2 class="display">Three steps, then it <em>runs itself</em></h2>
      <p class="lede">Set it up once. After that the only thing you do is reply
        &mdash; and we draft those too.</p>
    </div>
    <div class="grid grid--3" style="margin-top:2.75rem;">
      <?php foreach ([
        ['01','users','Add customers','Import a list, paste them in, or add one after each job.'],
        ['02','send','We ask, automatically','A text or email at the right moment, then one reminder. Never more.'],
        ['03','star','Reviews arrive','Watch them land, reply in a click, and show them on your site.'],
      ] as $i => [$num, $icon, $title, $body]): ?>
        <div class="step" data-reveal data-reveal-delay="<?= $i + 1 ?>">
          <?= Icon::chip($icon) ?>
          <div class="step__num"><?= $num ?></div>
          <h3><?= View::e($title) ?></h3>
          <p><?= View::e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php /* ---- Why the rating matters. Checkable facts only, no invented stats. */ ?>
<section class="section band">
  <div class="container">
    <div class="center" style="max-width:46rem;margin-inline:auto;" data-reveal>
      <p class="eyebrow">Why the number moves the needle</p>
      <h2 class="display">A rating is a <em>filter</em>, not a vanity metric</h2>
      <p class="lede">Google Maps lets people filter results by rating, and the
        lowest rung of that filter is 4.0. Below it you are not competing badly
        &mdash; you are not in the list at all.</p>
    </div>
    <div class="band__grid" style="margin-top:3rem;" data-reveal data-reveal-delay="1">
      <div>
        <div class="band__n"><span data-count="4">4</span>.0</div>
        <p class="band__l">The first rung of Google&rsquo;s rating filter</p>
      </div>
      <div>
        <div class="band__n"><span data-count="4">4</span>.5</div>
        <p class="band__l">The rung above, where most searches settle</p>
      </div>
      <div>
        <div class="band__n"><span data-count="0">0</span></div>
        <p class="band__l">Reviews we write, buy or gate. Ever.</p>
      </div>
      <div>
        <div class="band__n">$<span data-count="0">0</span></div>
        <p class="band__l">To start, and no card to find out</p>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container split">
    <div data-reveal>
      <p class="eyebrow">Review Growth Score</p>
      <h2 class="display">Know exactly what to <em>fix next</em></h2>
      <p class="lede">One number for how well your reputation engine is running,
        and one clear next step. No dashboards to interpret.</p>
      <div class="btn-row"><a class="btn btn--primary" href="/audit">Score my business free</a></div>
    </div>
    <div class="card" data-reveal data-reveal-delay="1">
      <div class="score"><span class="score__val" data-count="62">62</span><span class="score__max">/ 100</span></div>
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
        Next best action &rarr; turn on the 3-day reminder
      </p>
    </div>
  </div>
</section>

<?php require APP_ROOT . '/Views/partials/ticker.php'; ?>

<?php /* ---- Plans, read from the same catalogue as /pricing. ------------- */ ?>
<section class="section section--wash">
  <div class="container">
    <div class="center" style="max-width:44rem;margin-inline:auto;" data-reveal>
      <p class="eyebrow">Pricing</p>
      <h2 class="display">Start free. Move up <em>when it is working</em></h2>
      <p class="lede">No setup fee, no contract, no sales call. Competitors
        charge $300&ndash;$600 a month for this.</p>
    </div>
    <div class="compare" style="margin-top:2.75rem;">
      <?php foreach (Plans::selectable() as $key => $plan): ?>
        <div class="card plan<?= $plan['featured'] ? ' plan--featured' : '' ?>"
             data-reveal data-reveal-delay="<?= $plan['featured'] ? 2 : 1 ?>">
          <?php if ($plan['featured']): ?><span class="plan__badge">Most popular</span><?php endif; ?>
          <h3 style="font-size:1.15rem;"><?= View::e($plan['name']) ?></h3>
          <div style="margin:.6rem 0 .3rem;">
            <span class="plan__price"><?= $plan['price'] === 0 ? 'Free' : '$' . (int) $plan['price'] ?></span>
            <?php if ($plan['price'] > 0): ?><span class="plan__per">/month</span><?php endif; ?>
          </div>
          <p class="muted" style="font-size:.92rem;margin:0 0 .4rem;"><?= View::e($plan['tagline']) ?></p>
          <ul class="checklist">
            <?php foreach ($plan['features'] as [$label, $on]): ?>
              <li<?= $on ? '' : ' class="is-off"' ?>><?= View::e($label) ?></li>
            <?php endforeach; ?>
          </ul>
          <a class="btn <?= $plan['featured'] ? 'btn--primary' : 'btn--ghost' ?> btn--block"
             href="/members/signup?plan=<?= View::e($key) ?>">
            <?= $plan['price'] === 0 ? 'Start free' : 'Choose ' . View::e($plan['name']) ?>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="center" style="max-width:46rem;margin-inline:auto;" data-reveal>
      <p class="eyebrow">Built the right way</p>
      <h2 class="display">We ask <em>all</em> your customers</h2>
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
      ] as $i => [$icon, $title, $body]): ?>
        <div class="card feature" data-reveal data-reveal-delay="<?= $i + 1 ?>">
          <?= Icon::chip($icon) ?>
          <h3><?= View::e($title) ?></h3>
          <p><?= View::e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--wash">
  <div class="container center" data-reveal>
    <h2 class="display">See where you stand in <em>about a minute</em></h2>
    <p class="lede">We will show you your rating, your review velocity, how many
      go unanswered, and how you compare to the three nearest businesses like
      yours. Free, and no card.</p>
    <div class="btn-row" style="justify-content:center;">
      <a class="btn btn--primary" href="/audit">Get your free review audit</a>
      <a class="btn btn--ghost" href="/members/signup">Create a free account</a>
    </div>
  </div>
</section>
