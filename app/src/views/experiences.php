<h1 class="page-title">Experiences</h1>
<p class="subtle">Trips, celebrations and big days. Start from a template, journal as you go, and keep it all together.</p>
<p><a class="btn" href="<?= url('/experiences/new') ?>">Start an experience</a></p>
<?php
function exp_card(array $ev): void { ?>
  <a class="card exp-card" href="<?= url('/experiences/' . $ev['id']) ?>">
    <b><?= e($ev['title']) ?></b>
    <span class="subtle"><?= e(date('M j', strtotime($ev['start_date']))) ?><?= $ev['end_date'] !== $ev['start_date'] ? ' to ' . e(date('M j, Y', strtotime($ev['end_date']))) : ', ' . e(date('Y', strtotime($ev['start_date']))) ?><?= $ev['location'] !== '' ? ' · ' . e($ev['location']) : '' ?></span>
    <small class="due"><?= (int)$ev['entry_count'] ?> <?= (int)$ev['entry_count'] === 1 ? 'entry' : 'entries' ?></small>
  </a>
<?php }
if (!$now && !$soon && !$past): ?>
  <p class="subtle">Nothing here yet. Planning a cruise, a wedding or a weekend away? Start one above and add to it each day.</p>
<?php endif;
if ($now): ?><h2 class="section-h">Happening now</h2><?php foreach ($now as $ev) exp_card($ev); endif;
if ($soon): ?><h2 class="section-h">Coming up</h2><?php foreach ($soon as $ev) exp_card($ev); endif;
if ($past): ?><h2 class="section-h">Past</h2><?php foreach ($past as $ev) exp_card($ev); endif; ?>
