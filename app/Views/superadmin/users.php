<?php
use App\Support\Csrf;
use App\Support\Plans;
use App\Support\View;
/**
 * @var array $rows @var array $counts @var string $filter
 * @var int $page @var int $pages @var int $total @var int $perPage
 */
$first = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$last  = min($page * $perPage, $total);

/** Keeps the current filter on a page link, so paging does not silently reset it. */
$pageUrl = static fn (int $n): string =>
    '/superadmin/users?plan=' . rawurlencode($filter) . '&page=' . $n;

$tabs = ['all' => 'All'];
foreach (array_merge(Plans::SELECTABLE, [Plans::PARTNER]) as $plan) {
    $tabs[$plan] = Plans::name($plan);
}
$tabs['wants_upgrade'] = 'Wants an upgrade';
?>
<?php /* Listed by account, not by user. A plan belongs to an account and an
         account can have several logins, so a per-user list would show one
         business three times and count it three times. */ ?>
<div class="admin-title">
  <h1>Users</h1>
  <span class="muted" style="font-size:.9rem;">
    <?= $total === 0 ? 'none yet' : "showing {$first}&ndash;{$last} of {$total}" ?>
  </span>
</div>

<div class="filters">
  <?php foreach ($tabs as $key => $label): ?>
    <a href="/superadmin/users?plan=<?= rawurlencode((string) $key) ?>"
       <?= $filter === $key ? ' aria-current="page"' : '' ?>>
      <?= View::e($label) ?>
      <span class="filters__n"><?= (int) ($counts[$key] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php if (($counts['wants_upgrade'] ?? 0) > 0 && $filter !== 'wants_upgrade'): ?>
  <div class="notice" style="margin-bottom:1.5rem;">
    <strong><?= (int) $counts['wants_upgrade'] ?>
      <?= $counts['wants_upgrade'] === 1 ? 'account is' : 'accounts are' ?> waiting on a paid plan.</strong>
    <p>Nobody can be charged automatically yet, so these sit here until you set
      them up. <a href="/superadmin/users?plan=wants_upgrade">Show them</a>.</p>
  </div>
<?php endif; ?>

<div class="table-wrap">
  <?php if ($rows === []): ?>
    <p class="empty">
      <?= $filter === 'all'
        ? 'Nobody has signed up yet.'
        : 'Nobody on this one.' ?>
    </p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr>
          <th>Business</th>
          <th>Owner</th>
          <th>Plan</th>
          <th>Set up</th>
          <th>Signed up</th>
          <th>Change plan</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          $plan      = (string) $r['plan'];
          $requested = $r['requested_plan'] ?? null;
          $name      = trim(((string) ($r['first_name'] ?? '')) . ' ' . ((string) ($r['last_name'] ?? '')));
        ?>
          <tr>
            <td>
              <strong><?= View::e((string) $r['name']) ?></strong>
              <?php if ((string) $r['status'] !== 'active'): ?>
                <span class="pill-status st-declined"><?= View::e((string) $r['status']) ?></span>
              <?php endif; ?>
              <div class="muted" style="font-size:.8rem;">
                #<?= (int) $r['id'] ?>
                <?php if ((int) $r['locations'] > 1): ?>
                  &middot; <?= (int) $r['locations'] ?> locations
                <?php endif; ?>
              </div>
            </td>

            <td>
              <?php if ($r['email'] === null): ?>
                <span class="muted">no owner login</span>
              <?php else: ?>
                <?= View::e($name !== '' ? $name : '—') ?>
                <div class="muted" style="font-size:.8rem;">
                  <a href="mailto:<?= View::e((string) $r['email']) ?>"><?= View::e((string) $r['email']) ?></a>
                </div>
                <div class="muted" style="font-size:.78rem;">
                  <?= $r['last_login_at']
                    ? 'last in ' . View::e(date('j M Y', strtotime((string) $r['last_login_at'])))
                    : 'never signed in' ?>
                </div>
              <?php endif; ?>
            </td>

            <td>
              <span class="pill-status <?= $plan === Plans::FREE ? 'st-delivered' : 'st-converted' ?>">
                <?= View::e(Plans::name($plan)) ?>
              </span>
              <?php if ($requested !== null): ?>
                <div class="muted" style="font-size:.78rem;margin-top:.25rem;">
                  wants <?= View::e(Plans::name((string) $requested)) ?>
                  <?php if (!empty($r['requested_plan_at'])): ?>
                    &middot; <?= View::e(date('j M', strtotime((string) $r['requested_plan_at']))) ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>

            <?php /* Whether they have actually started. A signup with no review
                     link has never been able to send anything, which is the
                     difference between a dead account and a quiet one. */ ?>
            <td>
              <?php if ((int) $r['linked'] === 0): ?>
                <span class="muted">no review link</span>
              <?php else: ?>
                <?= (int) $r['asks'] ?> <?= (int) $r['asks'] === 1 ? 'ask' : 'asks' ?>
                <?php if (!empty($r['last_ask'])): ?>
                  <div class="muted" style="font-size:.78rem;">
                    last <?= View::e(date('j M', strtotime((string) $r['last_ask']))) ?>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </td>

            <td>
              <?= View::e(date('j M Y', strtotime((string) $r['created_at']))) ?>
              <div class="muted" style="font-size:.78rem;"><?= View::e((string) ($r['signup_ip'] ?? '')) ?></div>
            </td>

            <td>
              <?php /* A direct set, not an answer to a request: somebody paid by
                       invoice, or a plan needs winding back. Both are logged. */ ?>
              <form class="row-form" method="post" action="/superadmin/users/plan"
                    onsubmit="return confirm('Move <?= View::e(addslashes((string) $r['name'])) ?> to the selected plan?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="account_id" value="<?= (int) $r['id'] ?>">
                <?php /* aria-label rather than a visually-hidden <label>: one
                         element fewer, and nothing absolutely positioned inside
                         a horizontally scrolling table. */ ?>
                <select name="plan" aria-label="Plan for <?= View::e((string) $r['name']) ?>">
                  <?php foreach (array_merge(Plans::SELECTABLE, [Plans::PARTNER]) as $option): ?>
                    <option value="<?= View::e($option) ?>"<?= $option === $plan ? ' selected' : '' ?>>
                      <?= View::e(Plans::name($option)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit">Set</button>
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
      <a href="<?= View::e($pageUrl($page - 1)) ?>">&larr; Newer</a>
    <?php else: ?>
      <span class="is-off">&larr; Newer</span>
    <?php endif; ?>

    <span class="pager__at">Page <?= $page ?> of <?= $pages ?></span>

    <?php if ($page < $pages): ?>
      <a href="<?= View::e($pageUrl($page + 1)) ?>">Older &rarr;</a>
    <?php else: ?>
      <span class="is-off">Older &rarr;</span>
    <?php endif; ?>
  </nav>
<?php endif; ?>
