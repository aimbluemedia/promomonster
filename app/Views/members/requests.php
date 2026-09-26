<?php use App\Support\View; /** @var array $rows */ ?>
<div class="admin-title"><h1>Review requests</h1><span class="muted" style="font-size:.9rem;"><?= count($rows) ?> shown</span></div>
<div class="table-wrap">
  <?php if ($rows === []): ?>
    <p class="empty">No requests sent yet. Sending ships in the next release —
      until then we send them for you as part of your setup.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Customer</th><th>Channel</th><th>Status</th><th>Location</th><th>Sent</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= View::e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?: '—' ?></td>
            <td><?= View::e(strtoupper((string) $r['channel'])) ?></td>
            <td><span class="pill-status st-new"><?= View::e(str_replace('_', ' ', (string) $r['status'])) ?></span></td>
            <td><?= View::e($r['location_name']) ?></td>
            <td><?= View::e($r['sent_at'] ? date('j M, H:i', strtotime((string) $r['sent_at'])) : '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
