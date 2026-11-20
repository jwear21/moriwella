<?php
$h = (int)date('G'); $greet = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
$prev = date('Y-m-d', strtotime($day . ' -1 day')); $next = date('Y-m-d', strtotime($day . ' +1 day'));
?>
<?php $glyph = $h < 10 ? '<path d="M3 17h18M6.5 17a5.5 5.5 0 0111 0M12 5v3M4.9 8.9l1.8 1.8M19.1 8.9l-1.8 1.8"/>' : ($h < 17 ? '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6L7 7M17 17l1.4 1.4M18.4 5.6L17 7M7 17l-1.4 1.4"/>' : '<path d="M19 14.5A7.5 7.5 0 019.5 5 7.5 7.5 0 1019 14.5z"/>'); ?>
<?php if ($isToday): ?>
  <h1 class="page-title greet"><svg class="greet-ico" viewBox="0 0 24 24" aria-hidden="true"><?= $glyph ?></svg><?= $greet ?>, <?= e(explode(' ', $user['name'])[0]) ?>.</h1>
  <p class="subtle" id="today-label"><?= e(date('l, F j')) ?></p>
<?php else: ?>
  <h1 class="page-title"><?= e(date('l, F j, Y', strtotime($day))) ?></h1>
  <p class="daynav"><a class="link" href="<?= url('/day/' . $prev) ?>">&larr; Previous day</a> <a class="link" href="<?= url('/day/' . $next) ?>">Next day &rarr;</a> <a class="link" href="<?= url('/calendar?m=' . substr($day, 0, 7)) ?>">Calendar</a></p>
<?php endif; ?>

<?php if (!empty($showWelcome)): ?>
<section class="card welcome" aria-label="Welcome">
  <h2>Welcome to Moriwella</h2>
  <p>This is your private place to keep your days.</p>
  <ul class="welcome-list">
    <li><b>Today</b> is for writing, photos and dictation. Each day becomes one page.</li>
    <li><b>Lists</b> are for tasks and shopping, and you can share them with family.</li>
    <li><b>Experiences</b> are for trips and big events, with sections to fill in as you go.</li>
  </ul>
  <p class="subtle">You can add daily goals, like water or a walk, any time in Me.</p>
  <form method="post" action="<?= url('/welcome/dismiss') ?>" class="welcome-acts"><?= csrf_field() ?>
    <button class="btn" type="submit">Start writing</button>
    <a class="btn btn--quiet" href="<?= url('/settings') ?>#goals">Set up goals</a>
  </form>
</section>
<?php endif; ?>
<?php
$prompts = ['What\'s on your mind today?', 'What is one small thing you want to remember about today?', 'Who made you smile today?', 'What did you notice today that you might forget?', 'What are you grateful for right now?', 'What is today teaching you?', 'What was the best part of today so far?'];
$todayPrompt = $prompts[(int)date('z', strtotime($day)) % count($prompts)];
?>
<?php $hasDay = count(array_filter($entries, fn($e) => empty($e['event_id']))) > 0; ?>
<section class="card write">
  <form method="post" action="<?= url('/entries') ?>" id="entry-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="entry_date" id="entry-date" value="<?= e($day) ?>">
    <?php if (!$hasDay): ?>
      <label class="sr-only" for="title">Title (optional)</label>
      <input id="title" name="title" maxlength="200" placeholder="Title (optional)" class="title-in journal-field">
    <?php endif; ?>
    <label class="sr-only" for="body">What's on your mind?</label>
    <textarea id="body" name="body" rows="6" class="journal-field" placeholder="<?= $hasDay ? ($isToday ? 'What happened next?' : 'Add more to this day…') : ($isToday ? $todayPrompt : 'What happened this day?') ?>" <?= $isToday ? 'data-draft="mw-draft-body"' : '' ?>></textarea>
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
    <?php if (!empty($en['event_id'])): ?><p class="subtle"><a class="link" href="<?= url('/experiences/' . $en['event_id']) ?>">From an experience</a></p><?php endif; ?>
    <?php if ($en['title'] !== ''): ?><h3><?= e($en['title']) ?></h3><?php endif; ?>
    <?php foreach (body_parts($en['body'], (int)$en['created_at']) as $bp): ?><p class="entry-body"><?php if ($bp['ts']): ?><time class="stamp" data-ts="<?= (int)$bp['ts'] ?>"><?= e(stamp_time($bp['ts'])) ?></time> <?php endif; ?><?= nl2br(e($bp['text'])) ?></p><?php endforeach; ?>
    <?php if ($en['photos']): ?><div class="thumbs"><?php foreach ($en['photos'] as $p): ?>
      <div class="thumb" data-photo="<?= (int)$p['id'] ?>">
        <a href="<?= url('/photo/' . $p['id'] . '/web') . '?v=' . (int)$p['edited_at'] ?>" target="_blank" rel="noopener"><img src="<?= url('/photo/' . $p['id'] . '/thumb') . '?v=' . (int)$p['edited_at'] ?>" alt="Photo from <?= e($day) ?>" loading="lazy"></a>
        <button type="button" class="thumb-edit" data-edit-photo="<?= (int)$p['id'] ?>" aria-label="Edit this photo">Edit</button>
      </div>
    <?php endforeach; ?></div><?php endif; ?>
    <p><a class="link" href="<?= url('/entries/' . $en['id']) ?>">Edit</a></p>
  </article>
<?php endforeach; endif; ?>
<?php if (count(array_filter($entries, fn($e) => empty($e['event_id']))) > 1): ?>
  <form method="post" action="<?= url('/day/' . $day . '/combine') ?>" data-confirm="Combine these into one page? Photos move with them.">
    <?= csrf_field() ?><button class="btn btn--quiet" type="submit">Combine into one page</button>
  </form>
<?php endif; ?>

<?php if ($goals || $waterOn || !empty($upcoming)): ?>
<h2 class="section-h"><?= $isToday ? "Today's checklist" : 'Goals for this day' ?></h2>
<?php endif; ?>
<?php if ($goals): ?>
<section class="card goals" id="goals-card" data-day="<?= e($day) ?>" data-csrf="<?= e(csrf_token()) ?>">
  <div class="water-head"><b>Daily goals</b> <span class="water-count"><span id="goals-done"><?= count(array_filter($goals, fn($g) => $g['done'])) ?></span> of <?= count($goals) ?></span></div>
  <ul class="items goal-list">
  <?php foreach ($goals as $g): ?>
    <li class="item<?= $g['done'] ? ' done' : '' ?>">
      <button type="button" class="check" role="checkbox" aria-checked="<?= $g['done'] ? 'true' : 'false' ?>" aria-label="<?= e($g['name']) ?>" data-goal-url="<?= url('/goals/' . $g['id'] . '/toggle') ?>"></button>
      <div class="item-text"><span><?= e($g['name']) ?></span></div>
    </li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php if (!$goals && !$waterOn && $isToday): ?>
<p class="subtle goal-nudge"><a class="link" href="<?= url('/settings') ?>#goals">Set daily goals</a> to check off each day, like water, a walk, or homework.</p>
<?php endif; ?>

<?php if ($waterOn): ?>
<section class="card water" data-day="<?= e($day) ?>" data-oz="<?= (int)$water ?>" data-url="<?= url('/water') ?>" data-csrf="<?= e(csrf_token()) ?>">
  <div class="water-head"><b>Water</b> <span class="water-count"><span id="water-oz"><?= (int)$water ?></span> of 64 oz</span></div>
  <div class="water-cups" role="group" aria-label="Water cups, 8 oz each">
    <?php for ($i = 1; $i <= 8; $i++): ?>
      <button type="button" class="cup<?= $water >= $i * 8 ? ' on' : '' ?>" data-cup="<?= $i ?>" aria-pressed="<?= $water >= $i * 8 ? 'true' : 'false' ?>" aria-label="Cup <?= $i ?>"></button>
    <?php endfor; ?>
  </div>
  <p class="hint">Tap a cup for each 8 oz. Tap the last filled cup again to undo.</p>
</section>
<?php endif; ?>

<?php if (!empty($upcoming)): $t = date('Y-m-d'); ?>
<section class="card coming" aria-label="Coming up">
  <div class="water-head"><b>Coming up</b> <span class="water-count">next 7 days</span></div>
  <ul class="coming-list">
  <?php $moreN = max(0, count($upcoming) - 3); foreach (array_slice($upcoming, 0, 3) as $u): $late = $u['due_date'] < $t; $when = $u['due_date'] === $t ? 'Today' : ($u['due_date'] === date('Y-m-d', strtotime('+1 day')) ? 'Tomorrow' : ($late ? 'Overdue' : date('D, M j', strtotime($u['due_date'])))); ?>
    <li><a href="<?= url('/lists/' . $u['list_id']) ?>"><span class="coming-text"><?= e($u['text']) ?><small class="due"><?= e($u['list_name']) ?><?= !empty($u['assigned_name']) ? ' · for ' . e((int)$u['assigned_to'] === (int)$user['id'] ? 'you' : $u['assigned_name']) : '' ?></small></span><span class="when<?= $late ? ' late' : '' ?>"><?= e($when) ?></span></a></li>
  <?php endforeach; ?>
  </ul>
  <?php if ($moreN): ?><a class="link coming-more" href="<?= url('/lists') ?>">+<?= $moreN ?> more in your lists</a><?php endif; ?>
</section>
<?php endif; ?>

