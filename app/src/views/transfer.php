<p><a class="link back" href="<?= url('/settings') ?>">&larr; Account</a></p>
<h1 class="page-title">Import and export</h1>
<p class="subtle">Your words are yours. Take them with you, or bring them here from another app.</p>

<section class="card write xfer" id="export">
  <h2 class="section-h">Export your journal</h2>
  <p>A ZIP file with one readable page for every day, your original photos, and a data file holding every exact date and time. You can import it back into Moriwella any time.</p>
  <?php if ($entryCount > 0): ?>
  <form method="post" action="<?= url('/transfer/export') ?>" data-download="Packing up your journal" data-detail="This can take a minute if you have a lot of photos.">
    <?= csrf_field() ?>
    <button class="btn" type="submit">Download my journal</button>
    <span class="hint"><?= (int)$entryCount ?> <?= $entryCount === 1 ? 'entry' : 'entries' ?></span>
  </form>
  <?php else: ?>
  <p class="hint">There is nothing to export yet. Once you have written an entry, it will show up here.</p>
  <?php endif; ?>
</section>

<section class="card write xfer" id="import">
  <h2 class="section-h">Bring entries from another app</h2>
  <p>Choose where your file came from. You will see a preview before anything is added, and you can undo an import afterwards.</p>
  <form method="post" action="<?= url('/transfer/upload') ?>" enctype="multipart/form-data" data-loader="Reading your file" data-detail="Almost there. Nothing is added yet.">
    <?= csrf_field() ?>
    <div class="src-list" role="radiogroup" aria-label="Where did your file come from?">
      <?php $first = true; foreach ($sources as $key => $s): ?>
      <label class="src<?= $s['ready'] ? '' : ' src--soon' ?>">
        <input type="radio" name="source" value="<?= e($key) ?>" data-accept="<?= e($s['accept']) ?>"<?= $s['ready'] ? '' : ' disabled' ?><?= ($s['ready'] && $first) ? ' checked' : '' ?>>
        <span class="src-body"><b><?= e($s['name']) ?></b><small><?= e($s['help']) ?></small></span>
        <?php if (!$s['ready']): ?><span class="src-tag">Coming soon</span><?php endif; ?>
      </label>
      <?php if ($s['ready']) $first = false; endforeach; ?>
    </div>
    <label class="filepick"><span class="filepick-t">Choose your file</span><span class="filepick-n" id="filepick-n">No file chosen</span><input type="file" name="file" id="xfer-file" accept=".zip" required></label>
    <p class="hint">Files up to <?= e($maxUpload) ?>.</p>
    <button class="btn" type="submit">Read my file</button>
  </form>
</section>

<?php if ($imports): ?>
<section class="card write xfer">
  <h2 class="section-h">Recent imports</h2>
  <ul class="trk-list">
  <?php foreach ($imports as $im): ?>
    <li><span class="trk-name"><b><?= e($sources[$im['source']]['name'] ?? $im['source']) ?></b><small><?= (int)$im['entries'] ?> <?= (int)$im['entries'] === 1 ? 'entry' : 'entries' ?><?= $im['photos'] ? ', ' . (int)$im['photos'] . ' photos' : '' ?><?= !empty($im['moods']) ? ', ' . (int)$im['moods'] . ' days of mood' : '' ?> &middot; <?= e(date('M j, Y', (int)$im['created_at'])) ?></small></span>
      <form method="post" action="<?= url('/transfer/undo/' . $im['batch']) ?>" data-confirm="Remove everything that came in with this import? Your other entries are not touched."><?= csrf_field() ?><button class="trk-btn" type="submit">Undo</button></form></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
