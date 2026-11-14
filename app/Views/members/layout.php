<?php
/** @var string $content @var ?string $title @var array $account */
use App\Support\View;
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/members', PHP_URL_PATH) ?: '/members';
$flash = $_SESSION['members_flash'] ?? null;
unset($_SESSION['members_flash']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= View::e($title ?? 'PromoMonster') ?></title>
<link rel="stylesheet" href="<?= View::e(View::asset('/assets/css/app.css')) ?>">
</head>
<body class="members">
<a class="skip-link" href="#main">Skip to the page</a>

<?php /* Sidebar and page, side by side.

         What used to be here: a header, a row of eight links that scrolled
         sideways, and the three plan cards on every single page. The plan
         cards were put there so a member meeting a locked screen could see
         what the next tier costs without hunting -- that reason still holds,
         and the sidebar still answers it, but with one line of plan and
         allowance instead of three boxes above every page. The cards
         themselves now live where somebody goes to compare them. */ ?>
<div class="shell">
  <?= View::render('members/_sidebar', [
      'account' => $account ?? [],
      'path'    => $path,
  ]) ?>

  <main class="shell__main" id="main">
    <div class="shell__inner">
      <?php if ($flash !== null): ?>
        <div class="flash" role="status"><?= View::e($flash) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </div>
  </main>
</div>
</body>
</html>
