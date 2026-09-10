<?php use App\Support\Csrf; use App\Support\View;
/** @var array $rows @var string $filter @var array $statuses */ ?>
<div class="admin-title">
  <h1>Audit requests</h1>
  <span class="muted" style="font-size:.9rem;"><?= count($rows) ?> shown</span>
</div>

<div class="filters">
  <a href="/superadmin/audits"<?= $filter === '' ? ' aria-current="page"' : '' ?>>All</a>
  <?php foreach ($statuses as $s): ?>
    <a href="/superadmin/audits?status=<?= View::e($s) ?>"<?= $filter === $s ? ' aria-current="page"' : '' ?>>
      <?= View::e(ucfirst(str_replace('_', ' ', $s))) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
  <?php if ($rows === []): ?>
    <p class="empty">Nothing here.</p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr><th>Business</th><th>Contact</th><th>Vertical</th><th>Status</th><th>Notes &amp; update</th><th>Received</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          $extra = json_decode((string) ($r['results'] ?? '{}'), true) ?: []; ?>
          <tr>
            <td>
              <strong><?= View::e($r['business_name']) ?></strong>
              <?php if (!empty($extra['website'])): ?>
                <div class="mono" style="margin-top:.2rem;"><?= View::e($extra['website']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <a href="mailto:<?= View::e($r['email']) ?>" style="color:var(--brand);"><?= View::e($r['email']) ?></a>
              <?php if (!empty($extra['requested_by'])): ?>
                <div class="muted" style="font-size:.82rem;"><?= View::e($extra['requested_by']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= View::e($r['vertical'] ?? '—') ?></td>
            <td>
              <span class="pill-status st-<?= View::e($r['status']) ?>"><?= View::e(str_replace('_',' ',$r['status'])) ?></span>
              <?php if (!empty($r['handler_first'])): ?>
                <div class="muted" style="font-size:.78rem;margin-top:.25rem;">
                  <?= View::e($r['handler_first'] . ' ' . $r['handler_last']) ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="wrap">
              <form class="row-form" method="post" action="/superadmin/audits/update">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <select name="status" aria-label="Status">
                  <?php foreach ($statuses as $s): ?>
                    <option value="<?= View::e($s) ?>"<?= $r['status'] === $s ? ' selected' : '' ?>>
                      <?= View::e(ucfirst(str_replace('_', ' ', $s))) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="notes" placeholder="Notes"
                       value="<?= View::e($r['notes'] ?? '') ?>" aria-label="Notes">
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
