<?php use App\Support\Plans; use App\Support\View;
$plans = Plans::selectable();
$faqs = [
  ['Can I sign up right now?', 'Yes, on Free — create an account and you are in straight away. Pro and Premium are a different matter: card payments are not switched on yet, so choosing one records your request and we set the subscription up with you directly. Your account works on Free in the meantime and nothing is charged until you agree to it.'],
  ['Why is SMS on Pro and not Free?', 'Because sending review requests by text requires each business to be registered with the mobile carriers, and that registration carries a real monthly cost per business. Free stays genuinely useful over email rather than us pretending texting is free.'],
  ['Do you filter out unhappy customers before asking?', 'No, and we never will. That is called review gating: Google prohibits it and the FTC treats it as deceptive. Some tools still do it quietly. It puts the profile you have spent years building at risk.'],
  ['Can I offer a discount for leaving a review?', 'No. Google prohibits any incentive for a review, regardless of what the review says. Our templates keep you on the right side of that automatically.'],
  ['Can you post reviews to Google for me?', 'No, and no tool can — Google has no API that creates reviews, on purpose. A review has to be written and posted by the customer from their own Google account. What we do is make that as close to one tap as possible, then track which request produced which review. Anyone offering to post reviews for you is either fabricating them or misunderstanding how Google works.'],
  ['Can you get reviews removed?', 'Nobody can, and anyone who says otherwise is selling you something. What we can do is make sure the honest ones keep coming, which is what actually moves a rating.'],
  ['What about Yelp?', 'Yelp prohibits asking for reviews at all, so we do not offer it. Any tool that does is putting your Yelp page at risk.'],
  ['Do I need a contract?', 'No. Monthly, cancel whenever. Your reviews are on your own Google profile and stay there regardless.'],
];
?>
<section class="section">
  <div class="container center">
    <p class="eyebrow">Pricing</p>
    <h1>Straightforward monthly pricing</h1>
    <p class="lede">Start free. No setup fee, no contract, no sales call.
      Competitors charge $300–$600 a month for this.</p>
    <div class="notice" style="margin-top:2rem;max-width:44rem;margin-inline:auto;text-align:left;">
      <strong>Free is open now. Paid plans are set up by hand.</strong>
      <p>Create a Free account and you are in immediately. Card payments are not
        switched on yet, so choosing Pro or Premium records your request and we
        arrange the subscription with you &mdash; your account runs on Free until
        then, and nothing is charged without your say-so.</p>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="grid grid--3">
      <?php foreach ($plans as $key => $plan): ?>
        <div class="card plan<?= $plan['featured'] ? ' plan--featured' : '' ?>">
          <?php if ($plan['featured']): ?><span class="plan__badge">Most popular</span><?php endif; ?>
          <h3 style="font-size:1.15rem;"><?= View::e($plan['name']) ?></h3>
          <div style="margin:.6rem 0 .3rem;">
            <span class="plan__price"><?= $plan['price'] === 0 ? 'Free' : '$' . (int) $plan['price'] ?></span>
            <?php if ($plan['price'] > 0): ?><span class="plan__per">/month</span><?php endif; ?>
          </div>
          <p class="muted" style="font-size:.92rem;margin:0 0 .4rem;"><?= View::e($plan['tagline']) ?></p>
          <ul class="checklist">
            <?php foreach ($plan['features'] as [$label,$on]): ?>
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
