<?php
use App\Support\Csrf;
use App\Support\EmailTemplates;
use App\Support\View;
/**
 * @var array $account @var bool $ready @var array $sets
 * @var ?array $editing @var ?string $error
 */
$editingId   = $editing === null ? 0 : (int) $editing['id'];
$editingKind = $editing === null ? 'request' : (string) $editing['kind'];
$isCopy      = $editing !== null && (int) $editing['is_system'] === 1;
?>
<div class="admin-title">
  <h1>Email templates</h1>
</div>

<?php if ($error !== null): ?>
  <div class="alert" role="alert" style="margin-bottom:1.5rem;"><?= View::e($error) ?></div>
<?php endif; ?>

<?php if (!$ready): ?>
  <div class="notice" style="margin-bottom:1.5rem;border-left-color:var(--star);">
    <strong>Email templates are not switched on yet.</strong>
    <p>The database has not been updated for this yet. Everything still sends in
      the standard wording in the meantime &mdash; nothing is broken and nothing
      is queued up waiting.</p>
  </div>
<?php else: ?>

<?php /* The rules first, because they are not ours and cannot be worked around
         by writing the email differently. A business that reads this after
         writing a template that breaks them has wasted the effort. */ ?>
<div class="notice" style="margin-bottom:1.5rem;">
  <strong>Two things to keep on the right side of.</strong>
  <p style="margin:.45rem 0 0;">
    Do not offer anything in return for a review &mdash; no discount, no entry
    into a draw, nothing. Google removes reviews collected that way and it
    breaks US law besides. And ask <em>everybody</em>, not just the customers
    you expect to be pleased: picking who gets asked is review gating, which
    the FTC banned outright in 2024. Honest wording, sent to everyone, is also
    the version that works.
  </p>
</div>

<?php /* ---- The form ------------------------------------------------------ */ ?>
<div class="card" style="margin-bottom:1.5rem;">
  <div class="step-head">
    <span class="step-head__n"><?= $editingId > 0 && !$isCopy ? 'E' : '+' ?></span>
    <div>
      <h2 style="font-size:1.05rem;margin:0;">
        <?php if ($isCopy): ?>
          Start from &ldquo;<?= View::e((string) $editing['name']) ?>&rdquo;
        <?php elseif ($editingId > 0): ?>
          Edit &ldquo;<?= View::e((string) $editing['name']) ?>&rdquo;
        <?php else: ?>
          Write a template
        <?php endif; ?>
      </h2>
      <p class="muted" style="margin:.2rem 0 0;font-size:.9rem;">
        <?= $isCopy
          ? 'This is the wording we ship with. Saving makes a copy of your own — the original stays as it is.'
          : 'Plain text, the way you would actually type it to somebody. No formatting, no images.' ?>
      </p>
    </div>
  </div>

  <form class="form" method="post" action="/members/templates/save" style="margin-top:1.25rem;">
    <?= Csrf::field() ?>
    <?php /* A system row's id is deliberately not carried: save() would refuse
             to edit it anyway, and sending 0 makes the copy explicit. */ ?>
    <input type="hidden" name="id" value="<?= $isCopy ? 0 : $editingId ?>">

    <div class="form__row form__row--2">
      <div>
        <label for="name">Template name</label>
        <input class="field" id="name" name="name" type="text" required maxlength="120"
               value="<?= View::e($editing === null ? '' : ($isCopy ? 'My ' . mb_strtolower((string) $editing['name']) : (string) $editing['name'])) ?>"
               placeholder="After a pool clean" autocomplete="off">
        <p class="form__note">Only you see this. It is how you pick it later.</p>
      </div>
      <div>
        <label for="kind">When it is sent</label>
        <select class="field" id="kind" name="kind">
          <?php foreach (EmailTemplates::KINDS as $value => $label): ?>
            <option value="<?= View::e($value) ?>"<?= $editingKind === $value ? ' selected' : '' ?>>
              <?= View::e($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="form__note">The reminder goes out once, three days later.</p>
      </div>
    </div>

    <div>
      <label for="subject">Subject line</label>
      <input class="field" id="subject" name="subject" type="text" required maxlength="200"
             value="<?= View::e((string) ($editing['subject'] ?? '')) ?>"
             placeholder="How did we do, {{first_name}}?" autocomplete="off">
    </div>

    <div>
      <label for="body">Message</label>
      <textarea class="field" id="body" name="body" rows="14" required
                maxlength="<?= EmailTemplates::MAX_BODY ?>"
                style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.92rem;line-height:1.6;"
                placeholder="Hi {{first_name}},&#10;&#10;Thanks for choosing {{business_name}}..."><?= View::e((string) ($editing['body'] ?? '')) ?></textarea>
      <p class="form__note">
        We add your business name, postal address and an unsubscribe link to the
        bottom of every message. That is a legal requirement, so it is not
        something you need to write in &mdash; or can leave out.
      </p>
    </div>

    <?php /* ---- The merge fields, listed rather than described ------------- */ ?>
    <fieldset style="border:1px solid var(--line);border-radius:10px;padding:1rem;margin:0;">
      <legend style="font-size:.9rem;font-weight:600;padding:0 .4rem;">
        Type these in and we fill them in
      </legend>
      <dl class="merge-fields">
        <?php foreach (EmailTemplates::FIELDS as $field => $what): ?>
          <dt><code>{{<?= View::e($field) ?>}}</code></dt>
          <dd><?= View::e($what) ?></dd>
        <?php endforeach; ?>
      </dl>
      <p class="form__note" style="margin-top:.5rem;">
        Anything else in double braces is a typo, and we will say so rather than
        send an email with a gap in it.
      </p>
    </fieldset>

    <label class="checkline">
      <input type="checkbox" name="make_default" value="1"
             <?= $editing !== null && (int) ($editing['is_default'] ?? 0) === 1 ? 'checked' : '' ?>>
      <span>Use this one by default</span>
    </label>

    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
      <button class="btn btn--primary" type="submit">
        <?= $editingId > 0 && !$isCopy ? 'Save changes' : 'Save template' ?>
      </button>
      <?php if ($editingId > 0): ?>
        <a class="btn" href="/members/templates">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php /* ---- What exists, by kind ------------------------------------------ */ ?>
<?php foreach ($sets as $kind => $set): ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:1.05rem;margin:0 0 .3rem;"><?= View::e($set['label']) ?></h2>
    <p class="muted" style="margin:0 0 1.1rem;font-size:.9rem;">
      <?= $kind === 'request'
        ? 'Sent the moment you ask. The one marked default is what the send form starts on.'
        : 'Sent once, three days after the request, and only if they have not been back.' ?>
    </p>

    <?php foreach ($set['rows'] as $row): ?>
      <?php
        $id        = (int) $row['id'];
        $system    = (int) $row['is_system'] === 1;
        $isDefault = $set['defaultId'] === $id;
        $preview   = EmailTemplates::preview($row);
      ?>
      <div class="tmpl<?= $isDefault ? ' tmpl--default' : '' ?>">
        <div class="tmpl__head">
          <strong><?= View::e((string) $row['name']) ?></strong>
          <?php if ($isDefault): ?><span class="tmpl__tag">Default</span><?php endif; ?>
          <?php if ($system): ?><span class="tmpl__tag tmpl__tag--muted">Ours</span><?php endif; ?>
        </div>
        <p class="tmpl__subject"><?= View::e($preview['subject']) ?></p>

        <details>
          <summary>Read it as a customer would</summary>
          <?php /* Merged with sample values by the same renderer the sender
                   uses, so what is on screen is what goes out. */ ?>
          <pre class="tmpl__body"><?= View::e($preview['body']) ?></pre>
        </details>

        <div class="tmpl__actions">
          <a class="btn btn--sm" href="/members/templates?edit=<?= $id ?>">
            <?= $system ? 'Start from this' : 'Edit' ?>
          </a>

          <?php if (!$isDefault && !$system): ?>
            <form method="post" action="/members/templates/default" style="display:inline;">
              <?= Csrf::field() ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <button class="btn btn--sm" type="submit">Make default</button>
            </form>
          <?php elseif (!$isDefault && $system): ?>
            <?php /* Choosing the stock wording is clearing the account's flag,
                     not setting one on a row shared with every other account. */ ?>
            <form method="post" action="/members/templates/default" style="display:inline;">
              <?= Csrf::field() ?>
              <input type="hidden" name="id" value="0">
              <input type="hidden" name="kind" value="<?= View::e((string) $kind) ?>">
              <button class="btn btn--sm" type="submit">Make default</button>
            </form>
          <?php endif; ?>

          <?php if (!$system): ?>
            <form method="post" action="/members/templates/delete" style="display:inline;"
                  onsubmit="return confirm('Delete this template? Anything already queued will go out in your default wording.');">
              <?= Csrf::field() ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <button class="btn btn--sm btn--quiet" type="submit">Delete</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php endif; ?>
