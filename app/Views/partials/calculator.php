<?php use App\Support\View; ?>
<?php
/**
 * Five-star review calculator.
 *
 * The arithmetic is exact and worth stating, because it is the whole point:
 * to move an average of `a` over `n` reviews to a target `t` using only
 * five-star reviews, you need
 *
 *     k = n(t - a) / (5 - t)
 *
 * That is deterministic. What is NOT computable is what the extra reviews are
 * worth in revenue, so this calculator does not pretend to know — see the note
 * at the foot of the panel. Every competitor tool guesses there; guessing is
 * how you end up with a number you cannot defend to a customer.
 *
 * Rendered server-side with the defaults already filled in, so the panel is
 * readable and correct before any JavaScript runs.
 */
$defaultRating  = 4.2;
$defaultReviews = 47;
$defaultTarget  = 4.7;
$defaultJobs    = 60;

// Same formula as the JS, so the first paint matches what the script computes.
// round() before ceil() for the same reason the JS does it: in binary,
// (4.9 - 3.0) / (5 - 4.9) lands on 19.00000000000007 and ceil reports 20.
$needed = (int) ceil(round($defaultReviews * ($defaultTarget - $defaultRating) / (5 - $defaultTarget), 6));
?>
<div class="calc" id="calc"
     data-calc
     data-rating="<?= $defaultRating ?>"
     data-reviews="<?= $defaultReviews ?>"
     data-target="<?= $defaultTarget ?>"
     data-jobs="<?= $defaultJobs ?>">

  <div class="calc__panel">
    <h3>Your business today</h3>

    <div class="calc__field">
      <label class="calc__label" for="calc-rating">
        <span>Your Google rating now</span>
        <output class="calc__out" id="calc-rating-out" for="calc-rating"><?= number_format($defaultRating, 1) ?></output>
      </label>
      <input type="range" id="calc-rating" name="rating"
             min="1" max="4.9" step="0.1" value="<?= $defaultRating ?>">
      <p class="calc__hint">The star rating showing on your Google Business Profile.</p>
    </div>

    <div class="calc__field">
      <label class="calc__label" for="calc-reviews">
        <span>Reviews you have now</span>
        <output class="calc__out" id="calc-reviews-out" for="calc-reviews"><?= $defaultReviews ?></output>
      </label>
      <input type="range" id="calc-reviews" name="reviews"
             min="1" max="500" step="1" value="<?= $defaultReviews ?>">
      <p class="calc__hint">More reviews make a rating harder to move — in both directions.</p>
    </div>

    <div class="calc__field">
      <label class="calc__label" for="calc-target">
        <span>Rating you want</span>
        <output class="calc__out" id="calc-target-out" for="calc-target"><?= number_format($defaultTarget, 1) ?></output>
      </label>
      <input type="range" id="calc-target" name="target"
             min="3" max="5" step="0.1" value="<?= $defaultTarget ?>">
      <p class="calc__hint">4.5 is the cut-off in Google&rsquo;s own rating filter.</p>
    </div>

    <div class="calc__field">
      <label class="calc__label" for="calc-jobs">
        <span>Customers you serve a month</span>
        <output class="calc__out" id="calc-jobs-out" for="calc-jobs"><?= $defaultJobs ?></output>
      </label>
      <input type="range" id="calc-jobs" name="jobs"
             min="5" max="400" step="5" value="<?= $defaultJobs ?>">
      <p class="calc__hint">Used only to work out how long it takes, at a 25% response rate.</p>
    </div>
  </div>

  <div class="calc__result" data-calc-result>
    <p class="calc__eyebrow">Five-star reviews needed</p>

    <p class="calc__hero" data-calc-needed><?= number_format($needed) ?></p>
    <p class="calc__hero-unit" data-calc-unit>
      to go from <?= number_format($defaultRating, 1) ?> to <?= number_format($defaultTarget, 1) ?> stars
    </p>

    <div class="rating-pair">
      <div class="rating-line rating-line--now">
        <span class="rating-line__label">Now</span>
        <span class="stars-meter" aria-hidden="true">
          <span class="stars-meter__bg">★★★★★</span>
          <span class="stars-meter__fg" data-calc-stars-now
                style="--fill:<?= round($defaultRating / 5 * 100, 2) ?>%">★★★★★</span>
        </span>
        <span class="rating-line__val" data-calc-now><?= number_format($defaultRating, 1) ?></span>
      </div>
      <div class="rating-line">
        <span class="rating-line__label">Target</span>
        <span class="stars-meter" aria-hidden="true">
          <span class="stars-meter__bg">★★★★★</span>
          <span class="stars-meter__fg" data-calc-stars-target
                style="--fill:<?= round($defaultTarget / 5 * 100, 2) ?>%">★★★★★</span>
        </span>
        <span class="rating-line__val" data-calc-goal><?= number_format($defaultTarget, 1) ?></span>
      </div>
    </div>

    <dl class="calc__rows">
      <div class="calc__row">
        <dt>Review requests to send</dt>
        <dd data-calc-requests><?= number_format((int) ceil($needed / 0.25)) ?></dd>
      </div>
      <div class="calc__row">
        <dt>At <?= $defaultJobs ?> customers a month</dt>
        <dd data-calc-months><?= number_format((int) max(1, ceil($needed / 0.25 / $defaultJobs)), 0) ?> months</dd>
      </div>
      <div class="calc__row">
        <dt>Reviews after</dt>
        <dd><span data-calc-total><?= number_format($defaultReviews + $needed) ?></span>
          <span class="up" data-calc-delta>+<?= number_format($needed) ?></span></dd>
      </div>
    </dl>

    <p class="form__note" style="margin-top:1.25rem;">
      This is arithmetic, not a forecast. We will not put a revenue figure on it,
      because nobody can know yours — and a number you cannot defend to a customer
      is worse than no number.
    </p>

    <a class="btn btn--primary btn--block" style="margin-top:1.1rem;" href="/audit">
      Get your free review audit
    </a>
  </div>
</div>
