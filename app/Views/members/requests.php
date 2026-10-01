<?php
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\View;
/**
 * @var array $account @var ?array $location @var array $limit @var string $replyTo
 * @var int $stuck @var bool $sending @var array $blocked @var array $runner
 * @var array $available @var array $templates @var ?int $templateChosen
 * @var array $requests
 */
$business = trim((string) ($location['name'] ?? ($account['name'] ?? 'your business')));

// Defaulted rather than assumed. A view that fatals because a caller passed
// nine of ten variables is a 500 on the page whose whole job this week has been
// to stop being one.
$blocked = $blocked ?? [];
$runner  = $runner ?? ['state' => 'unknown'];

// Which card starts selected. PromoMonster, because it is the one that works
// for every account on day one -- a Google listing has to exist and be claimed
// before the other can send anything. If it cannot send, the first one that can
// is selected instead, so the form is never pre-set to something that would be
// refused the moment it was submitted.
//
// Read with array_key_exists, never with ??. target() reports "this can send"
// as ['error' => null], and ?? treats a null VALUE the same as a missing key --
// so `$available[$k]['error'] ?? 'not set up'` returns "not set up" precisely
// when everything is fine. That is how this first rendered with both cards
// greyed out and no form underneath them.
$problemWith = static function (array $available, string $key): ?string {
    if (!isset($available[$key]) || !array_key_exists('error', $available[$key])) {
        return 'This is not set up yet.';
    }

    return $available[$key]['error'];
};

$chosen = null;
foreach (['promomonster', 'google'] as $preference) {
    if ($problemWith($available, $preference) === null) {
        $chosen = $preference;
        break;
    }
}
$canSendAnything = $chosen !== null;
?>
<div class="admin-title">
  <h1>Review requests</h1>
  <?php if ($canSendAnything): ?>
    <span class="muted" style="font-size:.9rem;">
      <?php if ($limit['month_limit'] === null): ?>
        No monthly cap
      <?php else: ?>
        <?= (int) $limit['month_left'] ?> of <?= (int) $limit['month_limit'] ?> left this month
      <?php endif; ?>
    </span>
  <?php endif; ?>
</div>

<?php /* The same two notices the Google page used to carry, because they are
         about sending and this is now the screen that sends. */ ?>
<?php /* The database is behind the code in a way sending cannot survive.
         Worth its own notice above the others: with this true, every request
         added below is queued and can never leave, and the other notices would
         have somebody chasing the mail settings instead. */ ?>
<?php if ($blocked !== []): ?>
  <div class="notice" style="margin-bottom:1.5rem;border-left-color:var(--star);">
    <strong>The database is missing an update that sending needs.</strong>
    <p>You can still add requests and they are recorded safely, but nothing will
      go out until migration <?= View::e(implode(' and ', $blocked)) ?> has been
      run. Open <code>/diagnose.php</code> and look at the
      <strong>Required columns</strong> row &mdash; it names exactly what is
      missing.</p>
  </div>
<?php endif; ?>

<?php /* ---- The thing that actually sends ------------------------------- */ ?>
<?php /* "Queued" used to mean "waiting for a process we cannot see, on a
         schedule we were told about once". The runner now writes a row every
         time it wakes, so this is measured: when it last ran, and when the gap
         between its own runs says it will run again. A cron job that was never
         created and one that is about to fire are no longer the same picture. */ ?>
<?php
  $runState = (string) ($runner['state'] ?? 'unknown');
  $runWhen  = static function (?string $at): string {
      $stamp = $at === null ? false : strtotime($at);

      return $stamp === false ? '' : date('j M, H:i', $stamp);
  };
?>
<?php if ($runState === 'never'): ?>
  <?php /* Reworded once the request itself started sending on the click. The
           scheduled job is no longer what stands between pressing Send and an
           email arriving -- it is what sends the reminder three days later, and
           what retries anything that could not go out first time. Saying
           "nothing leaves until this runs" when the thing somebody just did
           does leave would be the page lying to them. */ ?>
  <div class="notice" style="margin-bottom:1.5rem;">
    <strong>Reminders are not set up yet.</strong>
    <p>Requests go out the moment you press Send. The one reminder three days
      later needs a scheduled job on the server, and it has never run &mdash; so
      reminders are not going out, and anything that failed first time is not
      being retried. In hPanel: <strong>Advanced &rarr; Cron Jobs</strong>,
      every 5 minutes, running <code>bin/send-due.php</code>. This box changes
      the moment it does.</p>
  </div>
<?php elseif ($runState === 'late'): ?>
  <div class="notice" style="margin-bottom:1.5rem;border-left-color:var(--star);">
    <strong>The scheduled job has stopped.</strong>
    <p>Requests still send when you press Send, but reminders and retries have
      stopped. Last run was <?= View::e($runWhen($runner['last_at'])) ?>,
      <?= View::e(App\Support\Heartbeat::inWords($runner['ago_seconds'])) ?> ago<?php
        if (($runner['every_seconds'] ?? null) !== null): ?>, and it had been
      running about every
      <?= View::e(App\Support\Heartbeat::inWords($runner['every_seconds'])) ?><?php
        endif; ?>. Nothing is lost &mdash; it all catches up when it starts
      again. Check the cron job in hPanel.</p>
  </div>
<?php elseif ($runState === 'ok'): ?>
  <p class="runner runner--ok">
    <span class="runner__dot"></span>
    Sender ran <strong><?= View::e($runWhen($runner['last_at'])) ?></strong>
    (<?= View::e(App\Support\Heartbeat::inWords($runner['ago_seconds'])) ?> ago)<?php
      if (($runner['next_at'] ?? null) !== null): ?>,
    runs again <strong><?= View::e($runWhen($runner['next_at'])) ?></strong><?php
      if (($runner['due_seconds'] ?? null) !== null && (int) $runner['due_seconds'] > 0): ?>
      &mdash; about <?= View::e(App\Support\Heartbeat::inWords($runner['due_seconds'])) ?> from now<?php
      endif; ?><?php endif; ?>.
  </p>
<?php elseif ($stuck > 0): ?>
  <?php /* No heartbeat table yet, so fall back to the old evidence: things
           have been sitting in the queue longer than anything should. */ ?>
  <div class="notice" style="margin-bottom:1.5rem;border-left-color:var(--star);">
    <strong><?= (int) $stuck ?> <?= $stuck === 1 ? 'request has' : 'requests have' ?> been waiting more than fifteen minutes.</strong>
    <p>Nothing has picked them up, which usually means the scheduled job on the
      server is not running yet. Nothing is lost &mdash; they will all go out as
      soon as it is.</p>
  </div>
<?php endif; ?>

<?php if (!$sending): ?>
  <div class="notice" style="margin-bottom:1.5rem;">
    <strong>Email sending is not switched on yet.</strong>
    <p>Requests you add here are queued and recorded, and they will send as soon
      as the mail provider is connected.</p>
  </div>
<?php endif; ?>

<form class="form" method="post" action="/members/ask">
  <?= Csrf::field() ?>

  <?php /* ---- Where the review goes ------------------------------------- */ ?>
  <?php /* First, and as two cards rather than a dropdown, because it is the
           decision that changes what the customer sees when they click. A
           select under the email field would read as a setting. */ ?>
  <fieldset class="dest"<?= $canSendAnything ? '' : ' disabled' ?>>
    <legend class="sr-only">Where should the review go?</legend>

    <?php
      $cards = [
        'promomonster' => [
          'title' => 'Send a PromoMonster review request',
          'blurb' => 'They land on your own review page here. You can put these '
                   . 'reviews on your website with the widget, and nobody else '
                   . 'can take them down.',
        ],
        'google'       => [
          'title' => 'Send a Google review request',
          'blurb' => 'They land on your Google review box. It is what a stranger '
                   . 'searching for you sees first, and the one you cannot '
                   . 'control once it is written.',
        ],
      ];
    ?>

    <?php foreach ($cards as $key => $card): ?>
      <?php
        $problem = $problemWith($available, $key);
        $usable  = $problem === null;
      ?>
      <label class="dest__card<?= $usable ? '' : ' dest__card--off' ?>">
        <input type="radio" name="destination" value="<?= View::e($key) ?>"
               <?= $chosen === $key ? 'checked' : '' ?>
               <?= $usable ? '' : 'disabled' ?> required>
        <span class="dest__body">
          <span class="dest__title">
            <?= Icon::render('star') ?>
            <?= View::e($card['title']) ?>
          </span>
          <span class="dest__blurb"><?= View::e($card['blurb']) ?></span>
          <?php if (!$usable): ?>
            <span class="dest__problem"><?= View::e($problem) ?></span>
          <?php endif; ?>
        </span>
      </label>
    <?php endforeach; ?>
  </fieldset>

  <?php /* ---- Who to ask ------------------------------------------------ */ ?>
  <?php if (!$canSendAnything): ?>
    <div class="alert" role="status" style="margin-top:1.5rem;">
      Neither destination is ready yet. Save your Google review link on the
      <a href="/members/reviews">Google reviews</a> page, or open
      <a href="/members/promomonster-reviews">PromoMonster reviews</a> to set up
      your own review page &mdash; either one on its own is enough to start.
    </div>
  <?php elseif (!$limit['allowed']): ?>
    <?php /* The pace limit used to replace the form with a red box and stop
             there. It said what had happened and not the only thing anybody
             wants at that moment, which is when they can send the next one --
             so a working rate limit read as a page that had broken. */ ?>
    <div class="notice" style="margin-top:1.5rem;border-left-color:var(--star);">
      <strong>Not right now.</strong>
      <p><?= View::e((string) $limit['reason']) ?></p>
      <?php if (!empty($limit['next_at'])): ?>
        <?php
          $freeAt = strtotime((string) $limit['next_at']);
          $sameYear = $freeAt !== false && date('Y') === date('Y', $freeAt);
        ?>
        <p style="margin-top:.5rem;">
          <strong>You can send the next one
            <?= $freeAt === false
                ? 'shortly'
                : View::e(date($sameYear ? 'l j M, H:i' : 'j M Y, H:i', $freeAt)) ?>.</strong>
          Anything already queued below is unaffected and still goes out.
        </p>
      <?php endif; ?>
      <p style="margin-top:.5rem;font-size:.9rem;">
        The cap is on new asks, not on reminders &mdash; the one follow-up for a
        request you have already sent never counts against it.
        <?php if (($limit['month_limit'] ?? null) !== null): ?>
          You have used <?= (int) $limit['month_used'] ?> of
          <?= (int) $limit['month_limit'] ?> this month.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="card" style="margin-top:1.5rem;">
      <h2 style="font-size:1.05rem;margin:0;">Ask a customer</h2>
      <p class="muted" style="margin:.2rem 0 1.25rem;font-size:.9rem;">
        One at a time, on the day you did the work. That timing matters more
        than anything else on this page.
      </p>

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

      <div style="margin-top:1rem;">
        <label for="email">Their email</label>
        <input class="field" id="email" name="email" type="email" required
               placeholder="dana@example.com" autocomplete="off">
      </div>

      <?php /* Only drawn once there is a choice to make. With one template the
               select is a control that cannot be operated, which is worse than
               no control: it implies a decision exists and then refuses it. */ ?>
      <?php if (count($templates) > 1): ?>
        <div style="margin-top:1rem;">
          <label for="template_id">Which wording</label>
          <select class="field" id="template_id" name="template_id">
            <?php foreach ($templates as $t): ?>
              <option value="<?= (int) $t['id'] ?>"<?= $templateChosen === (int) $t['id'] ? ' selected' : '' ?>>
                <?= View::e((string) $t['name']) ?><?= $templateChosen === (int) $t['id'] ? ' (default)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="form__note">
            Change the wording, or write another, on
            <a href="/members/templates">Email templates</a>.
          </p>
        </div>
      <?php endif; ?>

      <button class="btn btn--primary btn--xl" type="submit" style="justify-self:start;">
        Send the request
      </button>

      <p class="form__note">
        It goes out straight away &mdash; you will see whether it sent on the
        next screen.
      </p>

      <p class="form__note" style="margin-top:.75rem;">
        Goes out as <strong><?= View::e($business) ?></strong>, with replies coming
        to <strong><?= View::e($replyTo) ?></strong>. One reminder three days
        later, then we stop &mdash; and every customer gets the same message and
        the same link, which is the only compliant way to do this.
      </p>
    </div>
  <?php endif; ?>
</form>

<?php /* ---- What has been sent ------------------------------------------ */ ?>
<h2 style="font-size:1.05rem;margin:2rem 0 .9rem;">Everything you have sent</h2>
<?= View::render('members/_requests', [
    'rows'      => $requests,
    'empty'     => 'Nothing sent yet. Your first one goes above.',
    'showWhere' => true,
]) ?>
