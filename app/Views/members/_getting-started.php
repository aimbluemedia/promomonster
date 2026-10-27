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
<section class="gs<?= $progress['complete'] ? ' gs--done' : '' ?>">
  <div class="gs__head">
    <div>
      <h2 class="gs__title">
        <?= $progress['complete'] ? 'You are set up' : 'Get started' ?>
      </h2>
      <p class="gs__sub">
        <?php /* Counted, not typed: a Free account has one fewer step than a
                 paid one, and a heading that said "four" over three rows would
                 have somebody hunting for the missing one. */ ?>
        <?= $progress['complete']
            ? 'Everything we can check is done. Keep asking — a few a week, every week.'
            : View::e(ucfirst(Onboarding::inWords((int) $progress['total'])))
              . ' things and you are asking customers for reviews. Most of it is already done for you.' ?>
      </p>
    </div>
    <div class="gs__count">
      <strong><?= (int) $progress['done'] ?></strong> of <?= (int) $progress['total'] ?>
    </div>
  </div>

  <?php /* The bar counts only what can be finished: a step the plan forbids,
           and the one we cannot verify, are both out of the denominator. A bar
           that can never fill teaches people to ignore bars. */ ?>
  <div class="gs__bar" role="progressbar"
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
        <span class="gs__n" aria-hidden="true">
          <?= $step['done'] ? '&#10003;' : ($step['locked'] ? '&#128274;' : (int) $i + 1) ?>
        </span>

        <div class="gs__body">
          <h3 class="gs__h">
            <?= View::e($step['title']) ?>
            <?php if ($step['done']): ?>
              <span class="gs__tag">Done</span>
            <?php elseif ($step['locked'] && $step['needs'] !== null): ?>
              <span class="gs__tag gs__tag--lock">
                <?= View::e(Plans::name((string) $step['needs'])) ?>
                <?= View::e(Plans::money((string) $step['needs'])) ?>/month
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
            <a class="btn btn--sm" href="/members#plans">
              Upgrade to <?= View::e(Plans::name((string) $step['needs'])) ?>
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
