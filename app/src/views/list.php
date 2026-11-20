<?php $base = url('/lists/' . $list['id']); $done = array_filter($items, fn($i) => $i['done_at'] !== null); ?>
<p><a class="link" href="<?= url('/lists') ?>">&larr; All lists</a></p>
<h1 class="page-title"><?= e($list['name']) ?></h1>
<?php if (!$isOwner): ?><p class="subtle">Shared by <?= e($ownerName) ?></p><?php endif; ?>

<?php
$opts = function (array $people, $sel) { $h = '<option value="">Anyone</option>'; foreach ($people as $pp) $h .= '<option value="' . (int)$pp['id'] . '"' . ((int)$sel === (int)$pp['id'] ? ' selected' : '') . '>' . e($pp['name']) . '</option>'; return $h; };
$reps = function ($sel) { $h = ''; foreach ([''=>'Does not repeat','daily'=>'Every day','weekly'=>'Every week','monthly'=>'Every month'] as $k=>$v) $h .= '<option value="' . $k . '"' . ((string)$sel === (string)$k ? ' selected' : '') . '>' . $v . '</option>'; return $h; };
?>
<section class="card write">
  <form method="post" action="<?= $base ?>/items">
    <?= csrf_field() ?>
    <div class="inline-form">
      <label class="sr-only" for="itext">New item</label>
      <input id="itext" name="text" maxlength="300" placeholder="Add an item" required class="title-in">
      <button class="btn" type="submit">Add</button>
    </div>
    <details class="more-opts">
      <summary>Due date, who it is for, repeat</summary>
      <div class="opt-grid">
        <div><label for="idue">Due date</label><input id="idue" type="date" name="due_date"></div>
        <?php if (count($people) > 1): ?><div><label for="iwho">For</label><select id="iwho" name="assigned_to"><?= $opts($people, 0) ?></select></div><?php endif; ?>
        <div><label for="irep">Repeat</label><select id="irep" name="repeat"><?= $reps('') ?></select></div>
      </div>
    </details>
  </form>
</section>

<?php if (count($people) > 1): ?>
<div class="chips" role="group" aria-label="Show">
  <a class="chip<?= !$mine ? ' on' : '' ?>" href="<?= $base ?>">Everything</a>
  <a class="chip<?= $mine ? ' on' : '' ?>" href="<?= $base ?>?who=me">Mine</a>
</div>
<?php endif; ?>

<?php if (!$items): ?><p class="subtle"><?= $mine ? 'Nothing assigned to you.' : 'Nothing here yet. Add the first item above.' ?></p><?php endif; ?>
<ul class="items" id="items" data-csrf="<?= e(csrf_token()) ?>">
<?php foreach ($items as $it): ?>
  <li class="item<?= $it['done_at'] ? ' done' : '' ?>">
    <form method="post" action="<?= $base ?>/items/<?= $it['id'] ?>/toggle" class="toggle-form" data-list="<?= e($list['name']) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="check" role="checkbox" aria-checked="<?= $it['done_at'] ? 'true' : 'false' ?>" aria-label="<?= e($it['text']) ?>"></button>
    </form>
    <div class="item-text"><span><?= e($it['text']) ?></span>
      <small class="due-row">
      <?php if ($it['due_date'] && !$it['done_at']): ?><span class="due<?= $it['due_date'] < date('Y-m-d') ? ' late' : '' ?>">Due <?= e(date('M j', strtotime($it['due_date']))) ?></span><?php endif; ?>
      <?php if ($it['repeat']): ?><span class="due">Repeats <?= e(['daily' => 'daily', 'weekly' => 'weekly', 'monthly' => 'monthly'][$it['repeat']]) ?></span><?php endif; ?>
      <?php if (!empty($it['assigned_name'])): ?><span class="who"><?= e($it['assigned_name']) ?></span><?php endif; ?>
      <?php if ($it['done_at'] && $it['done_name']): ?><span class="due">Done by <?= e($it['done_name']) ?></span><?php endif; ?>
      </small>
      <?php if (!$it['done_at']): ?>
      <details class="item-edit">
        <summary>Edit</summary>
        <form method="post" action="<?= $base ?>/items/<?= $it['id'] ?>/edit">
          <?= csrf_field() ?>
          <label for="et<?= $it['id'] ?>">Item</label><input id="et<?= $it['id'] ?>" name="text" maxlength="300" value="<?= e($it['text']) ?>" required>
          <div class="opt-grid">
            <div><label for="ed<?= $it['id'] ?>">Due date</label><input id="ed<?= $it['id'] ?>" type="date" name="due_date" value="<?= e((string)$it['due_date']) ?>"></div>
            <?php if (count($people) > 1): ?><div><label for="ew<?= $it['id'] ?>">For</label><select id="ew<?= $it['id'] ?>" name="assigned_to"><?= $opts($people, $it['assigned_to']) ?></select></div><?php endif; ?>
            <div><label for="er<?= $it['id'] ?>">Repeat</label><select id="er<?= $it['id'] ?>" name="repeat"><?= $reps($it['repeat']) ?></select></div>
          </div>
          <button class="btn" type="submit">Save</button>
        </form>
      </details>
      <?php endif; ?>
    </div>
    <form method="post" action="<?= $base ?>/items/<?= $it['id'] ?>/delete" data-confirm="Remove this item?"><?= csrf_field() ?><button class="x" type="submit" aria-label="Remove item">&times;</button></form>
  </li>
<?php endforeach; ?>
</ul>
<?php if ($done): ?>
<form method="post" action="<?= $base ?>/clear" data-confirm="Clear all completed items?"><?= csrf_field() ?><button class="btn btn--quiet" type="submit">Clear completed</button></form>
<?php endif; ?>

<h2 class="section-h">Sharing</h2>
<section class="card">
  <p class="subtle">Owner: <?= e($ownerName) ?></p>
  <?php foreach ($members as $mb): ?>
    <form method="post" action="<?= $base ?>/members/<?= $mb['id'] ?>/remove" class="member">
      <?= csrf_field() ?><span><?= e($mb['name']) ?> <small class="due"><?= e($mb['email']) ?></small></span>
      <?php if ($isOwner || (int)$mb['id'] === (int)$user['id']): ?><button class="btn btn--quiet" type="submit"><?= $isOwner ? 'Remove' : 'Leave list' ?></button><?php endif; ?>
    </form>
  <?php endforeach; ?>
  <?php if ($isOwner): ?>
  <form method="post" action="<?= $base ?>/share" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="semail">Email of a Moriwella member</label>
    <input id="semail" type="email" name="email" placeholder="Their Moriwella email" required class="title-in">
    <button class="btn" type="submit">Share</button>
  </form>
  <form method="post" action="<?= $base ?>/delete" class="danger" data-confirm="Delete this list for everyone?"><?= csrf_field() ?><button class="btn btn--quiet" type="submit">Delete list</button></form>
  <?php endif; ?>
</section>
