<?php
use App\Support\Csrf;
use App\Support\EmailTemplates;
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

<?php /* There was a block here reporting whether the scheduled job had run,
         and warning when it had not. Nothing waits on a scheduled job any
         more: a request sends on the click, and the reminder is a button on
         the row below rather than a row queued three days out. A warning about
         a job the product no longer needs is one more thing to explain and
         then ignore, so it is gone. bin/send-due.php still works for anyone
         who wants it at volume; nothing requires it. */ ?>

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

      <?php /* ---- Which wording -------------------------------------- */ ?>
      <?php /* Cards rather than a dropdown. A select hides the one thing worth
               knowing before pressing Send -- what the customer is actually
               going to read -- behind a control that shows only a name. Here
               each option carries its own subject line, opens to the whole
               message, and links to where it is edited.

               Drawn even when there is only one, because then it is not a
               control at all, it is a preview, and seeing the words before they
               go is worth more than the row it costs. */ ?>
      <?php if ($templates !== []): ?>
        <fieldset class="pick" style="margin-top:1.25rem;">
          <legend class="pick__legend">Which wording</legend>

          <?php foreach ($templates as $t): ?>
            <?php
              $tid     = (int) $t['id'];
              $chosen  = $templateChosen === $tid;
              $preview = EmailTemplates::preview($t);
            ?>
            <div class="pick__card<?= $chosen ? ' is-on' : '' ?>">
              <?php /* Only the choosing part is inside the label. A details
                       summary or a link nested in a label would toggle the
                       radio as well as doing its own job, so reading the
                       message would silently change the selection. */ ?>
              <label class="pick__choose">
                <input type="radio" name="template_id" value="<?= $tid ?>"
                       <?= $chosen ? 'checked' : '' ?>>
                <span class="pick__body">
                  <span class="pick__name">
                    <?= View::e((string) $t['name']) ?>
                    <?php if ($chosen): ?><span class="pick__tag">default</span><?php endif; ?>
                  </span>
                  <span class="pick__subject"><?= View::e($preview['subject']) ?></span>
                </span>
              </label>

              <div class="pick__tools">
                <details class="pick__peek">
                  <summary>Read it</summary>
                  <pre><?= View::e($preview['body']) ?></pre>
                </details>
                <a class="pick__edit" href="/members/templates?edit=<?= $tid ?>">Edit</a>
              </div>
            </div>
          <?php endforeach; ?>

          <p class="form__note">
            Shown with a sample customer. Write another on
            <a href="/members/templates">Email templates</a>.
          </p>
        </fieldset>
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
    'rows'        => $requests,
    'empty'       => 'Nothing sent yet. Your first one goes above.',
    'showWhere'   => true,
    'showActions' => true,
    'reminders'   => $reminders ?? [],
]) ?>
