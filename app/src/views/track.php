<h1 class="page-title">Track</h1>
<p class="subtle">Little things worth noticing. Turn on only what feels right. They sit quietly under your journal on Today, so your words always come first.</p>
<p class="subtle"><a class="link" href="<?= url('/track/feelings') ?>">Look back at how you've been feeling</a></p>
<?php foreach ($groups as $gname => $items): ?>
<section class="card write trk-group">
  <h2 class="section-h"><?= e($gname) ?></h2>
  <ul class="trk-list">
  <?php foreach ($items as $k => $t): $isOn = isset($on[$k]); ?>
    <li id="t-<?= e($k) ?>">
      <span class="trk-name"><b><?= e($t['label']) ?></b><small><?php
        echo e(['water' => $t['hint'] ?? '', 'check' => 'A simple yes', 'count' => 'Count in ' . ($t['unit'] ?? ''), 'number' => 'Enter a number (' . ($t['unit'] ?? '') . ')', 'scale' => ($t['low'] ?? '') . ' to ' . ($t['high'] ?? '') . ', one tap', 'note' => 'Short notes, with the time'][$t['type']]);
      ?></small></span>
      <form method="post" action="<?= url('/track/toggle') ?>"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($k) ?>">
        <button type="submit" class="trk-btn<?= $isOn ? ' on' : '' ?>" aria-pressed="<?= $isOn ? 'true' : 'false' ?>"><?= $isOn ? 'On' : 'Add' ?></button>
      </form>
    </li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>
<section class="card write" id="goals">
  <h2 class="section-h">Your own checkmarks</h2>
  <p class="subtle">Anything you want to tick off each day that isn't above, like "Take a walk" or "Finish homework".</p>
  <?php foreach ($goals as $g): ?>
    <form method="post" action="<?= url('/goals/' . $g['id'] . '/delete') ?>" class="member"><?= csrf_field() ?><span><?= e($g['name']) ?></span><button class="textbtn" type="submit" aria-label="Remove <?= e($g['name']) ?>">Remove</button></form>
  <?php endforeach; ?>
  <form method="post" action="<?= url('/goals') ?>" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="gname">New checkmark</label>
    <input id="gname" name="name" maxlength="100" placeholder="For example: Take a walk" required class="title-in">
    <button class="btn" type="submit">Add</button>
  </form>
</section>
