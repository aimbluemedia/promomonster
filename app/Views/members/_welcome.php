<?php
use App\Support\Auth;
use App\Support\Plans;
use App\Support\ToutifyAuth;
use App\Support\View;
/**
 * The band across the top of the dashboard.
 *
 * It answers the three questions somebody has on arriving -- whose account is
 * this, what am I on, and how much of it is set up -- before they have to read
 * anything. The old dashboard opened with a price list, then a heading a third
 * of the way down.
 *
 * Every figure here is read, never assumed. The membership line only appears
 * for an account that really came through Toutify, because telling a
 * direct-signup member their PromoMonster is "included with your Toutify
 * membership" would be a sentence about somebody else's account.
 *
 * @var array  $account
 * @var array  $locations
 * @var ?string $pageUrl
 */
$me    = Auth::user() ?? [];
$plan  = (string) ($account['plan'] ?? Plans::FREE);
$first = trim((string) ($me['first_name'] ?? ''));

// Read from the session, not from the user row.
//
// The first version of this line checked $me['toutify_sub'], which would have
// been false for everybody forever: Auth::user() selects a fixed column list
// and toutify_sub is not in it. Adding it there would mean a SELECT naming a
// column that does not exist until migration 027 has run -- which is every
// page in the members area down, not one eyebrow missing.
//
// ToutifyController puts the sub in the session when it signs somebody in, so
// the session is both the honest answer to "did this login come through
// Toutify" and the one that needs no query and no migration.
$toutify    = $_SESSION['toutify'] ?? [];
$viaToutify = is_array($toutify)
    && is_string($toutify['sub'] ?? null)
    && $toutify['sub'] !== '';

$pills = [
    ['Plan', Plans::name($plan)],
    ['Businesses', (string) count($locations ?? [])],
    ['Review page', ($pageUrl ?? null) !== null ? 'Live' : 'Not set up'],
];
?>
<section class="hello">
  <div class="hello__text">
    <p class="hello__eyebrow"><?= $viaToutify ? 'Toutify member' : 'Your account' ?></p>
    <h1 class="hello__h">
      <?= $first !== '' ? 'Welcome back, ' . View::e($first) : 'Welcome back' ?>
    </h1>
    <p class="hello__sub">
      <?php if (($account['name'] ?? '') !== ''): ?>
        <strong><?= View::e((string) $account['name']) ?></strong>
      <?php endif; ?>
      <?php if ($viaToutify): ?>
        &middot; PromoMonster is included with your Toutify membership.
      <?php endif; ?>
    </p>

    <ul class="hello__pills">
      <?php foreach ($pills as [$label, $value]): ?>
        <li><span><?= View::e($label) ?></span><strong><?= View::e($value) ?></strong></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="hello__acts">
    <?php if (Plans::above($plan) !== []): ?>
      <a class="hello__btn hello__btn--solid" href="/members#plans">Upgrade my plan &rarr;</a>
    <?php endif; ?>
    <?php /* Toutify, from the band, either way round.

             Already signed in through Toutify: straight out to the issuer's
             own origin, in a new tab. Never a guessed path -- "/dashboard" on
             somebody else's site is a 404 that only shows up after it ships.

             Signed in some other way: the sign-in route, in THIS tab. An
             authorisation redirect opened in a new tab loses the session
             cookie the callback needs on some browsers, and leaves the
             original tab sitting on a stale page either way.

             Nothing at all when Toutify is not configured: /auth/toutify/login
             with no issuer and no client secret is an error page, and a button
             that leads to one is worse than no button. */ ?>
    <?php if (ToutifyAuth::configured()): ?>
      <?php if ($viaToutify): ?>
        <?php /* Straight to Toutify's own members area.

                 They are already signed in there: that is where they signed
                 in. Toutify set its own session cookie during the login that
                 brought them here, and this link rides it.

                 PromoMonster cannot sign anybody in to Toutify, and it is
                 worth being clear about why rather than leaving somebody to
                 wonder. Toutify is the identity provider and this is the
                 client -- it tells us who somebody is, and there is no
                 direction in OpenID Connect that goes the other way. If their
                 Toutify session has expired, Toutify's own page asks them to
                 sign in, which is Toutify's to handle and the correct thing
                 for it to do. */ ?>
        <a class="hello__btn" href="<?= View::e(ToutifyAuth::membersUrl()) ?>"
           target="_blank" rel="noopener">Toutify members &#8599;</a>
      <?php else: ?>
        <a class="hello__btn" href="/auth/toutify/login">Sign in to Toutify</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
