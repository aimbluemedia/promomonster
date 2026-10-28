<?php use App\Support\Icon; use App\Support\View; ?>
<section class="section">
  <div class="container split split--form">
    <div>
      <p class="eyebrow">For agencies</p>
      <h1>Add reputation management to what you already sell</h1>
      <p class="lede">Your clients already ask you why competitors outrank them.
        Reviews are a large part of the answer, and this is a service you can
        sell for $199–$399 a month on top of what you do now.</p>
      <ul class="checklist" style="margin-top:1.75rem;">
        <li>Manage every client from one login</li>
        <li>White-label reports with your own branding</li>
        <li>Per-client review growth scores your clients understand</li>
        <li>Playbooks by vertical, so onboarding is fast</li>
        <li>Revenue share on every account you bring</li>
      </ul>
      <p class="muted" style="margin-top:1.5rem;font-size:.95rem;">
        The partner programme is application-only. We check who is sending
        through the platform, because one operator importing a scraped list
        damages deliverability for every legitimate business on it.</p>
    </div>
    <div class="card" id="apply">
      <h2 style="font-size:1.25rem;">Apply to the partner programme</h2>
      <p class="muted" style="margin:.6rem 0 1.4rem;font-size:.94rem;">
        Tell us a little about your agency and we will get back to you
        personally.</p>
      <?php
        $role = 'agency';
        $source = 'agencies-page';
        $cta = 'Apply';
        $note = 'No card required. We reply personally, usually within a day.';
        require APP_ROOT . '/Views/partials/lead-form.php';
      ?>
    </div>
  </div>
</section>

<section class="section section--wash">
  <div class="container">
    <h2 class="center">What you are reselling</h2>
    <div class="grid grid--3" style="margin-top:2.25rem;">
      <?php foreach ([
        ['chart','A number that moves','Review count and rating are visible on the client\'s own Google profile. There is no arguing about whether it worked.'],
        ['shield','Nothing that puts them at risk','No gating, no incentives, no bought reviews. You are not handing a client a liability.'],
        ['layout','Reports they can read','Monthly, branded as yours, showing requests sent, reviews gained and what is still unanswered.'],
      ] as [$icon,$title,$body]): ?>
        <div class="card feature">
          <?= Icon::chip($icon) ?>
          <h3><?= View::e($title) ?></h3>
          <p><?= View::e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
