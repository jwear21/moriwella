<h1 class="page-title"><?= t('common.track') ?></h1>
<p class="subtle"><?= t('track.little_things_worth_noticing') ?></p>
<p class="subtle"><a class="link" href="<?= url('/track/feelings') ?>"><?= t('track.look_back_at_how') ?></a></p>
<?php foreach ($groups as $gname => $items): ?>
<section class="card write trk-group">
  <h2 class="section-h"><?= e($gname) ?></h2>
  <ul class="trk-list">
  <?php foreach ($items as $k => $t): $isOn = isset($on[$k]); ?>
    <li id="t-<?= e($k) ?>">
      <span class="trk-name"><b><?= e(tl($t['label'])) ?></b><small><?php
        echo e(['water' => tl($t['hint'] ?? ''), 'check' => tt('track.hint_check'), 'count' => tt('track.hint_count', ['unit' => tl($t['unit'] ?? '')]), 'number' => tt('track.hint_number', ['unit' => tl($t['unit'] ?? '')]), 'scale' => tt('track.hint_scale', ['low' => tl($t['low'] ?? ''), 'high' => tl($t['high'] ?? '')]), 'note' => tt('track.hint_note')][$t['type']]);
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
  <h2 class="section-h"><?= t('track.your_own_checkmarks') ?></h2>
  <p class="subtle"><?= t('track.anything_you_want_to') ?></p>
  <?php foreach ($goals as $g): ?>
    <form method="post" action="<?= url('/goals/' . $g['id'] . '/delete') ?>" class="member"><?= csrf_field() ?><span><?= e($g['name']) ?></span><button class="textbtn" type="submit" aria-label="<?= t('track.remove_goal', ['name' => $g['name']]) ?>"><?= t('common.remove') ?></button></form>
  <?php endforeach; ?>
  <form method="post" action="<?= url('/goals') ?>" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="gname"><?= t('track.new_checkmark') ?></label>
    <input id="gname" name="name" maxlength="100" placeholder="<?= t('track.for_example_take_a') ?>" required class="title-in">
    <button class="btn" type="submit"><?= t('common.add') ?></button>
  </form>
</section>
