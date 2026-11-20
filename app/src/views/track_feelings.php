<?php $faces = feelings_faces(); $label = [30 => 'Last 30 days', 90 => 'Last 3 months', 365 => 'Last year']; ?>
<p><a class="link" href="<?= url('/track') ?>">&larr; Track</a></p>
<h1 class="page-title">How you've been feeling</h1>
<div class="chips" role="group" aria-label="Time span">
  <?php foreach ($label as $n => $t): ?><a class="chip<?= $sum['days'] === $n ? ' on' : '' ?>" href="<?= url('/track/feelings') ?>?d=<?= $n ?>"><?= e($t) ?></a><?php endforeach; ?>
</div>
<?php if (!$sum['logged']): ?>
  <section class="card"><p class="subtle">Nothing here yet. Tap a face on Today whenever you like, and this page will start to show the shape of your days.</p></section>
<?php else: $top = max($sum['counts']); $avgFace = $faces[max(1, min(5, (int)round($sum['avg'])))]; ?>
  <section class="card feel-sum">
    <div class="feel-big" role="img" aria-label="Average mood: <?= e($avgFace['l']) ?>"><?= $avgFace['e'] ?></div>
    <p>You noted how you felt on <b><?= (int)$sum['logged'] ?></b> of the last <?= (int)$sum['days'] ?> days. Most often it was <b><?= e(strtolower($faces[array_search($top, $sum['counts'])]['l'])) ?></b>.</p>
  </section>
  <section class="card">
    <h2 class="section-h">Day by day</h2>
    <div class="feel-grid feel-grid--<?= $sum['days'] > 90 ? 'sm' : 'md' ?>">
      <?php foreach ($sum['cells'] as $c): ?><span class="feel-cell mood-<?= (int)$c['v'] ?>" title="<?= e(date('M j', strtotime($c['day']))) ?><?= $c['v'] ? ': ' . e($faces[$c['v']]['l']) : '' ?>"><?= ($c['v'] && $sum['days'] <= 90) ? $faces[$c['v']]['e'] : '' ?></span><?php endforeach; ?>
    </div>
  </section>
  <section class="card">
    <h2 class="section-h">How the days added up</h2>
    <ul class="feel-bars">
      <?php foreach ([5, 4, 3, 2, 1] as $v): ?><li><span class="fb-face" aria-hidden="true"><?= $faces[$v]['e'] ?></span><span class="fb-name"><?= e($faces[$v]['l']) ?></span><span class="fb-track"><span class="fb-fill mood-<?= $v ?>" style="width:<?= $top ? round(100 * $sum['counts'][$v] / $top) : 0 ?>%"></span></span><span class="fb-n"><?= (int)$sum['counts'][$v] ?></span></li><?php endforeach; ?>
    </ul>
  </section>
  <?php if ($sum['feelings']): ?>
  <section class="card">
    <h2 class="section-h">Feelings you named most</h2>
    <div class="chips chips--wrap"><?php foreach ($sum['feelings'] as $f): ?><span class="chip chip--static"><?= e($f['feeling']) ?> <small><?= (int)$f['c'] ?></small></span><?php endforeach; ?></div>
  </section>
  <?php endif; ?>
<?php endif; ?>
