<?php use App\Support\View; /** @var array $rows */ ?>
<div class="admin-title"><h1>Reviews</h1><span class="muted" style="font-size:.9rem;"><?= count($rows) ?> shown</span></div>
<div class="table-wrap">
  <?php if ($rows === []): ?>
    <p class="empty">No reviews synced yet. Monitoring turns on once your Google
      listing is connected — we do that during setup.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Author</th><th>Rating</th><th>Review</th><th>Location</th><th>Replied</th><th>Posted</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= View::e($r['author_name'] ?? 'Anonymous') ?></td>
            <td class="stars"><?= str_repeat('★', (int) ($r['rating'] ?? 0)) ?></td>
            <td class="wrap"><?= View::e(mb_substr((string) ($r['body'] ?? ''), 0, 160)) ?></td>
            <td><?= View::e($r['location_name']) ?></td>
            <td><?= $r['replied_at'] ? 'Yes' : '<span class="pill-status st-new">Needs reply</span>' ?></td>
            <td><?= View::e($r['posted_at'] ? date('j M Y', strtotime((string) $r['posted_at'])) : '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
