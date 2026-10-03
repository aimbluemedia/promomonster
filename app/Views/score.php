<?php use App\Support\Icon; use App\Support\View;
/** @var ?string $error @var ?array $result @var array $old */ ?>

<?php if ($result !== null): ?>
  <section class="section" id="result">
    <div class="container" style="max-width:50rem;">
      <div class="center" style="margin-bottom:2rem;">
        <p class="eyebrow">Your Free Review Score</p>
        <h1 class="display">Here is where <em>you stand</em></h1>
      </div>
      <div class="card">
        <?php require APP_ROOT . '/Views/partials/score-result.php'; ?>
      </div>

      <div class="notice" style="margin-top:1.5rem;">
        <strong>Fixing these is what we do.</strong>
        <p>A free account gets you the Google review link, the QR code and the
          request templates &mdash; every customer asked the same way, which is the
          only compliant way to do it.</p>
        <div class="btn-row" style="margin-top:1rem;">
          <a class="btn btn--primary" href="/members/signup">Create a free account</a>
          <a class="btn btn--ghost" href="/compare">Compare me with competitors</a>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="section<?= $result !== null ? ' section--wash' : '' ?>" id="start">
  <div class="container" style="max-width:48rem;">
    <?php if ($result === null): ?>
      <div class="center" style="margin-bottom:2rem;">
        <p class="eyebrow">Free Review Score</p>
        <h1 class="display">How well is your site set up to <em>win reviews?</em></h1>
        <p class="lede">Put in your address and we will read your page and score it
          out of 100 against the seven things that decide whether customers ever
          leave you a review.</p>
      </div>
    <?php else: ?>
      <h2 class="center" style="margin-bottom:1.5rem;">Score another site</h2>
    <?php endif; ?>

    <?php if ($error !== null): ?>
      <div class="alert" role="alert" style="margin-bottom:1.5rem;"><?= View::e($error) ?></div>
    <?php endif; ?>

    <div class="card score-card">
      <?php require APP_ROOT . '/Views/partials/score-form.php'; ?>
    </div>

    <div class="grid grid--3" style="margin-top:2rem;">
      <?php foreach ([
        ['clock',  'About ten seconds', 'We read the page you give us and score it on the spot.'],
        ['search', 'Every point traceable', 'Open your own source and you will find exactly what we found.'],
        ['shield', 'Nothing gated, ever', 'We never suggest screening customers. Google prohibits it.'],
      ] as [$icon, $title, $body]): ?>
        <div class="card feature">
          <?= Icon::chip($icon) ?>
          <h3><?= View::e($title) ?></h3>
          <p><?= View::e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
