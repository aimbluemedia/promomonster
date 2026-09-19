<?php
/** @var string $content @var ?string $title @var ?string $description @var ?string $current */
use App\Support\View;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($title ?? 'PromoMonster') ?></title>
<meta name="description" content="<?= View::e($description ?? '') ?>">
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%230b6b5b'/><circle cx='16' cy='15' r='7.5' fill='%23faf9f6'/><circle cx='16' cy='15' r='3.4' fill='%23101418'/></svg>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<?php require APP_ROOT . '/Views/partials/header.php'; ?>
<main id="main"><?= $content ?></main>
<?php require APP_ROOT . '/Views/partials/footer.php'; ?>
<?php /* Deferred and entirely optional: the page is complete without it. */ ?>
<script src="/assets/js/app.js" defer></script>
</body>
</html>
