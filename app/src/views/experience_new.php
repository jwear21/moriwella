<p><a class="link" href="<?= url('/experiences') ?>">&larr; All experiences</a></p>
<?php if (!$tpl): ?>
<h1 class="page-title">Start an experience</h1>
<p class="subtle">Pick a template. Every section can be renamed, removed or added to afterwards.</p>
<?php if ($mine): ?>
  <h2 class="section-h">Your templates</h2>
  <div class="tpl-grid">
  <?php foreach ($mine as $t): ?>
    <a class="card tpl" href="<?= url('/experiences/new') ?>?t=<?= urlencode('mine:' . $t['id']) ?>"><b><?= e($t['name']) ?></b><span class="subtle"><?= count($t['sections']) ?> sections</span></a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<h2 class="section-h">Starter templates</h2>
<div class="tpl-grid">
<?php foreach ($starters as $k => $t): ?>
  <a class="card tpl" href="<?= url('/experiences/new') ?>?t=<?= urlencode('starter:' . $k) ?>"><b><?= e($t['name']) ?></b><span class="subtle"><?= e($t['blurb']) ?></span></a>
<?php endforeach; ?>
</div>
<?php else: ?>
<h1 class="page-title"><?= e($tpl['name']) ?></h1>
<?php if ($tpl['sections']): ?><p class="subtle">Sections to start with: <?= e(implode(', ', array_column($tpl['sections'], 'name'))) ?>.</p><?php endif; ?>
<section class="card write">
  <form method="post" action="<?= url('/experiences/new') ?>">
    <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($key) ?>">
    <label for="xtitle">Name</label>
    <input id="xtitle" name="title" maxlength="150" required placeholder="<?= $tpl['key'] === 'cruise' ? 'Caribbean cruise, November' : 'Give it a name' ?>">
    <label for="xloc">Where (optional)</label>
    <input id="xloc" name="location" maxlength="150" placeholder="Place or route">
    <div class="date-pair">
      <div><label for="xstart">Starts</label><input id="xstart" type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div><label for="xend">Ends</label><input id="xend" type="date" name="end_date" value="<?= e(date('Y-m-d')) ?>" required></div>
    </div>
    <p class="hint">One night or several weeks. You can change the dates later.</p>
    <button class="btn" type="submit">Create</button>
    <a class="btn btn--quiet" href="<?= url('/experiences/new') ?>">Choose a different template</a>
  </form>
</section>
<?php endif; ?>
