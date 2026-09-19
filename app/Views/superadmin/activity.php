<?php use App\Support\View; /** @var array $rows */ ?>
<div class="admin-title"><h1>Activity</h1></div>
<p class="muted" style="font-size:.9rem;margin:0 0 1.25rem;max-width:46rem;">
  Append-only. Every admin action that changes state is recorded here with who
  did it and from where.</p>

<div class="table-wrap">
  <?php if ($rows === []): ?>
    <p class="empty">Nothing recorded yet.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Target</th><th>Change</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= View::e(date('j M, H:i:s', strtotime((string) $r['created_at']))) ?></td>
            <td><?= View::e($r['actor_email'] ?? 'system') ?></td>
            <td><code><?= View::e($r['action']) ?></code></td>
            <td><?= View::e($r['target_type'] ? $r['target_type'] . ' #' . $r['target_id'] : '—') ?></td>
            <td class="wrap"><span class="mono"><?= View::e(mb_substr((string) ($r['after_state'] ?? '—'), 0, 90)) ?></span></td>
            <td class="mono"><?= View::e($r['ip'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
