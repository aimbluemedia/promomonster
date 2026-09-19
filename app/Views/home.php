<?php use App\Support\Icon; use App\Support\View; ?>

<?php /* Dark band first, then the photograph hero it sits above. */ ?>
<?php require APP_ROOT . '/Views/partials/hero-band.php'; ?>

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
        <a class="btn btn--primary" href="#score">Get your Free Review Score</a>
        <a class="btn btn--ghost" href="/how-it-works">See how it works</a>
      </div>
    </div>
  </div>
</section>


<?php /* ---- Free Review Score. Directly under the hero on purpose: it is the
         one thing we want a first-time visitor to do. ------------------- */ ?>
<section class="section score-band" id="score">
  <div class="container" style="max-width:54rem;">
    <div class="center" style="margin-bottom:1.75rem;">
      <p class="eyebrow">Free Review Score</p>
      <h2 class="display" style="margin-bottom:.6rem;">Try it right now &mdash;
        <em>see your score in seconds</em></h2>
      <p class="lede" style="margin-inline:auto;">We read your website and score it
        out of 100 on how well it is set up to win customer reviews. No credit card,
        no sales call.</p>
    </div>

    <div class="card score-card score-card--hero">
      <?php require APP_ROOT . '/Views/partials/score-form.php'; ?>
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

<?php /* ---- Why to open the free account.

         The closing argument of the page. The cards are reasons a small
         business owner already cares about — leads, trust, what the business
         is worth when they sell it — not a feature list, because nobody buys
         a feature list.

         Every claim here is reasoning the reader can check against their own
         experience, or a fact we can point at: the rating filter is in Google
         Maps, and the free-plan tools in the last card are exactly what
         Plans::all() gives a free account. No invented statistics, no revenue
         projection, no "businesses see 3x more" — a number we cannot defend is
         worse than no number. ------------------------------------------- */ ?>
<section class="section" id="free">
  <div class="container">
    <div class="center" style="max-width:48rem;margin-inline:auto;" data-reveal>
      <p class="eyebrow">Free account &middot; no credit card</p>
      <?php /* The width and the nowrap keep the phrase on one line: left to
               itself it breaks as "Start" / "free", which reads as two
               thoughts. Both are safe down to 320px. */ ?>
      <h2 class="display" style="max-width:15ch;margin-inline:auto;">Get reviews
        like a pro. <em style="white-space:nowrap;">Start free.</em></h2>
      <p class="lede">If you run a small service business, reviews are not a
        marketing extra you get to later. They are the thing that decides who
        gets the call. Every reason below is one you already feel &mdash; the
        free account is just how you stop having to remember. No card, nothing
        to cancel, and your first requests can go out this afternoon.</p>
    </div>

    <div class="grid grid--3" style="margin-top:2.75rem;">
      <?php foreach ([
        ['chart', 'More leads from the work you already do',
         'When three businesses come up side by side, the rating is the
          tie-breaker. Every van, every sign and every ad you already pay for
          lands harder behind a 4.8 than behind a 3.9.'],
        ['users', 'Trust before you ever speak to them',
         'A stranger decides whether to call you by reading what your last
          twenty customers said. It is the reference you cannot hand out
          yourself, and it is working while you are on a job.'],
        ['star', 'A business worth more when you sell it',
         'A buyer is buying your reputation along with the vans. A 4.8 with
          400 reviews transfers to them on day one. A 3.9 with 40 is something
          they have to fix first, and they will price it that way.'],
        ['search', 'Get past Google’s rating filter',
         'Google Maps lets people filter results by rating, and the lowest rung
          of that filter is 4.0. Below it you are not competing badly —
          you are not in the list they are looking at.'],
        ['megaphone', 'Stop competing on price alone',
         'A strong rating is the reason someone pays your quote instead of the
          cheapest one. Without it, price is the only thing left for them to
          compare you on, and that is a race you do not want to win.'],
        ['pin', 'Keep up with the shop down the road',
         'Your competitors are asking their customers. If you are not, the gap
          widens every month — whether or not you are doing the better
          work. This is the one area where effort compounds.'],
        ['bell', 'Never have to remember to ask',
         'Most owners do not have few reviews because customers said no. They
          have few because nobody asked, on the day, every time. That is the
          part we take off your hands.'],
        ['message', 'Hear about a bad review first',
         'Monitoring and alerts come with the free account, so you find out
          from us and reply the same day — not from a customer mentioning
          it three weeks later.'],
        ['qr', 'Free tools, not a free trial',
         'Your Google review link and a printable QR code, 25 email review
          requests a month, review monitoring, and your Review Growth Score.
          Free for as long as you want it, with no card on file.'],
      ] as $i => [$icon, $title, $body]): ?>
        <div class="card feature" data-reveal data-reveal-delay="<?= ($i % 3) + 1 ?>">
          <?= Icon::chip($icon) ?>
          <h3><?= View::e($title) ?></h3>
          <p><?= View::e(preg_replace('/\s+/', ' ', $body)) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="join-foot" data-reveal>
      <p class="join-rule">
        <?= Icon::render('shield') ?>
        <span><strong>And we get them the honest way: we ask everyone.</strong>
          Filtering who gets asked is review gating. Google prohibits it
          outright, the FTC treats suppressing or buying reviews as deceptive,
          and it is the quickest way to lose the profile you spent years
          building. We will never do it, on any plan.</span>
      </p>
      <p class="join-then">Free is a plan, not a countdown. Open the account,
        get your link and your QR code, and start asking today &mdash; upgrade
        only if the day comes when 25 requests a month is not enough.</p>
      <div class="btn-row" style="justify-content:center;">
        <a class="btn btn--primary btn--lg" href="/members/signup">Create your free account</a>
        <a class="btn btn--ghost" href="#score">Or score my website first</a>
      </div>
    </div>
  </div>
</section>
