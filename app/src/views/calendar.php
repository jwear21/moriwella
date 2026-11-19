<?php
$days = (int)date('t', $first); $lead = (int)date('w', $first);
$todayStr = date('Y-m-d');
?>
<h1 class="page-title"><?= e(date('F Y', $first)) ?></h1>
<p class="daynav"><a class="link" href="<?= url('/calendar?m=' . $prev) ?>">&larr; <?= e(date('M', strtotime($prev . '-01'))) ?></a>
  <a class="link" href="<?= url('/calendar') ?>">This month</a>
  <a class="link" href="<?= url('/calendar?m=' . $next) ?>"><?= e(date('M', strtotime($next . '-01'))) ?> &rarr;</a></p>
<div class="cal" role="grid" aria-label="<?= e(date('F Y', $first)) ?>">
  <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dn): ?><div class="cal-h" role="columnheader"><?= $dn ?></div><?php endforeach; ?>
  <?php for ($i = 0; $i < $lead; $i++): ?><div class="cal-c cal-c--empty" aria-hidden="true"></div><?php endfor; ?>
  <?php for ($d = 1; $d <= $days; $d++):
    $ds = sprintf('%s-%02d', $month, $d); $c = $counts[$ds] ?? 0; $pc = $pcounts[$ds] ?? 0; ?>
    <a class="cal-c<?= $c ? ' has' : '' ?><?= $ds === $todayStr ? ' today' : '' ?>" href="<?= url('/day/' . $ds) ?>" role="gridcell" aria-label="<?= e(date('F j', strtotime($ds))) ?><?= $c ? ', ' . $c . ' ' . ($c === 1 ? 'entry' : 'entries') : '' ?>">
      <span class="cal-n"><?= $d ?></span>
      <?php if ($c): ?><span class="cal-dot"></span><?php endif; ?>
      <?php if ($pc): ?><span class="cal-ph"><?= $pc ?> &#9635;</span><?php endif; ?>
    </a>
  <?php endfor; ?>
</div>
<p class="hint">A dot means you wrote something. Tap any day to see it or add to it.</p>
