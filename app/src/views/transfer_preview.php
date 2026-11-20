<p><a class="link back" href="<?= url('/transfer') ?>#import">&larr; Import and export</a></p>
<h1 class="page-title">Ready to import</h1>
<p class="subtle">From <?= e($source) ?>. Nothing has been added yet.</p>
<section class="card write xfer">
  <div class="pv-stats">
    <div><b><?= count($entries) ?></b><span><?= count($entries) === 1 ? 'entry' : 'entries' ?></span></div>
    <?php if ($photos): ?><div><b><?= (int)$photos ?></b><span>photos</span></div><?php endif; ?>
    <?php if ($moods): ?><div><b><?= (int)$moods ?></b><span>days of mood</span></div><?php endif; ?>
    <div><b><?= e($from !== '' ? date('M Y', strtotime($from)) : '') ?><?= ($from !== '' && substr($from, 0, 7) !== substr($to, 0, 7)) ? ' to ' . e(date('M Y', strtotime($to))) : '' ?></b><span>dates</span></div>
  </div>
  <?php if ($moods): ?><p class="hint">Moods go into Track as a daily 1 to 5 rating (rad is 5, awful is 1). If a day already has a mood, yours is kept.</p><?php endif; ?>
  <?php if ($noteless): ?><p class="hint"><?= (int)$noteless ?> <?= $noteless === 1 ? 'check-in' : 'check-ins' ?> had no note, so <?= $noteless === 1 ? 'it is' : 'they are' ?> not turned into entries.</p><?php endif; ?>
  <?php if (!empty($otherFiles)): ?><p class="hint"><?= (int)$otherFiles ?> attached <?= $otherFiles === 1 ? 'file' : 'files' ?> (like iPhone HEIC photos, videos or PDFs) can't be brought in yet. The text of those entries is included.</p><?php endif; ?>
  <?php if ($dupes): ?><p class="hint"><?= (int)$dupes ?> of these <?= $dupes === 1 ? 'is' : 'are' ?> already in your journal and will be skipped.</p><?php endif; ?>
  <?php if ($entries): ?><h2 class="section-h">A look at the first few</h2><?php endif; ?>
  <ul class="pv-list">
  <?php foreach (array_slice($entries, 0, 5) as $e): $txt = trim(($e['title'] !== '' ? $e['title'] . '. ' : '') . ($e['paragraphs'][0]['text'] ?? '')); ?>
    <li><time><?= e(date('M j, Y', strtotime($e['date']))) ?></time><span><?= e(mb_substr($txt !== '' ? $txt : 'Photos', 0, 140)) ?></span><?php if ($e['photos']): ?><small><?= count($e['photos']) ?> photo<?= count($e['photos']) === 1 ? '' : 's' ?></small><?php endif; ?></li>
  <?php endforeach; ?>
  </ul>
  <div class="pv-acts">
    <form method="post" action="<?= url('/transfer/import/' . $token) ?>" data-loader="Bringing your entries home" data-detail="Please keep this page open."><?= csrf_field() ?><button class="btn" type="submit"><?= $entries ? 'Import ' . count($entries) . (count($entries) === 1 ? ' entry' : ' entries') : 'Import moods' ?></button></form>
    <form method="post" action="<?= url('/transfer/cancel/' . $token) ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit">Cancel</button></form>
  </div>
</section>
