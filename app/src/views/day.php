<?php
$h = (int)date('G'); $greet = $h < 12 ? t('day.good_morning') : ($h < 18 ? t('day.good_afternoon') : t('day.good_evening'));
$prev = date('Y-m-d', strtotime($day . ' -1 day')); $next = date('Y-m-d', strtotime($day . ' +1 day'));
?>
<?php $glyph = $h < 10 ? '<path d="M3 17h18M6.5 17a5.5 5.5 0 0111 0M12 5v3M4.9 8.9l1.8 1.8M19.1 8.9l-1.8 1.8"/>' : ($h < 17 ? '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6L7 7M17 17l1.4 1.4M18.4 5.6L17 7M7 17l-1.4 1.4"/>' : '<path d="M19 14.5A7.5 7.5 0 019.5 5 7.5 7.5 0 1019 14.5z"/>'); ?>
<?php if ($isToday): ?>
  <h1 class="page-title greet"><svg class="greet-ico" viewBox="0 0 24 24" aria-hidden="true"><?= $glyph ?></svg><?= $greet ?>, <?= e(explode(' ', $user['name'])[0]) ?>.</h1>
  <p class="subtle" id="today-label"><?= e(ldate('l, F j')) ?></p>
<?php else: ?>
  <h1 class="page-title"><?= e(ldate('l, F j, Y', strtotime($day))) ?></h1>
  <p class="daynav"><a class="link" href="<?= url('/day/' . $prev) ?>"><?= t('day.previous_day') ?></a> <a class="link" href="<?= url('/day/' . $next) ?>"><?= t('day.next_day') ?></a> <a class="link" href="<?= url('/calendar?m=' . substr($day, 0, 7)) ?>"><?= t('common.calendar') ?></a></p>
<?php endif; ?>

<?php if (!empty($showWelcome)): ?>
<section class="card welcome" aria-label="<?= t('day.welcome') ?>">
  <h2><?= t('day.welcome_to_moriwella') ?></h2>
  <p><?= t('day.this_is_your_private') ?></p>
  <ul class="welcome-list">
    <li><b><?= t('common.today') ?></b> <?= t('day.is_for_writing_photos') ?></li>
    <li><b><?= t('common.lists') ?></b> <?= t('day.are_for_tasks_and') ?></li>
    <li><b><?= t('common.experiences') ?></b> <?= t('day.are_for_trips_and') ?></li>
  </ul>
  <p class="subtle"><?= t('day.if_you_like_track') ?></p>
  <form method="post" action="<?= url('/welcome/dismiss') ?>" class="welcome-acts"><?= csrf_field() ?>
    <button class="btn" type="submit"><?= t('day.start_writing') ?></button>
    <a class="btn btn--quiet" href="<?= url('/track') ?>"><?= t('day.choose_what_to_track') ?></a>
  </form>
</section>
<?php endif; ?>
<?php
$prompts = [t('day.prompt_1'), t('day.prompt_2'), t('day.prompt_3'), t('day.prompt_4'), t('day.prompt_5'), t('day.prompt_6'), t('day.prompt_7')];
$todayPrompt = $prompts[(int)date('z', strtotime($day)) % count($prompts)];
?>
<?php $hasDay = count(array_filter($entries, fn($e) => empty($e['event_id']))) > 0; ?>
<?php $faces = feelings_faces(); $fsel = array_flip($feel['feelings']); ?>
<section class="card feel" id="feel" data-day="<?= e($day) ?>" data-url="<?= url('/feel') ?>" data-csrf="<?= e(csrf_token()) ?>" data-moods="<?= e(json_encode($feel['moods'])) ?>">
  <h2 class="feel-h"><?= $isToday ? t('day.how_feeling') : t('day.how_were_feeling') ?></h2>
  <div class="feel-faces" role="radiogroup" aria-label="<?= t('day.overall_mood_for_the') ?>">
    <?php foreach ($faces as $n => $f): ?>
    <button type="button" class="face" role="radio" aria-checked="<?= $feel['mood'] === $n ? 'true' : 'false' ?>" data-v="<?= $n ?>"><span class="face-e" aria-hidden="true"><?= $f['e'] ?></span><span class="face-l"><?= e(tl($f['l'])) ?></span></button>
    <?php endforeach; ?>
  </div>
  <p class="hint feel-hint"><?= $isToday ? t('day.feel_hint_today') : t('day.feel_hint_past') ?></p>
  <ul class="mood-list" id="mood-list"<?= count($feel['moods']) < 2 ? ' hidden' : '' ?>>
    <?php if (count($feel['moods']) >= 2) foreach ($feel['moods'] as $m): $f = $faces[$m['v']]; ?>
    <li><span class="ml-e" aria-hidden="true"><?= $f['e'] ?></span><span class="ml-l"><?= e(tl($f['l'])) ?></span><?php if ($m['ts']): ?><time class="stamp" data-ts="<?= (int)$m['ts'] ?>"><?= e(date('g:i a', $m['ts'])) ?></time> <span class="ml-part"><?= e(feelings_part($m['ts'])) ?></span><?php endif; ?><button type="button" class="ml-x" data-id="<?= (int)$m['id'] ?>" aria-label="<?= t('day.remove_this_mood') ?>">&times;</button></li>
    <?php endforeach; ?>
  </ul>
  <details class="feel-more"<?= $fsel ? ' open' : '' ?>>
    <summary><?= t('day.more_feelings') ?><span class="feel-n"><?= $fsel ? ' (' . count($fsel) . ')' : '' ?></span></summary>
    <?php foreach (feelings_catalog() as $g => $list): ?>
    <p class="feel-g"><?= e(tl($g)) ?></p>
    <div class="feel-chips">
      <?php foreach ($list as $k): ?><button type="button" class="fchip" aria-pressed="<?= isset($fsel[$k]) ? 'true' : 'false' ?>" data-f="<?= e($k) ?>"><?php $w = tl($k); ?><?= e(mb_strtoupper(mb_substr($w, 0, 1)) . mb_substr($w, 1)) ?></button><?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </details>
</section>

<section class="card write write-cta">
  <p class="subtle"><?= $hasDay ? ($isToday ? t('day.cta_more_today') : t('day.cta_more_day')) : ($isToday ? $todayPrompt : t('day.ph_this_day')) ?></p>
  <a class="btn" href="<?= url('/journal/book?write=' . $day) ?>"><?= $hasDay ? ($isToday ? t('day.btn_add_today') : t('day.btn_add_day')) : t('day.cta_write') ?></a>
  <a class="link" href="<?= url('/journal/book') ?>"><?= t('day.cta_read') ?></a>
</section>

<h2 class="section-h"><?= $isToday ? t('day.h_so_far') : t('day.h_this_day') ?></h2>
<?php if (!$entries): ?>
  <p class="subtle"><?= $isToday ? t('day.nothing_today') : t('day.no_entries') ?></p>
<?php else: foreach ($entries as $en): ?>
  <article class="card entry-card">
    <?php if (!empty($en['event_id'])): ?><p class="subtle"><a class="link" href="<?= url('/experiences/' . $en['event_id']) ?>"><?= t('day.from_an_experience') ?></a></p><?php endif; ?>
    <?php if ($en['title'] !== ''): ?><h3><?= e($en['title']) ?></h3><?php endif; ?>
    <?php foreach (body_parts($en['body'], (int)$en['created_at']) as $bp): ?><p class="entry-body"><?php if ($bp['ts']): ?><time class="stamp" data-ts="<?= (int)$bp['ts'] ?>"><?= e(stamp_time($bp['ts'])) ?></time> <?php endif; ?><?= nl2br(e($bp['text'])) ?></p><?php endforeach; ?>
    <?php if ($en['photos']): ?><div class="thumbs"><?php foreach ($en['photos'] as $p): ?>
      <div class="thumb" data-photo="<?= (int)$p['id'] ?>">
        <a href="<?= url('/photo/' . $p['id'] . '/web') . '?v=' . (int)$p['edited_at'] ?>" target="_blank" rel="noopener"><img src="<?= url('/photo/' . $p['id'] . '/thumb') . '?v=' . (int)$p['edited_at'] ?>" alt="<?= t('common.photo_from', ['title' => $day]) ?>" loading="lazy"></a>
        <button type="button" class="thumb-edit" data-edit-photo="<?= (int)$p['id'] ?>" aria-label="<?= t('common.edit_this_photo') ?>"><?= t('common.edit') ?></button>
      </div>
    <?php endforeach; ?></div><?php endif; ?>
    <p><a class="link" href="<?= url('/journal/book?edit=' . $en['id']) ?>"><?= t('common.edit') ?></a></p>
  </article>
<?php endforeach; endif; ?>
<?php if (count(array_filter($entries, fn($e) => empty($e['event_id']))) > 1): ?>
  <form method="post" action="<?= url('/day/' . $day . '/combine') ?>" data-confirm="<?= t('day.combine_these_into_one') ?>">
    <?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('day.combine_into_one_page') ?></button>
  </form>
<?php endif; ?>

<?php if ($goals || $waterOn || !empty($trackers) || !empty($upcoming)): ?>
<h2 class="section-h"><?= $isToday ? t('day.h_small_today') : t('day.h_small_day') ?></h2>
<?php endif; ?>
<?php if ($goals): ?>
<section class="card goals" id="goals-card" data-day="<?= e($day) ?>" data-csrf="<?= e(csrf_token()) ?>">
  <div class="water-head"><b><?= t('day.checkmarks') ?></b> <span class="water-count"><span id="goals-done"><?= count(array_filter($goals, fn($g) => $g['done'])) ?></span> of <?= count($goals) ?></span></div>
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
<?php if (!$goals && !$waterOn && empty($trackers) && $isToday): ?>
<p class="subtle goal-nudge"><a class="link" href="<?= url('/track') ?>"><?= t('day.track_little_things') ?></a> <?= t('day.if_you_like_such') ?></p>
<?php endif; ?>

<?php if ($waterOn): $wg = tracker_goals((int)current_user()["id"])["water"]; $wn = tracker_bullets($wg); $wi = tracker_icons(); ?>
<section class="card water" data-day="<?= e($day) ?>" data-oz="<?= (int)$water ?>" data-per="<?= e(fmt_num($wg['per'])) ?>" data-cap="<?= e(fmt_num($wg['target'])) ?>" data-url="<?= url('/water') ?>" data-csrf="<?= e(csrf_token()) ?>">
  <div class="water-head"><b><?= t('day.water') ?></b> <span class="water-count"><span id="water-oz"><?= (int)$water ?></span> <?= t('day.of_n_oz', ['n' => fmt_num($wg['target'])]) ?></span></div>
  <div class="water-cups" role="group" aria-label="<?= t('day.water_cups_8_oz') ?>">
    <?php for ($i = 1; $i <= $wn; $i++): $thr = min($i * $wg['per'], $wg['target']); $on = $water >= $thr; ?>
      <?php if ($wg['icon'] === 'glass'): ?>
      <button type="button" class="cup<?= $on ? ' on' : '' ?>" data-cup="<?= $i ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>" aria-label="<?= t('day.cup_n', ['n' => $i]) ?>"></button>
      <?php else: ?>
      <button type="button" class="gb cup<?= $on ? ' on' : '' ?>" data-cup="<?= $i ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>" aria-label="<?= t('day.cup_n', ['n' => $i]) ?>"><span aria-hidden="true"><?= e($wi[$wg['icon']] ?? $wi['star']) ?></span></button>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <p class="hint"><?= t('day.tap_a_bullet', ['per' => fmt_num($wg['per'])]) ?></p>
</section>
<?php endif; ?>

<?php if (!empty($trackers)): ?>
<section class="card little" id="little" data-day="<?= e($day) ?>" data-csrf="<?= e(csrf_token()) ?>" data-url="<?= url('/track/log') ?>">
  <div class="water-head"><b><?= t('day.little_things') ?></b> <a class="link" href="<?= url('/track') ?>"><?= t('day.choose') ?></a></div>
  <?php foreach ($trackers as $t): $k = $t['key']; $v = $t['value']; ?>
  <div class="lt-row lt-<?= e($t['type']) ?>" data-key="<?= e($k) ?>">
    <?php if ($t['type'] === 'check'): ?>
      <button type="button" class="lt-check" role="checkbox" aria-checked="<?= $v ? 'true' : 'false' ?>"><span class="box" aria-hidden="true"></span><?= e(tl($t['label'])) ?></button>
    <?php elseif (in_array($t['type'], ['count'], true) && !empty($t['goal'])): $g = $t['goal']; $gn = tracker_bullets($g); $gi = tracker_icons(); ?>
      <span class="lt-label"><?= e(tl($t['label'])) ?></span>
      <span class="lt-goalcount"><output><?= $v ? (int)$v : 0 ?></output> <?= t('day.of_target', ['target' => fmt_num($g['target']), 'unit' => tl($t['unit'] ?? '')]) ?></span>
      <div class="goal-bullets" role="group" aria-label="<?= e(tl($t['label'])) ?>" data-per="<?= e((string)$g['per']) ?>" data-target="<?= e((string)$g['target']) ?>" data-val="<?= $v ? (int)$v : 0 ?>">
        <?php for ($i = 1; $i <= $gn; $i++): $on = (float)($v ?? 0) >= min($i * $g['per'], $g['target']); ?>
        <button type="button" class="gb<?= $on ? ' on' : '' ?>" data-i="<?= $i ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>" aria-label="<?= t('day.bullet_n', ['n' => $i, 'total' => $gn]) ?>"><span aria-hidden="true"><?= e($gi[$g['icon']] ?? $gi['star']) ?></span></button>
        <?php endfor; ?>
      </div>
    <?php elseif ($t['type'] === 'count'): ?>
      <span class="lt-label"><?= e(tl($t['label'])) ?></span>
      <span class="lt-step" data-max="<?= (int)($t['max'] ?? 12) ?>">
        <button type="button" class="lt-dec" aria-label="<?= t('day.one_less', ['what' => tl($t['label'])]) ?>">&minus;</button>
        <output><?= $v ? (int)$v : 0 ?></output>
        <button type="button" class="lt-inc" aria-label="<?= t('day.one_more', ['what' => tl($t['label'])]) ?>">+</button>
      </span>
    <?php elseif ($t['type'] === 'number'): ?>
      <label class="lt-label" for="lt-<?= e($k) ?>"><?= e(tl($t['label'])) ?></label>
      <span class="lt-num"><input id="lt-<?= e($k) ?>" type="number" inputmode="decimal" min="0" step="<?= e((string)($t['step'] ?? 1)) ?>" value="<?= $v !== null ? e(fmt_num($v)) : '' ?>" placeholder="<?= t('day.x') ?>"> <small><?= e($t['unit'] ?? '') ?></small></span>
      <?php if (!empty($t['goal'])): $g = $t['goal']; $gn = tracker_bullets($g); $gi = tracker_icons(); ?>
      <div class="goal-bullets" role="group" aria-label="<?= e(tl($t['label'])) ?>" data-per="<?= e((string)$g['per']) ?>" data-target="<?= e((string)$g['target']) ?>" data-val="<?= $v !== null ? e(fmt_num($v)) : 0 ?>">
        <?php for ($i = 1; $i <= $gn; $i++): $on = (float)($v ?? 0) >= min($i * $g['per'], $g['target']); ?>
        <button type="button" class="gb<?= $on ? ' on' : '' ?>" data-i="<?= $i ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>" aria-label="<?= t('day.bullet_n', ['n' => $i, 'total' => $gn]) ?>"><span aria-hidden="true"><?= e($gi[$g['icon']] ?? $gi['star']) ?></span></button>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    <?php elseif ($t['type'] === 'scale'): ?>
      <span class="lt-label"><?= e(tl($t['label'])) ?></span>
      <span class="lt-scale" role="radiogroup" aria-label="<?= e(tl($t['label'])) ?>">
        <small><?= e(tl($t['low'])) ?></small>
        <?php for ($i = 1; $i <= 5; $i++): ?><button type="button" role="radio" aria-checked="<?= (int)$v === $i ? 'true' : 'false' ?>" data-v="<?= $i ?>" aria-label="<?= $i ?> of 5"><?= $i ?></button><?php endfor; ?>
        <small><?= e(tl($t['high'])) ?></small>
      </span>
    <?php elseif ($t['type'] === 'note'): ?>
      <span class="lt-label"><?= e(tl($t['label'])) ?></span>
      <ul class="lt-notes">
        <?php foreach ($t['notes'] as $n): ?>
        <li><time class="stamp" data-ts="<?= (int)$n['created_at'] ?>"><?= e(stamp_time((int)$n['created_at'])) ?></time><span><?= e($n['text']) ?></span>
          <form method="post" action="<?= url('/track/note/' . $n['id'] . '/delete') ?>"><?= csrf_field() ?><button class="textbtn" type="submit" aria-label="<?= t('day.remove_this_note') ?>"><?= t('common.remove') ?></button></form></li>
        <?php endforeach; ?>
      </ul>
      <form method="post" action="<?= url('/track/note') ?>" class="inline-form lt-add"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($k) ?>"><input type="hidden" name="day" value="<?= e($day) ?>">
        <label class="sr-only" for="ln-<?= e($k) ?>"><?= e(tl($t['label'])) ?></label>
        <input id="ln-<?= e($k) ?>" name="text" maxlength="300" placeholder="<?= e(tl($t['ph'] ?? '')) ?>" class="title-in"><button class="btn btn--quiet" type="submit"><?= t('common.add') ?></button></form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($upcoming)): $t = date('Y-m-d'); ?>
<section class="card coming" aria-label="<?= t('common.coming_up') ?>">
  <div class="water-head"><b><?= t('common.coming_up') ?></b> <span class="water-count"><?= t('day.next_7_days') ?></span></div>
  <ul class="coming-list">
  <?php $moreN = max(0, count($upcoming) - 3); foreach (array_slice($upcoming, 0, 3) as $u): $late = $u['due_date'] < $t; $when = $u['due_date'] === $t ? t('day.when_today') : ($u['due_date'] === date('Y-m-d', strtotime('+1 day')) ? t('day.when_tomorrow') : ($late ? t('day.when_overdue') : ldate('D, M j', strtotime($u['due_date'])))); ?>
    <li><a href="<?= url('/lists/' . $u['list_id']) ?>"><span class="coming-text"><?= e($u['text']) ?><small class="due"><?= e($u['list_name']) ?><?= !empty($u['assigned_name']) ? e(t('day.for_person', ['name' => (int)$u['assigned_to'] === (int)$user['id'] ? t('day.you') : $u['assigned_name']])) : '' ?></small></span><span class="when<?= $late ? ' late' : '' ?>"><?= e($when) ?></span></a></li>
  <?php endforeach; ?>
  </ul>
  <?php if ($moreN): ?><a class="link coming-more" href="<?= url('/lists') ?>"><?= t('day.more_in_lists', ['n' => $moreN]) ?></a><?php endif; ?>
</section>
<?php endif; ?>

