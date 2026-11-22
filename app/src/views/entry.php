<h1 class="page-title"><?= t('entry.edit_entry') ?></h1>
<section class="card write">
  <form method="post" enctype="multipart/form-data" id="entry-form">
    <?= csrf_field() ?>
    <label><?= t('entry.date') ?><input type="date" name="entry_date" value="<?= e($entry['entry_date']) ?>" required></label>
    <label><?= t('entry.title') ?><input name="title" maxlength="200" value="<?= e($entry['title']) ?>" class="title-in journal-field"></label>
    <label><?= t('entry.entry') ?><textarea id="body" name="body" rows="10" class="journal-field"><?= e(body_plain($entry['body'])) ?></textarea></label>
    <div class="photo-pick">
      <label class="btn btn--quiet" for="photos"><?= t('common.add_photos') ?></label>
      <input id="photos" type="file" name="photos[]" accept="image/*" multiple class="sr-only">
      <label class="btn btn--quiet" for="camera" id="camera-btn"><?= t('common.take_photo') ?></label>
      <input id="camera" type="file" name="photos[]" accept="image/*" capture="environment" class="sr-only">
      <button type="button" class="btn btn--quiet" id="dictate-btn" hidden aria-pressed="false"><?= t('common.dictate') ?></button>
      <span class="hint" id="photo-state"></span>
    </div>
    <div class="thumbs" id="photo-preview"></div>
    <div class="progress" id="upload-progress" hidden><div class="bar" id="upload-bar"></div></div>
    <div class="write-foot">
      <a class="link" href="<?= url('/day/' . $entry['entry_date']) ?>"><?= t('entry.back') ?></a>
      <button class="btn" type="submit" id="save-btn"><?= t('entry.save_changes') ?></button>
    </div>
  </form>
  <?php if ($entry['photos']): ?>
  <h2 class="section-h"><?= t('entry.photos') ?></h2>
  <div class="thumbs">
    <?php foreach ($entry['photos'] as $p): ?>
      <form method="post" action="<?= url('/photos/' . $p['id'] . '/delete') ?>" class="thumb-del" data-confirm="<?= t('entry.remove_this_photo') ?>">
        <?= csrf_field() ?>
        <img src="<?= url('/photo/' . $p['id'] . '/thumb') . '?v=' . (int)$p['edited_at'] ?>" alt="<?= t('entry.entry_photo') ?>" loading="lazy" data-pv data-pid="<?= (int)$p['id'] ?>" data-photo-img="<?= (int)$p['id'] ?>">
        <span class="thumb-actions">
          <button class="btn btn--quiet" type="submit"><?= t('common.remove') ?></button>
        </span>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <form method="post" class="danger" data-confirm="<?= t('entry.delete_this_entry_and') ?>">
    <?= csrf_field() ?><input type="hidden" name="delete" value="1">
    <button class="btn btn--quiet" type="submit"><?= t('entry.delete_entry') ?></button>
  </form>
</section>
