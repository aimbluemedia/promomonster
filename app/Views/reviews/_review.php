<?php use App\Support\HostedReviews; use App\Support\View;
/** @var array $r @var string $business */
$source = (string) $r['source'];
$badge  = HostedReviews::sourceBadge($source);
?>
<article class="rev">
  <div class="rev__head">
    <?= View::render('reviews/_stars', ['rating' => (int) $r['rating']]) ?>
    <strong class="rev__who"><?= View::e((string) $r['author_name']) ?></strong>
    <?php if (!empty($r['author_city'])): ?>
      <span class="rev__city"><?= View::e((string) $r['author_city']) ?></span>
    <?php endif; ?>
    <?php if ($badge !== ''): ?>
      <?php /* The Google mark is a plain letter, not Google's logo: using
               theirs would imply they endorse this page, and they do not. */ ?>
      <span class="rev__badge<?= $source === 'google' ? ' rev__badge--g' : '' ?>"
            title="<?= View::e(HostedReviews::sourceLabel($source)) ?>">
        <?= $source === 'google' ? 'G' : View::e($badge) ?>
      </span>
    <?php endif; ?>
    <span class="rev__when"><?= View::e(date('j M Y', strtotime((string) $r['created_at']))) ?></span>
  </div>

  <p class="rev__body"><?= nl2br(View::e((string) $r['body'])) ?></p>

  <p class="rev__source">
    <?= View::e(HostedReviews::sourceLabel($source)) ?><?php
    if (!empty($r['source_url'])): ?> &middot;
      <a href="<?= View::e((string) $r['source_url']) ?>" target="_blank" rel="noopener nofollow">read it on Google</a>
    <?php endif; ?>
  </p>

  <?php if (!empty($r['reply_body'])): ?>
    <div class="rev__reply">
      <strong><?= View::e($business) ?> replied</strong>
      <p><?= nl2br(View::e((string) $r['reply_body'])) ?></p>
    </div>
  <?php endif; ?>
</article>
