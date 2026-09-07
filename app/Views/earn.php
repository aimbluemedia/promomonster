<?php
use App\Support\View;
$faqs = [
  ['How much can I actually make?', 'A typical study takes about ninety seconds and pays $0.35, which works out to roughly $14 an hour while studies are available. We are honest that this is side income, not a job — how much you make depends on how many studies are running that match you. We would rather tell you that up front than have you find out.'],
  ['When do I get paid?', 'Cash out any time you are above $10. Payouts run weekly by PayPal. Your first one is held about seven days while we verify the account, then it is weekly after that.'],
  ['Is there anything to buy?', 'No. There are no fees, no upgrades, no starter kit, and we will never ask you for money. If anything ever asks you to pay us to earn, it is not us.'],
  ['What do I actually do?', 'Visit a website for a minute, then answer a few questions about it — what you think the company does, whether anything confused you, which of two headlines is clearer. There are no right answers. Honest reactions are the entire product.'],
  ['Why do some answers get rejected?', 'Studies include attention checks, and one-word or copy-pasted answers get rejected because businesses are paying for real opinions. Write what you actually thought, even if it is short and blunt, and you will be fine.'],
  ['Who can join?', 'Eighteen or over and based in the US for now. We will open other countries once payouts and support are running smoothly.'],
];
?>
<section class="section section--hero section--hero-bordered">
  <div class="container split">
    <div>
      <p class="eyebrow">For panel members</p>
      <h1>Get paid to share your opinion.</h1>
      <p class="lede">Businesses want to know what real people think of their
        websites. You look, you answer honestly, you get paid. Around
        <strong style="color:var(--ink);">$8&ndash;$16 an hour</strong>, on your
        own schedule.</p>
      <ul class="tick-list tick-list--earn" style="margin-top:2rem;">
        <li>No selling, no calls, no experience needed</li>
        <li>Work whenever you like — no minimum hours</li>
        <li>Cash out at $10, paid weekly by PayPal</li>
        <li>Completely free, with no fees taken from your earnings</li>
      </ul>
    </div>
    <div id="start">
      <div class="card">
        <h2 style="font-size:1.25rem;">Join the panel</h2>
        <p class="muted" style="margin:.5rem 0 1.5rem;font-size:.9375rem;">
          We open spots in batches, so there&rsquo;s always work waiting when
          you log in. Tell us where you are and we&rsquo;ll email you when yours
          is ready.</p>
        <?php
          $role = 'panelist';
          $source = 'earn-page';
          $cta = 'Join the panel';
          $note = "Free to join. 18+ and US-based. We'll never ask you for payment.";
          require APP_ROOT . '/Views/partials/waitlist-form.php';
        ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2>Straight answers</h2>
    <dl class="dl dl--2" style="max-width:60rem;">
      <?php foreach ($faqs as [$q, $a]): ?>
        <div><dt><?= View::e($q) ?></dt><dd><?= View::e($a) ?></dd></div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
