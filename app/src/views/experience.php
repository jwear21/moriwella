<p><a class="link" href="<?= url('/experiences') ?>">&larr; All experiences</a></p>
<h1 class="page-title"><?= e($ev['title']) ?></h1>
<p class="subtle"><?= e(date('M j', strtotime($ev['start_date']))) ?><?= $ev['end_date'] !== $ev['start_date'] ? ' to ' . e(date('M j, Y', strtotime($ev['end_date']))) : ', ' . e(date('Y', strtotime($ev['start_date']))) ?><?= $ev['location'] !== '' ? ' · ' . e($ev['location']) : '' ?>
<?php if ($dayNo): ?> · Day <?= $dayNo ?> of <?= $total ?><?php elseif ($upTo): ?> · Starts in <?= $upTo ?> <?= $upTo === 1 ? 'day' : 'days' ?><?php endif; ?></p>

<section class="card write">
  <form method="post" action="<?= url('/entries') ?>" id="entry-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
    <div class="date-pair">
      <div><label for="entry-date">Day</label><input id="entry-date" type="date" name="entry_date" value="<?= e($addDate) ?>" min="<?= e($ev['start_date']) ?>" max="<?= e($ev['end_date']) ?>"></div>
      <?php if ($sections): ?><div><label for="section-pick">Section</label>
        <select id="section-pick" name="section_id">
          <option value="" data-prompt="">No section</option>
          <?php foreach ($sections as $sc): ?><option value="<?= (int)$sc['id'] ?>" data-prompt="<?= e($sc['prompt']) ?>"<?= $filter === (int)$sc['id'] ? ' selected' : '' ?>><?= e($sc['name']) ?></option><?php endforeach; ?>
        </select></div><?php endif; ?>
    </div>
    <label class="sr-only" for="title">Title (optional)</label>
    <input id="title" name="title" maxlength="200" placeholder="Title (optional)" class="title-in">
    <label class="sr-only" for="body">What happened?</label>
    <textarea id="body" name="body" rows="6" placeholder="What happened?"></textarea>
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
    <div class="write-foot"><span class="hint" id="draft-state" aria-live="polite"></span><button class="btn" type="submit" id="save-btn">Add to this experience</button></div>
  </form>
</section>

<?php if ($sections): ?>
<div class="chips" role="group" aria-label="Filter by section">
  <a class="chip<?= !$filter ? ' on' : '' ?>" href="<?= url('/experiences/' . $ev['id']) ?>">All</a>
  <?php foreach ($sections as $sc): ?><a class="chip<?= $filter === (int)$sc['id'] ? ' on' : '' ?>" href="<?= url('/experiences/' . $ev['id']) ?>?s=<?= (int)$sc['id'] ?>"><?= e($sc['name']) ?></a><?php endforeach; ?>
</div>
<?php endif; ?>

<?php $names = array_column($sections, 'name', 'id'); ?>
<?php if (!$entries): ?>
  <p class="subtle"><?= $filter ? 'Nothing in this section yet.' : 'Nothing yet. Add your first moment above.' ?></p>
<?php else: $lastDay = ''; foreach ($entries as $en):
  if ($en['entry_date'] !== $lastDay): $lastDay = $en['entry_date']; $dn = days_between($ev['start_date'], $en['entry_date']) + 1; ?>
    <h2 class="section-h"><?= e(date('l, F j', strtotime($en['entry_date']))) ?><?php if ($dn >= 1 && $dn <= $total && $total > 1): ?> <small class="due">Day <?= $dn ?></small><?php endif; ?></h2>
  <?php endif; ?>
  <article class="card entry-card">
    <?php if (!empty($en['section_id']) && isset($names[$en['section_id']])): ?><span class="chip chip--static"><?= e($names[$en['section_id']]) ?></span><?php endif; ?>
    <?php if ($en['title'] !== ''): ?><h3><?= e($en['title']) ?></h3><?php endif; ?>
    <?php if ($en['body'] !== ''): ?><p class="entry-body"><?= nl2br(e($en['body'])) ?></p><?php endif; ?>
    <?php if ($en['photos']): ?><div class="thumbs"><?php foreach ($en['photos'] as $p): ?>
      <div class="thumb" data-photo="<?= (int)$p['id'] ?>">
        <a href="<?= url('/photo/' . $p['id'] . '/web') . '?v=' . (int)$p['edited_at'] ?>" target="_blank" rel="noopener"><img src="<?= url('/photo/' . $p['id'] . '/thumb') . '?v=' . (int)$p['edited_at'] ?>" alt="Photo from <?= e($ev['title']) ?>" loading="lazy"></a>
        <button type="button" class="thumb-edit" data-edit-photo="<?= (int)$p['id'] ?>" aria-label="Edit this photo">Edit</button>
      </div>
    <?php endforeach; ?></div><?php endif; ?>
    <p><a class="link" href="<?= url('/entries/' . $en['id']) ?>">Edit</a></p>
  </article>
<?php endforeach; endif; ?>

<h2 class="section-h" id="sections">Sections</h2>
<section class="card write">
  <p class="subtle">Rename them, change the prompt that appears while you write, or add your own.</p>
  <?php foreach ($sections as $sc): $base = url('/experiences/' . $ev['id'] . '/section/' . $sc['id']); ?>
    <details class="sec-edit">
      <summary><?= e($sc['name']) ?></summary>
      <form method="post" action="<?= $base ?>/rename">
        <?= csrf_field() ?>
        <label for="sn<?= (int)$sc['id'] ?>">Name</label><input id="sn<?= (int)$sc['id'] ?>" name="name" maxlength="80" value="<?= e($sc['name']) ?>" required>
        <label for="sp<?= (int)$sc['id'] ?>">Prompt (optional)</label><input id="sp<?= (int)$sc['id'] ?>" name="prompt" maxlength="200" value="<?= e($sc['prompt']) ?>">
        <button class="btn" type="submit">Save</button>
      </form>
      <form method="post" action="<?= $base ?>/delete" data-confirm="Remove this section? Entries in it stay in the experience."><?= csrf_field() ?><button class="btn btn--quiet" type="submit">Remove section</button></form>
    </details>
  <?php endforeach; ?>
  <form method="post" action="<?= url('/experiences/' . $ev['id'] . '/sections') ?>" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="newsec">New section</label>
    <input id="newsec" name="name" maxlength="80" placeholder="Add a section, for example: Friends" required class="title-in">
    <button class="btn" type="submit">Add section</button>
  </form>
  <form method="post" action="<?= url('/experiences/' . $ev['id'] . '/save-template') ?>" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="tname">Template name</label>
    <input id="tname" name="name" maxlength="80" placeholder="Save these sections as my template, named…" class="title-in">
    <button class="btn btn--quiet" type="submit">Save as template</button>
  </form>
</section>

<h2 class="section-h">Details</h2>
<section class="card write">
  <form method="post" action="<?= url('/experiences/' . $ev['id'] . '/update') ?>">
    <?= csrf_field() ?>
    <label for="etitle">Name</label><input id="etitle" name="title" maxlength="150" value="<?= e($ev['title']) ?>" required>
    <label for="eloc">Where</label><input id="eloc" name="location" maxlength="150" value="<?= e($ev['location']) ?>">
    <div class="date-pair">
      <div><label for="es">Starts</label><input id="es" type="date" name="start_date" value="<?= e($ev['start_date']) ?>" required></div>
      <div><label for="ee">Ends</label><input id="ee" type="date" name="end_date" value="<?= e($ev['end_date']) ?>" required></div>
    </div>
    <button class="btn" type="submit">Save details</button>
  </form>
  <form method="post" action="<?= url('/experiences/' . $ev['id'] . '/delete') ?>" class="danger" data-confirm="Remove this experience? Your entries stay in your journal."><?= csrf_field() ?><button class="btn btn--quiet" type="submit">Remove experience</button></form>
</section>
