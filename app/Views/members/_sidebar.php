<?php
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Plans;
use App\Support\SendLimit;
use App\Support\View;
/**
 * The members sidebar.
 *
 * The nav moved from a row above the page to a column beside it for one
 * reason: eight links in a row that scrolls sideways means the last three are
 * off the edge of a phone and behind a drag on a laptop. A column shows all
 * eight at once, with room for the icon and the grouping that tells somebody
 * which half of the product they are in.
 *
 * Grouped the way an owner thinks rather than the way the app is built -- the
 * screens opened weekly first, the account ones after. The allowance card near
 * the foot is the only piece of selling on the page, and it sits where it gets
 * read rather than where it interrupts.
 *
 * @var array  $account
 * @var string $path     the current path, for marking the active link
 */
$me   = Auth::user() ?? [];
$plan = (string) ($account['plan'] ?? Plans::FREE);

// Two counts, from the same source the sender enforces. A sidebar that said
// "Free plan" and nothing else would be a label; "3 of 5 used" is a reason to
// do something, and it is the number that actually stops a send.
$limit = SendLimit::check((int) ($account['id'] ?? 0), $plan);
$cap   = $limit['month_limit'];
$used  = (int) $limit['month_used'];
$pct   = ($cap === null || $cap === 0) ? 0 : min(100, (int) round($used / $cap * 100));

$name = trim(((string) ($me['first_name'] ?? '')) . ' ' . ((string) ($me['last_name'] ?? '')));
$who  = $name !== '' ? $name : (string) ($account['name'] ?? 'Your account');
?>
<aside class="side" aria-label="Members menu">
  <div class="side__brand">
    <?= View::render('partials/logo', ['href' => '/members', 'size' => 28]) ?>
  </div>

  <?php /* One primary action, and it is the one the product is for. Every
           other link on this page is a way of getting back to it. */ ?>
  <a class="side__cta" href="/members/requests">
    <?= Icon::render('send') ?>
    <span>Ask for a review</span>
  </a>

  <?php
    $groups = [
      'Reviews' => [
        '/members'                      => ['layout',  'Dashboard'],
        '/members/requests'             => ['send',    'Review requests'],
        '/members/reviews'              => ['star',    'Google reviews'],
        '/members/promomonster-reviews' => ['sparkle', 'Your review page'],
        '/members/templates'            => ['message', 'Email templates'],
        '/members/playbook'             => ['list',    'Your playbook'],
      ],
      'Account' => [
        '/members/plan'     => ['chart',  'My plan'],
        '/members/profile'  => ['users',  'Your details'],
        '/members/settings' => ['shield', 'Settings'],
      ],
    ];
  ?>

  <div class="side__scroll">
    <?php foreach ($groups as $heading => $links): ?>
      <p class="side__group"><?= View::e($heading) ?></p>
      <nav class="side__nav" aria-label="<?= View::e($heading) ?>">
        <?php foreach ($links as $href => [$icon, $label]): ?>
          <?php
            // Exact match only. A "starts with" test would light Dashboard up
            // on every screen, because every one of these paths begins with
            // /members.
            $here = $path === $href;
          ?>
          <a href="<?= $href ?>" class="side__link<?= $here ? ' is-here' : '' ?>"
             <?= $here ? 'aria-current="page"' : '' ?>>
            <?= Icon::render($icon) ?>
            <span><?= View::e($label) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endforeach; ?>
  </div>

  <div class="side__plan">
    <div class="side__planhead">
      <strong><?= View::e(Plans::name($plan)) ?></strong>
      <span><?= $cap === null ? 'Unlimited' : $used . ' / ' . (int) $cap ?></span>
    </div>
    <?php if ($cap !== null): ?>
      <div class="side__meter" role="progressbar" aria-valuenow="<?= $pct ?>"
           aria-valuemin="0" aria-valuemax="100"
           aria-label="Review requests used this month">
        <span style="width:<?= $pct ?>%"></span>
      </div>
    <?php endif; ?>
    <p>Review requests this month</p>
    <?php /* Only when there is something to move up to. "Upgrade" on the
             dearest plan is a dead link that makes the page look unfinished. */ ?>
    <?php if (Plans::above($plan) !== []): ?>
      <a href="/members/plan">Compare the plans &rarr;</a>
    <?php endif; ?>
  </div>

  <?php /* Who is signed in, and the way out. At the foot because it is what
           you look for last and least. */ ?>
  <div class="side__me">
    <span class="side__avatar" aria-hidden="true"><?= View::e(mb_strtoupper(mb_substr(
        $name !== '' ? $name : (string) ($me['email'] ?? '?'), 0, 1,
    ))) ?></span>
    <span class="side__who">
      <strong><?= View::e($who) ?></strong>
      <span><?= View::e((string) ($me['email'] ?? '')) ?></span>
    </span>
    <form method="post" action="/members/logout">
      <?= Csrf::field() ?>
      <button type="submit">Sign out</button>
    </form>
  </div>
</aside>
