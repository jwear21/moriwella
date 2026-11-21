<?php $base = url('/lists/' . $list['id']); $done = array_filter($items, fn($i) => $i['done_at'] !== null); ?>
<p class="back"><a class="link" href="<?= url('/lists') ?>"><?= t('list.all_lists') ?></a></p>
<h1 class="page-title"><?= e($list['name']) ?></h1>
<?php if (!$isOwner): ?><p class="subtle"><?= t('list.shared_by', ['name' => $ownerName]) ?></p><?php endif; ?>

<?php
$opts = function (array $people, $sel) { $h = '<option value="">' . t('list.anyone') . '</option>'; foreach ($people as $pp) $h .= '<option value="' . (int)$pp['id'] . '"' . ((int)$sel === (int)$pp['id'] ? ' selected' : '') . '>' . e($pp['name']) . '</option>'; return $h; };
$reps = function ($sel) { $h = ''; foreach ([''=>t('list.rep_none'),'daily'=>t('list.rep_daily'),'weekly'=>t('list.rep_weekly'),'monthly'=>t('list.rep_monthly')] as $k=>$v) $h .= '<option value="' . $k . '"' . ((string)$sel === (string)$k ? ' selected' : '') . '>' . $v . '</option>'; return $h; };
?>
<section class="card write">
  <form method="post" action="<?= $base ?>/items">
    <?= csrf_field() ?>
    <div class="inline-form">
      <label class="sr-only" for="itext"><?= t('list.new_item') ?></label>
      <input id="itext" name="text" maxlength="300" placeholder="<?= t('list.add_an_item') ?>" required class="title-in">
      <button class="btn" type="submit"><?= t('common.add') ?></button>
    </div>
    <details class="more-opts">
      <summary><?= t('list.due_date_who_it') ?></summary>
      <div class="opt-grid">
        <div><label for="idue"><?= t('list.due_date') ?></label><input id="idue" type="date" name="due_date"></div>
        <?php if (count($people) > 1): ?><div><label for="iwho"><?= t('list.for') ?></label><select id="iwho" name="assigned_to"><?= $opts($people, 0) ?></select></div><?php endif; ?>
        <div><label for="irep"><?= t('list.repeat') ?></label><select id="irep" name="repeat"><?= $reps('') ?></select></div>
      </div>
    </details>
  </form>
</section>

<?php if (count($people) > 1): ?>
<div class="chips" role="group" aria-label="<?= t('list.show') ?>">
  <a class="chip<?= !$mine ? ' on' : '' ?>" href="<?= $base ?>"><?= t('list.everything') ?></a>
  <a class="chip<?= $mine ? ' on' : '' ?>" href="<?= $base ?>?who=me"><?= t('list.mine') ?></a>
</div>
<?php endif; ?>

<?php if (!$items): ?><p class="subtle"><?= $mine ? t('list.nothing_mine') : t('list.nothing_yet') ?></p><?php endif; ?>
<ul class="items" id="items" data-csrf="<?= e(csrf_token()) ?>">
<?php foreach ($items as $it): ?>
  <li class="item<?= $it['done_at'] ? ' done' : '' ?>">
    <form method="post" action="<?= $base ?>/items/<?= $it['id'] ?>/toggle" class="toggle-form" data-list="<?= e($list['name']) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="check" role="checkbox" aria-checked="<?= $it['done_at'] ? 'true' : 'false' ?>" aria-label="<?= e($it['text']) ?>"></button>
    </form>
    <div class="item-text"><span><?= e($it['text']) ?></span>
      <small class="due-row">
      <?php if ($it['due_date'] && !$it['done_at']): ?><span class="due<?= $it['due_date'] < date('Y-m-d') ? ' late' : '' ?>"><?= t('list.due', ['date' => ldate('M j', strtotime($it['due_date']))]) ?></span><?php endif; ?>
      <?php if ($it['repeat']): ?><span class="due"><?= t('list.repeats', ['when' => t('list.repeat_' . $it['repeat'])]) ?></span><?php endif; ?>
      <?php if (!empty($it['assigned_name'])): ?><span class="who"><?= e($it['assigned_name']) ?></span><?php endif; ?>
      <?php if ($it['done_at'] && $it['done_name']): ?><span class="due"><?= t('list.done_by', ['name' => $it['done_name']]) ?></span><?php endif; ?>
      </small>
      <?php if (!$it['done_at']): ?>
      <details class="item-edit">
        <summary><?= t('common.edit') ?></summary>
        <form method="post" action="<?= $base ?>/items/<?= $it['id'] ?>/edit">
          <?= csrf_field() ?>
          <label for="et<?= $it['id'] ?>"><?= t('list.item') ?></label><input id="et<?= $it['id'] ?>" name="text" maxlength="300" value="<?= e($it['text']) ?>" required>
          <div class="opt-grid">
            <div><label for="ed<?= $it['id'] ?>"><?= t('list.due_date') ?></label><input id="ed<?= $it['id'] ?>" type="date" name="due_date" value="<?= e((string)$it['due_date']) ?>"></div>
            <?php if (count($people) > 1): ?><div><label for="ew<?= $it['id'] ?>"><?= t('list.for') ?></label><select id="ew<?= $it['id'] ?>" name="assigned_to"><?= $opts($people, $it['assigned_to']) ?></select></div><?php endif; ?>
            <div><label for="er<?= $it['id'] ?>"><?= t('list.repeat') ?></label><select id="er<?= $it['id'] ?>" name="repeat"><?= $reps($it['repeat']) ?></select></div>
          </div>
          <button class="btn" type="submit"><?= t('common.save') ?></button>
        </form>
      </details>
      <?php endif; ?>
    </div>
    <form method="post" action="<?= $base ?>/items/<?= $it['id'] ?>/delete" data-confirm="<?= t('list.remove_this_item') ?>"><?= csrf_field() ?><button class="x" type="submit" aria-label="<?= t('list.remove_item') ?>">&times;</button></form>
  </li>
<?php endforeach; ?>
</ul>
<?php if ($done): ?>
<form method="post" action="<?= $base ?>/clear" data-confirm="<?= t('list.clear_all_completed_items') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('list.clear_completed') ?></button></form>
<?php endif; ?>

<h2 class="section-h"><?= t('list.sharing') ?></h2>
<section class="card">
  <p class="subtle"><?= t('list.owner', ['name' => $ownerName]) ?></p>
  <?php $famIds = array_column($family, 'id'); $shared = array_column($members, 'id'); ?>
  <?php foreach ($members as $mb): if ($isOwner && in_array((int)$mb['id'], $famIds, true)) continue; ?>
    <form method="post" action="<?= $base ?>/members/<?= $mb['id'] ?>/remove" class="member">
      <?= csrf_field() ?><span><?= e($mb['name']) ?> <small class="due"><?= e($mb['email']) ?></small></span>
      <?php if ($isOwner || (int)$mb['id'] === (int)$user['id']): ?><button class="btn btn--quiet" type="submit"><?= $isOwner ? t('common.remove') : t('list.leave') ?></button><?php endif; ?>
    </form>
  <?php endforeach; ?>
  <?php if ($isOwner && $family): ?>
  <form method="post" action="<?= $base ?>/share_family">
    <?= csrf_field() ?>
    <p class="subtle"><?= t('list.share_with_family') ?></p>
    <?php foreach ($family as $f): ?>
      <label class="check-row"><input type="checkbox" name="people[]" value="<?= (int)$f['id'] ?>"<?= in_array((int)$f['id'], $shared, true) ? ' checked' : '' ?>> <?= e($f['name']) ?> <small class="due"><?= e($f['email']) ?></small></label>
    <?php endforeach; ?>
    <button class="btn" type="submit"><?= t('list.save_sharing') ?></button>
  </form>
  <?php endif; ?>
  <?php if ($isOwner): ?>
  <p class="subtle"><?= $family ? t('list.someone_outside_family') : t('list.share_by_email') ?></p>
  <form method="post" action="<?= $base ?>/share" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="semail"><?= t('list.email_of_a_moriwella') ?></label>
    <input id="semail" type="email" name="email" placeholder="<?= t('list.their_moriwella_email') ?>" required class="title-in">
    <button class="btn" type="submit"><?= t('list.share') ?></button>
  </form>
  <form method="post" action="<?= $base ?>/delete" class="danger" data-confirm="<?= t('list.delete_this_list_for') ?>"><?= csrf_field() ?><button class="btn btn--quiet" type="submit"><?= t('list.delete_list') ?></button></form>
  <?php endif; ?>
</section>
