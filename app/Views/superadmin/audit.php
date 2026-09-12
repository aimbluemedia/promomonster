<?php use App\Support\Csrf; use App\Support\View;
/** @var array $audit @var ?array $results @var bool $aiReady @var array $statuses */
$id = (int) $audit['id'];

/** One business block in the comparison form. */
function businessFields(string $prefix, string $label, string $name = '', bool $required = false): void
{ ?>
  <fieldset class="compare-block">
    <legend><?= View::e($label) ?></legend>
    <div class="form__row form__row--2">
      <div>
        <label for="<?= $prefix ?>_name">Business name</label>
        <input class="field" id="<?= $prefix ?>_name" name="<?= $prefix ?>_name" type="text"
               value="<?= View::e($name) ?>" <?= $required ? 'required' : '' ?>
               placeholder="<?= $required ? 'The business being audited' : 'Leave blank to skip' ?>">
      </div>
      <div class="form__row form__row--2">
        <div>
          <label for="<?= $prefix ?>_rating">Rating</label>
          <input class="field" id="<?= $prefix ?>_rating" name="<?= $prefix ?>_rating"
                 type="number" step="0.1" min="1" max="5" placeholder="4.2">
        </div>
        <div>
          <label for="<?= $prefix ?>_count">Reviews</label>
          <input class="field" id="<?= $prefix ?>_count" name="<?= $prefix ?>_count"
                 type="number" min="0" placeholder="47">
        </div>
      </div>
    </div>
    <div style="margin-top:.8rem;">
      <label for="<?= $prefix ?>_reviews">Review text &mdash; paste from the public profile,
        <strong>one blank line between each review</strong></label>
      <textarea class="field" id="<?= $prefix ?>_reviews" name="<?= $prefix ?>_reviews" rows="5"
                placeholder="Turned up on time and left the place spotless.&#10;&#10;Quoted fairly, no surprises on the invoice."></textarea>
    </div>
  </fieldset>
<?php }
?>

<div class="admin-title">
  <h1>Audit #<?= $id ?></h1>
  <a href="/superadmin/audits" style="font-size:.9rem;color:var(--brand);">&larr; All audit requests</a>
</div>

<div class="table-wrap" style="margin-bottom:1.5rem;">
  <table class="data">
    <tbody>
      <tr><th style="width:12rem;">Business</th><td><strong><?= View::e((string) $audit['business_name']) ?></strong></td></tr>
      <tr><th>Email</th><td class="mono"><?= View::e((string) $audit['email']) ?></td></tr>
      <tr><th>Vertical</th><td><?= View::e((string) ($audit['vertical'] ?? '—')) ?></td></tr>
      <tr><th>Status</th><td><?= View::e(ucfirst(str_replace('_', ' ', (string) $audit['status']))) ?></td></tr>
      <tr><th>Requested</th><td class="mono"><?= View::e((string) $audit['created_at']) ?></td></tr>
    </tbody>
  </table>
</div>

<?php if ($results !== null): ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:1.05rem;margin-bottom:.25rem;">Comparison</h2>
    <p class="muted" style="font-size:.8rem;margin:0 0 1.25rem;">
      <?= View::e((string) ($results['_meta']['model'] ?? 'Claude')) ?> &middot;
      <?= (int) ($results['_meta']['reviews_seen'] ?? 0) ?> reviews read &middot;
      <?= View::e((string) ($results['_meta']['generated_at'] ?? '')) ?>
    </p>

    <p style="font-size:1.15rem;font-weight:700;color:var(--ink);margin:0 0 1rem;">
      <?= View::e((string) ($results['headline'] ?? '')) ?>
    </p>

    <dl class="calc__rows" style="border-top:0;padding-top:0;margin-top:0;">
      <div class="calc__row"><dt>Standing</dt>
        <dd><?= View::e(ucfirst(str_replace('_', ' ', (string) ($results['standing'] ?? '—')))) ?></dd></div>
      <div class="calc__row"><dt>Rating and volume</dt>
        <dd style="font-weight:400;text-align:left;max-width:32rem;"><?= View::e((string) ($results['rating_gap'] ?? '')) ?></dd></div>
      <?php if (($results['five_star_reviews_needed'] ?? null) !== null): ?>
        <div class="calc__row"><dt>Five-star reviews to match the leader</dt>
          <dd><?= (int) $results['five_star_reviews_needed'] ?></dd></div>
      <?php endif; ?>
    </dl>

    <?php foreach ([
      'their_strengths'      => 'What their own reviewers praise',
      'competitor_strengths' => 'What competitors are praised for and they are not',
      'weaknesses'           => 'Complaints and gaps',
    ] as $key => $heading): ?>
      <?php if (!empty($results[$key])): ?>
        <h3 style="font-size:.95rem;margin:1.5rem 0 .6rem;"><?= View::e($heading) ?></h3>
        <ul class="checklist">
          <?php foreach ($results[$key] as $item): ?>
            <li><?= View::e((string) $item) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if (!empty($results['actions'])): ?>
      <h3 style="font-size:.95rem;margin:1.5rem 0 .6rem;">Recommended next steps</h3>
      <ol style="margin:0;padding-left:1.2rem;display:grid;gap:.7rem;">
        <?php foreach ($results['actions'] as $action): ?>
          <li>
            <strong><?= View::e((string) ($action['action'] ?? '')) ?></strong>
            <span class="muted" style="display:block;font-size:.88rem;"><?= View::e((string) ($action['why'] ?? '')) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>

    <?php if (!empty($results['data_limits'])): ?>
      <p class="form__note" style="margin-top:1.5rem;">
        <strong>What this could not see:</strong> <?= View::e((string) $results['data_limits']) ?>
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2 style="font-size:1.05rem;margin-bottom:.25rem;">
    <?= $results !== null ? 'Run it again' : 'Compare against competitors' ?>
  </h2>
  <p class="muted" style="font-size:.88rem;margin:0 0 1.5rem;">
    Paste what is on each public profile. Ratings and review counts are exact;
    the review text is a sample, and the report says so.
  </p>

  <?php if (!$aiReady): ?>
    <div class="notice" style="margin-bottom:1.25rem;">
      <strong>No Anthropic API key configured.</strong>
      <p>Add <code>'anthropic' =&gt; ['api_key' =&gt; '...']</code> to
        <code>app/config.php</code> and this form will work.</p>
    </div>
  <?php endif; ?>

  <form class="form" method="post" action="/superadmin/audits/compare">
    <?= Csrf::field() ?>
    <input type="hidden" name="audit_id" value="<?= $id ?>">

    <?php businessFields('subject', 'The business being audited', (string) $audit['business_name'], true); ?>
    <?php businessFields('c1', 'Competitor 1'); ?>
    <?php businessFields('c2', 'Competitor 2 (optional)'); ?>
    <?php businessFields('c3', 'Competitor 3 (optional)'); ?>

    <button class="btn btn--primary" type="submit" <?= $aiReady ? '' : 'disabled' ?>>
      Run the comparison
    </button>
    <p class="form__note">Takes 20&ndash;60 seconds. Roughly $0.05&ndash;$0.15 of API usage per audit.</p>
  </form>
</div>
