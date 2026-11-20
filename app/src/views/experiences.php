<h1 class="page-title"><?= t('common.experiences') ?></h1>
<p class="subtle"><?= t('experiences.trips_celebrations_and_big') ?></p>
<p><a class="btn" href="<?= url('/experiences/new') ?>"><?= t('common.start_an_experience') ?></a></p>
<?php
function exp_card(array $ev): void { ?>
  <a class="card exp-card" href="<?= url('/experiences/' . $ev['id']) ?>">
    <b><?= e($ev['title']) ?></b>
    <span class="subtle"><?= e(ldate('M j', strtotime($ev['start_date']))) ?><?= $ev['end_date'] !== $ev['start_date'] ? ' to ' . e(ldate('M j, Y', strtotime($ev['end_date']))) : ', ' . e(date('Y', strtotime($ev['start_date']))) ?><?= $ev['location'] !== '' ? ' · ' . e($ev['location']) : '' ?></span>
    <small class="due"><?= (int)$ev['entry_count'] ?> <?= (int)$ev['entry_count'] === 1 ? 'entry' : 'entries' ?></small>
  </a>
<?php }
if (!$now && !$soon && !$past): ?>
  <p class="subtle"><?= t('experiences.nothing_here_yet_planning') ?></p>
<?php endif;
if ($now): ?><h2 class="section-h"><?= t('experiences.happening_now') ?></h2><?php foreach ($now as $ev) exp_card($ev); endif;
if ($soon): ?><h2 class="section-h"><?= t('common.coming_up') ?></h2><?php foreach ($soon as $ev) exp_card($ev); endif;
if ($past): ?><h2 class="section-h"><?= t('experiences.past') ?></h2><?php foreach ($past as $ev) exp_card($ev); endif; ?>
