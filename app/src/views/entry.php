<h1 class="page-title">Edit entry</h1>
<section class="card write">
  <form method="post" enctype="multipart/form-data" id="entry-form">
    <?= csrf_field() ?>
    <label>Date<input type="date" name="entry_date" value="<?= e($entry['entry_date']) ?>" required></label>
    <label>Title<input name="title" maxlength="200" value="<?= e($entry['title']) ?>" class="title-in journal-field"></label>
    <label>Entry<textarea id="body" name="body" rows="10" class="journal-field"><?= e(body_plain($entry['body'])) ?></textarea></label>
    <div class="photo-pick">
      <label class="btn btn--quiet" for="photos">Add photos</label>
      <input id="photos" type="file" name="photos[]" accept="image/*" multiple class="sr-only">
      <label class="btn btn--quiet" for="camera" id="camera-btn">Take photo</label>
      <input id="camera" type="file" name="photos[]" accept="image/*" capture="environment" class="sr-only">
      <button type="button" class="btn btn--quiet" id="dictate-btn" hidden aria-pressed="false">Dictate</button>
      <span class="hint" id="photo-state"></span>
    </div>
    <div class="thumbs" id="photo-preview"></div>
    <div class="progress" id="upload-progress" hidden><div class="bar" id="upload-bar"></div></div>
    <div class="write-foot">
      <a class="link" href="<?= url('/day/' . $entry['entry_date']) ?>">Back</a>
      <button class="btn" type="submit" id="save-btn">Save changes</button>
    </div>
  </form>
  <?php if ($entry['photos']): ?>
  <h2 class="section-h">Photos</h2>
  <div class="thumbs">
    <?php foreach ($entry['photos'] as $p): ?>
      <form method="post" action="<?= url('/photos/' . $p['id'] . '/delete') ?>" class="thumb-del" data-confirm="Remove this photo?">
        <?= csrf_field() ?>
        <img src="<?= url('/photo/' . $p['id'] . '/thumb') . '?v=' . (int)$p['edited_at'] ?>" alt="Entry photo" loading="lazy" data-photo-img="<?= (int)$p['id'] ?>">
        <span class="thumb-actions">
          <button type="button" class="btn btn--quiet" data-edit-photo="<?= (int)$p['id'] ?>">Edit</button>
          <button class="btn btn--quiet" type="submit">Remove</button>
        </span>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <form method="post" class="danger" data-confirm="Delete this entry and its photos? This can't be undone.">
    <?= csrf_field() ?><input type="hidden" name="delete" value="1">
    <button class="btn btn--quiet" type="submit">Delete entry</button>
  </form>
</section>
