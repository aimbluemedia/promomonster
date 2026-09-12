<?php use App\Support\Csrf; use App\Support\Plans; use App\Support\View;
/** @var array $counts @var array $recent @var array $upgrades */ ?>
<div class="admin-title"><h1>Overview</h1></div>

<div class="stat-grid">
  <?php foreach ([
    ['audits_new',        'New audit requests'],
    ['audits_week',       'Requests this week'],
    ['audits_converted',  'Converted to customers'],
    ['agencies_new',      'New agency applications'],
    ['upgrades_pending',  'Plan requests waiting'],
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

<?php if ($upgrades !== []): ?>
  <div class="admin-title" style="margin-top:2.5rem;">
    <h2 style="font-size:1.2rem;">Plan requests</h2>
    <span class="muted" style="font-size:.9rem;"><?= count($upgrades) ?> waiting</span>
  </div>
  <p class="muted" style="font-size:.88rem;margin:0 0 1rem;">
    There is no payment processor yet, so these are requests, not purchases.
    Arrange payment first, then apply the plan here.
  </p>
  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Business</th><th>Owner</th><th>Now</th><th>Wants</th><th>Asked</th><th>Action</th>
      </tr></thead>
      <tbody>
        <?php foreach ($upgrades as $row): ?>
          <tr>
            <td><strong><?= View::e($row['name']) ?></strong></td>
            <td class="mono"><?= View::e($row['owner_email'] ?? '—') ?></td>
            <td><?= View::e(Plans::name((string) $row['plan'])) ?></td>
            <td><strong><?= View::e(Plans::name((string) $row['requested_plan'])) ?></strong>
              &middot; $<?= (int) Plans::price((string) $row['requested_plan']) ?>/mo</td>
            <td class="mono"><?= View::e((string) $row['requested_plan_at']) ?></td>
            <td>
              <form class="row-form" method="post" action="/superadmin/accounts/plan">
                <?= Csrf::field() ?>
                <input type="hidden" name="account_id" value="<?= (int) $row['id'] ?>">
                <button name="decision" value="apply" type="submit">Apply</button>
                <button name="decision" value="dismiss" type="submit"
                        style="background:var(--surface);color:var(--body);border-color:var(--line);">Dismiss</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

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
