<?php use App\Support\View; /** @var array $suppressions @var array $brands */ ?>
<div class="admin-title"><h1>Compliance</h1></div>

<h2 style="font-size:1.1rem;margin-bottom:.5rem;">Opt-outs</h2>
<p class="muted" style="font-size:.9rem;margin:0 0 1rem;max-width:46rem;">
  Global and permanent. Addresses are stored hashed, so there is nothing to read
  here — only that an opt-out exists. Deleting an account never clears one.</p>
<div class="table-wrap">
  <?php if ($suppressions === []): ?>
    <p class="empty">No opt-outs recorded.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Channel</th><th>Reason</th><th>Recorded</th></tr></thead>
      <tbody>
        <?php foreach ($suppressions as $s): ?>
          <tr>
            <td><?= View::e($s['channel']) ?></td>
            <td><?= View::e(str_replace('_', ' ', $s['reason'])) ?></td>
            <td><?= View::e(date('j M Y, H:i', strtotime((string) $s['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<h2 style="font-size:1.1rem;margin:2.25rem 0 .5rem;">Carrier registration</h2>
<p class="muted" style="font-size:.9rem;margin:0 0 1rem;max-width:46rem;">
  Every account that sends SMS needs its own 10DLC brand and campaign approved.
  Unregistered traffic is blocked by carriers, not merely flagged, so nothing
  sends until a brand reaches <strong>approved</strong>.</p>
<div class="table-wrap">
  <?php if ($brands === []): ?>
    <p class="empty">No registrations yet. SMS lands in Phase 2.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Account</th><th>Legal name</th><th>Type</th><th>Status</th><th>Submitted</th></tr></thead>
      <tbody>
        <?php foreach ($brands as $b): ?>
          <tr>
            <td><?= View::e($b['account_name']) ?></td>
            <td><?= View::e($b['legal_name']) ?></td>
            <td><?= View::e(str_replace('_', ' ', $b['entity_type'])) ?></td>
            <td><?= View::e(str_replace('_', ' ', $b['status'])) ?></td>
            <td><?= View::e($b['submitted_at'] ? date('j M Y', strtotime((string) $b['submitted_at'])) : '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
