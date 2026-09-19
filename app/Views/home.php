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

<?php /* ---- The free playbook.

         Given away whole, on the page, with nothing to fill in and nothing
         held back for the paid plans. Two reasons. It is the honest pitch:
         everything below is something a business can do on its own with a
         phone and a habit, and saying so is more persuasive than pretending
         otherwise. And it is the only part of this page worth reading if you
         never buy anything.

         Nothing here is a claim we cannot point at. The rating filter is
         checkable in Google Maps; the gating line is Google's published policy
         and the FTC's rule on consumer reviews. The step copy is written with
         real punctuation and escaped on the way out, rather than carrying HTML
         entities through a variable. ------------------------------------- */ ?>
<section class="section" id="playbook">
  <div class="container">
    <div class="center" style="max-width:48rem;margin-inline:auto;" data-reveal>
      <p class="eyebrow">The free playbook</p>
      <?php /* The width and the nowrap are there so the phrase does not break
           across lines: left to itself it wraps as "step" / "by step", which
           reads as two thoughts. Both are safe down to 320px, where the
           clamped font size makes the phrase well under a line. */ ?>
      <h2 class="display" style="max-width:19ch;margin-inline:auto;">How to get
        more customer reviews, <em style="white-space:nowrap;">step by step</em></h2>
      <p class="lede">If you run a service business, this is the marketing your
        time is best spent on. Your rating is printed next to your name in every
        search result and every map pin, and Google Maps lets people filter by
        rating &mdash; the lowest rung of that filter is 4.0. No advert buys you
        past it. Below is the whole process, in the order to do it. No email, no
        download, no catch.</p>
    </div>

    <ol class="playbook" role="list" data-reveal data-reveal-delay="1">
      <?php foreach ([
        ['Claim your Google Business Profile',
         'Everything else hangs off this, it is free, and an unclaimed profile
          cannot hand out a review link. While you are in there, finish it:
          hours, service area, phone, and photographs of real jobs.'],
        ['Get your short review link',
         'Your profile gives you one link that opens the review box directly.
          Save it somewhere you can paste it from in two seconds. That link is
          the whole campaign — everything after this is getting it in front of
          people.'],
        ['Put it on a QR code, on paper',
         'The invoice, the van, the door hanger, the counter. A customer with a
          phone already in their hand and a code in front of them is the easiest
          review you will ever get.'],
        ['Ask on the day of the job',
         'While they can still see the work. A week later you are asking someone
          to remember you; on the day you are asking them to react, and that is
          a far smaller favour.'],
        ['Ask by text, with the link in it',
         'One tap, not an address to type. A business card is a reminder to do
          it later, and later means never. If you change one thing on this list,
          change this one.'],
        ['Ask every single customer',
         'No screening, and no “how did we do?” survey first to decide who gets
          asked. Same message, same link, everyone. It is the only compliant way
          to do it, and it is what makes a 4.8 believable to someone reading the
          one-star.'],
        ['Send one reminder, then stop',
         'Three days later, once. That catches the people who meant to and got
          busy. A second reminder catches nobody — it just irritates a customer
          you already did good work for.'],
        ['Reply to every review within 48 hours',
         'Good and bad. Replies are public, and the next customer reads them
          more carefully than they read the review. A calm reply under a bad
          review has won more work than most adverts.'],
        ['Show the reviews on your own website',
         'You earned them. Put them where people land after they search for you,
          not only on a profile they may never scroll.'],
      ] as [$title, $body]): ?>
        <li class="playbook__step">
          <h3><?= View::e($title) ?></h3>
          <p><?= View::e(preg_replace('/\s+/', ' ', $body)) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>

    <div class="playbook__foot" data-reveal>
      <p class="playbook__rule">
        <?= Icon::render('shield') ?>
        <span><strong>One rule holds the whole thing up: ask everyone.</strong>
          Filtering who gets asked is review gating. Google prohibits it outright,
          the FTC treats suppressing or buying reviews as deceptive, and it is the
          quickest way to lose a profile you spent years building. We will never
          do it for you, and you should not do it yourself.</span>
      </p>
      <p class="playbook__then">Work through all nine and you will have more
        reviews than you do now, whether or not you ever pay us a penny.
        PromoMonster is for when you would rather it happened without you
        having to remember.</p>
      <div class="btn-row" style="justify-content:center;">
        <a class="btn btn--primary" href="#score">Get your Free Review Score</a>
        <a class="btn btn--ghost" href="/members/signup">Create a free account</a>
      </div>
    </div>
  </div>
</section>
