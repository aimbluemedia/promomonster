<?php
use App\Support\Icon;
use App\Support\Onboarding;
use App\Support\Plans;
use App\Support\View;
/**
 * The run of steps at the top of the dashboard.
 *
 * One step is highlighted at a time — the next one that can actually be done.
 * A list where everything shouts is a list where nothing does, so finished
 * steps go quiet and grey, later ones stay plain, and only the live one gets
 * the border, the blurb and the button.
 *
 * @var array $steps @var array $progress
 */
?>
<?php
// "You are set up" used to sit directly above an unticked step 5 and a locked
// step, under a bar reading "3 of 3". Three things on one screen disagreeing
// about whether the job was finished -- which, to somebody who has not read
// the code, means the screen is broken.
//
// The website step is never ticked by design, so "complete" never did mean
// there was nothing left to do. It means nothing WE CAN CHECK is left, and
// the heading now says that instead of claiming the job is over.
$lastStep = $progress['complete'] && $progress['next'] !== null;
?>
<?php /* The whole-panel green is for "nothing left", not "one thing left" --
         a finished-looking box above an unfinished row is the contradiction
         this change exists to remove. The BAR still fills, because it counts
         what we can check and that part really is done. */ ?>
<section class="gs<?= $progress['complete'] && !$lastStep ? ' gs--done' : '' ?>">
  <div class="gs__head">
    <div>
      <h2 class="gs__title">
        <?php if ($lastStep): ?>
          One thing left
        <?php elseif ($progress['complete']): ?>
          You are set up
        <?php else: ?>
          Get started
        <?php endif; ?>
      </h2>
      <p class="gs__sub">
        <?php /* Counted, not typed: a Free account has one fewer step than a
                 paid one, and a heading that said "four" over three rows would
                 have somebody hunting for the missing one. */ ?>
        <?php if ($lastStep): ?>
          Everything on our side is done. The last step happens on your own
          website, so it is the one we cannot tick for you.
        <?php elseif ($progress['complete']): ?>
          Everything we can check is done. Keep asking — a few a week, every week.
        <?php else: ?>
          <?= View::e(ucfirst(Onboarding::inWords((int) $progress['total']))) ?>
          things and you are asking customers for reviews. Most of it is already
          done for you.
        <?php endif; ?>
      </p>
    </div>
    <div class="gs__count">
      <strong><?= (int) $progress['done'] ?></strong> of <?= (int) $progress['total'] ?>
      <?php /* Said plainly. "3 of 3" beside a list with a padlock in it reads
               as an error unless the thing being counted is named. */ ?>
      <span class="gs__countwhat">we can check</span>
    </div>
  </div>

  <?php /* The bar counts only what can be finished: a step the plan forbids,
           and the one we cannot verify, are both out of the denominator. A bar
           that can never fill teaches people to ignore bars. */ ?>
  <div class="gs__bar<?= $progress['percent'] === 100 ? ' is-full' : '' ?>" role="progressbar"
       aria-valuenow="<?= (int) $progress['percent'] ?>" aria-valuemin="0" aria-valuemax="100"
       aria-label="Setup progress">
    <span style="width:<?= (int) $progress['percent'] ?>%"></span>
  </div>

  <ol class="gs__steps">
    <?php foreach ($steps as $i => $step): ?>
      <?php
        $isNext = $progress['next'] === $step['key'];
        $class  = $step['done'] ? 'is-done' : ($step['locked'] ? 'is-locked' : ($isNext ? 'is-next' : ''));
      ?>
      <li class="gs__step <?= $class ?>">
        <?php /* No numbers. With three steps ticked and one locked, the only
                 digit left on screen was a lone "5" with no 1 to 4 anywhere
                 near it, which reads as a list that has lost four rows. A tick,
                 a padlock, an arrow for the live one and an empty circle for
                 the rest says the same thing without the arithmetic. */ ?>
        <span class="gs__n" aria-hidden="true">
          <?php if ($step['done']): ?>&#10003;
          <?php elseif ($step['locked']): ?>&#128274;
          <?php elseif ($isNext): ?>&rarr;
          <?php else: ?>&#9675;
          <?php endif; ?>
        </span>

        <div class="gs__body">
          <h3 class="gs__h">
            <?= View::e($step['title']) ?>
            <?php if ($step['done']): ?>
              <span class="gs__tag">Done</span>
            <?php elseif ($step['locked'] && $step['needs'] !== null): ?>
              <?php /* Every plan that grants it, not just the cheapest. Naming
                       Pro alone left somebody already weighing Premium to
                       wonder whether the dearer plan covered it too -- and the
                       locked SECTIONS have named both all along, so the
                       dashboard was the odd one out. */ ?>
              <?php $grants = $step['ability'] === null
                  ? [(string) $step['needs']]
                  : Plans::plansWith((string) $step['ability']); ?>
              <span class="gs__tag gs__tag--lock">
                <?php foreach ($grants as $i => $planKey): ?>
                  <?= $i > 0 ? ' or ' : '' ?>
                  <?= View::e(Plans::name($planKey)) ?>
                  <?= View::e(Plans::money($planKey)) ?>/month
                <?php endforeach; ?>
              </span>
            <?php elseif (!$step['checkable']): ?>
              <?php /* Said out loud rather than quietly left unticked. The
                       snippet is served from here but nothing records who
                       fetched it, and a tick we guessed at would tell somebody
                       their site is live when it is not. */ ?>
              <span class="gs__tag gs__tag--soft">We cannot check this one</span>
            <?php endif; ?>
          </h3>

          <?php /* Only the live step carries its explanation. Five blurbs at
                   once is a page nobody reads. */ ?>
          <?php if ($isNext || $step['locked']): ?>
            <p class="gs__what"><?= View::e($step['what']) ?></p>
          <?php endif; ?>

          <?php if ($step['locked'] && $step['needs'] !== null): ?>
            <?php $grants = $step['ability'] === null
                ? [(string) $step['needs']]
                : Plans::plansWith((string) $step['ability']); ?>
            <a class="btn btn--sm" href="/members#plans">
              Upgrade to
              <?= View::e(implode(' or ', array_map(
                  static fn (string $k): string => Plans::name($k),
                  $grants,
              ))) ?>
            </a>
          <?php elseif ($isNext): ?>
            <a class="btn btn--primary btn--sm" href="<?= View::e($step['href']) ?>">
              <?= View::e($step['cta']) ?>
            </a>
          <?php elseif (!$step['done']): ?>
            <a class="gs__quiet" href="<?= View::e($step['href']) ?>"><?= View::e($step['cta']) ?></a>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
