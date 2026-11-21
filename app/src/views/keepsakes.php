<?php
/* Shared album and printable scrapbook sections of an experience. Variables from the page: $ev, $user, $photoTotal. */
$uid = (int)$user['id']; $evId = (int)$ev['id'];
$albums = albums_for_event($evId, $uid); $sbs = scrapbooks_for_event($evId, $uid); $eph = event_photo_list($evId, $uid);
$sbBusy = false; foreach ($sbs as $b) if (in_array($b['status'], ['queued', 'rendering'], true)) $sbBusy = true;
$pre = sb_preflight($evId, $uid);
?>
<?php if ($eph || $albums): ?>
<h2 class="section-h" id="album"><?= t('album.section') ?></h2>
<section class="card keep-card">
  <p class="subtle"><?= t('album.section_intro') ?></p>
  <?php foreach ($albums as $al): $st = album_state($al); $link = album_link($al); ?>
  <div class="album-row" data-state="<?= e($st) ?>">
    <div class="album-head"><strong><?= e($al['title']) ?></strong>
      <span class="tag album-tag album-tag--<?= e($st) ?>"><?= t('album.state_' . $st) ?></span></div>
    <p class="hint"><?= e(tn('album.photo_count', (int)$al['n'])) ?> · <?= (int)$al['expires_at'] > 0 ? t('album.until', ['date' => ldate('M j, Y', (int)$al['expires_at'])]) : t('album.no_expiry') ?> · <?= t('album.stats', ['views' => (int)$al['views']]) ?></p>
    <?php if ($st === 'on'): ?>
    <div class="album-link"><input type="text" readonly value="<?= e($link) ?>" aria-label="<?= e(t('album.copy')) ?>" data-copy-src><button type="button" class="btn btn--quiet" data-copy data-done="<?= e(t('album.copied')) ?>"><?= t('album.copy') ?></button></div>
    <?php endif; ?>
    <div class="album-acts">
      <?php if ($st === 'on'): ?><a class="btn btn--quiet" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= t('album.open') ?></a><?php endif; ?>
      <?php if ($st === 'on'): ?><form method="post" action="<?= url('/albums/' . $al['id'] . '/off') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('album.turn_off') ?></button></form>
      <?php elseif ($st === 'off'): ?><form method="post" action="<?= url('/albums/' . $al['id'] . '/on') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('album.turn_on') ?></button></form><?php endif; ?>
      <form method="post" action="<?= url('/albums/' . $al['id'] . '/expiry') ?>" class="album-exp"><?= csrf_field() ?>
        <select name="expires" aria-label="<?= e(t('album.expires')) ?>"><?php foreach (ALBUM_EXPIRY_DAYS as $d): ?><option value="<?= $d ?>"><?= t('album.exp_' . $d) ?></option><?php endforeach; ?></select>
        <button class="btn btn--quiet" type="submit"><?= t('album.change_expiry') ?></button></form>
      <form method="post" action="<?= url('/albums/' . $al['id'] . '/delete') ?>" data-confirm="<?= t('album.delete_confirm') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('album.delete') ?></button></form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if ($eph): ?>
  <details class="keep-new"<?= $albums ? '' : ' open' ?>>
    <summary><?= $albums ? t('album.make_another') : t('album.make') ?></summary>
    <form method="post" action="<?= url('/experiences/' . $evId . '/album') ?>" data-pick-form>
      <?= csrf_field() ?>
      <label for="al-title"><?= t('album.title') ?></label>
      <input id="al-title" name="title" maxlength="100" value="<?= e($ev['title']) ?>">
      <label for="al-note"><?= t('album.note') ?></label>
      <input id="al-note" name="note" maxlength="400" placeholder="<?= e(t('album.note_ph')) ?>">
      <label for="al-exp"><?= t('album.expires') ?></label>
      <select id="al-exp" name="expires"><?php foreach (ALBUM_EXPIRY_DAYS as $d): ?><option value="<?= $d ?>"<?= $d === 30 ? ' selected' : '' ?>><?= t('album.exp_' . $d) ?></option><?php endforeach; ?></select>
      <label class="check"><input type="checkbox" name="downloads" value="1" checked> <?= t('album.downloads') ?></label>
      <p class="pick-head"><strong><?= t('album.choose') ?></strong> <span class="hint" data-pick-count data-fmt="<?= e(t('album.choose_count', ['n' => '{n}', 'total' => '{total}'])) ?>"></span>
        <button type="button" class="linkbtn" data-pick-all><?= t('album.select_all') ?></button> <button type="button" class="linkbtn" data-pick-none><?= t('album.select_none') ?></button></p>
      <div class="pick-grid">
        <?php foreach ($eph as $p): ?>
        <label class="pick"><input type="checkbox" name="photos[]" value="<?= (int)$p['id'] ?>" checked><img src="<?= url('/photo/' . $p['id'] . '/thumb') . '?v=' . (int)$p['edited_at'] ?>" alt="" loading="lazy"></label>
        <?php endforeach; ?>
      </div>
      <button class="btn" type="submit"><?= t('album.create') ?></button>
    </form>
  </details>
  <?php endif; ?>
  <p class="hint"><a class="link" href="<?= url('/albums') ?>"><?= t('album.see_all') ?></a></p>
</section>
<?php endif; ?>

<?php if ($eph || $sbs): ?>
<h2 class="section-h" id="scrapbook"><?= t('scrapbook.section') ?></h2>
<section class="card keep-card scrap-card">
  <p class="subtle"><?= t('scrapbook.intro') ?></p>
  <?php foreach ($sbs as $b): ?>
  <div class="scrap-row" data-scrap="<?= (int)$b['id'] ?>" data-status="<?= e($b['status']) ?>" data-url="<?= url('/scrapbooks/' . $b['id'] . '/status') ?>">
    <?php if ($b['status'] === 'done'): ?>
      <p><strong><?= e(tl(SB_STYLES[$b['style']]['name'] ?? '')) ?></strong> · <span class="hint"><?= t('scrapbook.meta', ['pages' => (int)$b['pages'], 'mb' => round($b['bytes'] / 1048576, 1), 'date' => ldate('M j', (int)$b['finished_at'])]) ?></span></p>
      <?php if ((int)$b['soft'] > 0): ?><p class="warn-text"><?= t('scrapbook.soft_done', ['n' => (int)$b['soft']]) ?></p><?php endif; ?>
      <div class="keep-acts"><a class="btn" href="<?= url('/scrapbooks/' . $b['id'] . '/file') ?>" target="_blank" rel="noopener"><?= t('scrapbook.open_pdf') ?></a>
        <a class="btn btn--quiet" href="<?= url('/scrapbooks/' . $b['id'] . '/file') ?>?dl=1"><?= t('scrapbook.download') ?></a>
        <form method="post" action="<?= url('/scrapbooks/' . $b['id'] . '/delete') ?>" data-confirm="<?= t('scrapbook.delete_confirm') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('scrapbook.delete') ?></button></form></div>
    <?php elseif ($b['status'] === 'failed'): ?>
      <p class="err-text"><?= e($b['error']) ?></p>
      <form method="post" action="<?= url('/scrapbooks/' . $b['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('scrapbook.dismiss') ?></button></form>
    <?php else: ?>
      <p><strong class="scrap-state"><?= $b['status'] === 'queued' ? t('scrapbook.waiting') : t('scrapbook.making') ?></strong> <span class="hint scrap-pct"><?= (int)$b['progress'] ?>%</span></p>
      <div class="progress"><div class="bar" style="width:<?= (int)$b['progress'] ?>%"></div></div>
      <p class="hint"><?= t('scrapbook.leave') ?></p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (!$sbBusy && $eph): ?>
  <details class="keep-new"<?= $sbs ? '' : ' open' ?>>
    <summary><?= $sbs ? t('scrapbook.make_another') : t('scrapbook.make') ?></summary>
    <form method="post" action="<?= url('/experiences/' . $evId . '/scrapbook') ?>">
      <?= csrf_field() ?>
      <label for="sb-style"><?= t('scrapbook.look') ?></label>
      <select id="sb-style" name="style"><?php foreach (SB_STYLES as $k => $s): ?><option value="<?= e($k) ?>"><?= t('scrapbook.style_' . $k) ?></option><?php endforeach; ?></select>
      <label for="sb-size"><?= t('scrapbook.paper') ?></label>
      <select id="sb-size" name="size"><?php foreach (SB_SIZES as $k => $s): ?><option value="<?= e($k) ?>"><?= t('scrapbook.size_' . $k) ?></option><?php endforeach; ?></select>
      <label for="sb-text"><?= t('scrapbook.text') ?></label>
      <select id="sb-text" name="text_mode"><option value="captions"><?= t('scrapbook.text_captions') ?></option><option value="excerpt"><?= t('scrapbook.text_excerpt') ?></option></select>
      <label for="sb-sub"><?= t('scrapbook.opening') ?></label>
      <input id="sb-sub" name="subtitle" maxlength="90" placeholder="<?= e(t('scrapbook.opening_ph')) ?>">
      <label for="sb-close"><?= t('scrapbook.closing') ?></label>
      <input id="sb-close" name="closing" maxlength="160" placeholder="<?= e(t('scrapbook.closing_ph')) ?>">
      <?php if ($pre['small'] > 0): ?><p class="warn-text"><?= t('scrapbook.soft_warn', ['n' => $pre['small'], 'total' => $pre['total']]) ?></p><?php endif; ?>
      <button class="btn" type="submit"><?= t('scrapbook.make') ?></button>
    </form>
  </details>
  <?php endif; ?>
</section>
<?php endif; ?>
<script src="<?= url('/static/keepsakes.js') ?>?v=<?= asset_v('static/keepsakes.js') ?>" defer></script>
