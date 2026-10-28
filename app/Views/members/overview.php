<?php use App\Support\View; /** @var array $account @var array $locations @var array $stats
 *  @var array $steps @var array $progress */ ?>
<?php /* The plan cards used to be rendered here. They are in the layout now,
         under the nav on every page, so the dashboard would have shown them
         twice. */ ?>
<?php /* ---- Get started ---------------------------------------------- */ ?>
<?php /* Above the figures. Four numbers reading zero is a diagnosis without a
         prescription -- this is the prescription, and it stops being the first
         thing on the page by going quiet once it is finished, not by being
         dismissed. */ ?>
<?php /* The page says whose it is before it says anything else. The heading
         used to appear a third of the way down, under the plans and the
         checklist, so the first thing on screen was a price list. */ ?>
<div class="admin-title">
  <h1>Dashboard</h1>
  <?php /* The plan badge that sat here is gone: the cards above the nav
           already say which plan this is, and two answers to the same
           question on one screen is one too many. */ ?>
  <span class="muted" style="font-size:.95rem;"><?= View::e($account['name'] ?? '') ?></span>
</div>

<?php /* ---- What to do next ------------------------------------------- */ ?>
<?= View::render('members/_getting-started', [
    'steps'    => $steps,
    'progress' => $progress,
]) ?>

<?php /* An "Early access -- nothing here is pretending to work yet" notice
         stood here. It was written before any of this worked, and it sat
         directly above a checklist ticking real work and four figures counting
         real rows. Telling somebody the product is a mock-up while it does
         their job for them is worse than saying nothing. */ ?>

<?php /* ---- How it is going ------------------------------------------- */ ?>
<h2 style="font-size:1.15rem;margin:2.25rem 0 .35rem;">How it is going</h2>
<p class="muted" style="margin:0 0 1rem;font-size:.9rem;">
  Everything you have asked for, and everything that has come back.
</p>

<div class="stat-grid">
  <?php /* Labelled in the owner's words, not ours. "Requests sent" is what
           the table is called; "Customers you have asked" is what happened. */ ?>
  <?php foreach ([
    ['contacts',   'Customers added',        'People on your list'],
    ['requests',   'Customers you asked',    'Review requests you have sent'],
    ['reviews',    'Reviews you have got',   'On Google and on your own page'],
    ['unanswered', 'Reviews to reply to',    'Replying is the bit most people skip'],
  ] as [$key, $label, $hint]): ?>
    <div class="stat-card">
      <div class="stat-card__v"><?= (int) ($stats[$key] ?? 0) ?></div>
      <div class="stat-card__l"><?= View::e($label) ?></div>
      <div class="stat-card__h"><?= View::e($hint) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<h2 style="font-size:1.15rem;margin:2.25rem 0 .35rem;">Your business</h2>
<p class="muted" style="margin:0 0 1rem;font-size:.9rem;">
  What we have on file. Tell us if any of it is wrong.
</p>
<div class="table-wrap">
  <?php if ($locations === []): ?>
    <p class="empty">No locations yet — we add these for you during setup.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Your business</th><th>Trade</th><th>Google listing</th><th>Review link</th></tr></thead>
      <tbody>
        <?php foreach ($locations as $l): ?>
          <tr>
            <td><strong><?= View::e($l['name']) ?></strong>
              <?php if (!empty($l['city'])): ?>
                <div class="muted" style="font-size:.82rem;"><?= View::e($l['city'] . ', ' . ($l['region'] ?? '')) ?></div>
              <?php endif; ?>
            </td>
            <td><?= View::e($l['vertical'] ?? '—') ?></td>
            <td><?= $l['google_place_id'] ? 'Connected' : '<span class="muted">Not connected</span>' ?></td>
            <td>
              <?php if (!empty($l['google_review_url'])): ?>
                <a href="<?= View::e($l['google_review_url']) ?>" style="color:var(--brand);" rel="noopener" target="_blank">Open</a>
              <?php else: ?><span class="muted">—</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
