<?php use App\Support\View; /** @var ?array $playbook */ ?>
<div class="admin-title"><h1>Your playbook</h1></div>
<p class="muted" style="font-size:.94rem;max-width:44rem;margin:0 0 1.5rem;">
  The moment to ask, who should ask, and what they should say. Getting the
  verbal ask right lifts response more than any software setting.</p>

<?php if ($playbook === null): ?>
  <div class="table-wrap"><p class="empty">
    We build your playbook during setup, once we know what kind of business you run.
  </p></div>
<?php else: ?>
  <div class="card">
    <h2 style="font-size:1.15rem;margin-bottom:1rem;"><?= View::e($playbook['display_name']) ?></h2>
    <ul class="checklist">
      <li><strong>Ask at</strong> <?= View::e($playbook['trigger_moment']) ?></li>
      <li><strong>Who asks</strong> <?= View::e($playbook['who_asks']) ?></li>
      <li><strong>Channel</strong> <?= View::e(strtoupper((string) $playbook['primary_channel'])) ?></li>
      <?php if ($playbook['follow_up_days']): ?>
        <li><strong>Reminder</strong> once, <?= (int) $playbook['follow_up_days'] ?> days later</li>
      <?php endif; ?>
      <?php if ($playbook['offline_method']): ?>
        <li><strong>Offline</strong> <?= View::e($playbook['offline_method']) ?></li>
      <?php endif; ?>
    </ul>
    <p style="margin:1.4rem 0 0;padding-top:1.1rem;border-top:1px solid var(--line);font-size:.94rem;color:var(--body);">
      <strong style="color:var(--ink);">They say:</strong>
      &ldquo;<?= View::e($playbook['verbal_ask']) ?>&rdquo;</p>
    <?php if ($playbook['compliance_note']): ?>
      <p class="muted" style="margin-top:1rem;font-size:.88rem;"><?= View::e($playbook['compliance_note']) ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>
