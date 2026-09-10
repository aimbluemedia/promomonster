<?php use App\Support\View; /** @var array $counts @var array $recent */ ?>
<div class="admin-title"><h1>Overview</h1></div>

<div class="stat-grid">
  <?php foreach ([
    ['audits_new',        'New audit requests'],
    ['audits_week',       'Requests this week'],
    ['audits_converted',  'Converted to customers'],
    ['agencies_new',      'New agency applications'],
  ] as [$key, $label]): ?>
    <div class="stat-card">
      <div class="stat-card__v"><?= (int) ($counts[$key] ?? 0) ?></div>
      <div class="stat-card__l"><?= View::e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="stat-grid" style="margin-top:1rem;">
  <?php foreach ([
    ['audits_total',       'Audit requests, all time'],
    ['agencies_total',     'Agency applications'],
    ['accounts_total',     'Customer accounts'],
    ['suppressions_total', 'Opt-outs on record'],
  ] as [$key, $label]): ?>
    <div class="stat-card">
      <div class="stat-card__v"><?= (int) ($counts[$key] ?? 0) ?></div>
      <div class="stat-card__l"><?= View::e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<h2 style="font-size:1.15rem;margin:2.25rem 0 1rem;">Latest audit requests</h2>
<div class="table-wrap">
  <?php if ($recent === []): ?>
    <p class="empty">No audit requests yet. They arrive from the form on <code>/audit</code>.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Business</th><th>Email</th><th>Vertical</th><th>Status</th><th>Received</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><strong><?= View::e($r['business_name']) ?></strong></td>
            <td><?= View::e($r['email']) ?></td>
            <td><?= View::e($r['vertical'] ?? '—') ?></td>
            <td><span class="pill-status st-<?= View::e($r['status']) ?>"><?= View::e(str_replace('_',' ',$r['status'])) ?></span></td>
            <td><?= View::e(date('j M, H:i', strtotime((string) $r['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<p style="margin-top:1rem;"><a href="/superadmin/audits" style="color:var(--brand);font-weight:700;">All audit requests →</a></p>
