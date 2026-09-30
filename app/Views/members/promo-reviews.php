<?php use App\Support\Csrf; use App\Support\HostedReviews; use App\Support\View;
/** @var array $account @var bool $ready @var ?string $slug @var ?string $pageUrl
 *  @var ?string $widgetJs @var array $summary @var array $reviews
 *  @var array $requests @var ?string $error */
?>
<div class="admin-title">
  <h1>PromoMonster reviews</h1>
  <?php if ($summary['count'] > 0): ?>
    <span class="muted" style="font-size:.9rem;">
      <?= View::e(number_format((float) $summary['average'], 1)) ?> out of 5
      from <?= (int) $summary['count'] ?> <?= $summary['count'] === 1 ? 'review' : 'reviews' ?>
    </span>
  <?php endif; ?>
</div>

<?php if (!$ready || $slug === null): ?>
  <div class="notice" style="margin-bottom:1.5rem;border-left-color:var(--star);">
    <strong>Your review page is not switched on yet.</strong>
    <p>The database needs migration 020 applying before this can work. Nothing
      you do here is lost &mdash; it simply has nowhere to go yet.</p>
  </div>
<?php else: ?>

  <?php if ($error !== null): ?>
    <div class="alert" role="alert" style="margin-bottom:1.5rem;"><?= View::e($error) ?></div>
  <?php endif; ?>

  <?php /* ---- 1. The page -------------------------------------------------- */ ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="step-head">
      <span class="step-head__n">1</span>
      <div>
        <h2 style="font-size:1.05rem;margin:0;">Your review page</h2>
        <p class="muted" style="margin:.2rem 0 0;font-size:.9rem;">
          Send this to a customer, put it on a card, or link it from your site.
          Anyone who opens it can leave you a review.
        </p>
      </div>
    </div>
    <p class="review-link" style="margin-top:1rem;">
      <a href="<?= View::e($pageUrl) ?>" target="_blank" rel="noopener"><?= View::e($pageUrl) ?></a>
    </p>
    <p class="muted" style="font-size:.86rem;margin:.5rem 0 0;">
      Reviews sent through a personal link from <strong>Google reviews</strong>
      are marked <em>Verified customer</em>. Ones left straight from this page
      are not, which is the honest difference.
    </p>
  </div>

  <?php /* ---- 2. Add one by hand -------------------------------------------- */ ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="step-head">
      <span class="step-head__n">2</span>
      <div>
        <h2 style="font-size:1.05rem;margin:0;">Add a review yourself</h2>
        <p class="muted" style="margin:.2rem 0 0;font-size:.9rem;">
          For one a customer gave you in person or on the phone, or one already
          on your Google listing. Both are labelled as added by you.
        </p>
      </div>
    </div>

    <form class="form" method="post" action="/members/promomonster-reviews/add" style="margin-top:1.25rem;">
      <?= Csrf::field() ?>

      <div class="form__row form__row--2">
        <div>
          <label for="author_name">Customer's name</label>
          <input class="field" id="author_name" name="author_name" type="text" required
                 maxlength="120" placeholder="Dana Reyes" autocomplete="off">
        </div>
        <div>
          <label for="rating">Rating</label>
          <select class="field" id="rating" name="rating" required>
            <option value="">Choose…</option>
            <?php foreach ([5 => '5 - excellent', 4 => '4 - good', 3 => '3 - okay',
                            2 => '2 - poor', 1 => '1 - bad'] as $n => $word): ?>
              <option value="<?= $n ?>"><?= View::e($word) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div>
        <label for="author_city">City, State</label>
        <input class="field" id="author_city" name="author_city" type="text" required maxlength="120"
               placeholder="Mesa, AZ" autocomplete="off">
      </div>

      <div>
        <label for="body">What they said</label>
        <textarea class="field" id="body" name="body" rows="4" required maxlength="4000"
                  placeholder="Their words, as close as you can remember."></textarea>
      </div>

      <fieldset style="border:0;padding:0;margin:0;">
        <legend style="font-size:.9rem;font-weight:600;margin-bottom:.4rem;">Where is it from?</legend>
        <label style="display:block;font-weight:400;font-size:.92rem;">
          <input type="radio" name="source" value="entered_by_business" checked>
          A customer gave it to me directly
        </label>
        <label style="display:block;font-weight:400;font-size:.92rem;">
          <input type="radio" name="source" value="google">
          It is already on my Google listing &mdash; shows with a <strong>G</strong>
        </label>
      </fieldset>

      <div>
        <label for="source_url">Link to it on Google <span class="muted">(optional)</span></label>
        <input class="field" id="source_url" name="source_url" type="url"
               placeholder="https://g.page/..." autocomplete="off" spellcheck="false">
        <p class="form__note">Only used for Google reviews. It lets a reader check it
          for themselves, which is what makes the G worth anything.</p>
      </div>

      <button class="btn btn--primary" type="submit" style="justify-self:start;">Add review</button>
    </form>
  </div>

  <?php /* ---- 3. The widget ---------------------------------------------- */ ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="step-head">
      <span class="step-head__n">3</span>
      <div>
        <h2 style="font-size:1.05rem;margin:0;">Put them on your website</h2>
        <p class="muted" style="margin:.2rem 0 0;font-size:.9rem;">
          Paste this where you want the reviews to appear. Works on any site
          that lets you add HTML.
        </p>
      </div>
    </div>
    <p style="margin-top:1rem;">
      <code style="display:block;padding:.8rem;overflow-wrap:anywhere;">&lt;script src="<?= View::e($widgetJs) ?>" async&gt;&lt;/script&gt;</code>
    </p>
    <p class="muted" style="font-size:.86rem;margin:.6rem 0 0;">
      It shows your latest twelve, your average, and a link to the full page.
    </p>
  </div>

  <?php /* ---- What is there ------------------------------------------------ */ ?>
  <h2 style="font-size:1.05rem;margin:2rem 0 .9rem;">Your reviews</h2>

  <?php if ($reviews === []): ?>
    <p class="empty">Nothing yet. Share your page, or add one above.</p>
  <?php else: ?>
    <?php foreach ($reviews as $r): ?>
      <div class="card" style="margin-bottom:1rem;">
        <?= View::render('reviews/_review', ['r' => $r, 'business' => (string) $account['name']]) ?>

        <details style="margin-top:.6rem;">
          <summary class="link-summary"><?= empty($r['reply_body']) ? 'Reply to this' : 'Change your reply' ?></summary>
          <form class="form" method="post" action="/members/promomonster-reviews/reply" style="margin-top:.8rem;">
            <?= Csrf::field() ?>
            <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
            <label class="sr-only" for="reply-<?= (int) $r['id'] ?>">Your reply</label>
            <textarea class="field" id="reply-<?= (int) $r['id'] ?>" name="reply" rows="3"
                      maxlength="4000"><?= View::e((string) ($r['reply_body'] ?? '')) ?></textarea>
            <button class="btn btn--primary" type="submit" style="justify-self:start;">Save reply</button>
          </form>
        </details>
      </div>
    <?php endforeach; ?>

    <?php /* Said plainly, where the business will see it, so nobody discovers
             it by trying. */ ?>
    <p class="muted" style="font-size:.86rem;margin-top:1.25rem;">
      Every review here is public and stays public. You can reply to one, but
      there is no way to hide or delete it &mdash; a page that only showed the
      good ones would be worth nothing to the people reading it, and hiding
      negative reviews is against the FTC's rules on consumer reviews.
    </p>
  <?php endif; ?>

  <?php /* ---- Who was asked ---------------------------------------------- */ ?>
  <?php /* The reviews above are what came back. This is what went out, and the
           gap between the two is the number worth looking at: requests with no
           click are a wording problem, clicks with no review are a page
           problem. */ ?>
  <div class="admin-title" style="margin-top:2rem;">
    <h2 style="font-size:1.05rem;margin:0;">Requests sent to your page</h2>
    <a class="btn btn--sm" href="/members/requests">Send another</a>
  </div>
  <?= View::render('members/_requests', [
      'rows'      => $requests,
      'empty'     => 'None yet. Send one from the Review requests page.',
      'showWhere' => false,
  ]) ?>
<?php endif; ?>
