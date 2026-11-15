<?php
use App\Support\Icon;
use App\Support\Plans;
use App\Support\View;
/**
 * The set-up steps at the top of the dashboard, as cards.
 *
 * Three things decide what this looks like:
 *
 *   The cards are the steps that COUNT -- the ones that can be finished and
 *   that the plan allows. The heading counts those cards and the bar counts
 *   the same set, so the number in the heading, the number beside the bar and
 *   the number of boxes on screen can never disagree. An earlier version of
 *   this screen said "3 of 3" above a list with a padlock in it, which to
 *   somebody who has not read the code means the page is broken.
 *
 *   The two steps that cannot be counted are still shown, underneath, smaller.
 *   The Google one is locked by plan and the website one is the one we will
 *   never tick. Hiding either would leave somebody wondering why a nav item
 *   does nothing, or never learning that the last step exists.
 *
 *   One card is live at a time. Amber for "your turn", green for done, and
 *   nothing shouting on the rest.
 *
 * @var array $steps @var array $progress
 */
?>
<?php
// Cards: what can be finished. Notes: what cannot, and why.
$cards = array_values(array_filter(
    $steps,
    static fn (array $s): bool => $s['checkable'] && !$s['locked'],
));
$notes = array_values(array_filter(
    $steps,
    static fn (array $s): bool => !$s['checkable'] || $s['locked'],
));

// "You are set up" used to sit directly above an unticked step and a locked
// one. The website step is never ticked by design, so "complete" has never
// meant there is nothing left to do -- it means nothing WE CAN CHECK is left,
// and the heading says that rather than claiming the job is over.
$lastStep = $progress['complete'] && $progress['next'] !== null;

// From progress(), not counted again here. It filters the steps with exactly
// the same test these cards do, so taking the number from there is what keeps
// the heading, the bar and the number of boxes on screen from ever drifting
// apart. A second count is a second thing to get wrong.
$total = (int) $progress['total'];

$icons = [
    'page'    => 'sparkle',
    'google'  => 'star',
    'send'    => 'send',
    'collect' => 'message',
    'display' => 'layout',
];
?>
<section class="gs<?= $progress['complete'] && !$lastStep ? ' gs--done' : '' ?>">
  <?php if (!$progress['complete']): ?>
    <p class="gs__flag">Start here first</p>
  <?php endif; ?>

  <div class="gs__head">
    <div class="gs__headtext">
      <h2 class="gs__title">
        <?php if ($lastStep): ?>
          One thing left
        <?php elseif ($progress['complete']): ?>
          You are set up
        <?php else: ?>
          Finish these <?= (int) $total ?> steps to start collecting reviews
        <?php endif; ?>
      </h2>
      <p class="gs__sub">
        <?php if ($lastStep): ?>
          Everything on our side is done. The last step happens on your own
          website, so it is the one we cannot tick for you.
        <?php elseif ($progress['complete']): ?>
          Everything we can check is done. Keep asking &mdash; a few a week, every week.
        <?php else: ?>
          All <?= (int) $total ?> need doing before reviews start coming in.
          Most of it is already done for you.
        <?php endif; ?>
      </p>
    </div>

    <?php /* The count and the bar read the same set as the cards below, and
             say out loud what they are counting. "2 of 3" beside a list with
             a padlock in it reads as an error unless the thing being counted
             is named. */ ?>
    <div class="gs__meter">
      <p class="gs__count">
        <strong><?= (int) $progress['done'] ?></strong> of <?= (int) $total ?> done
      </p>
      <div class="gs__bar<?= $progress['percent'] === 100 ? ' is-full' : '' ?>"
           role="progressbar" aria-valuenow="<?= (int) $progress['percent'] ?>"
           aria-valuemin="0" aria-valuemax="100" aria-label="Setup progress">
        <span style="width:<?= (int) $progress['percent'] ?>%"></span>
      </div>
    </div>
  </div>

  <ol class="gs__cards">
    <?php foreach ($cards as $i => $step): ?>
      <?php
        $isNext = $progress['next'] === $step['key'];
        $state  = $step['done'] ? 'is-done' : ($isNext ? 'is-next' : '');
      ?>
      <li class="gs__card <?= $state ?>">
        <div class="gs__cardtop">
          <span class="gs__icon" aria-hidden="true">
            <?= Icon::render($icons[$step['key']] ?? 'check') ?>
          </span>
          <?php if ($step['done']): ?>
            <span class="gs__badge gs__badge--done">&#10003; Done</span>
          <?php elseif ($isNext): ?>
            <span class="gs__badge gs__badge--next">Do this next</span>
          <?php endif; ?>
        </div>

        <p class="gs__step">Step <?= (int) $i + 1 ?></p>
        <h3 class="gs__h"><?= View::e($step['title']) ?></h3>

        <?php /* Unfinished cards carry their explanation; finished ones carry
                 what came of it. A finished step does not need telling you
                 what it was for, and five blurbs at once is a panel nobody
                 reads -- but an empty box in a row of equal-height cards
                 reads as one that failed to load.

                 The chips come from Onboarding, out of the same state that
                 decided the step was done, so a card cannot show a count for
                 something it is also calling unfinished. */ ?>
        <?php if (!$step['done']): ?>
          <p class="gs__what"><?= View::e($step['what']) ?></p>
        <?php elseif (($step['facts'] ?? []) !== []): ?>
          <ul class="gs__facts">
            <?php foreach ($step['facts'] as $fact): ?>
              <li><?= View::e((string) $fact) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <a class="gs__btn<?= $isNext ? ' gs__btn--go' : '' ?>"
           href="<?= View::e($step['href']) ?>">
          <?= View::e($step['cta']) ?><?= $isNext ? ' &rarr;' : '' ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ol>

  <?php if ($notes !== []): ?>
    <?php /* Underneath, and quieter. Neither of these can be counted -- one
             because the plan does not include it, one because it happens on a
             website we cannot see -- so neither belongs in a progress bar.
             Both still belong on the page. */ ?>
    <ul class="gs__also">
      <?php foreach ($notes as $step): ?>
        <li class="gs__note">
          <span class="gs__noteicon" aria-hidden="true">
            <?= $step['locked'] ? '&#128274;' : Icon::render($icons[$step['key']] ?? 'check') ?>
          </span>
          <div>
            <h3><?= View::e($step['title']) ?></h3>
            <p><?= View::e($step['what']) ?></p>
          </div>
          <?php if ($step['locked'] && $step['needs'] !== null): ?>
            <?php
              // Every plan that grants it, not just the cheapest. Naming Pro
              // alone left somebody already weighing Premium wondering whether
              // the dearer plan covered it too.
              $grants = $step['ability'] === null
                  ? [(string) $step['needs']]
                  : Plans::plansWith((string) $step['ability']);
            ?>
            <a class="gs__notebtn" href="/members/plan">
              <?= View::e('Upgrade to ' . implode(' or ', array_map(
                  static fn (string $k): string => Plans::name($k),
                  $grants,
              ))) ?>
              <span class="gs__notecost">
                <?php foreach ($grants as $n => $planKey): ?>
                  <?= $n > 0 ? ' or ' : '' ?><?= View::e(Plans::name($planKey)) ?>
                  <?= View::e(Plans::money($planKey)) ?>/month
                <?php endforeach; ?>
              </span>
            </a>
          <?php else: ?>
            <a class="gs__notebtn" href="<?= View::e($step['href']) ?>">
              <?= View::e($step['cta']) ?>
              <span class="gs__notecost">We cannot check this one</span>
            </a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
