<?php
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\ReviewLink;
use App\Support\View;
/**
 * @var array $account @var ?array $location @var array $limit
 * @var string $replyTo @var int $stuck @var bool $sending @var array $recent
 */
$reviewUrl = trim((string) ($location['google_review_url'] ?? ''));
$ready     = $reviewUrl !== '';
$business  = trim((string) ($location['name'] ?? ($account['name'] ?? 'your business')));
?>
<?php /* One screen, two steps, in the order a new account hits them.

         Step one exists because nothing in this app could set
         locations.google_review_url until now -- it was a column filled in by
         hand during onboarding, which is fine for five accounts and impossible
         for fifty. Without it the sender refuses every request, so it is the
         first thing on the page and the only thing on it until it is done. */ ?>
<div class="admin-title">
  <h1>Get reviews</h1>
  <?php if ($ready): ?>
    <span class="muted" style="font-size:.9rem;">
      <?php if ($limit['month_limit'] === null): ?>
        No monthly cap
      <?php else: ?>
        <?= (int) $limit['month_left'] ?> of <?= (int) $limit['month_limit'] ?> left this month
      <?php endif; ?>
    </span>
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

<?php /* ---- Step two: ask somebody ---------------------------------------- */ ?>
<div class="card<?= $ready ? '' : ' is-waiting' ?>" style="margin-bottom:1.5rem;">
  <div class="step-head">
    <span class="step-head__n">2</span>
    <div>
      <h2 style="font-size:1.05rem;margin:0;">Ask a customer</h2>
      <p class="muted" style="margin:.2rem 0 0;font-size:.9rem;">
        One at a time, on the day you did the work. That timing matters more
        than anything else on this page.
      </p>
    </div>
  </div>

  <?php if (!$ready): ?>
    <p class="muted" style="margin:1.1rem 0 0;font-size:.92rem;">
      Save your review link above and this opens up.
    </p>
  <?php elseif (!$limit['allowed']): ?>
    <div class="alert" role="status" style="margin-top:1.1rem;"><?= View::e((string) $limit['reason']) ?></div>
  <?php else: ?>
    <form class="form" method="post" action="/members/ask" style="margin-top:1.25rem;">
      <?= Csrf::field() ?>
      <div class="form__row form__row--2">
        <div>
          <label for="first_name">Their first name</label>
          <input class="field" id="first_name" name="first_name" type="text" required
                 maxlength="80" placeholder="Dana" autocomplete="off">
        </div>
        <div>
          <label for="last_name">Last name <span class="muted">(optional)</span></label>
          <input class="field" id="last_name" name="last_name" type="text"
                 maxlength="80" placeholder="Reyes" autocomplete="off">
        </div>
      </div>
      <div>
        <label for="email">Their email</label>
        <input class="field" id="email" name="email" type="email" required
               placeholder="dana@example.com" autocomplete="off">
      </div>

      <button class="btn btn--primary btn--xl" type="submit" style="justify-self:start;">
        Send the request
      </button>

      <p class="form__note" style="margin-top:.25rem;">
        Goes out as <strong><?= View::e($business) ?></strong>, with replies coming
        to <strong><?= View::e($replyTo) ?></strong>. One reminder three days
        later, then we stop &mdash; and every customer gets the same message and
        the same link, which is the only compliant way to do this.
      </p>
    </form>
  <?php endif; ?>
</div>

<?php /* ---- What has happened -------------------------------------------- */ ?>
<h2 style="font-size:1.05rem;margin:2rem 0 .9rem;">Recent asks</h2>
<div class="table-wrap">
  <?php if ($recent === []): ?>
    <p class="empty">Nothing sent yet. Your first one goes above.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Customer</th><th>Status</th><th>Sent</th><th>Opened the link</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>
              <strong><?= View::e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?: '—' ?></strong>
              <div class="muted" style="font-size:.82rem;"><?= View::e((string) $r['email']) ?></div>
            </td>
            <td>
              <span class="pill-status st-<?= View::e((string) $r['status']) ?>">
                <?= View::e(str_replace('_', ' ', (string) $r['status'])) ?>
              </span>
              <?php if (!empty($r['failure_reason'])): ?>
                <div class="muted" style="font-size:.8rem;max-width:20rem;"><?= View::e((string) $r['failure_reason']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= $r['sent_at']
                  ? View::e(date('j M, H:i', strtotime((string) $r['sent_at'])))
                  : '<span class="muted">queued</span>' ?></td>
            <td><?= $r['first_clicked_at']
                  ? View::e(date('j M, H:i', strtotime((string) $r['first_clicked_at'])))
                  : '<span class="muted">—</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<p class="muted" style="margin-top:1rem;font-size:.88rem;">
  Every request ever sent is on the <a href="/members/requests" style="color:var(--brand);">Requests</a> page.
</p>
