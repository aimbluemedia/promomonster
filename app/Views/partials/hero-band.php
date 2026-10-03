<?php use App\Support\Icon; use App\Support\View; ?>
<?php
/**
 * Dark hero band, sitting between the nav and the photograph hero.
 *
 * Modelled on the reference layout the user supplied, with one deliberate
 * difference: where that page puts invented traction in the strip at the
 * bottom ("10K+ businesses, 50K+ pieces of content"), this puts facts that can
 * be checked. PromoMonster has no customers yet, and a number a visitor can
 * disprove on day one is worse than no number — the whole product is positioned
 * on not making things up.
 *
 * The band is dark in both colour schemes on purpose, so every colour here is
 * literal rather than a token. All of them were measured against the darkest
 * end of the gradient: white 17.6:1, the accent 8.2:1, body text 8.4:1.
 */
?>
<div class="hero-band">
  <div class="hero-band__strip">
    <div class="container hero-band__strip-inner">
      <span><?= Icon::render('sparkle') ?> Ask every customer &middot; By email, with your
        review link &middot; One reminder, then it stops</span>
      <span class="hero-band__strip-right">Start free. No credit card. No subscription.</span>
    </div>
  </div>

  <div class="container hero-band__inner">
    <div class="hero-band__copy">
      <p class="hero-band__eyebrow">Reviews decide who gets the call</p>

      <h1 class="hero-band__title">
        Reviews Are the <em>Lifeblood</em> of Your Business
      </h1>
      <p class="hero-band__sub">Start Free. No Credit Card. No Subscription.</p>

      <?php /* One short paragraph. Both claims are checkable in a minute — the
               rating sits on the listing above the website link, and Google Maps
               really does filter below 4.0. No borrowed percentages. */ ?>
      <p class="hero-band__lede">
        Your stars are the first thing a customer sees, and Google Maps filters
        you out below 4.0. PromoMonster emails every customer your Google review
        link, on the day of the job, and reminds them once.
      </p>

      <div class="hero-band__cta">
        <a class="btn btn--primary btn--xl" href="#score">Get my Free Review Score &rarr;</a>
        <?php /* Was "See how it works", pointing at the explainer page. Both
                 buttons now ask for something rather than one of them offering
                 more reading: a visitor who wants to know how it works can read
                 the page below, and one who is ready should not have to. The
                 star matches the mark in the logo; the magnifier that was here
                 belonged to a page about looking things up. */ ?>
        <a class="btn hero-band__ghost btn--xl" href="/members/signup">
          <?= Icon::render('star') ?> Join FREE Now!
        </a>
      </div>
      <p class="hero-band__note">Takes about ten seconds. No credit card.</p>

      <div class="hero-band__chips">
        <span class="hero-band__chips-label">Works with:</span>
        <?php foreach ([
          ['star', 'Google'],
          ['send', 'Email'],
          ['qr', 'QR codes'],
          ['message', 'One reminder'],
        ] as [$icon, $label]): ?>
          <span class="hero-chip"><?= Icon::render($icon) ?><?= $label ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <?php /* Illustrative, and read as such: a rating at the centre with the
             channels that feed it orbiting around. Not a screenshot, not a
             claim. aria-hidden because it repeats what the copy already says. */ ?>
    <?php /* aria-hidden sits on the illustration, not on the whole column, so
             the "example reviews" caption below it is still announced. Hiding
             that caption from a screen reader while showing invented reviews to
             everyone else is exactly the wrong way round. */ ?>
    <div class="hero-band__art">
      <div class="globe-wrap" aria-hidden="true">
        <?php /* Built by bin/build-globe.php and committed: the projection never
                 changes, so this is a cached static file rather than ~900 dots
                 re-emitted inside every page. */ ?>
        <img class="globe" src="/assets/img/globe.svg" width="400" height="400"
             alt="" decoding="async">

        <?php
        /**
         * Illustrative review cards.
         *
         * These are NOT real reviews and must never be able to pass for them.
         * This company's entire position is that it does not write, buy or
         * fabricate reviews, so a homepage decorated with invented testimonials
         * that read as genuine would be the single most damaging thing on the
         * site — and the FTC treats fabricated consumer reviews as deceptive
         * regardless of intent.
         *
         * So: no real business names, no photographs, first name and initial
         * only, a visible "Example" caption under the globe, and the whole
         * group aria-hidden. They show the SHAPE of what arrives, which is the
         * point of the illustration, without claiming anyone said it.
         */
        ?>
        <?php foreach ([
          ['n1', 5, 'Turned up on time and left it spotless.', 'Dana R.'],
          ['n2', 5, 'Quoted fairly &mdash; no surprises on the invoice.', 'Marcus T.'],
          ['n3', 4, 'Quick fix and a friendly team.',            'Priya S.'],
          ['n4', 5, 'Answered my question within minutes.',      'Sam K.'],
          ['n5', 5, 'Second time using them. Same standard.',    'Jordan L.'],
        ] as [$pos, $stars, $quote, $who]): ?>
          <figure class="globe__review globe__review--<?= $pos ?>">
            <span class="globe__stars" aria-hidden="true"><?php
              echo str_repeat('&#9733;', $stars) . str_repeat('&#9734;', 5 - $stars);
            ?></span>
            <blockquote><?= $quote ?></blockquote>
            <figcaption><?= $who ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <p class="hero-band__art-note">Example reviews, for illustration</p>
    </div>
  </div>

  <?php /* The reference fills this panel with invented traction — 10K+
           businesses, 100K+ monthly views. There are no customers yet, so it
           carries things a visitor can check instead. */ ?>
  <div class="container">
    <div class="hero-panel">
      <p class="hero-panel__label">Reviews, done properly</p>
      <div class="hero-panel__grid">
        <?php foreach ([
          ['shield', '0',   'Reviews written, bought or gated. Ever.'],
          ['chart',  '100', 'Point review score, in about ten seconds.'],
          ['star',   '$0',  'To start. No card, no subscription.'],
        ] as [$icon, $value, $label]): ?>
          <div class="hero-stat">
            <span class="hero-stat__tile"><?= Icon::render($icon) ?></span>
            <span>
              <span class="hero-stat__v"><?= $value ?></span>
              <span class="hero-stat__l"><?= $label ?></span>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
