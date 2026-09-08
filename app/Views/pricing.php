<?php use App\Support\View;
$plans = [
  ['Starter','$39','/month', false, 'One location, email requests. Everything you need to start asking properly.', [
    ['Google review link and QR codes', true],
    ['Email review requests + reminder', true],
    ['Review monitoring and alerts', true],
    ['Review Growth Score and playbook', true],
    ['AI-drafted replies', true],
    ['SMS requests', false],
    ['Website widget', false],
  ]],
  ['Growth','$99','/month', true, 'Adds SMS, which is what most customers actually respond to.', [
    ['Everything in Starter', true],
    ['SMS review requests + reminder', true],
    ['Carrier registration handled for you', true],
    ['Website review widget', true],
    ['Social content from reviews', true],
    ['Team accounts', true],
    ['Multi-location', false],
  ]],
  ['Pro','$199','/month', false, 'For multiple locations, or a team that needs its own logins and numbers.', [
    ['Everything in Growth', true],
    ['Multi-location, scored separately', true],
    ['Per-team-member reporting', true],
    ['Priority support', true],
    ['Downloadable reports', true],
    ['White-label reporting', true],
  ]],
];
$faqs = [
  ['When can I actually sign up?', 'Not yet. Right now we run review audits and setups by hand for a small number of businesses while the platform is built. That is deliberate: it is how we learn what to build. Request an audit and we will tell you honestly whether we can help you now or whether you should check back.'],
  ['Why does SMS start at Growth and not Starter?', 'Because sending review requests by text requires each business to be registered with the mobile carriers, and that carries a real monthly cost. At $39 those fees would eat most of the plan. Starter stays genuinely useful without it, and Growth covers it properly rather than us pretending it is free.'],
  ['Do you filter out unhappy customers before asking?', 'No, and we never will. That is called review gating: Google prohibits it and the FTC treats it as deceptive. Some tools still do it quietly. It puts the profile you have spent years building at risk.'],
  ['Can I offer a discount for leaving a review?', 'No. Google prohibits any incentive for a review, regardless of what the review says. Our templates keep you on the right side of that automatically.'],
  ['Can you get reviews removed?', 'Nobody can, and anyone who says otherwise is selling you something. What we can do is make sure the honest ones keep coming, which is what actually moves a rating.'],
  ['What about Yelp?', 'Yelp prohibits asking for reviews at all, so we do not offer it. Any tool that does is putting your Yelp page at risk.'],
  ['Do I need a contract?', 'No. Monthly, cancel whenever. Your reviews are on your own Google profile and stay there regardless.'],
];
?>
<section class="section">
  <div class="container center">
    <p class="eyebrow">Pricing</p>
    <h1>Straightforward monthly pricing</h1>
    <p class="lede">No setup fee, no contract, no sales call. Competitors charge
      $300–$600 a month for this.</p>
    <div class="notice" style="margin-top:2rem;max-width:44rem;margin-inline:auto;text-align:left;">
      <strong>These plans are not open yet.</strong>
      <p>We are in early access: audits and setups are run by hand while the
        platform is built, and there is nothing to pay for today. This is what
        pricing will look like when self-serve opens. Start with the free audit
        and you will be first in.</p>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="grid grid--3">
      <?php foreach ($plans as [$name,$price,$per,$featured,$blurb,$rows]): ?>
        <div class="card plan<?= $featured ? ' plan--featured' : '' ?>">
          <?php if ($featured): ?><span class="plan__badge">Most popular</span><?php endif; ?>
          <h3 style="font-size:1.15rem;"><?= View::e($name) ?></h3>
          <div style="margin:.6rem 0 .3rem;">
            <span class="plan__price"><?= View::e($price) ?></span>
            <span class="plan__per"><?= View::e($per) ?></span>
          </div>
          <p class="muted" style="font-size:.92rem;margin:0 0 .4rem;"><?= View::e($blurb) ?></p>
          <ul class="checklist">
            <?php foreach ($rows as [$label,$on]): ?>
              <li<?= $on ? '' : ' class="is-off"' ?>><?= View::e($label) ?></li>
            <?php endforeach; ?>
          </ul>
          <a class="btn <?= $featured ? 'btn--primary' : 'btn--ghost' ?> btn--block" href="/audit">Start with a free audit</a>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="form__note center" style="margin-top:1.5rem;">
      Agencies and multi-client resellers — see <a href="/agencies" style="color:var(--brand);">For Agencies</a>.
    </p>
  </div>
</section>

<section class="section section--wash">
  <div class="container">
    <h2 class="center">Questions people actually ask</h2>
    <div class="faq" style="margin-top:2rem;max-width:52rem;margin-inline:auto;">
      <?php foreach ($faqs as $i => [$q,$a]): ?>
        <details<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= View::e($q) ?></summary>
          <p><?= View::e($a) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
