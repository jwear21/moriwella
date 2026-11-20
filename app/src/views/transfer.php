<p><a class="link back" href="<?= url('/settings') ?>"><?= t('common.account') ?></a></p>
<h1 class="page-title"><?= t('common.import_and_export') ?></h1>
<p class="subtle"><?= t('transfer.your_words_are_yours') ?></p>

<section class="card write xfer" id="export">
  <h2 class="section-h"><?= t('transfer.export_your_journal') ?></h2>
  <p><?= t('transfer.take_everything_with_you') ?></p>
  <?php if ($entryCount > 0): ?>
  <form method="post" action="<?= url('/transfer/export') ?>" data-download="<?= t('transfer.packing_up_your_journal') ?>" data-detail="<?= t('transfer.this_can_take_a') ?>">
    <?= csrf_field() ?>
    <div class="src-list" role="radiogroup" aria-label="<?= t('transfer.export_format') ?>">
      <label class="src"><input type="radio" name="format" value="moriwella" checked><span class="src-body"><b><?= t('transfer.moriwella_archive') ?></b><small><?= t('transfer.a_zip_with_one') ?></small></span></label>
      <label class="src"><input type="radio" name="format" value="evernote"><span class="src-body"><b><?= t('transfer.for_evernote') ?></b><small><?= t('transfer.an_enex_file_in') ?></small></span></label>
      <label class="src"><input type="radio" name="format" value="dayone"><span class="src-body"><b><?= t('transfer.for_day_one') ?></b><small><?= t('transfer.a_zip_in_day') ?></small></span></label>
    </div>
    <button class="btn" type="submit"><?= t('transfer.download_my_journal') ?></button>
    <span class="hint"><?= (int)$entryCount ?> <?= $entryCount === 1 ? 'entry' : 'entries' ?></span>
  </form>
  <?php else: ?>
  <p class="hint"><?= t('transfer.there_is_nothing_to') ?></p>
  <?php endif; ?>
</section>

<section class="card write xfer" id="import">
  <h2 class="section-h"><?= t('transfer.bring_entries_from_another') ?></h2>
  <p><?= t('transfer.choose_where_your_file') ?></p>
  <form method="post" action="<?= url('/transfer/upload') ?>" enctype="multipart/form-data" data-loader="<?= t('transfer.reading_your_file') ?>" data-detail="<?= t('transfer.almost_there_nothing_is') ?>">
    <?= csrf_field() ?>
    <div class="src-list" role="radiogroup" aria-label="<?= t('transfer.where_did_your_file') ?>">
      <?php $first = true; foreach ($sources as $key => $s): ?>
      <label class="src<?= $s['ready'] ? '' : ' src--soon' ?>">
        <input type="radio" name="source" value="<?= e($key) ?>" data-accept="<?= e($s['accept']) ?>"<?= $s['ready'] ? '' : ' disabled' ?><?= ($s['ready'] && $first) ? ' checked' : '' ?>>
        <span class="src-body"><b><?= e($s['name']) ?></b><small><?= e(tl($s['help'])) ?></small></span>
        <?php if (!$s['ready']): ?><span class="src-tag"><?= t('transfer.coming_soon') ?></span><?php endif; ?>
      </label>
      <?php if ($s['ready']) $first = false; endforeach; ?>
    </div>
    <label class="filepick"><span class="filepick-t"><?= t('transfer.choose_your_file') ?></span><span class="filepick-n" id="filepick-n"><?= t('transfer.no_file_chosen') ?></span><input type="file" name="file" id="xfer-file" accept=".zip" required></label>
    <p class="hint"><?= t('transfer.files_up_to', ['size' => $maxUpload]) ?></p>
    <button class="btn" type="submit"><?= t('transfer.read_my_file') ?></button>
  </form>
</section>

<?php if ($imports): ?>
<section class="card write xfer">
  <h2 class="section-h"><?= t('transfer.recent_imports') ?></h2>
  <ul class="trk-list">
  <?php foreach ($imports as $im): ?>
    <li><span class="trk-name"><b><?= e($sources[$im['source']]['name'] ?? $im['source']) ?></b><small><?= (int)$im['entries'] ?> <?= (int)$im['entries'] === 1 ? 'entry' : 'entries' ?><?= $im['photos'] ? ', ' . (int)$im['photos'] . ' photos' : '' ?><?= !empty($im['moods']) ? ', ' . (int)$im['moods'] . ' moods' : '' ?> &middot; <?= e(ldate('M j, Y', (int)$im['created_at'])) ?></small></span>
      <form method="post" action="<?= url('/transfer/undo/' . $im['batch']) ?>" data-confirm="<?= t('transfer.remove_everything_that_came') ?>"><?= csrf_field() ?><button class="trk-btn" type="submit"><?= t('transfer.undo') ?></button></form></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
