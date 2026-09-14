<?php use App\Support\Icon; use App\Support\View; ?>
<section class="section">
  <div class="container split split--form">
    <div>
      <p class="eyebrow">Free review audit</p>
      <h1>See exactly where your reviews stand</h1>
      <p class="lede">Tell us your business and we&rsquo;ll send you a short
        report — no charge, no card, no sales call unless you want one.</p>
      <div class="grid" style="margin-top:2rem;gap:1rem;">
        <?php foreach ([
          ['star','Your rating and review count','And how fast they are actually growing.'],
          ['chart','How you compare','Against the three nearest businesses competing for the same customers.'],
          ['clock','Your review velocity','How many arrived in the last 90 days, which matters more than the total.'],
          ['message','What is going unanswered','Unanswered reviews are the cheapest thing to fix and the most visible.'],
        ] as [$icon,$title,$body]): ?>
          <div style="display:flex;gap:1rem;align-items:flex-start;">
            <?= Icon::chip($icon) ?>
            <div>
              <h3 style="font-size:1rem;"><?= View::e($title) ?></h3>
              <p class="muted" style="margin:.2rem 0 0;font-size:.92rem;"><?= View::e($body) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card" id="start">
      <h2 style="font-size:1.25rem;">Request your audit</h2>
      <p class="muted" style="margin:.6rem 0 1.4rem;font-size:.94rem;">
        Early audits are put together by hand, so they take a day — and they are
        far more useful than an automated one.</p>
      <?php
        $role = 'business';
        $source = 'audit-page';
        $cta = 'Send me my free audit';
        $note = 'No card. No obligation. We will not add you to a drip sequence.';
        require APP_ROOT . '/Views/partials/lead-form.php';
      ?>
    </div>
  </div>
</section>
