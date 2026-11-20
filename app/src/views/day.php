<?php
$h = (int)date('G'); $greet = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
$prev = date('Y-m-d', strtotime($day . ' -1 day')); $next = date('Y-m-d', strtotime($day . ' +1 day'));
?>
<?php if ($isToday): ?>
  <h1 class="page-title"><?= $greet ?>, <?= e(explode(' ', $user['name'])[0]) ?>.</h1>
  <p class="subtle" id="today-label"><?= e(date('l, F j')) ?></p>
<?php else: ?>
  <h1 class="page-title"><?= e(date('l, F j, Y', strtotime($day))) ?></h1>
  <p class="daynav"><a class="link" href="<?= url('/day/' . $prev) ?>">&larr; Previous day</a> <a class="link" href="<?= url('/day/' . $next) ?>">Next day &rarr;</a> <a class="link" href="<?= url('/calendar?m=' . substr($day, 0, 7)) ?>">Calendar</a></p>
<?php endif; ?>

<section class="card water" data-day="<?= e($day) ?>" data-oz="<?= (int)$water ?>" data-url="<?= url('/water') ?>" data-csrf="<?= e(csrf_token()) ?>">
  <div class="water-head"><b>Water</b> <span class="water-count"><span id="water-oz"><?= (int)$water ?></span> of 64 oz</span></div>
  <div class="water-cups" role="group" aria-label="Water cups, 8 oz each">
    <?php for ($i = 1; $i <= 8; $i++): ?>
      <button type="button" class="cup<?= $water >= $i * 8 ? ' on' : '' ?>" data-cup="<?= $i ?>" aria-pressed="<?= $water >= $i * 8 ? 'true' : 'false' ?>" aria-label="Cup <?= $i ?>"></button>
    <?php endfor; ?>
  </div>
  <p class="hint">Tap a cup for each 8 oz. Tap the last filled cup again to undo.</p>
</section>

<?php $hasDay = count($entries) > 0; ?>
<section class="card write">
  <form method="post" action="<?= url('/entries') ?>" id="entry-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="entry_date" id="entry-date" value="<?= e($day) ?>">
    <?php if ($hasDay): ?>
      <label class="check-row"><input type="checkbox" name="separate" value="1" id="separate"> Make this its own entry</label>
      <div id="title-wrap" hidden><label class="sr-only" for="title">Title</label><input id="title" name="title" maxlength="200" placeholder="Title for this entry" class="title-in"></div>
    <?php else: ?>
      <label class="sr-only" for="title">Title (optional)</label>
      <input id="title" name="title" maxlength="200" placeholder="Title (optional)" class="title-in">
    <?php endif; ?>
    <label class="sr-only" for="body">What's on your mind?</label>
    <textarea id="body" name="body" rows="7" placeholder="<?= $hasDay ? ($isToday ? 'What happened next?' : 'Add more to this day…') : ($isToday ? 'What\'s on your mind today?' : 'What happened this day?') ?>" <?= $isToday ? 'data-draft="mw-draft-body"' : '' ?>></textarea>
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
      <span class="hint" id="draft-state" aria-live="polite"></span>
      <button class="btn" type="submit" id="save-btn"><?= $hasDay ? ($isToday ? 'Add to your day' : 'Add to this day') : 'Save entry' ?></button>
    </div>
  </form>
</section>

<h2 class="section-h"><?= $isToday ? 'Your day so far' : 'This day' ?></h2>
<?php if (!$entries): ?>
  <p class="subtle"><?= $isToday ? 'Nothing yet today. Even one sentence counts.' : 'No entries on this day.' ?></p>
<?php else: foreach ($entries as $en): ?>
  <article class="card entry-card">
    <?php if ($en['title'] !== ''): ?><h3><?= e($en['title']) ?></h3><?php endif; ?>
    <?php if ($en['body'] !== ''): ?><p class="entry-body"><?= nl2br(e($en['body'])) ?></p><?php endif; ?>
    <?php if ($en['photos']): ?><div class="thumbs"><?php foreach ($en['photos'] as $p): ?>
      <div class="thumb" data-photo="<?= (int)$p['id'] ?>">
        <a href="<?= url('/photo/' . $p['id'] . '/web') . '?v=' . (int)$p['edited_at'] ?>" target="_blank" rel="noopener"><img src="<?= url('/photo/' . $p['id'] . '/thumb') . '?v=' . (int)$p['edited_at'] ?>" alt="Photo from <?= e($day) ?>" loading="lazy"></a>
        <button type="button" class="thumb-edit" data-edit-photo="<?= (int)$p['id'] ?>" aria-label="Edit this photo">Edit</button>
      </div>
    <?php endforeach; ?></div><?php endif; ?>
    <p><a class="link" href="<?= url('/entries/' . $en['id']) ?>">Edit</a></p>
  </article>
<?php endforeach; endif; ?>
<?php if (count($entries) > 1): ?>
  <form method="post" action="<?= url('/day/' . $day . '/combine') ?>" data-confirm="Combine these into one page? Photos move with them.">
    <?= csrf_field() ?><button class="btn btn--quiet" type="submit">Combine into one page</button>
  </form>
<?php endif; ?>
