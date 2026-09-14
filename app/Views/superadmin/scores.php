<?php use App\Support\Csrf; use App\Support\View;
/** @var array $rows @var int $page @var int $pages @var int $total @var int $perPage */
$first = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$last  = min($page * $perPage, $total);
?>
<div class="admin-title">
  <h1>Review scores</h1>
  <span class="muted" style="font-size:.9rem;">
    <?= $total === 0 ? 'none yet' : "showing {$first}&ndash;{$last} of {$total}" ?>
  </span>
</div>

<div class="table-wrap">
  <?php if ($rows === []): ?>
    <p class="empty">No scores yet. They appear here as visitors use the form on the homepage.</p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr><th>Score</th><th>Website</th><th>Email</th><th>Findings</th><th>When</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          $result = json_decode((string) ($r['results'] ?? '{}'), true) ?: [];
          $score = (int) ($r['score'] ?? 0);
          $tone = $score >= 75 ? 'good' : ($score >= 45 ? 'mid' : 'low');
          $passed = is_array($result['passed'] ?? null) ? count($result['passed']) : 0;
          $checks = is_array($result['checks'] ?? null) ? count($result['checks']) : 0;
        ?>
          <tr>
            <td>
              <span class="score-pill score-pill--<?= $tone ?>"><?= $score ?></span>
              <div class="muted" style="font-size:.78rem;margin-top:.2rem;">
                <?= View::e((string) ($result['band'] ?? '')) ?>
              </div>
            </td>
            <td class="wrap">
              <?php $site = (string) ($r['website'] ?: $r['business_name']); ?>
              <a href="<?= View::e($site) ?>" target="_blank" rel="noopener noreferrer nofollow"
                 style="color:var(--brand);font-weight:600;word-break:break-all;">
                <?= View::e($site) ?></a>
            </td>
            <td class="mono">
              <a href="mailto:<?= View::e((string) $r['email']) ?>" style="color:var(--brand);">
                <?= View::e((string) $r['email']) ?></a>
            </td>
            <td class="muted" style="font-size:.85rem;">
              <?= $checks === 0 ? '&mdash;' : $passed . ' of ' . $checks . ' in place' ?>
            </td>
            <td class="mono" style="font-size:.82rem;"><?= View::e((string) $r['created_at']) ?></td>
            <td>
              <?php /* Deleting also clears this address's rate-limit buckets, so
                       the same site can be scored again straight away. */ ?>
              <form class="row-form" method="post" action="/superadmin/scores/delete"
                    onsubmit="return confirm('Delete this score? <?= View::e((string) $r['email']) ?> will be able to run another one.');">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="page" value="<?= (int) $page ?>">
                <button type="submit"
                        style="background:var(--surface);color:#b3261e;border-color:var(--line);">
                  Delete
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?>
      <a href="/superadmin/scores?page=<?= $page - 1 ?>">&larr; Newer</a>
    <?php else: ?>
      <span class="is-off">&larr; Newer</span>
    <?php endif; ?>

    <span class="pager__at">Page <?= $page ?> of <?= $pages ?></span>

    <?php if ($page < $pages): ?>
      <a href="/superadmin/scores?page=<?= $page + 1 ?>">Older &rarr;</a>
    <?php else: ?>
      <span class="is-off">Older &rarr;</span>
    <?php endif; ?>
  </nav>
<?php endif; ?>
