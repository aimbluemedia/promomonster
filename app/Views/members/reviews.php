<?php
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\ReviewLink;
use App\Support\View;
/**
 * @var array $account @var ?array $location @var int $stuck @var bool $sending
 * @var array $requests
 */
$reviewUrl = trim((string) ($location['google_review_url'] ?? ''));
$ready     = $reviewUrl !== '';
$business  = trim((string) ($location['name'] ?? ($account['name'] ?? 'your business')));
?>
<?php /* Everything Google, and nothing else.

         This page exists because nothing in the app could set
         locations.google_review_url -- it was a column filled in by hand during
         onboarding, fine for five accounts and impossible for fifty. Without it
         a Google request has nowhere to land.

         The form that asks a customer used to be the second half of this
         screen. It moved to Review requests when a request gained a second
         possible destination: a form that only ever meant Google could not be
         the one that offers the choice. */ ?>
<div class="admin-title">
  <h1>Google reviews</h1>
  <?php if ($ready): ?>
    <a class="btn btn--sm" href="/members/requests">Send a request</a>
  <?php endif; ?>
</div>

<?php /* The most likely reason Send appears to do nothing is that the cron job
         was never set up, and from in here that looks identical to everything
         working. Say so rather than let somebody conclude the product is
         broken. */ ?>
<?php if ($stuck > 0): ?>
  <div class="notice" style="margin-bottom:1.5rem;border-left-color:var(--star);">
    <strong><?= (int) $stuck ?> <?= $stuck === 1 ? 'request has' : 'requests have' ?> been waiting more than fifteen minutes.</strong>
    <p>Nothing has picked them up, which usually means the scheduled job on the
      server is not running yet. Nothing is lost &mdash; they will all go out as
      soon as it is. Drop us a line if you are not sure.</p>
  </div>
<?php elseif (!$sending): ?>
  <div class="notice" style="margin-bottom:1.5rem;">
    <strong>Email sending is not switched on yet.</strong>
    <p>Requests you add here are queued and recorded, and they will send as soon
      as the mail provider is connected.</p>
  </div>
<?php endif; ?>

<?php /* ---- Step one: the review link ----------------------------------- */ ?>
<div class="card" style="margin-bottom:1.5rem;">
  <div class="step-head">
    <span class="step-head__n"><?= $ready ? Icon::render('check') : '1' ?></span>
    <div>
      <h2 style="font-size:1.05rem;margin:0;">Your Google review link</h2>
      <p class="muted" style="margin:.2rem 0 0;font-size:.9rem;">
        <?= $ready
          ? 'Saved. Every request you send points customers straight at your review box.'
          : 'The one link that opens the review box on your Google listing. Everything else needs it.' ?>
      </p>
    </div>
  </div>

  <?php if ($ready): ?>
    <p class="review-link">
      <?= Icon::render('star') ?>
      <a href="<?= View::e($reviewUrl) ?>" target="_blank" rel="noopener noreferrer">
        <?= View::e(ReviewLink::short($reviewUrl)) ?>
      </a>
      <span class="muted">&mdash; open it to check it works</span>
    </p>
  <?php endif; ?>

  <details<?= $ready ? '' : ' open' ?> style="margin-top:<?= $ready ? '1rem' : '1.25rem' ?>;">
    <summary class="link-summary"><?= $ready ? 'Change it' : 'Where do I find it?' ?></summary>

    <?php /* Written out rather than linked, because an owner who has never seen
             this link is exactly the person who will not go hunting for it. */ ?>
    <ol class="how-to">
      <li>Search Google for your own business name while signed in to the account that manages it.</li>
      <li>In the panel that appears, choose <strong>Ask for reviews</strong>.</li>
      <li>Copy the link it gives you and paste it below.</li>
    </ol>
    <p class="muted" style="font-size:.86rem;margin:.75rem 0 0;">
      It usually starts <code>https://g.page/r/</code> or
      <code>https://search.google.com/local/writereview</code>. A link to your
      listing on Maps is a different thing and will not work &mdash; we will tell
      you if you paste one.
    </p>

    <form class="form" method="post" action="/members/review-link" style="margin-top:1.1rem;">
      <?= Csrf::field() ?>
      <div>
        <label class="sr-only" for="review_url">Google review link</label>
        <input class="field" id="review_url" name="review_url" type="url" required
               value="<?= View::e($reviewUrl) ?>"
               placeholder="https://g.page/r/..." autocomplete="off" spellcheck="false">
      </div>
      <button class="btn btn--primary" type="submit" style="justify-self:start;">
        <?= $ready ? 'Save link' : 'Save my review link' ?>
      </button>
    </form>
  </details>
</div>

<?php /* ---- What has been sent to Google ------------------------------- */ ?>
<h2 style="font-size:1.05rem;margin:2rem 0 .9rem;">Requests sent to Google</h2>
<?= View::render('members/_requests', [
    'rows'      => $requests,
    'empty'     => 'None yet. Send one from the Review requests page.',
    'showWhere' => false,
]) ?>

<p class="muted" style="margin-top:1rem;font-size:.88rem;">
  Requests to every destination are on the
  <a href="/members/requests" style="color:var(--brand);">Review requests</a> page.
</p>
