<?php use App\Support\Plans; use App\Support\View;
$plans = Plans::selectable();
$faqs = [
  ['Can I sign up right now?', 'Yes, on Free — create an account and you are in straight away. Pro and Premium are a different matter: card payments are not switched on yet, so choosing one records your request and we set the subscription up with you directly. Your account works on Free in the meantime and nothing is charged until you agree to it.'],
  ['Do you send review requests by text?', 'No. Texting review requests means registering every single business with the mobile carriers first, at a recurring cost per business that a $19 plan cannot carry honestly. We send by email and put your Google review link in it, which needs nobody\'s permission and costs you nothing extra.'],
  ['What is the difference between the paid plans and Free?', 'How many you can send, and whose name it goes out under. Free sends four a month from PromoMonster with our footer on it. Pro sends sixty a month under your own business name with no footer, lets you write your own wording, import a customer list and see who opened the link. Premium raises that to three hundred, across up to five locations, with a login and an ask link for each of your people.'],
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
      <p>Signing up creates a Free account, whichever button you press. Once you
        are in, Settings has a Change plan panel where you can ask for Pro or
        Premium, and we arrange it with you from there.</p>
      <p>PromoMonster is in early access, so anything marked
        <span class="tag tag--soon" style="margin-left:0;">In build</span> below
        is on the roadmap and not available yet. Everything else you get today,
        including the pieces we still set up with you by hand. We would rather
        tell you here than let you find out after paying.</p>
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
            <?php /* The "In build" tag is only printed on things a customer would
                     otherwise assume they are buying: an included feature that
                     is not delivered yet. An excluded line needs no tag, and
                     tagging every live line would bury the ones that matter. */ ?>
            <?php foreach ($plan['features'] as [$label, $on, $state]): ?>
              <li<?= $on ? '' : ' class="is-off"' ?>><span><?= View::e($label) ?><?php
                if ($on && $state === Plans::STATE_SOON): ?><span class="tag tag--soon"><?=
                  View::e(Plans::STATE_LABELS[$state]) ?></span><?php endif; ?></span></li>
            <?php endforeach; ?>
          </ul>
          <?php /* Every button goes to the same place, with no plan on the URL.
                   Signup is Free only now, and the controller ignores a plan in
                   the request entirely -- so "Choose Pro" carrying ?plan=pro
                   would have quietly produced a Free account and lost the fact
                   that somebody wanted Pro. The upgrade is asked for in members
                   settings once the account exists, which is what the label
                   says. */ ?>
          <a class="btn <?= $plan['featured'] ? 'btn--primary' : 'btn--ghost' ?> btn--block"
             href="/members/signup">
            <?= $plan['price'] === 0 ? 'Start free' : 'Start free, then ask for ' . View::e($plan['name']) ?>
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
