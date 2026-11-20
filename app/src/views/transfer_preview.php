<p><a class="link back" href="<?= url('/transfer') ?>#import"><?= t('transfer_preview.import_and_export') ?></a></p>
<h1 class="page-title"><?= t('transfer_preview.ready_to_import') ?></h1>
<p class="subtle"><?= t('transfer_preview.from_source', ['source' => $source]) ?></p>
<section class="card write xfer">
  <div class="pv-stats">
    <div><b><?= count($entries) ?></b><span><?= count($entries) === 1 ? 'entry' : 'entries' ?></span></div>
    <?php if ($photos): ?><div><b><?= (int)$photos ?></b><span><?= $photos === 1 ? 'photo' : 'photos' ?></span></div><?php endif; ?>
    <?php if ($moods): ?><div><b><?= (int)$moods ?></b><span><?= $moods === 1 ? 'mood' : 'moods' ?></span></div><?php endif; ?>
    <div><b><?= e($from !== '' ? ldate('M Y', strtotime($from)) : '') ?><?= ($from !== '' && substr($from, 0, 7) !== substr($to, 0, 7)) ? ' to ' . e(ldate('M Y', strtotime($to))) : '' ?></b><span><?= t('transfer_preview.dates') ?></span></div>
  </div>
  <?php if ($moods): ?><p class="hint"><?= t('transfer_preview.each_mood_keeps_its') ?></p><?php endif; ?>
  <?php if ($noteless): ?><p class="hint"><?= e(tn('transfer_preview.noteless', (int)$noteless)) ?></p><?php endif; ?>
  <?php if (!empty($otherFiles)): ?><p class="hint"><?= (int)$otherFiles ?> attached <?= $otherFiles === 1 ? 'file' : 'files' ?> (like iPhone HEIC photos, videos or PDFs) can't be brought in yet. The text of those entries is included.</p><?php endif; ?>
  <?php if ($dupes): ?><p class="hint"><?= (int)$dupes ?> of these <?= $dupes === 1 ? 'is' : 'are' ?> already in your journal and will be skipped.</p><?php endif; ?>
  <?php if ($entries): ?><h2 class="section-h"><?= t('transfer_preview.a_look_at_the') ?></h2><?php endif; ?>
  <ul class="pv-list">
  <?php foreach (array_slice($entries, 0, 5) as $e): $txt = trim(($e['title'] !== '' ? $e['title'] . '. ' : '') . ($e['paragraphs'][0]['text'] ?? '')); ?>
    <li><time><?= e(ldate('M j, Y', strtotime($e['date']))) ?></time><span><?= e(mb_substr($txt !== '' ? $txt : t('common.photos'), 0, 140)) ?></span><?php if ($e['photos']): ?><small><?= e(tn('msg.n_photos', count($e['photos']))) ?></small><?php endif; ?></li>
  <?php endforeach; ?>
  </ul>
  <div class="pv-acts">
    <form method="post" action="<?= url('/transfer/import/' . $token) ?>" data-loader="<?= t('transfer_preview.bringing_your_entries_home') ?>" data-detail="<?= t('transfer_preview.please_keep_this_page') ?>"><?= csrf_field() ?><button class="btn" type="submit"><?= $entries ? e(tn('transfer_preview.import_n', count($entries))) : t('transfer_preview.import_moods') ?></button></form>
    <form method="post" action="<?= url('/transfer/cancel/' . $token) ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('transfer_preview.cancel') ?></button></form>
  </div>
</section>
