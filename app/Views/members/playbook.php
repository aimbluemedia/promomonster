<?php use App\Support\ReviewPlaces; use App\Support\Trades; use App\Support\View;
/** @var ?array $playbook @var string $trade */
$trade = $trade ?? '';
?>
<div class="admin-title"><h1>Your playbook</h1></div>
<p class="muted" style="font-size:.94rem;max-width:44rem;margin:0 0 1.5rem;">
  The moment to ask, who should ask, and what they should say. Getting the
  verbal ask right lifts response more than any software setting.</p>

<?php if ($playbook === null): ?>
  <?php /* Two different situations, and they used to read as one. "Once we
           know what kind of business you run" is fair when nobody has told us
           and is a lie when somebody just did -- and a member who sets their
           trade and sees the same sentence concludes the form did not save. */ ?>
  <div class="table-wrap"><p class="empty">
    <?php if ($trade === ''): ?>
      Tell us what kind of business you run and we will put your playbook
      together. <a href="/members/settings">Set your trade</a>.
    <?php else: ?>
      You are down as <strong><?= View::e(Trades::label($trade)) ?></strong>.
      Your playbook for that trade is still being written &mdash; we are doing
      them by hand, one trade at a time. Everything below applies whatever you
      do, so it is worth reading in the meantime.
    <?php endif; ?>
  </p></div>
<?php else: ?>
  <div class="card">
    <h2 style="font-size:1.15rem;margin-bottom:1rem;"><?= View::e($playbook['display_name']) ?></h2>
    <ul class="checklist">
      <li><strong>Ask at</strong> <?= View::e($playbook['trigger_moment']) ?></li>
      <li><strong>Who asks</strong> <?= View::e($playbook['who_asks']) ?></li>
      <li><strong>Channel</strong> <?= View::e(strtoupper((string) $playbook['primary_channel'])) ?></li>
      <?php /* "Reminder ... days later" read as a schedule. Nothing here is
               scheduled: the chase is a button on the request, available once
               a day has passed. This is advice about when to press it. */ ?>
      <?php if ($playbook['follow_up_days']): ?>
        <li><strong>Chase once</strong> about
          <?= (int) $playbook['follow_up_days'] ?>
          <?= (int) $playbook['follow_up_days'] === 1 ? 'day' : 'days' ?> later,
          then stop</li>
      <?php endif; ?>
      <?php if ($playbook['offline_method']): ?>
        <li><strong>Offline</strong> <?= View::e($playbook['offline_method']) ?></li>
      <?php endif; ?>
    </ul>
    <p style="margin:1.4rem 0 0;padding-top:1.1rem;border-top:1px solid var(--line);font-size:.94rem;color:var(--body);">
      <strong style="color:var(--ink);">They say:</strong>
      &ldquo;<?= View::e($playbook['verbal_ask']) ?>&rdquo;</p>
    <?php /* The objection column has been in the schema since migration 009
             and nothing has ever shown it. It is the most useful line on the
             card: what the customer says back, and what to say to that. */ ?>
    <?php if (!empty($playbook['objection'])): ?>
      <p style="margin:1rem 0 0;font-size:.94rem;color:var(--body);">
        <strong style="color:var(--ink);">When they hesitate:</strong>
        <?= View::e((string) $playbook['objection']) ?></p>
    <?php endif; ?>
    <?php if ($playbook['compliance_note']): ?>
      <p class="muted" style="margin-top:1rem;font-size:.88rem;"><?= View::e($playbook['compliance_note']) ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php /* ---- How to ask ---------------------------------------------------- */ ?>
<?php /* Outside the block above on purpose. A per-trade playbook is written
         during setup and most accounts do not have one, so everything
         conditional on it is a page that reads as empty. These two sections
         are the same for everybody and are the reason to open this screen. */ ?>
<h2 style="font-size:1.15rem;margin:2.25rem 0 .4rem;">The ten that matter</h2>
<p class="muted" style="font-size:.92rem;max-width:44rem;margin:0 0 1.25rem;">
  In order of how much they change the outcome. The first three cost nothing
  and do most of the work; the rest are about not undoing them.
</p>

<ol class="dos">
  <?php foreach (ReviewPlaces::PRACTICES as $i => $practice): ?>
    <li class="dos__item">
      <span class="dos__n"><?= $i + 1 ?></span>
      <div>
        <strong class="dos__do"><?= View::e($practice['do']) ?></strong>
        <p class="dos__why"><?= View::e($practice['why']) ?></p>
      </div>
    </li>
  <?php endforeach; ?>
</ol>

<?php /* ---- Where to ask -------------------------------------------------- */ ?>
<h2 style="font-size:1.15rem;margin:2.5rem 0 .4rem;">Where else to be</h2>
<p class="muted" style="font-size:.92rem;max-width:44rem;margin:0 0 1.25rem;">
  <?= ReviewPlaces::count() ?> places a business can collect reviews, grouped by
  trade. You do not want all of them &mdash; being properly present on three
  beats being thinly present on fifteen. Find your row, pick one or two beyond
  Google, and ask for those the same way.
</p>

<?php foreach (ReviewPlaces::GROUPS as $g => $group): ?>
  <?php /* The first group open, the rest closed. Everybody needs the first one
           and almost nobody needs more than one of the others, so opening them
           all would bury the advice under sixty rows of other people's trades. */ ?>
  <details class="where" <?= $g === 0 ? 'open' : '' ?>>
    <summary class="where__head">
      <span class="where__name"><?= View::e($group['name']) ?></span>
      <span class="where__count"><?= count($group['places']) ?></span>
    </summary>
    <p class="where__note"><?= View::e($group['note']) ?></p>
    <dl class="where__list">
      <?php foreach ($group['places'] as $place): ?>
        <dt><?= View::e($place[0]) ?></dt>
        <dd><?= View::e($place[1]) ?></dd>
      <?php endforeach; ?>
    </dl>
  </details>
<?php endforeach; ?>

<p class="muted" style="font-size:.86rem;max-width:44rem;margin:1.5rem 0 0;">
  No links, deliberately: a list this long goes out of date, and a confident
  link to a domain that has changed hands is worse than none. The names are
  exact enough to search.
</p>
