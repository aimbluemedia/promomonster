<?php use App\Support\View; /** @var array $account @var array $user @var array $team */ ?>
<div class="admin-title"><h1>Settings</h1></div>

<div class="grid grid--2">
  <div class="card">
    <h2 style="font-size:1.05rem;margin-bottom:.9rem;">Account</h2>
    <ul class="checklist">
      <li><strong>Business</strong> <?= View::e($account['name'] ?? '—') ?></li>
      <li><strong>Plan</strong> <?= View::e(ucfirst((string) ($account['plan'] ?? 'trial'))) ?></li>
      <li><strong>Status</strong> <?= View::e(ucfirst((string) ($account['status'] ?? '—'))) ?></li>
      <li><strong>Signed in as</strong> <?= View::e($user['email'] ?? '') ?></li>
    </ul>
    <p class="muted" style="margin-top:1.1rem;font-size:.88rem;">
      Billing and plan changes open when self-serve launches. Until then, email
      us and a person will sort it.</p>
  </div>

  <div class="card">
    <h2 style="font-size:1.05rem;margin-bottom:.9rem;">Your team</h2>
    <?php if ($team === []): ?>
      <p class="muted" style="font-size:.9rem;margin:0;">Just you for now.</p>
    <?php else: ?>
      <ul class="checklist">
        <?php foreach ($team as $m): ?>
          <li><strong><?= View::e(trim($m['first_name'] . ' ' . $m['last_name'])) ?></strong>
            <?= View::e($m['email']) ?> · <?= View::e($m['role']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p class="muted" style="margin-top:1.1rem;font-size:.88rem;">
      Adding teammates ships with the sending release — the people who ask
      customers need their own logins.</p>
  </div>
</div>
