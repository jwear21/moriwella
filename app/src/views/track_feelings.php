<?php $faces = feelings_faces(); $label = [30 => t('track_feelings.last_30'), 90 => t('track_feelings.last_90'), 365 => t('track_feelings.last_365')]; ?>
<p><a class="link" href="<?= url('/track') ?>"><?= t('track_feelings.track') ?></a></p>
<h1 class="page-title"><?= t('common.how_you_ve_been') ?></h1>
<div class="chips" role="group" aria-label="<?= t('track_feelings.time_span') ?>">
  <?php foreach ($label as $n => $t): ?><a class="chip<?= $sum['days'] === $n ? ' on' : '' ?>" href="<?= url('/track/feelings') ?>?d=<?= $n ?>"><?= e($t) ?></a><?php endforeach; ?>
</div>
<?php if (!$sum['logged']): ?>
  <section class="card"><p class="subtle"><?= t('track_feelings.nothing_here_yet_tap') ?></p></section>
<?php else: $top = max($sum['counts']); $avgFace = $faces[max(1, min(5, (int)round($sum['avg'])))]; ?>
  <section class="card feel-sum">
    <div class="feel-big" role="img" aria-label="<?= t('track_feelings.average_mood', ['mood' => $avgFace['l']]) ?>"><?= $avgFace['e'] ?></div>
    <p><?= t('track_feelings.you_noted_how_you') ?> <?= t('track_feelings.summary', ['logged' => new Raw('<b>' . (int)$sum['logged'] . '</b>'), 'days' => (int)$sum['days'], 'top' => new Raw('<b>' . e(mb_strtolower($faces[array_search($top, $sum['counts'])]['l'])) . '</b>')]) ?></p>
  </section>
  <section class="card">
    <h2 class="section-h"><?= t('track_feelings.day_by_day') ?></h2>
    <div class="feel-grid feel-grid--<?= $sum['days'] > 90 ? 'sm' : 'md' ?>">
      <?php foreach ($sum['cells'] as $c): ?><span class="feel-cell mood-<?= (int)$c['v'] ?>" title="<?= e(ldate('M j', strtotime($c['day']))) ?><?= $c['v'] ? ': ' . e($faces[$c['v']]['l']) : '' ?>"><?= ($c['v'] && $sum['days'] <= 90) ? $faces[$c['v']]['e'] : '' ?></span><?php endforeach; ?>
    </div>
  </section>
  <section class="card">
    <h2 class="section-h"><?= t('track_feelings.how_the_days_added') ?></h2>
    <ul class="feel-bars">
      <?php foreach ([5, 4, 3, 2, 1] as $v): ?><li><span class="fb-face" aria-hidden="true"><?= $faces[$v]['e'] ?></span><span class="fb-name"><?= e($faces[$v]['l']) ?></span><span class="fb-track"><span class="fb-fill mood-<?= $v ?>" style="width:<?= $top ? round(100 * $sum['counts'][$v] / $top) : 0 ?>%"></span></span><span class="fb-n"><?= (int)$sum['counts'][$v] ?></span></li><?php endforeach; ?>
    </ul>
  </section>
  <?php if ($sum['feelings']): ?>
  <section class="card">
    <h2 class="section-h"><?= t('track_feelings.feelings_you_named_most') ?></h2>
    <div class="chips chips--wrap"><?php foreach ($sum['feelings'] as $f): ?><span class="chip chip--static"><?= e($f['feeling']) ?> <small><?= (int)$f['c'] ?></small></span><?php endforeach; ?></div>
  </section>
  <?php endif; ?>
<?php endif; ?>
