<?php
use App\Support\View;
/**
 * The score readout. Shared by /score and the superadmin audit screen.
 *
 * The dial is a ring whose sweep is the score, with the number in the middle —
 * the number is the point, so it is the largest thing and the ring is support.
 * Colour never carries the verdict on its own: the band is written out.
 *
 * @var array $result
 */
$score = (int) ($result['score'] ?? 0);
$band  = (string) ($result['band'] ?? '');
$circ  = 2 * M_PI * 54;                 // r=54 in the 120-box below
$dash  = $circ * ($score / 100);
$tone  = $score >= 75 ? 'good' : ($score >= 45 ? 'mid' : 'low');
?>
<div class="score-readout">
  <div class="dial dial--<?= $tone ?>">
    <svg viewBox="0 0 120 120" role="img"
         aria-label="Review score <?= $score ?> out of 100">
      <circle class="dial__track" cx="60" cy="60" r="54" fill="none" stroke-width="11"></circle>
      <circle class="dial__value" cx="60" cy="60" r="54" fill="none" stroke-width="11"
              stroke-linecap="round"
              stroke-dasharray="<?= round($dash, 2) ?> <?= round($circ - $dash, 2) ?>"
              transform="rotate(-90 60 60)"></circle>
    </svg>
    <div class="dial__centre">
      <span class="dial__num"><?= $score ?></span>
      <span class="dial__max">/ 100</span>
    </div>
  </div>

  <div class="score-readout__body">
    <p class="score-readout__band"><?= View::e($band) ?></p>
    <p class="score-readout__url"><?= View::e((string) ($result['url'] ?? '')) ?></p>
    <?php if (!empty($result['summary'])): ?>
      <p class="score-readout__summary"><?= View::e((string) $result['summary']) ?></p>
    <?php endif; ?>
    <?php if (!empty($result['platform'])): ?>
      <p class="form__note">Review platform detected: <strong><?= View::e((string) $result['platform']) ?></strong></p>
    <?php endif; ?>
  </div>
</div>

<ul class="score-checks">
  <?php foreach (($result['checks'] ?? []) as $check): ?>
    <li class="score-check<?= $check['passed'] ? ' is-on' : '' ?>">
      <span class="score-check__mark" aria-hidden="true"><?= $check['passed'] ? '✓' : '○' ?></span>
      <span>
        <strong><?= View::e((string) $check['label']) ?></strong>
        <span class="score-check__pts"><?= $check['passed'] ? '+' : '' ?><?= (int) $check['weight'] ?></span>
        <span class="score-check__detail"><?= View::e((string) $check['detail']) ?></span>
      </span>
    </li>
  <?php endforeach; ?>
</ul>

<p class="form__note" style="margin-top:1.25rem;">
  <strong>What this could not see:</strong> your actual Google rating and review
  count &mdash; that needs your Google listing, not your website &mdash; and anything
  behind a login or drawn in by JavaScript after the page loads. This is a score for
  how your site is set up to collect and show reviews.
</p>
