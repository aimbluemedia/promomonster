<?php use App\Support\Csrf; use App\Support\HostedReviews; use App\Support\View;
/** @var array $account @var bool $ready @var ?string $slug @var ?string $pageUrl
 *  @var ?string $widgetJs @var array $summary @var array $reviews
 *  @var array $requests @var ?string $error @var bool $canAdd @var bool $branded
 *  @var array $drafts @var bool $canDraft @var array $draftMonth */
$canAdd  = $canAdd ?? true;
$branded = $branded ?? true;

// Defaults, so the page renders before migration 028 has been run and before
// anything has ever asked for a draft. A members screen that fatals on a
// missing key is worse than one with a button temporarily absent.
$drafts     = $drafts ?? [];
$canDraft   = $canDraft ?? false;
$draftMonth = $draftMonth ?? ['allowed' => false, 'used' => 0, 'limit' => 0, 'reason' => null];
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

  <?php /* ---- The three steps ------------------------------------------- */ ?>
  <?php /* One per row, each laid out across: the numeral on a left rail, the
           title and what it is for beside it, and who it is for at the far
           right. Whatever the step actually gives you -- a link, a snippet, a
           form -- sits underneath, aligned past the numeral.

           Three cards side by side made every one of them a narrow column: a
           URL broke across three lines, a script tag across four, and the
           eight-field form had to be moved out of its own card to fit. Full
           width gives each step room to show the thing it is about, and the
           rail keeps the row scannable.

           In the order somebody does them. Adding by hand is the only one a
           plan can refuse, so it goes last rather than sitting between the
           two everybody can do. */ ?>

  <div class="steps">

  <?php /* ---- 1. The page ---------------------------------------------- */ ?>
  <section class="stepcard">
    <header class="stepcard__band">
      <span class="stepcard__n" aria-hidden="true">1</span>
      <h2 class="stepcard__h">Your review page</h2>
      <p class="stepcard__pill">All membership access</p>
    </header>
    <div class="stepcard__body">
      <p class="stepcard__sub">
        Send this to a customer, put it on a card, or link it from your site.
        Anyone who opens it can leave you a review.
      </p>
      <a class="stepcard__link" href="<?= View::e($pageUrl) ?>" target="_blank" rel="noopener">
        <span class="stepcard__linkurl"><?= View::e($pageUrl) ?></span>
        <span class="stepcard__linkgo" aria-hidden="true">&#8599;</span>
      </a>
      <p class="stepcard__fine">
        Reviews sent through a personal link from <strong>Google reviews</strong>
        are marked <em>Verified customer</em>. Ones left straight from this page
        are not, which is the honest difference.
      </p>
    </div>
  </section>

  <?php /* ---- 2. The widget -------------------------------------------- */ ?>
  <section class="stepcard">
    <?php /* The widget is on every plan. Only our name on it is not, and the
             line below says so -- which is why this does not quietly become
             "Premium". */ ?>
    <header class="stepcard__band">
      <span class="stepcard__n" aria-hidden="true">2</span>
      <h2 class="stepcard__h">Put them on your website</h2>
      <p class="stepcard__pill">All membership access</p>
    </header>
    <div class="stepcard__body">
      <p class="stepcard__sub">
        Paste this where you want the reviews to appear. Works on any site
        that lets you add HTML.
      </p>
      <pre class="stepcard__code"><code>&lt;script src="<?= View::e($widgetJs) ?>" async&gt;&lt;/script&gt;</code></pre>
      <p class="stepcard__fine">
        It shows your latest twelve, your average, and a link to the full page.
        <?php if ($branded): ?>
          The link reads <strong>&ldquo;Every review, on PromoMonster&rdquo;</strong>.
          <?php /* The link itself stays on every plan, branded or not. It is how
                   a reader checks these are ALL the reviews rather than a
                   flattering dozen, and selling the ability to hide that would
                   be selling the ability to mislead. White-label removes our
                   name, not the proof. */ ?>
          <?php $plain = App\Support\Plans::cheapestWith(App\Support\Plans::UNBRANDED); ?>
          <?php if ($plain !== null): ?>
            Want it without our name on it? That is
            <a href="/members#plans"><?= View::e(App\Support\Plans::name($plain)) ?></a>.
          <?php endif; ?>
        <?php else: ?>
          On your plan it carries no PromoMonster name &mdash; the link back just
          reads <strong>&ldquo;See every review&rdquo;</strong>.
        <?php endif; ?>
      </p>
    </div>
  </section>

  <?php /* ---- 3. By hand ------------------------------------------------ */ ?>
  <?php $needsPlan = App\Support\Plans::cheapestWith(App\Support\Plans::ADD_REVIEWS); ?>
  <section class="stepcard stepcard--paid<?= $canAdd ? '' : ' stepcard--locked' ?>"
           id="add-review">
    <header class="stepcard__band">
      <span class="stepcard__n" aria-hidden="true">3</span>
      <h2 class="stepcard__h">Add a review yourself</h2>
      <?php /* No modifier class. The amber comes from --accent on the card,
               so a second class here would style nothing -- which is exactly
               what the styled-class check caught. */ ?>
      <p class="stepcard__pill">
        <?= View::e(App\Support\Plans::name((string) $needsPlan)) ?> access
      </p>
    </header>
    <div class="stepcard__body">
      <p class="stepcard__sub">
        For one a customer gave you in person or on the phone, or one already
        on your Google listing. Both are labelled as added by you, so nobody
        reading them is misled about where they came from.
      </p>
      <?php if (!$canAdd): ?>
        <?php if ($needsPlan !== null): ?>
          <p class="stepcard__price">
            On <strong><?= View::e(App\Support\Plans::name($needsPlan)) ?></strong>,
            <?= View::e(App\Support\Plans::money($needsPlan)) ?> a month.
          </p>
          <p class="stepcard__acts">
            <a class="btn btn--primary btn--sm" href="/members#plans">
              Compare the plans
            </a>
          </p>
        <?php endif; ?>
      <?php else: ?>
        <form class="form stepcard__form" method="post"
              action="/members/promomonster-reviews/add">
          <?= Csrf::field() ?>

          <div class="form__row form__row--2">
            <div>
              <label for="author_name">Customer&rsquo;s name</label>
              <input class="field" id="author_name" name="author_name" type="text" required
                     maxlength="120" placeholder="Dana Reyes" autocomplete="off">
            </div>
            <div>
              <label for="rating">Rating</label>
              <select class="field" id="rating" name="rating" required>
                <option value="">Choose&hellip;</option>
                <?php foreach ([5 => '5 - excellent', 4 => '4 - good', 3 => '3 - okay',
                                2 => '2 - poor', 1 => '1 - bad'] as $n => $word): ?>
                  <option value="<?= $n ?>"><?= View::e($word) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form__row form__row--2">
            <div>
              <label for="author_city">City, State</label>
              <input class="field" id="author_city" name="author_city" type="text" required
                     maxlength="120" placeholder="Mesa, AZ" autocomplete="off">
            </div>
            <div>
              <label for="source_url">Link to it on Google <span class="muted">(optional)</span></label>
              <input class="field" id="source_url" name="source_url" type="url"
                     placeholder="https://g.page/..." autocomplete="off" spellcheck="false">
            </div>
          </div>

          <div>
            <label for="body">What they said</label>
            <textarea class="field" id="body" name="body" rows="3" required maxlength="4000"
                      placeholder="Their words, as close as you can remember."></textarea>
          </div>

          <fieldset class="stepcard__from">
            <legend>Where is it from?</legend>
            <label>
              <input type="radio" name="source" value="entered_by_business" checked>
              A customer gave it to me directly
            </label>
            <label>
              <input type="radio" name="source" value="google">
              It is already on my Google listing &mdash; shows with a <strong>G</strong>
            </label>
          </fieldset>

          <p class="stepcard__fine" style="margin:0;">
            The Google link is only used for a Google review. It lets a reader
            check it for themselves, which is what makes the G worth anything.
          </p>

          <button class="btn btn--primary" type="submit" style="justify-self:start;">
            Add review
          </button>
        </form>
      <?php endif; ?>
    </div>
  </section>

  </div>

  <?php /* ---- What is there ------------------------------------------------ */ ?>
  <h2 style="font-size:1.05rem;margin:2rem 0 .9rem;">Your reviews</h2>

  <?php if ($reviews === []): ?>
    <p class="empty">Nothing yet. Share your page, or add one above.</p>
  <?php else: ?>
    <?php foreach ($reviews as $r): ?>
      <?php
        $rid   = (int) $r['id'];
        $saved = (string) ($r['reply_body'] ?? '');
        $draft = (string) ($drafts[$rid] ?? '');

        // What goes in the box: their own saved reply if they have one,
        // otherwise the draft if we made one. Never the draft over the top of
        // something they wrote -- a box that quietly replaces a member's own
        // words is the one thing this feature must not do.
        $inBox = $saved !== '' ? $saved : $draft;

        // Open the panel when there is something to look at, so the draft
        // they just asked for is on screen rather than behind a click.
        $open = $draft !== '' && $saved === '';
      ?>
      <div class="card" id="review-<?= $rid ?>" style="margin-bottom:1rem;">
        <?= View::render('reviews/_review', ['r' => $r, 'business' => (string) $account['name']]) ?>

        <details style="margin-top:.6rem;"<?= $open ? ' open' : '' ?>>
          <summary class="link-summary"><?= $saved === '' ? 'Reply to this' : 'Change your reply' ?></summary>

          <?php /* Two forms, not two buttons in one. A second submit button
                   inside the reply form would post the textarea along with it,
                   and a draft request carrying the member's half-written reply
                   is a draft request that can lose it. */ ?>
          <?php if ($canDraft && $saved === ''): ?>
            <form method="post" action="/members/promomonster-reviews/draft" style="margin-top:.8rem;">
              <?= Csrf::field() ?>
              <input type="hidden" name="review_id" value="<?= $rid ?>">
              <button class="btn btn--ghost btn--sm" type="submit"
                      <?= $draftMonth['allowed'] ? '' : 'disabled' ?>>
                <?= $draft === '' ? 'Draft one for me' : 'Draft another' ?>
              </button>
              <?php if (!$draftMonth['allowed']): ?>
                <span class="muted" style="font-size:.82rem;margin-left:.5rem;">
                  <?= View::e((string) $draftMonth['reason']) ?>
                </span>
              <?php endif; ?>
            </form>
          <?php endif; ?>

          <form class="form" method="post" action="/members/promomonster-reviews/reply" style="margin-top:.8rem;">
            <?= Csrf::field() ?>
            <input type="hidden" name="review_id" value="<?= $rid ?>">
            <label class="sr-only" for="reply-<?= $rid ?>">Your reply</label>
            <textarea class="field" id="reply-<?= $rid ?>" name="reply" rows="3"
                      maxlength="4000"><?= View::e($inBox) ?></textarea>
            <?php if ($draft !== '' && $saved === ''): ?>
              <?php /* Said every time, right above the button that publishes
                       it. A box that filled itself invites a reflex click, and
                       a reply posted in the owner's name that the owner never
                       read is the failure this whole feature has to avoid. */ ?>
              <p class="muted" style="font-size:.82rem;margin:.1rem 0 0;">
                This is a draft, not your words yet. Read it, change what does not
                sound like you, then save. Nothing is public until you press Save.
              </p>
            <?php endif; ?>
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
