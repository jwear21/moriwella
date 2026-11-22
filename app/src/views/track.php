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
        <button type="submit" class="trk-btn<?= $isOn ? ' on' : '' ?>" aria-pressed="<?= $isOn ? 'true' : 'false' ?>"><?= $isOn ? t('album.state_on') : t('common.add') ?></button>
      </form>
      <?php if ($isOn && in_array($t['type'], ['water', 'count', 'number'], true)): $gg = $goalsT[$k] ?? null; $unit = $k === 'water' ? 'oz' : tl($t['unit'] ?? ''); ?>
      <details class="goal-set"<?= isset($_GET['g']) && $_GET['g'] === $k ? ' open' : '' ?>>
        <summary><?= $gg ? e(tt('track.goal_summary', ['target' => fmt_num($gg['target']), 'unit' => $unit, 'per' => fmt_num($gg['per'])])) . ' · ' . t('track.goal_change') : t('track.goal_set') ?></summary>
        <form method="post" action="<?= url('/track/goal') ?>" class="goal-form"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($k) ?>">
          <label><?= t('track.goal_target') ?> (<?= e($unit) ?>) <input type="number" name="target" min="1" step="any" inputmode="decimal" value="<?= $gg ? e(fmt_num($gg['target'])) : '' ?>" required></label>
          <label><?= t('track.goal_per') ?> (<?= e($unit) ?>) <input type="number" name="per" min="0.01" step="any" inputmode="decimal" value="<?= $gg ? e(fmt_num($gg['per'])) : '' ?>"></label>
          <fieldset class="goal-icons"><legend><?= t('track.goal_icon') ?></legend>
            <?php foreach ($iconsT as $ik => $iv): ?>
            <label class="goal-icon"><input type="radio" name="icon" value="<?= e($ik) ?>"<?= ($gg['icon'] ?? ($k === 'water' ? 'glass' : 'star')) === $ik ? ' checked' : '' ?>><span aria-hidden="true"><?= e($iv) ?></span><small><?= e(tt('icon.' . $ik)) ?></small></label>
            <?php endforeach; ?>
          </fieldset>
          <div class="goal-acts"><button class="btn" type="submit"><?= t('track.goal_save') ?></button><?php if (!empty($t['custom'])): ?><button class="textbtn" type="submit" formaction="<?= url('/track/custom/delete') ?>" formnovalidate onclick="return confirm('<?= e(tt('track.t_delete')) ?>?')"><?= t('track.t_delete') ?></button><?php endif; ?><?php if ($gg && $k !== 'water'): ?><button class="textbtn" type="submit" name="clear" value="1" formnovalidate><?= t('track.goal_remove') ?></button><?php endif; ?></div>
        </form>
      </details>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>
<section class="card write" id="make">
  <h2 class="section-h"><?= t('track.make_tracker') ?></h2>
  <p class="subtle"><?= t('track.make_hint') ?></p>
  <form method="post" action="<?= url('/track/custom') ?>" class="goal-form"><?= csrf_field() ?>
    <label><?= t('track.t_name') ?> <input name="label" maxlength="60" required placeholder="<?= t('track.t_name_ph') ?>" class="title-in"></label>
    <label><?= t('track.t_unit') ?> <input name="unit" maxlength="20" placeholder="<?= t('track.t_unit_ph') ?>"></label>
    <label><?= t('track.t_target') ?> <input type="number" name="target" min="1" step="any" inputmode="decimal" required></label>
    <label><?= t('track.t_bullets') ?> <input type="number" name="bullets" min="1" max="24" step="1" inputmode="numeric" value="4"></label>
    <p class="hint"><?= t('track.t_bullets_note') ?></p>
    <fieldset class="goal-icons"><legend><?= t('track.goal_icon') ?></legend>
      <?php foreach ($iconsT as $ik => $iv): ?>
      <label class="goal-icon"><input type="radio" name="icon" value="<?= e($ik) ?>"<?= $ik === 'star' ? ' checked' : '' ?>><span aria-hidden="true"><?= e($iv) ?></span><small><?= e(tt('icon.' . $ik)) ?></small></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="goal-acts"><button class="btn" type="submit"><?= t('track.t_make') ?></button></div>
  </form>
</section>
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
