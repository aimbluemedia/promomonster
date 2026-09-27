<?php use App\Support\Csrf; use App\Support\HostedReviews; use App\Support\View;
/** @var array $account @var array $summary @var array $reviews
 *  @var ?array $contact @var ?string $error @var bool $thanks @var ?string $title */
$name = trim((string) $account['name']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($title ?? ($name . ' reviews')) ?></title>
<?php /* A real description, because this page is meant to be found and shared. */ ?>
<meta name="description" content="<?= View::e('What customers say about ' . $name . '.') ?>">
<link rel="stylesheet" href="<?= View::e(View::asset('/assets/css/app.css')) ?>">
</head>
<body>
<section class="section">
  <div class="container" style="max-width:44rem;">

    <a class="logo" href="/" style="margin-bottom:2rem;">
      <svg width="26" height="26" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect width="32" height="32" rx="9" fill="var(--brand)"/>
        <path d="M16 7.5l2.3 4.7 5.2.75-3.75 3.65.9 5.15L16 19.3l-4.65 2.45.9-5.15L8.5 12.95l5.2-.75z" fill="#fff"/>
      </svg>
      <span class="logo__word">Promo<span class="logo__accent">Monster</span></span>
    </a>

    <h1 style="margin-bottom:.35rem;"><?= View::e($name) ?></h1>

    <?php if ($summary['count'] > 0): ?>
      <p class="rev-summary">
        <?= View::render('reviews/_stars', ['rating' => (int) round((float) $summary['average'])]) ?>
        <strong><?= View::e(number_format((float) $summary['average'], 1)) ?></strong>
        out of 5 &middot; <?= (int) $summary['count'] ?>
        <?= $summary['count'] === 1 ? 'review' : 'reviews' ?>
      </p>
    <?php else: ?>
      <p class="muted" style="margin:0 0 2rem;">No reviews yet. Yours would be the first.</p>
    <?php endif; ?>

    <?php if ($thanks): ?>
      <div class="flash" role="status" style="margin-bottom:1.5rem;">
        Thank you &mdash; your review is published below.
      </div>
    <?php endif; ?>

    <?php /* ---- Leave one ------------------------------------------------ */ ?>
    <div class="card" style="margin-bottom:2.5rem;">
      <h2 style="font-size:1.05rem;margin:0 0 .3rem;">Leave a review</h2>
      <p class="muted" style="margin:0 0 1.1rem;font-size:.9rem;">
        <?php if ($contact !== null): ?>
          Writing as <strong><?= View::e(trim((string) $contact['first_name'] . ' ' . (string) $contact['last_name'])) ?></strong>.
          Yours will be marked as a verified customer.
        <?php else: ?>
          It goes straight onto this page, good or bad. <?= View::e($name) ?>
          can reply to it but cannot remove it.
        <?php endif; ?>
      </p>

      <?php if ($error !== null): ?>
        <div class="alert" role="alert" style="margin-bottom:1rem;"><?= View::e($error) ?></div>
      <?php endif; ?>

      <form class="form" method="post" action="/reviews/<?= View::e((string) $account['public_slug']) ?>">
        <?= Csrf::field() ?>
        <?php if ($contact !== null): ?>
          <input type="hidden" name="c" value="<?= View::e((string) ($_GET['c'] ?? '')) ?>">
        <?php endif; ?>

        <?php /* Radios, not a select: five options, and a star rating people
                 can see is the one control on this form that must be obvious. */ ?>
        <fieldset class="rating-pick">
          <legend>How did they do?</legend>
          <?php foreach ([5 => 'Excellent', 4 => 'Good', 3 => 'Okay', 2 => 'Poor', 1 => 'Bad'] as $n => $word): ?>
            <label>
              <input type="radio" name="rating" value="<?= $n ?>" required>
              <?= View::render('reviews/_stars', ['rating' => $n]) ?>
              <span class="rating-pick__word"><?= View::e($word) ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <div>
          <label for="author_name">Your name</label>
          <input class="field" id="author_name" name="author_name" type="text" required maxlength="120"
                 value="<?= View::e($contact === null ? '' : trim((string) $contact['first_name'] . ' ' . (string) $contact['last_name'])) ?>"
                 autocomplete="name">
        </div>

        <div>
          <label for="body">What happened?</label>
          <textarea class="field" id="body" name="body" rows="5" required maxlength="4000"
                    placeholder="What did they do, and how did it go?"></textarea>
        </div>

        <div class="honeypot" aria-hidden="true">
          <label for="website_url">Leave this empty</label>
          <input id="website_url" name="website_url" type="text" tabindex="-1" autocomplete="off">
        </div>

        <button class="btn btn--primary btn--xl" type="submit" style="justify-self:start;">Post my review</button>
      </form>
    </div>

    <?php /* ---- What people said ------------------------------------------ */ ?>
    <?php if ($reviews !== []): ?>
      <h2 style="font-size:1.05rem;margin:0 0 1rem;">What customers said</h2>
      <?php foreach ($reviews as $r): ?>
        <?= View::render('reviews/_review', ['r' => $r, 'business' => $name]) ?>
      <?php endforeach; ?>

      <p class="muted" style="font-size:.82rem;margin-top:2rem;">
        Every review left here is shown, in the order it arrived. <?= View::e($name) ?>
        can reply to a review but cannot hide or delete one.
      </p>
    <?php endif; ?>
  </div>
</section>
</body>
</html>
