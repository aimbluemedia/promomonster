<?php use App\Support\ReviewRequests; use App\Support\View; ?>
<?php
/**
 * The sent-requests table, shared by the three screens that show it.
 *
 * One partial rather than three copies of the same markup: the Review requests
 * page shows everything, the Google page and the PromoMonster page each show
 * their own. Three hand-written copies is how the columns drift apart and how
 * one of them ends up saying "sent" for a row that failed.
 *
 * @var list<array<string,mixed>> $rows
 * @var string $empty       what to say when there is nothing
 * @var bool   $showWhere   whether the destination column earns its place
 */
$showWhere = $showWhere ?? false;
?>
<div class="table-wrap">
  <?php if ($rows === []): ?>
    <p class="empty"><?= View::e($empty) ?></p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr>
          <th>Customer</th>
          <?php if ($showWhere): ?><th>Where</th><?php endif; ?>
          <th>Status</th>
          <th>Sent</th>
          <th>Opened the link</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <strong><?= View::e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?: '&mdash;' ?></strong>
              <div class="muted" style="font-size:.82rem;"><?= View::e((string) $r['email']) ?></div>
              <?php if ((int) ($r['is_follow_up'] ?? 0) === 1): ?>
                <div class="muted" style="font-size:.78rem;">reminder</div>
              <?php endif; ?>
            </td>

            <?php if ($showWhere): ?>
              <td>
                <?php $where = ReviewRequests::destination((string) ($r['destination'] ?? 'google')); ?>
                <span class="dest-tag dest-tag--<?= View::e($where) ?>">
                  <?= View::e(ReviewRequests::DESTINATIONS[$where]) ?>
                </span>
              </td>
            <?php endif; ?>

            <td>
              <span class="pill-status st-<?= View::e((string) $r['status']) ?>">
                <?= View::e(str_replace('_', ' ', (string) $r['status'])) ?>
              </span>
              <?php if (!empty($r['failure_reason'])): ?>
                <div class="muted" style="font-size:.8rem;max-width:20rem;"><?= View::e((string) $r['failure_reason']) ?></div>
              <?php endif; ?>
            </td>

            <td><?= $r['sent_at']
                  ? View::e(date('j M, H:i', strtotime((string) $r['sent_at'])))
                  : '<span class="muted">queued</span>' ?></td>

            <td><?= $r['first_clicked_at']
                  ? View::e(date('j M, H:i', strtotime((string) $r['first_clicked_at'])))
                  : '<span class="muted">&mdash;</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
