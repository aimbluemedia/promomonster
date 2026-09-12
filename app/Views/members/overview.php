<?php use App\Support\View; /** @var array $account @var array $locations @var array $stats */ ?>
<div class="admin-title">
  <h1>Dashboard</h1>
  <span class="pill-status st-new"><?= View::e(ucfirst((string) ($account['plan'] ?? 'trial'))) ?></span>
</div>

<div class="notice" style="margin-bottom:1.5rem;">
  <strong>Early access.</strong>
  <p>Your audit, playbook and QR setup are prepared by hand while the platform
     is built. The screens below fill in as each piece ships — nothing here is
     pretending to work yet.</p>
</div>

<div class="stat-grid">
  <?php foreach ([
    ['contacts',   'Customers added'],
    ['requests',   'Requests sent'],
    ['reviews',    'Reviews collected'],
    ['unanswered', 'Awaiting a reply'],
  ] as [$key, $label]): ?>
    <div class="stat-card">
      <div class="stat-card__v"><?= (int) ($stats[$key] ?? 0) ?></div>
      <div class="stat-card__l"><?= View::e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<h2 style="font-size:1.15rem;margin:2.25rem 0 1rem;">Your locations</h2>
<div class="table-wrap">
  <?php if ($locations === []): ?>
    <p class="empty">No locations yet — we add these for you during setup.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Location</th><th>Vertical</th><th>Google listing</th><th>Review link</th></tr></thead>
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
