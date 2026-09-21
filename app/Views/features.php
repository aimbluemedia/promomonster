<?php use App\Support\Icon; use App\Support\Plans; use App\Support\View;
$groups = [
  ['Collect', [
    ['users','Add customers','Import a CSV, paste a list, or add one after each job. Phone, email, or both.','soon'],
    ['send','Automatic review requests','Requests go out on the schedule your playbook recommends, not whenever someone remembers.','soon'],
    ['message','Your own wording','Start from a template written for your trade, then make it sound like you.','soon'],
    ['qr','QR codes','On the invoice, the receipt, the counter, the van. For customers who never open a text.','now'],
  ]],
  ['Follow through', [
    ['list','Request tracking','Who was asked, when, what happened. Sent, delivered, clicked, reviewed.','soon'],
    ['clock','Reminders','One reminder, timed to your vertical, and never a third message.','soon'],
    ['bell','One reminder','Three days later, once, and never a third message.','soon'],
    ['sparkle','AI-assisted replies','Paste a review you have been left and get a reply in your voice to post. You read it first, and we never post for you.','soon'],
  ]],
  ['Put it to work', [
    ['chart','Click tracking','Who opened the link, and when. Your own data, in your own account.','soon'],
    ['pin','Multi-location','Every location scored separately, and rolled up for whoever owns them all.','soon'],
    ['share','Import from a spreadsheet','Bring a customer list in as a CSV instead of typing it out.','soon'],
    ['megaphone','Promotion network','Put your best content in front of more people. A separate second product, once the first one is working.','soon'],
  ]],
];
// The same two words the pricing page uses, from the same place, so the
// two pages can never disagree about what "In build" is called.
$tagLabel = Plans::STATE_LABELS;
?>
<section class="section">
  <div class="container">
    <div class="prose">
      <p class="eyebrow">Features</p>
      <h1>Everything the reputation side of a local business needs</h1>
      <p class="lede">Collect reviews, respond to them, and use them. Nothing
        here requires you to bend a platform rule.</p>
      <div class="notice" style="margin-top:2rem;">
        <strong>Email, and nothing that needs a platform's permission.</strong>
        <p>PromoMonster asks by email and links to your own Google review page.
          There is no SMS, because texting review requests means registering
          every business with the mobile carriers at a real monthly cost, and
          no review monitoring or website widget, because those need Google
          Business Profile API access we do not have. We would rather ship a
          smaller thing that works.</p>
      </div>
      <div class="notice" style="margin-top:1rem;">
        <strong>PromoMonster is in early access.</strong>
        <p>Review audits, your vertical playbook and QR setup are delivered by
          hand today, while the platform is built. Everything marked
          <span class="tag tag--soon" style="margin-left:0;">In build</span> is
          on the roadmap and not available yet. We would rather tell you that
          than let you find out after paying.</p>
      </div>
    </div>
  </div>
</section>

<?php foreach ($groups as $i => [$heading, $items]): ?>
<section class="section<?= $i % 2 === 0 ? ' section--wash' : '' ?>"<?= $i === 0 ? ' style="padding-top:0;"' : '' ?>>
  <div class="container">
    <h2><?= View::e($heading) ?></h2>
    <div class="grid grid--4" style="margin-top:2rem;">
      <?php foreach ($items as [$icon,$title,$body,$state]): ?>
        <div class="card feature">
          <?= Icon::chip($icon) ?>
          <h3><?= View::e($title) ?><span class="tag tag--<?= View::e($state) ?>"><?= View::e($tagLabel[$state]) ?></span></h3>
          <p><?= View::e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<section class="section">
  <div class="container center">
    <h2>Start with the audit</h2>
    <p class="lede">It takes a minute and it tells you which of these you
      actually need first.</p>
    <div class="btn-row" style="justify-content:center;">
      <a class="btn btn--primary" href="/audit">Get your free review audit</a>
    </div>
  </div>
</section>
