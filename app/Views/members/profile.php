<?php
use App\Support\View;
/**
 * The member's details, which live on Toutify.
 *
 * Read-only here on purpose. Two editable copies of one profile is two places
 * to change an email address and one of them silently wrong; Toutify owns it,
 * so Toutify is where it is changed.
 *
 * @var array $user @var array $websites @var string $editUrl @var string $sitesUrl
 */
$name = trim((string) ($user['toutify_name'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));
?>
<div class="admin-title">
  <h1>Your details</h1>
  <span class="muted" style="font-size:.9rem;">Held by Toutify</span>
</div>

<div class="card" style="margin-bottom:1.5rem;">
  <p class="muted" style="margin:0 0 1.1rem;font-size:.9rem;">
    These come from your Toutify account, which is also what you sign in with.
    Change them there and they update here within a minute.
  </p>

  <ul class="checklist">
    <li><strong>Name</strong> <?= View::e($name !== '' ? $name : '—') ?></li>
    <li><strong>Email</strong> <?= View::e((string) ($user['email'] ?? '—')) ?></li>
    <li><strong>Business</strong> <?= View::e((string) ($user['business_name'] ?? '—')) ?></li>
    <li><strong>Phone</strong> <?= View::e((string) ($user['phone'] ?? '—')) ?></li>
    <li><strong>Website</strong>
      <?php if (!empty($user['website'])): ?>
        <a href="<?= View::e((string) $user['website']) ?>" rel="noopener" target="_blank">
          <?= View::e((string) $user['website']) ?></a>
      <?php else: ?>—<?php endif; ?>
    </li>
  </ul>

  <div style="margin-top:1.25rem;">
    <a class="btn btn--primary" href="<?= View::e($editUrl) ?>" rel="noopener">Edit on Toutify</a>
  </div>
</div>

<div class="card">
  <h2 style="font-size:1.05rem;margin:0 0 .4rem;">Your websites</h2>
  <p class="muted" style="margin:0 0 1.1rem;font-size:.9rem;">
    The sites on your Toutify account that PromoMonster is switched on for.
  </p>

  <?php if ($websites === []): ?>
    <p class="empty">No websites yet. Add one on Toutify and it appears here.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Website</th><th>Business</th><th>Plan</th></tr></thead>
      <tbody>
        <?php foreach ($websites as $site): ?>
          <tr>
            <td><a href="<?= View::e((string) $site['url']) ?>" rel="noopener" target="_blank">
              <?= View::e((string) $site['url']) ?></a></td>
            <td><?= View::e((string) ($site['business_name'] ?? '—')) ?></td>
            <td><?= View::e((string) ($site['tier'] ?? '—')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div style="margin-top:1.25rem;">
    <a class="btn btn--ghost" href="<?= View::e($sitesUrl) ?>" rel="noopener">
      Manage my websites on Toutify
    </a>
  </div>
</div>
