<?php use App\Support\Csrf; use App\Support\View;
/** @var array $rows @var array $statuses */ ?>
<div class="admin-title">
  <h1>Agency applications</h1>
  <span class="muted" style="font-size:.9rem;"><?= count($rows) ?> shown</span>
</div>

<div class="table-wrap">
  <?php if ($rows === []): ?>
    <p class="empty">No applications yet. They arrive from <code>/agencies</code>.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Agency</th><th>Contact</th><th>What they said</th><th>Status</th><th>Received</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <strong><?= View::e($r['company'] ?? '—') ?></strong>
              <?php if (!empty($r['website'])): ?>
                <div class="mono" style="margin-top:.2rem;"><?= View::e($r['website']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <a href="mailto:<?= View::e($r['email']) ?>" style="color:var(--brand);"><?= View::e($r['email']) ?></a>
              <?php if (!empty($r['name'])): ?>
                <div class="muted" style="font-size:.82rem;"><?= View::e($r['name']) ?></div>
              <?php endif; ?>
            </td>
            <td class="wrap"><?= View::e($r['goal'] ?? '—') ?></td>
            <td>
              <span class="pill-status st-<?= View::e($r['status']) ?>"><?= View::e($r['status']) ?></span>
              <form class="row-form" method="post" action="/superadmin/leads/update" style="margin-top:.4rem;">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <select name="status" aria-label="Status">
                  <?php foreach ($statuses as $s): ?>
                    <option value="<?= View::e($s) ?>"<?= $r['status'] === $s ? ' selected' : '' ?>><?= View::e(ucfirst($s)) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit">Save</button>
              </form>
            </td>
            <td><?= View::e(date('j M, H:i', strtotime((string) $r['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
