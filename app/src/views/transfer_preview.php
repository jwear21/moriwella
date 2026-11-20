<p><a class="link back" href="<?= url('/transfer') ?>#import">&larr; Import and export</a></p>
<h1 class="page-title">Ready to import</h1>
<p class="subtle">From <?= e($source) ?>. Nothing has been added yet.</p>
<section class="card write xfer">
  <div class="pv-stats">
    <div><b><?= count($entries) ?></b><span><?= count($entries) === 1 ? 'entry' : 'entries' ?></span></div>
    <div><b><?= (int)$photos ?></b><span>photos</span></div>
    <div><b><?= e($from !== '' ? date('M Y', strtotime($from)) : '') ?><?= ($from !== '' && substr($from, 0, 7) !== substr($to, 0, 7)) ? ' to ' . e(date('M Y', strtotime($to))) : '' ?></b><span>dates</span></div>
  </div>
  <?php if ($dupes): ?><p class="hint"><?= (int)$dupes ?> of these <?= $dupes === 1 ? 'is' : 'are' ?> already in your journal and will be skipped.</p><?php endif; ?>
  <h2 class="section-h">A look at the first few</h2>
  <ul class="pv-list">
  <?php foreach (array_slice($entries, 0, 5) as $e): $txt = trim(($e['title'] !== '' ? $e['title'] . '. ' : '') . ($e['paragraphs'][0]['text'] ?? '')); ?>
    <li><time><?= e(date('M j, Y', strtotime($e['date']))) ?></time><span><?= e(mb_substr($txt !== '' ? $txt : 'Photos', 0, 140)) ?></span><?php if ($e['photos']): ?><small><?= count($e['photos']) ?> photo<?= count($e['photos']) === 1 ? '' : 's' ?></small><?php endif; ?></li>
  <?php endforeach; ?>
  </ul>
  <div class="pv-acts">
    <form method="post" action="<?= url('/transfer/import/' . $token) ?>" data-loader="Bringing your entries home" data-detail="Please keep this page open."><?= csrf_field() ?><button class="btn" type="submit">Import <?= count($entries) ?> <?= count($entries) === 1 ? 'entry' : 'entries' ?></button></form>
    <form method="post" action="<?= url('/transfer/cancel/' . $token) ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit">Cancel</button></form>
  </div>
</section>
