<?php use App\Support\Icon; use App\Support\View;
$groups = [
  ['Collect', [
    ['users','Add customers','Import a CSV, paste a list, or add one after each job. Phone, email, or both.'],
    ['send','Automatic review requests','Requests go out on the schedule your playbook recommends, not whenever someone remembers.'],
    ['message','SMS and email','Text gets the response; email gets the considered ones. Use either, or both.'],
    ['qr','QR codes','On the invoice, the receipt, the counter, the van. For customers who never open a text.'],
  ]],
  ['Follow through', [
    ['list','Request tracking','Who was asked, when, what happened. Sent, delivered, clicked, reviewed.'],
    ['clock','Reminders','One reminder, timed to your vertical, and never a third message.'],
    ['bell','Review monitoring','New reviews appear as they land, so nothing sits unanswered for a week.'],
    ['sparkle','AI-assisted replies','A drafted reply in your voice for every review. You read it and send it — we never post for you.'],
  ]],
  ['Put it to work', [
    ['layout','Website widget','Real reviews on your own site, pulled live, never edited or cherry-picked.'],
    ['pin','Multi-location','Every location scored separately, and rolled up for whoever owns them all.'],
    ['share','Social content from reviews','Turn a genuine review into a post, with attribution and nothing invented.'],
    ['megaphone','Promotion network','Later: put your best content in front of more people. A second product, once the first is working.'],
  ]],
];
?>
<section class="section">
  <div class="container">
    <div class="prose">
      <p class="eyebrow">Features</p>
      <h1>Everything the reputation side of a local business needs</h1>
      <p class="lede">Collect reviews, respond to them, and use them. Nothing
        here requires you to bend a platform rule.</p>
    </div>
  </div>
</section>

<?php foreach ($groups as $i => [$heading, $items]): ?>
<section class="section<?= $i % 2 === 0 ? ' section--wash' : '' ?>"<?= $i === 0 ? ' style="padding-top:0;"' : '' ?>>
  <div class="container">
    <h2><?= View::e($heading) ?></h2>
    <div class="grid grid--4" style="margin-top:2rem;">
      <?php foreach ($items as [$icon,$title,$body]): ?>
        <div class="card feature">
          <?= Icon::chip($icon) ?>
          <h3><?= View::e($title) ?></h3>
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
