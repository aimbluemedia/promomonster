<?php
use App\Support\View;
/**
 * The PromoMonster lockup: mark, wordmark, and "by Toutify" under it.
 *
 * A partial because this markup was copied into ten files -- header, footer,
 * both layouts, four sign-in screens, the public review page. Adding a byline
 * to ten copies is how nine of them end up with it.
 *
 * The byline is a second line under the wordmark rather than a third word
 * beside it. "PromoMonster by Toutify" on one line reads as the product's
 * name; stacked and smaller, it reads as who makes it, which is what it is.
 *
 * @var ?string $href   where it links, or null for no link (it is already the
 *                      page you are on, so a link back to it is a dead click)
 * @var ?int    $size   mark size in px
 * @var ?bool   $byline whether to show "by Toutify"
 */
$href   = $href   ?? '/';
$size   = $size   ?? 26;
$byline = $byline ?? true;
$tag    = $href === null ? 'span' : 'a';
?>
<<?= $tag ?> class="logo<?= $byline ? ' logo--stacked' : '' ?>"<?= $href === null ? '' : ' href="' . View::e($href) . '"' ?>>
  <?php /* aria-hidden with the name carried by the text beside it, so a screen
           reader says "PromoMonster by Toutify" once rather than describing a
           star it cannot use. */ ?>
  <svg width="<?= (int) $size ?>" height="<?= (int) $size ?>" viewBox="0 0 32 32" fill="none" aria-hidden="true">
    <rect width="32" height="32" rx="9" fill="var(--brand)"/>
    <path d="M16 7.5l2.3 4.7 5.2.75-3.75 3.65.9 5.15L16 19.3l-4.65 2.45.9-5.15L8.5 12.95l5.2-.75z" fill="#fff"/>
  </svg>
  <span class="logo__text">
    <span class="logo__word">Promo<span class="logo__accent">Monster</span></span>
    <?php if ($byline): ?>
      <span class="logo__by">by Toutify</span>
    <?php endif; ?>
  </span>
</<?= $tag ?>>
