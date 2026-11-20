<p><a class="link back" href="<?= url('/admin') ?>">&larr; Administration</a></p>
<h1 class="page-title">Demo accounts</h1>
<p class="subtle">Fake people with real-looking journals, for testing. They have no password. Use Act as on the Administration page to step into one.</p>

<?php if ($result !== null): ?>
<section class="card" role="status">
  <h2 class="section-h">What was created</h2>
  <ul class="demo-res">
  <?php foreach ($result as $r): ?>
    <li><b><?= e($r['name'] ?: 'Unnamed') ?></b>
      <?php if ($r['note']): ?><span class="hint"><?= e($r['note']) ?></span>
      <?php else: ?><span class="hint"><?= (int)$r['entries'] ?> entries, <?= (int)$r['photos'] ?> photos, <?= (int)$r['moods'] ?> moods</span><?php endif; ?>
      <?php foreach ($r['problems'] as $p): ?><span class="hint err"><?= e($p) ?></span><?php endforeach; ?>
    </li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="card write xfer">
  <h2 class="section-h">Add demo accounts</h2>
  <p>Choose the seed ZIP. Each person's files are read the same way a real import is.</p>
  <form method="post" action="<?= url('/admin/demo') ?>" enctype="multipart/form-data" data-loader="Building the demo accounts" data-detail="This can take a few minutes. Please keep this page open.">
    <?= csrf_field() ?><input type="hidden" name="seed" value="1">
    <label class="filepick"><span class="filepick-t">Choose the seed ZIP</span><span class="filepick-n" id="filepick-n">No file chosen</span><input type="file" name="seed" id="xfer-file" accept=".zip" required></label>
    <p class="hint">Files up to <?= e($maxUpload) ?>.</p>
    <button class="btn" type="submit">Create demo accounts</button>
  </form>
</section>

<section class="card write xfer">
  <h2 class="section-h">Current demo accounts</h2>
  <?php if (!$demo): ?><p class="hint">None yet.</p><?php else: ?>
  <ul class="demo-res">
    <?php foreach ($demo as $d): ?><li><b><?= e($d['name']) ?></b><span class="hint"><?= e($d['email']) ?> &middot; <?= (int)$d['entries'] ?> entries, <?= (int)$d['photos'] ?> photos</span></li><?php endforeach; ?>
  </ul>
  <form method="post" action="<?= url('/admin/demo') ?>" data-confirm="Remove every demo account, with all their entries and photos?">
    <?= csrf_field() ?><input type="hidden" name="remove" value="1"><button class="btn btn--quiet" type="submit">Remove all demo accounts</button>
  </form>
  <?php endif; ?>
</section>
