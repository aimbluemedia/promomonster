<?php use App\Support\HostedReviews; use App\Support\View;
/** @var array $account @var array $summary @var array $reviews @var string $pageUrl */

/* Every style is inline, on purpose.
 *
 * This markup is injected into somebody else's website, whose CSS we have never
 * seen and cannot change. A class name we pick will collide with one of theirs
 * sooner or later, and the failure mode is a business's reviews rendering as
 * unstyled grey text on their own homepage. Inline wins every cascade except
 * !important, needs no second request, and cannot be broken by a theme update.
 *
 * Deliberately no web font and no colour scheme: it inherits the host page's
 * font so it looks like part of the site rather than a bolted-on box.
 */
$box   = 'border:1px solid #dfe3e8;border-radius:10px;padding:14px 16px;margin:0 0 10px;background:#fff;';
$muted = 'color:#5b646e;font-size:13px;';
$stars = static function (int $n): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span style="color:' . ($i <= $n ? '#f0a202' : '#d8d8d8') . ';">&#9733;</span>';
    }
    return '<span style="letter-spacing:1px;">' . $out . '</span>';
};
?>
<div style="font:inherit;line-height:1.5;color:#1c2024;max-width:640px;">
  <?php if ((int) $summary['count'] === 0): ?>
    <div style="<?= $box ?>">
      <?= $stars(0) ?>
      <span style="<?= $muted ?>">No reviews yet.</span>
    </div>
  <?php else: ?>
    <div style="<?= $box ?>display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
      <?= $stars((int) round((float) $summary['average'])) ?>
      <strong style="font-size:18px;"><?= View::e(number_format((float) $summary['average'], 1)) ?></strong>
      <span style="<?= $muted ?>">
        out of 5 from <?= (int) $summary['count'] ?>
        <?= $summary['count'] === 1 ? 'review' : 'reviews' ?>
      </span>
    </div>

    <?php foreach ($reviews as $r):
      $source = (string) $r['source'];
      $badge  = HostedReviews::sourceBadge($source);
    ?>
      <div style="<?= $box ?>">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
          <?= $stars((int) $r['rating']) ?>
          <strong><?= View::e((string) $r['author_name']) ?></strong>
          <?php if (!empty($r['author_city'])): ?>
            <span style="<?= $muted ?>"><?= View::e((string) $r['author_city']) ?></span>
          <?php endif; ?>
          <?php if ($badge !== ''): ?>
            <?php /* A plain letter, not Google's logo. Reproducing their mark
                     on a third-party page implies an endorsement nobody gave. */ ?>
            <span title="<?= View::e(HostedReviews::sourceLabel($source)) ?>"
                  style="display:inline-block;min-width:18px;height:18px;line-height:18px;text-align:center;
                         border-radius:9px;font-size:11px;font-weight:700;padding:0 6px;
                         background:<?= $source === 'google' ? '#eef2f7;color:#3c4858' : '#e3f3ec;color:#0b6b5b' ?>;">
              <?= $source === 'google' ? 'G' : View::e($badge) ?>
            </span>
          <?php endif; ?>
          <span style="<?= $muted ?>margin-left:auto;">
            <?= View::e(date('j M Y', strtotime((string) $r['created_at']))) ?>
          </span>
        </div>
        <div><?= nl2br(View::e((string) $r['body'])) ?></div>
        <?php if (!empty($r['reply_body'])): ?>
          <div style="margin-top:8px;padding-left:12px;border-left:3px solid #dfe3e8;">
            <strong style="font-size:13px;"><?= View::e((string) $account['name']) ?> replied</strong>
            <div style="<?= $muted ?>"><?= nl2br(View::e((string) $r['reply_body'])) ?></div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php /* The link back is not decoration: a reader has to be able to check
           that this is the whole set and not a flattering selection. */ ?>
  <div style="<?= $muted ?>">
    <a href="<?= View::e($pageUrl) ?>" target="_blank" rel="noopener"
       style="color:#0475a3;">Every review, on PromoMonster</a>
  </div>
</div>
