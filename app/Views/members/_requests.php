<?php use App\Support\Csrf; use App\Support\ReviewRequests; use App\Support\View; ?>
<?php
/**
 * The sent-requests table, shared by the three screens that show it.
 *
 * One partial rather than three copies of the same markup: the Review requests
 * page shows everything, the Google page and the PromoMonster page each show
 * their own. Three hand-written copies is how the columns drift apart and how
 * one of them ends up saying "sent" for a row that failed.
 *
 * The column that earns its place is "When". A queued row that says nothing but
 * "queued" tells a business the thing they did not ask: they want to know when
 * it leaves, and if the answer is "nothing is picking these up" they need to
 * find that out here rather than from a customer who never got an email.
 *
 * @var list<array<string,mixed>> $rows
 * @var string $empty       what to say when there is nothing
 * @var bool   $showWhere   whether the destination column earns its place
 * @var bool   $showActions whether this screen can act on a row
 * @var list<array<string,mixed>> $reminders  follow-up wording to offer
 */
$showWhere   = $showWhere ?? false;
$showActions = $showActions ?? false;
$reminders   = $reminders ?? [];

/** A datetime from the database, in words a person reads. */
$when = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    $stamp = strtotime($value);
    if ($stamp === false) {
        return '';
    }

    // The year only when it is not this one: "3 Oct, 14:05" is what somebody
    // wants nine times in ten, and "3 Oct 2025" is what they need the tenth.
    return date('Y') === date('Y', $stamp)
        ? date('j M, H:i', $stamp)
        : date('j M Y, H:i', $stamp);
};

$counts = ['waiting' => 0, 'sent' => 0, 'opened' => 0];
foreach ($rows as $r) {
    if (in_array((string) $r['status'], ['queued', 'scheduled'], true)) {
        $counts['waiting']++;
    } elseif (!in_array((string) $r['status'], ['failed', 'cancelled'], true)) {
        $counts['sent']++;
    }
    if (!empty($r['first_clicked_at'])) {
        $counts['opened']++;
    }
}
?>
<?php if ($rows !== []): ?>
  <?php /* The three numbers a business actually watches, before the detail.
           Opened out of sent is the one that says whether the wording works. */ ?>
  <p class="req-tally">
    <?php if ($counts['waiting'] > 0): ?>
      <strong><?= $counts['waiting'] ?></strong> not sent yet &middot;
    <?php endif; ?>
    <strong><?= $counts['sent'] ?></strong> sent &middot;
    <strong><?= $counts['opened'] ?></strong> opened the link
  </p>
<?php endif; ?>

<div class="table-wrap">
  <?php if ($rows === []): ?>
    <p class="empty"><?= View::e($empty) ?></p>
  <?php else: ?>
    <table class="data">
      <thead>
        <tr>
          <th>Customer</th>
          <?php if ($showWhere): ?><th>Where</th><?php endif; ?>
          <th>Wording</th>
          <th>Status</th>
          <th>When</th>
          <th>Opened the link</th>
          <?php if ($showActions): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            $status  = (string) $r['status'];
            $waiting = in_array($status, ['queued', 'scheduled'], true);
            $due     = (int) ($r['is_due'] ?? 1) === 1;
          ?>
          <tr>
            <td>
              <strong><?= View::e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?: '&mdash;' ?></strong>
              <div class="muted" style="font-size:.82rem;"><?= View::e((string) $r['email']) ?></div>
              <?php if ((int) ($r['is_follow_up'] ?? 0) === 1): ?>
                <div class="muted" style="font-size:.78rem;">reminder</div>
              <?php endif; ?>
            </td>

            <?php if ($showWhere): ?>
              <td>
                <?php $where = ReviewRequests::destination((string) ($r['destination'] ?? 'google')); ?>
                <span class="dest-tag dest-tag--<?= View::e($where) ?>">
                  <?= View::e(ReviewRequests::DESTINATIONS[$where]) ?>
                </span>
              </td>
            <?php endif; ?>

            <td class="muted" style="font-size:.86rem;">
              <?= View::e((string) ($r['template_name'] ?? '')) ?: '&mdash;' ?>
            </td>

            <td>
              <span class="pill-status st-<?= View::e($status) ?>">
                <?= View::e(str_replace('_', ' ', $status)) ?>
              </span>
              <?php if (!empty($r['failure_reason'])): ?>
                <div class="muted" style="font-size:.8rem;max-width:20rem;"><?= View::e((string) $r['failure_reason']) ?></div>
              <?php endif; ?>
              <?php if ($waiting && (int) ($r['attempts'] ?? 0) > 0): ?>
                <div class="muted" style="font-size:.78rem;">
                  <?= (int) $r['attempts'] ?> attempt<?= (int) $r['attempts'] === 1 ? '' : 's' ?> so far
                </div>
              <?php endif; ?>
            </td>

            <?php /* One column, one answer, whatever state the row is in.
                     "queued" on its own was the complaint: it says what the row
                     is and not the thing anybody wants, which is when it goes. */ ?>
            <td>
              <?php if ($waiting && $due): ?>
                <?php /* It no longer "goes out on the next send run": there is
                         no run. A request sends on the click, so a row still
                         waiting is one that did not get away, and the honest
                         thing is to say so and offer the button. */ ?>
                <strong>Not sent</strong>
                <div class="muted" style="font-size:.78rem;">try again when you are ready</div>
              <?php elseif ($waiting): ?>
                <strong><?= View::e($when((string) $r['scheduled_for'])) ?></strong>
                <div class="muted" style="font-size:.78rem;">waiting</div>
              <?php elseif (!empty($r['sent_at'])): ?>
                <?= View::e($when((string) $r['sent_at'])) ?>
                <div class="muted" style="font-size:.78rem;">sent</div>
              <?php else: ?>
                <span class="muted"><?= View::e($when((string) $r['created_at'])) ?></span>
                <div class="muted" style="font-size:.78rem;">added</div>
              <?php endif; ?>
            </td>

            <td>
              <?php if (!empty($r['first_clicked_at'])): ?>
                <?= View::e($when((string) $r['first_clicked_at'])) ?>
              <?php else: ?>
                <span class="muted">&mdash;</span>
                <?php /* A fetch that was not a person. Shown rather than
                         dropped: it is the difference between an email nobody
                         touched and one a mail scanner opened on the way in,
                         and only one of those says anything about the
                         customer. */ ?>
                <?php if ((int) ($r['fetches'] ?? 0) > 0): ?>
                  <div class="muted" style="font-size:.76rem;">
                    <?= (int) $r['fetches'] ?>
                    machine <?= (int) $r['fetches'] === 1 ? 'fetch' : 'fetches' ?>,
                    not opened
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </td>

            <?php /* The reminder used to be a row scheduled three days out and
                     sent by a cron job. It is now a button that appears exactly
                     when it is worth pressing: sent, not opened, a day gone by,
                     and no reminder sent yet. The condition IS the information
                     the timer was standing in for. */ ?>
            <?php if ($showActions): ?>
              <td class="req-act">
                <?php if (ReviewRequests::canRemind($r)): ?>
                  <details class="req-remind">
                    <summary class="btn btn--sm">Send a reminder</summary>
                    <form method="post" action="/members/remind" class="req-remind__form">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <?php if (count($reminders) > 1): ?>
                        <label class="sr-only" for="rt<?= (int) $r['id'] ?>">Which wording</label>
                        <select class="field" id="rt<?= (int) $r['id'] ?>" name="template_id">
                          <?php foreach ($reminders as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= View::e((string) $t['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      <?php endif; ?>
                      <button class="btn btn--sm btn--primary" type="submit">Send it</button>
                      <p class="form__note">
                        Goes now, in different words. This is the only reminder
                        they will get.
                      </p>
                    </form>
                  </details>

                <?php elseif ((int) ($r['has_reminder'] ?? 0) === 1): ?>
                  <span class="muted" style="font-size:.8rem;">reminded</span>

                <?php elseif (empty($r['sent_at']) && !in_array((string) $r['status'], ['cancelled'], true)): ?>
                  <form method="post" action="/members/retry" style="display:inline;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn--sm" type="submit">Try again</button>
                  </form>

                <?php elseif (!empty($r['first_clicked_at'])): ?>
                  <span class="muted" style="font-size:.8rem;">opened it</span>

                <?php elseif ((int) ($r['email_opted_out'] ?? 0) === 1): ?>
                  <span class="muted" style="font-size:.8rem;">opted out</span>

                <?php elseif ((int) ($r['is_follow_up'] ?? 0) === 0 && !empty($r['sent_at'])): ?>
                  <?php /* Sent, unopened, but too recently to chase. Saying so
                           beats an absent button nobody can explain. */ ?>
                  <span class="muted" style="font-size:.8rem;">
                    chase after <?= (int) ReviewRequests::REMIND_AFTER_HOURS ?>h
                  </span>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
