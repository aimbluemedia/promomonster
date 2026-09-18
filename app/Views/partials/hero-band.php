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

      <?php /* One short paragraph. Both claims are checkable in a minute — the
               rating sits on the listing above the website link, and Google Maps
               really does filter below 4.0. No borrowed percentages. */ ?>
      <p class="hero-band__lede">
        Your stars are the first thing a customer sees, and Google Maps filters
        you out below 4.0. PromoMonster asks every customer, drafts your replies,
        and puts the results to work on your site.
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
    <?php
    /**
     * The globe.
     *
     * Built rather than drawn: the graticule and the dot mesh are projected
     * from real spherical coordinates, so the latitude squash and the way dots
     * crowd towards the limb come out right instead of being faked with
     * hand-tuned ellipses. R and the centre are the only inputs.
     *
     * Only the front hemisphere is drawn, with dot size and opacity falling off
     * by depth — that is what reads as a sphere rather than a circle.
     */
    $R = 150.0; $cx = 200.0; $cy = 200.0;

    // Latitude rings: a circle of constant latitude, seen edge-on, is an
    // ellipse whose centre rises with the latitude and whose height is the
    // radius squashed by the viewing angle.
    $latitudes = [];
    foreach ([-60, -40, -20, 0, 20, 40, 60] as $deg) {
        $t = deg2rad($deg);
        $latitudes[] = [
            'cy' => $cy - $R * sin($t),
            'rx' => $R * cos($t),
            'ry' => $R * cos($t) * 0.26,
        ];
    }

    // Longitude arcs: same height, width narrowing to nothing at the limb.
    $longitudes = [];
    foreach ([0, 30, 60, 90, 120, 150] as $deg) {
        $longitudes[] = $R * abs(cos(deg2rad($deg)));
    }

    // Dot mesh over the front hemisphere.
    $dots = [];
    for ($lat = -75; $lat <= 75; $lat += 10) {
        $t = deg2rad($lat);
        // Fewer dots near the poles, so spacing stays even on the surface.
        $step = max(9, (int) round(9 / max(0.18, cos($t))));
        for ($lon = -90; $lon <= 90; $lon += $step) {
            $g = deg2rad($lon);
            $depth = cos($t) * cos($g);      // 1 facing us, 0 at the limb
            if ($depth <= 0.06) {
                continue;
            }
            $dots[] = [
                'x' => $cx + $R * cos($t) * sin($g),
                'y' => $cy - $R * sin($t),
                'r' => 0.9 + 1.5 * $depth,
                'o' => 0.14 + 0.5 * $depth,
            ];
        }
    }
    ?>
    <?php /* aria-hidden sits on the illustration, not on the whole column, so
             the "example reviews" caption below it is still announced. Hiding
             that caption from a screen reader while showing invented reviews to
             everyone else is exactly the wrong way round. */ ?>
    <div class="hero-band__art">
      <div class="globe-wrap" aria-hidden="true">
        <svg class="globe" viewBox="0 0 400 400">
          <defs>
            <radialGradient id="pm-sphere" cx="38%" cy="30%" r="78%">
              <stop offset="0%"  stop-color="#1b5c8f"/>
              <stop offset="55%" stop-color="#0d3b5e"/>
              <stop offset="100%" stop-color="#071a2c"/>
            </radialGradient>
            <radialGradient id="pm-halo" cx="50%" cy="50%" r="50%">
              <stop offset="60%" stop-color="rgba(56,189,248,0)"/>
              <stop offset="88%" stop-color="rgba(56,189,248,.28)"/>
              <stop offset="100%" stop-color="rgba(56,189,248,0)"/>
            </radialGradient>
            <clipPath id="pm-clip"><circle cx="200" cy="200" r="150"/></clipPath>
          </defs>

          <circle cx="200" cy="200" r="196" fill="url(#pm-halo)"/>
          <circle cx="200" cy="200" r="150" fill="url(#pm-sphere)"/>

          <g clip-path="url(#pm-clip)" fill="none" stroke="#38bdf8" stroke-opacity=".20">
            <?php foreach ($latitudes as $l): ?>
              <ellipse cx="200" cy="<?= round($l['cy'], 1) ?>"
                       rx="<?= round($l['rx'], 1) ?>" ry="<?= round($l['ry'], 1) ?>"/>
            <?php endforeach; ?>
            <?php foreach ($longitudes as $rx): ?>
              <ellipse cx="200" cy="200" rx="<?= round($rx, 1) ?>" ry="150"/>
            <?php endforeach; ?>
          </g>

          <g clip-path="url(#pm-clip)" fill="#7dd3fc">
            <?php foreach ($dots as $d): ?>
              <circle cx="<?= round($d['x'], 1) ?>" cy="<?= round($d['y'], 1) ?>"
                      r="<?= round($d['r'], 2) ?>" opacity="<?= round($d['o'], 2) ?>"/>
            <?php endforeach; ?>
          </g>

          <?php /* Rim light: brighter where the sphere turns away from us. */ ?>
          <circle cx="200" cy="200" r="150" fill="none"
                  stroke="rgba(125,211,252,.45)" stroke-width="1.5"/>
        </svg>

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
