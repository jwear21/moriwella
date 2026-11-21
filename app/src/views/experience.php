<p><a class="link" href="<?= url('/experiences') ?>"><?= t('common.all_experiences') ?></a></p>
<h1 class="page-title"><?= e($ev['title']) ?></h1>
<p class="subtle"><?= e(ldate('M j', strtotime($ev['start_date']))) ?><?= $ev['end_date'] !== $ev['start_date'] ? ' to ' . e(ldate('M j, Y', strtotime($ev['end_date']))) : ', ' . e(date('Y', strtotime($ev['start_date']))) ?><?= $ev['location'] !== '' ? ' · ' . e($ev['location']) : '' ?>
<?php if ($dayNo): ?> · <?= t('experience.day_of', ['n' => $dayNo, 'total' => $total]) ?><?php elseif ($upTo): ?> · <?= e(tn('experience.starts_in', (int)$upTo)) ?><?php endif; ?></p>

<section class="card write">
  <form method="post" action="<?= url('/entries') ?>" id="entry-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
    <div class="date-pair">
      <div><label for="entry-date"><?= t('experience.day') ?></label><input id="entry-date" type="date" name="entry_date" value="<?= e($addDate) ?>" min="<?= e($ev['start_date']) ?>" max="<?= e($ev['end_date']) ?>"></div>
      <?php if ($sections): ?><div><label for="section-pick"><?= t('experience.section') ?></label>
        <select id="section-pick" name="section_id">
          <option value="" data-prompt=""><?= t('experience.no_section') ?></option>
          <?php foreach ($sections as $sc): ?><option value="<?= (int)$sc['id'] ?>" data-prompt="<?= e($sc['prompt']) ?>"<?= $filter === (int)$sc['id'] ? ' selected' : '' ?>><?= e($sc['name']) ?></option><?php endforeach; ?>
        </select></div><?php endif; ?>
    </div>
    <label class="sr-only" for="title"><?= t('common.title_optional') ?></label>
    <input id="title" name="title" maxlength="200" placeholder="<?= t('common.title_optional') ?>" class="title-in journal-field">
    <label class="sr-only" for="body"><?= t('experience.what_happened') ?></label>
    <textarea id="body" name="body" rows="6" placeholder="<?= t('experience.what_happened') ?>" class="journal-field"></textarea>
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
    <div class="write-foot"><span class="hint" id="draft-state" aria-live="polite"></span><button class="btn" type="submit" id="save-btn"><?= t('experience.add_to_this_experience') ?></button></div>
  </form>
</section>

<?php if ($sections): ?>
<div class="chips" role="group" aria-label="<?= t('experience.filter_by_section') ?>">
  <a class="chip<?= !$filter ? ' on' : '' ?>" href="<?= url('/experiences/' . $ev['id']) ?>"><?= t('experience.all') ?></a>
  <?php foreach ($sections as $sc): ?><a class="chip<?= $filter === (int)$sc['id'] ? ' on' : '' ?>" href="<?= url('/experiences/' . $ev['id']) ?>?s=<?= (int)$sc['id'] ?>"><?= e($sc['name']) ?></a><?php endforeach; ?>
</div>
<?php endif; ?>

<?php $names = array_column($sections, 'name', 'id'); ?>
<?php if (!$entries): ?>
  <p class="subtle"><?= $filter ? t('experience.nothing_section') : t('experience.nothing_yet') ?></p>
<?php else: $lastDay = ''; foreach ($entries as $en):
  if ($en['entry_date'] !== $lastDay): $lastDay = $en['entry_date']; $dn = days_between($ev['start_date'], $en['entry_date']) + 1; ?>
    <h2 class="section-h"><?= e(ldate('l, F j', strtotime($en['entry_date']))) ?><?php if ($dn >= 1 && $dn <= $total && $total > 1): ?> <small class="due"><?= t('experience.day_n', ['n' => $dn]) ?></small><?php endif; ?></h2>
  <?php endif; ?>
  <article class="card entry-card">
    <?php if (!empty($en['section_id']) && isset($names[$en['section_id']])): ?><span class="chip chip--static"><?= e($names[$en['section_id']]) ?></span><?php endif; ?>
    <?php if ($en['title'] !== ''): ?><h3><?= e($en['title']) ?></h3><?php endif; ?>
    <?php foreach (body_parts($en['body'], (int)$en['created_at']) as $bp): ?><p class="entry-body"><?php if ($bp['ts']): ?><time class="stamp" data-ts="<?= (int)$bp['ts'] ?>"><?= e(stamp_time($bp['ts'])) ?></time> <?php endif; ?><?= nl2br(e($bp['text'])) ?></p><?php endforeach; ?>
    <?php if ($en['photos']): ?><div class="thumbs"><?php foreach ($en['photos'] as $p): ?>
      <div class="thumb" data-photo="<?= (int)$p['id'] ?>">
        <a href="<?= url('/photo/' . $p['id'] . '/web') . '?v=' . (int)$p['edited_at'] ?>" target="_blank" rel="noopener"><img src="<?= url('/photo/' . $p['id'] . '/thumb') . '?v=' . (int)$p['edited_at'] ?>" alt="<?= t('common.photo_from', ['title' => $ev['title']]) ?>" loading="lazy"></a>
        <button type="button" class="thumb-edit" data-edit-photo="<?= (int)$p['id'] ?>" aria-label="<?= t('common.edit_this_photo') ?>"><?= t('common.edit') ?></button>
      </div>
    <?php endforeach; ?></div><?php endif; ?>
    <p><a class="link" href="<?= url('/entries/' . $en['id']) ?>"><?= t('common.edit') ?></a></p>
  </article>
<?php endforeach; endif; ?>

<?php if ($photoTotal >= 3 || $movies): $busy = false; foreach ($movies as $mv) if (in_array($mv['status'], ['queued', 'rendering'], true)) $busy = true; ?>
<h2 class="section-h" id="movie"><?= t('experience.memory_movie') ?></h2>
<section class="card movie-card">
  <p class="subtle"><?= t('experience.turn_this_experience_into') ?></p>
  <?php foreach ($movies as $mv): ?>
    <div class="movie-row" data-movie="<?= (int)$mv['id'] ?>" data-status="<?= e($mv['status']) ?>" data-url="<?= url('/movies/' . $mv['id'] . '/status') ?>">
      <?php if ($mv['status'] === 'done'): ?>
        <video controls playsinline preload="metadata" src="<?= url('/movies/' . $mv['id'] . '/file') ?>"></video>
        <p class="hint"><?= e(tl(MV_THEMES[$mv['theme']]['name'] ?? '')) ?> · <?= t('experience.movie_meta', ['seconds' => (int)round($mv['seconds']), 'mb' => round($mv['bytes'] / 1048576, 1), 'date' => ldate('M j', (int)$mv['finished_at'])]) ?></p>
        <?php $cn = movie_credit_note((int)$mv['id']); if ($cn['spent']): ?><p class="hint"><?= t('billing.movie_used', ['kind' => t('billing.kind_' . $cn['spent'])]) ?></p><?php endif; ?>
        <p class="movie-actions"><a class="btn btn--quiet" href="<?= url('/movies/' . $mv['id'] . '/file') ?>?dl=1"><?= t('experience.download') ?></a>
          <form method="post" action="<?= url('/movies/' . $mv['id'] . '/delete') ?>" data-confirm="<?= t('experience.delete_this_movie_your') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('experience.delete') ?></button></form></p>
      <?php elseif ($mv['status'] === 'failed'): ?>
        <p class="err-text"><?= e($mv['error']) ?></p>
        <?php if (movie_credit_note((int)$mv['id'])['returned']): ?><p class="hint"><?= t('billing.movie_returned') ?></p><?php endif; ?>
        <form method="post" action="<?= url('/movies/' . $mv['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('experience.dismiss') ?></button></form>
      <?php else: ?>
        <p><strong class="movie-state"><?= $mv['status'] === 'queued' ? t('experience.movie_waiting') : t('experience.movie_making') ?></strong> <span class="hint movie-pct"><?= (int)$mv['progress'] ?>%</span></p>
        <div class="progress"><div class="bar" style="width:<?= (int)$mv['progress'] ?>%"></div></div>
        <p class="hint"><?= t('experience.you_can_leave_this') ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$busy && $photoTotal >= 3): ?>
  <details class="movie-new"<?= $movies ? '' : ' open' ?>>
    <summary><?= $movies ? t('experience.movie_another') : t('experience.movie_make') ?></summary>
    <form method="post" action="<?= url('/experiences/' . $ev['id'] . '/movie') ?>">
      <?= csrf_field() ?>
      <label for="mv-theme"><?= t('experience.look') ?></label>
      <select id="mv-theme" name="theme"><?php foreach (MV_THEMES as $k => $t): ?><option value="<?= e($k) ?>"><?= e(tl($t['name'])) ?></option><?php endforeach; ?></select>
      <label for="mv-len"><?= t('experience.length') ?></label>
      <select id="mv-len" name="length"><?php foreach (MV_LENGTHS as $k => $t): ?><option value="<?= e($k) ?>"<?= $k === 'full' ? ' selected' : '' ?>><?= e(tl($t['name'])) ?></option><?php endforeach; ?></select>
      <label for="mv-sub"><?= t('experience.opening_line_optional') ?></label>
      <input id="mv-sub" name="subtitle" maxlength="90" placeholder="<?= t('experience.for_the_family_with') ?>">
      <label for="mv-close"><?= t('experience.closing_note_optional') ?></label>
      <input id="mv-close" name="closing" maxlength="160" placeholder="<?= t('experience.thank_you_for_the') ?>">
      <?php $cb = credits_balance((int)$user['id']); ?>
      <?php if ($cb['available'] > 0): ?>
      <div class="credit-explain" role="note">
        <p><strong><?= t('billing.cost_line', ['n' => $cb['available']]) ?></strong></p>
        <ul>
          <?php if ($cb['trial'] > 0): ?><li><?= t('billing.cost_trial', ['n' => $cb['trial']]) ?></li><?php endif; ?>
          <?php if ($cb['included'] > 0): ?><li><?= t('billing.cost_included', ['n' => $cb['included'], 'date' => ldate('M j', (int)$cb['renews'])]) ?></li><?php endif; ?>
          <?php if ($cb['purchased'] > 0): ?><li><?= t('billing.cost_purchased', ['n' => $cb['purchased']]) ?></li><?php endif; ?>
        </ul>
        <p class="hint"><?= t('billing.cost_order', ['left' => max(0, $cb['available'] - 1)]) ?></p>
        <p class="hint"><?= t('billing.cost_retry') ?> <a class="link" href="<?= url('/billing') ?>#credits"><?= t('menu.buy_credits') ?></a></p>
      </div>
      <button class="btn" type="submit"><?= t('experience.make_my_movie') ?></button>
      <?php else: ?>
      <p class="hint"><?= t('billing.none_left') ?></p>
      <a class="btn" href="<?= url('/billing') ?>#credits"><?= t('menu.get_credit') ?></a>
      <?php endif; ?>
    </form>
  </details>
  <?php endif; ?>
</section>
<script src="<?= url('/static/movie.js') ?>" defer></script>
<?php endif; ?>

<h2 class="section-h" id="sections"><?= t('experience.sections') ?></h2>
<section class="card write">
  <p class="subtle"><?= t('experience.rename_them_change_the') ?></p>
  <?php foreach ($sections as $sc): $base = url('/experiences/' . $ev['id'] . '/section/' . $sc['id']); ?>
    <details class="sec-edit">
      <summary><?= e($sc['name']) ?></summary>
      <form method="post" action="<?= $base ?>/rename">
        <?= csrf_field() ?>
        <label for="sn<?= (int)$sc['id'] ?>"><?= t('common.name') ?></label><input id="sn<?= (int)$sc['id'] ?>" name="name" maxlength="80" value="<?= e($sc['name']) ?>" required>
        <label for="sp<?= (int)$sc['id'] ?>"><?= t('experience.prompt_optional') ?></label><input id="sp<?= (int)$sc['id'] ?>" name="prompt" maxlength="200" value="<?= e($sc['prompt']) ?>">
        <button class="btn" type="submit"><?= t('common.save') ?></button>
      </form>
      <form method="post" action="<?= $base ?>/delete" data-confirm="<?= t('experience.remove_this_section_entries') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('experience.remove_section') ?></button></form>
    </details>
  <?php endforeach; ?>
  <form method="post" action="<?= url('/experiences/' . $ev['id'] . '/sections') ?>" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="newsec"><?= t('experience.new_section') ?></label>
    <input id="newsec" name="name" maxlength="80" placeholder="<?= t('experience.add_a_section_for') ?>" required class="title-in">
    <button class="btn" type="submit"><?= t('experience.add_section') ?></button>
  </form>
  <form method="post" action="<?= url('/experiences/' . $ev['id'] . '/save-template') ?>" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="tname"><?= t('experience.template_name') ?></label>
    <input id="tname" name="name" maxlength="80" placeholder="<?= t('experience.save_these_sections_as') ?>" class="title-in">
    <button class="btn btn--quiet" type="submit"><?= t('experience.save_as_template') ?></button>
  </form>
</section>

<h2 class="section-h"><?= t('experience.details') ?></h2>
<section class="card write">
  <form method="post" action="<?= url('/experiences/' . $ev['id'] . '/update') ?>">
    <?= csrf_field() ?>
    <label for="etitle"><?= t('common.name') ?></label><input id="etitle" name="title" maxlength="150" value="<?= e($ev['title']) ?>" required>
    <label for="eloc"><?= t('experience.where') ?></label><input id="eloc" name="location" maxlength="150" value="<?= e($ev['location']) ?>">
    <div class="date-pair">
      <div><label for="es"><?= t('common.starts') ?></label><input id="es" type="date" name="start_date" value="<?= e($ev['start_date']) ?>" required></div>
      <div><label for="ee"><?= t('common.ends') ?></label><input id="ee" type="date" name="end_date" value="<?= e($ev['end_date']) ?>" required></div>
    </div>
    <button class="btn" type="submit"><?= t('experience.save_details') ?></button>
  </form>
  <form method="post" action="<?= url('/experiences/' . $ev['id'] . '/delete') ?>" class="danger" data-confirm="<?= t('experience.remove_this_experience_your') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('experience.remove_experience') ?></button></form>
</section>
<?php require __DIR__ . "/keepsakes.php"; ?>
