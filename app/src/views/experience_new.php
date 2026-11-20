<p><a class="link" href="<?= url('/experiences') ?>"><?= t('common.all_experiences') ?></a></p>
<?php if (!$tpl): ?>
<h1 class="page-title"><?= t('common.start_an_experience') ?></h1>
<p class="subtle"><?= t('experience_new.pick_a_template_every') ?></p>
<?php if ($mine): ?>
  <h2 class="section-h"><?= t('experience_new.your_templates') ?></h2>
  <div class="tpl-grid">
  <?php foreach ($mine as $t): ?>
    <a class="card tpl" href="<?= url('/experiences/new') ?>?t=<?= urlencode('mine:' . $t['id']) ?>"><b><?= e(tl($t['name'])) ?></b><span class="subtle"><?= e(tn('experience_new.n_sections', count($t['sections']))) ?></span></a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<h2 class="section-h"><?= t('experience_new.starter_templates') ?></h2>
<div class="tpl-grid">
<?php foreach ($starters as $k => $t): ?>
  <a class="card tpl" href="<?= url('/experiences/new') ?>?t=<?= urlencode('starter:' . $k) ?>"><b><?= e(tl($t['name'])) ?></b><span class="subtle"><?= e(tl($t['blurb'])) ?></span></a>
<?php endforeach; ?>
</div>
<?php else: ?>
<h1 class="page-title"><?= e(tl($tpl['name'])) ?></h1>
<?php if ($tpl['sections']): ?><p class="subtle"><?= t('experience_new.sections_to_start', ['list' => implode(', ', array_column($tpl['sections'], 'name'))]) ?></p><?php endif; ?>
<section class="card write">
  <form method="post" action="<?= url('/experiences/new') ?>">
    <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($key) ?>">
    <label for="xtitle"><?= t('common.name') ?></label>
    <input id="xtitle" name="title" maxlength="150" required placeholder="<?= $tpl['key'] === 'cruise' ? t('experience_new.ph_cruise') : t('experience_new.ph_name') ?>">
    <label for="xloc"><?= t('experience_new.where_optional') ?></label>
    <input id="xloc" name="location" maxlength="150" placeholder="<?= t('experience_new.place_or_route') ?>">
    <div class="date-pair">
      <div><label for="xstart"><?= t('common.starts') ?></label><input id="xstart" type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div><label for="xend"><?= t('common.ends') ?></label><input id="xend" type="date" name="end_date" value="<?= e(date('Y-m-d')) ?>" required></div>
    </div>
    <p class="hint"><?= t('experience_new.one_night_or_several') ?></p>
    <button class="btn" type="submit"><?= t('experience_new.create') ?></button>
    <a class="btn btn--quiet" href="<?= url('/experiences/new') ?>"><?= t('experience_new.choose_a_different_template') ?></a>
  </form>
</section>
<?php endif; ?>
