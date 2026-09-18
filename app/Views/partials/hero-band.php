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
      <span><?= Icon::render('sparkle') ?> Ask every customer &middot; Reply in a click &middot;
        Show your reviews on your own site</span>
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

      <?php /* Every claim here is something the reader can go and check in a
               minute: the rating sits on the listing above the website link,
               Google Maps really does filter by rating, and AI assistants
               really do summarise the same reviews. No invented percentages —
               the ones every competitor quotes are exactly the kind of number a
               customer can catch us on. */ ?>
      <p class="hero-band__lede">
        Your stars are the first thing a customer sees &mdash; before your website,
        before your price. Google Maps lets people filter you out below 4.0
        outright, and the AI assistants they now ask for a recommendation are
        reading those same reviews. It is the one asset you cannot buy, only earn.
      </p>
      <p class="hero-band__lede hero-band__lede--tight">
        PromoMonster earns it on purpose: every customer asked at the right
        moment, replies drafted for you, and the results working on your own site.
      </p>

      <div class="hero-band__cta">
        <a class="btn btn--primary btn--xl" href="#score">Get my Free Review Score &rarr;</a>
        <a class="btn hero-band__ghost btn--xl" href="/how-it-works">
          <?= Icon::render('search') ?> See how it works
        </a>
      </div>
      <p class="hero-band__note">Takes about ten seconds. No credit card.</p>

      <div class="hero-band__chips">
        <span class="hero-band__chips-label">Works with:</span>
        <?php foreach ([
          ['star', 'Google'],
          ['send', 'SMS &amp; email'],
          ['qr', 'QR codes'],
          ['layout', 'Your website'],
        ] as [$icon, $label]): ?>
          <span class="hero-chip"><?= Icon::render($icon) ?><?= $label ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <?php /* Illustrative, and read as such: a rating at the centre with the
             channels that feed it orbiting around. Not a screenshot, not a
             claim. aria-hidden because it repeats what the copy already says. */ ?>
    <div class="hero-band__art" aria-hidden="true">
      <div class="orbit">
        <span class="orbit__ring orbit__ring--1"></span>
        <span class="orbit__ring orbit__ring--2"></span>
        <span class="orbit__ring orbit__ring--3"></span>

        <div class="orbit__core">
          <span class="orbit__stars">★★★★★</span>
          <span class="orbit__num">4.8</span>
          <span class="orbit__label">earned, not bought</span>
        </div>

        <?php foreach ([
          ['n1', 'star',    'New 5&#9733; review'],
          ['n2', 'send',    'Request sent'],
          ['n3', 'sparkle', 'Reply drafted'],
          ['n4', 'qr',      'QR scanned'],
          ['n5', 'layout',  'Live on your site'],
        ] as [$pos, $icon, $label]): ?>
          <span class="orbit__node orbit__node--<?= $pos ?>">
            <?= Icon::render($icon) ?><span><?= $label ?></span>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php /* Where the reference puts invented traction. These are checkable. */ ?>
  <div class="container">
    <div class="hero-band__facts">
      <p class="hero-band__facts-label">Honest by design</p>
      <div class="hero-band__facts-grid">
        <?php foreach ([
          ['shield', '0',   'Reviews we write, buy or gate. Ever.'],
          ['chart',  '100', 'Point score, and every point traceable to your page.'],
          ['star',   '$0',  'To start, and no card to find out.'],
        ] as [$icon, $value, $label]): ?>
          <div class="hero-fact">
            <?= Icon::render($icon) ?>
            <div>
              <span class="hero-fact__v"><?= $value ?></span>
              <span class="hero-fact__l"><?= $label ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
