<?php use App\Support\Csrf; use App\Support\Icon; use App\Support\View;
/** @var ?string $error @var ?array $results @var array $old @var bool $available */

/** One business block on the public form. */
function compareFields(string $prefix, string $legend, string $nameValue = '',
                       bool $required = false, string $placeholder = ''): void
{ ?>
  <fieldset class="compare-block">
    <legend><?= View::e($legend) ?></legend>
    <div class="form__row form__row--2">
      <div>
        <label for="<?= $prefix ?>_name">Business name</label>
        <input class="field" id="<?= $prefix ?>_name" name="<?= $prefix ?>_name" type="text"
               maxlength="160" value="<?= View::e($nameValue) ?>"
               placeholder="<?= View::e($placeholder) ?>" <?= $required ? 'required' : '' ?>>
      </div>
      <div class="form__row form__row--2">
        <div>
          <label for="<?= $prefix ?>_rating">Google rating</label>
          <input class="field" id="<?= $prefix ?>_rating" name="<?= $prefix ?>_rating"
                 type="number" step="0.1" min="1" max="5" placeholder="4.2">
        </div>
        <div>
          <label for="<?= $prefix ?>_count">Total reviews</label>
          <input class="field" id="<?= $prefix ?>_count" name="<?= $prefix ?>_count"
                 type="number" min="0" placeholder="47">
        </div>
      </div>
    </div>
    <div style="margin-top:.8rem;">
      <label for="<?= $prefix ?>_reviews">
        Paste a few recent reviews &mdash; <strong>one blank line between each</strong> (optional,
        but it is what makes the comparison useful)
      </label>
      <textarea class="field" id="<?= $prefix ?>_reviews" name="<?= $prefix ?>_reviews" rows="5"
                placeholder="Turned up on time and left the place spotless.&#10;&#10;Quoted fairly, no surprises on the invoice."></textarea>
    </div>
  </fieldset>
<?php }
?>

<section class="section">
  <div class="container center" style="max-width:46rem;">
    <p class="eyebrow">Free comparison</p>
    <h1 class="display">How do you <em>really</em> compare?</h1>
    <p class="lede">Put your Google rating and reviews next to two competitors and
      get a straight answer: where you stand, what their reviewers praise that
      yours do not, and the specific next step. One free comparison, no card.</p>
  </div>
</section>

<?php if ($results !== null): ?>
  <section class="section" style="padding-top:0;" id="result">
    <div class="container" style="max-width:52rem;">
      <div class="card">
        <p class="eyebrow" style="margin-bottom:.75rem;">Your comparison</p>
        <?php require APP_ROOT . '/Views/partials/comparison-report.php'; ?>

        <div class="notice" style="margin-top:1.75rem;">
          <strong>That was your one free comparison.</strong>
          <p>A free account runs them whenever you like, and starts asking your
            customers for reviews properly &mdash; every customer, the same way.</p>
          <div class="btn-row" style="margin-top:1rem;">
            <a class="btn btn--primary" href="/members/signup">Create a free account</a>
            <a class="btn btn--ghost" href="/audit">Get the full audit</a>
          </div>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="section<?= $results !== null ? ' section--wash' : '' ?>" id="start"
         style="<?= $results !== null ? '' : 'padding-top:0;' ?>">
  <div class="container" style="max-width:52rem;">

    <?php if ($error !== null): ?>
      <div class="alert" role="alert" style="margin-bottom:1.5rem;"><?= View::e($error) ?></div>
    <?php endif; ?>

    <?php if (!$available): ?>
      <div class="notice" style="margin-bottom:1.5rem;">
        <strong>The comparison tool is briefly unavailable.</strong>
        <p>Request the <a href="/audit">free review audit</a> instead and a person
          will run one for you.</p>
      </div>
    <?php endif; ?>

    <div class="card">
      <h2 style="font-size:1.15rem;margin-bottom:.3rem;">
        <?= $results !== null ? 'Run another' : 'Start your comparison' ?>
      </h2>
      <p class="muted" style="font-size:.9rem;margin:0 0 1.5rem;">
        Open your Google Business Profile and your competitors&rsquo; in another tab and copy
        across what you see. Ratings and review counts are the important part; review
        text is what lets us read the themes.
      </p>

      <form class="form" method="post" action="/compare">
        <?= Csrf::field() ?>

        <?php compareFields('subject', 'Your business', (string) ($old['business'] ?? ''),
                            true, 'Acme Pools'); ?>
        <?php compareFields('c1', 'Competitor 1', '', true, 'Blue Water Pools'); ?>
        <?php compareFields('c2', 'Competitor 2 (optional)', '', false, 'Sunbelt Pool Care'); ?>

        <div class="form__row form__row--2">
          <div>
            <label for="email">Where should we send it?</label>
            <input class="field" id="email" name="email" type="email" required
                   value="<?= View::e((string) ($old['email'] ?? '')) ?>"
                   placeholder="you@yourbusiness.com" autocomplete="email">
          </div>
          <div>
            <label for="vertical">What do you do?</label>
            <input class="field" id="vertical" name="vertical" type="text" maxlength="60"
                   placeholder="Pool service, HVAC, dental…">
          </div>
        </div>

        <div class="honeypot" aria-hidden="true">
          <label for="website_url">Leave this field empty</label>
          <input id="website_url" name="website_url" type="text" tabindex="-1" autocomplete="off">
        </div>

        <button class="btn btn--primary btn--block" type="submit" <?= $available ? '' : 'disabled' ?>>
          Compare me with my competitors
        </button>
        <p class="form__note center">
          Takes about half a minute. One free comparison per business &mdash; no card, and
          we will not put a made-up revenue figure on it.
        </p>
      </form>
    </div>

    <div class="grid grid--3" style="margin-top:2rem;">
      <?php foreach ([
        ['shield', 'Nothing is gated', 'We never suggest screening customers before you ask. Google prohibits it.'],
        ['search', 'Numbers you can check', 'Ratings and counts are exact. Themes come from the reviews you paste, and we say so.'],
        ['chart',  'No invented revenue', 'We will not guess what reviews are worth to you. Nobody honestly can.'],
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
