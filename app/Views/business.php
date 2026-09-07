<?php
$studies = [
  ['Site feedback', 'from $49', 'Real people visit your page and answer 3–6 questions. What do you sell, how clear was it, what would stop them getting in touch.', '50 responses · ~4 hours'],
  ['Head-to-head test', 'from $75', 'Two headlines, two hero images, two offers, two logos. Which wins — and, more usefully, why, in their own words.', '50 responses · ~4 hours'],
  ['Search listing test', 'from $75', 'Your listing shown against three real competitors. Which would they click, and what made the difference — reviews, brand, offer, wording.', '50 responses · ~6 hours'],
  ['Ad creative test', 'from $75', 'Your thumbnail, title and first three seconds, shown at real feed scale. Would they stop scrolling? What did they think it was about?', '50 responses · ~6 hours'],
];
$reasons = [
  ['Every study has an open-text question', 'Ratings tell you something is wrong. Sentences tell you what. The open answers are where the useful part lives, so we require at least one.'],
  ['Low-effort answers get rejected', 'Attention checks, minimum response times, duplicate and gibberish detection. If a response is junk, reject it and we re-field it free.'],
  ['Members are paid properly', 'Around $8–$16 an hour, well above what micro-task platforms typically pay. People who are paid fairly write real answers.'],
  ['Ordinary people, not marketers', 'Our panel is consumers, not other business owners. That sounds obvious and it is the single biggest difference in whether the feedback reflects your actual customers.'],
];
use App\Support\View;
?>
<section class="section section--hero section--hero-bordered">
  <div class="container">
    <div class="prose">
      <p class="eyebrow">For businesses</p>
      <h1>Your analytics say they left. We&rsquo;ll tell you why.</h1>
      <p class="lede">Send your page to 50&ndash;500 real people and get their
        honest first impressions in their own words. No panel minimums, no
        annual contract, no sales call unless you want one.</p>
    </div>
    <div class="stat-row">
      <div><div class="stat__value">50–500</div><div class="stat__label">Real respondents per study</div></div>
      <div><div class="stat__value">~4 hrs</div><div class="stat__label">Typical turnaround</div></div>
      <div><div class="stat__value">$0.98</div><div class="stat__label">Per response, from</div></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2>What you can run</h2>
    <div class="grid grid--sm2" style="margin-top:2rem;">
      <?php foreach ($studies as [$name, $price, $body, $detail]): ?>
        <div class="card">
          <div class="card__head">
            <h3><?= View::e($name) ?></h3>
            <span class="price"><?= View::e($price) ?></span>
          </div>
          <p class="muted" style="margin:.625rem 0 0;font-size:.9375rem;"><?= View::e($body) ?></p>
          <p class="mono" style="margin-top:1rem;"><?= View::e($detail) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--bordered">
  <div class="container split">
    <div>
      <h2>What makes the answers worth reading</h2>
      <dl class="dl">
        <?php foreach ($reasons as [$term, $desc]): ?>
          <div><dt><?= View::e($term) ?></dt><dd><?= View::e($desc) ?></dd></div>
        <?php endforeach; ?>
      </dl>
    </div>
    <div id="start">
      <div class="card">
        <h2 style="font-size:1.25rem;">Start your first study</h2>
        <p class="muted" style="margin:.5rem 0 1.5rem;font-size:.9375rem;">
          We&rsquo;re running early studies hands-on, so tell us what you want
          to learn and we&rsquo;ll set it up with you and get results back
          within a day or two.</p>
        <?php
          $role = 'business';
          $source = 'business-page';
          $cta = 'Request a study';
          $note = "No card required. We'll reply personally, usually within a day.";
          require APP_ROOT . '/Views/partials/waitlist-form.php';
        ?>
      </div>
    </div>
  </div>
</section>
