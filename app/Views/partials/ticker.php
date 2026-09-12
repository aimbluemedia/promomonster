<?php
/**
 * Accent strip used to break the page into chapters, as the reference layout
 * does. The claims are duplicated because a marquee needs two identical halves
 * to loop seamlessly — the second copy is hidden from assistive tech so the
 * same sentence is not announced twice.
 */
$claims = [
    'Never gates reviews',
    'Google &amp; FTC compliant',
    'SMS and email included',
    'Live in a day',
    'Starts free, no card',
    'Replies drafted for you',
];
?>
<div class="ticker" role="presentation">
  <div class="ticker__track">
    <div class="ticker__group">
      <?php foreach ($claims as $claim): ?><span><?= $claim ?></span><?php endforeach; ?>
    </div>
    <div class="ticker__group" aria-hidden="true">
      <?php foreach ($claims as $claim): ?><span><?= $claim ?></span><?php endforeach; ?>
    </div>
  </div>
</div>
