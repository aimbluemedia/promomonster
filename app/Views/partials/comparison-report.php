<?php
use App\Support\View;
/**
 * Renders one comparison. Shared by the superadmin audit screen and the public
 * /compare page so the report a visitor sees is literally the same code the
 * operator sees — no second implementation to drift.
 *
 * @var array $results
 */
?>
<p style="font-size:1.15rem;font-weight:700;color:var(--ink);margin:0 0 1rem;">
  <?= View::e((string) ($results['headline'] ?? '')) ?>
</p>

<dl class="calc__rows" style="border-top:0;padding-top:0;margin-top:0;">
  <div class="calc__row">
    <dt>Standing</dt>
    <dd><?= View::e(ucfirst(str_replace('_', ' ', (string) ($results['standing'] ?? '—')))) ?></dd>
  </div>
  <div class="calc__row">
    <dt>Rating and volume</dt>
    <dd style="font-weight:400;text-align:left;max-width:32rem;">
      <?= View::e((string) ($results['rating_gap'] ?? '')) ?></dd>
  </div>
  <?php if (($results['five_star_reviews_needed'] ?? null) !== null): ?>
    <div class="calc__row">
      <dt>Five-star reviews to match the leader</dt>
      <dd><?= (int) $results['five_star_reviews_needed'] ?></dd>
    </div>
  <?php endif; ?>
</dl>

<?php foreach ([
  'their_strengths'      => 'What your reviewers praise',
  'competitor_strengths' => 'What competitors are praised for and you are not',
  'weaknesses'           => 'Complaints and gaps',
] as $key => $heading): ?>
  <?php if (!empty($results[$key])): ?>
    <h3 style="font-size:.95rem;margin:1.5rem 0 .6rem;"><?= View::e($heading) ?></h3>
    <ul class="checklist">
      <?php foreach ($results[$key] as $item): ?>
        <li><?= View::e((string) $item) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endforeach; ?>

<?php if (!empty($results['actions'])): ?>
  <h3 style="font-size:.95rem;margin:1.5rem 0 .6rem;">What to do next</h3>
  <ol style="margin:0;padding-left:1.2rem;display:grid;gap:.7rem;">
    <?php foreach ($results['actions'] as $action): ?>
      <li>
        <strong><?= View::e((string) ($action['action'] ?? '')) ?></strong>
        <span class="muted" style="display:block;font-size:.88rem;">
          <?= View::e((string) ($action['why'] ?? '')) ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>

<?php if (!empty($results['data_limits'])): ?>
  <p class="form__note" style="margin-top:1.5rem;">
    <strong>What this could not see:</strong> <?= View::e((string) $results['data_limits']) ?>
  </p>
<?php endif; ?>
