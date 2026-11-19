<?php $base = url('/lists/' . $list['id']); $done = array_filter($items, fn($i) => $i['done_at'] !== null); ?>
<p><a class="link" href="<?= url('/lists') ?>">&larr; All lists</a></p>
<h1 class="page-title"><?= e($list['name']) ?></h1>
<?php if (!$isOwner): ?><p class="subtle">Shared by <?= e($ownerName) ?></p><?php endif; ?>

<section class="card write">
  <form method="post" action="<?= $base ?>/items" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="itext">New item</label>
    <input id="itext" name="text" maxlength="300" placeholder="Add an item" required class="title-in">
    <label class="sr-only" for="idue">Due date (optional)</label>
    <input id="idue" type="date" name="due_date">
    <button class="btn" type="submit">Add</button>
  </form>
</section>

<?php if (!$items): ?><p class="subtle">Nothing here yet. Add the first item above.</p><?php endif; ?>
<ul class="items" id="items" data-csrf="<?= e(csrf_token()) ?>">
<?php foreach ($items as $it): ?>
  <li class="item<?= $it['done_at'] ? ' done' : '' ?>">
    <form method="post" action="<?= $base ?>/items/<?= $it['id'] ?>/toggle" class="toggle-form" data-list="<?= e($list['name']) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="check" role="checkbox" aria-checked="<?= $it['done_at'] ? 'true' : 'false' ?>" aria-label="<?= e($it['text']) ?>"></button>
    </form>
    <div class="item-text"><span><?= e($it['text']) ?></span>
      <?php if ($it['due_date'] && !$it['done_at']): ?><small class="due<?= $it['due_date'] < date('Y-m-d') ? ' late' : '' ?>">Due <?= e(date('M j', strtotime($it['due_date']))) ?></small><?php endif; ?>
      <?php if ($it['done_at'] && $it['done_name']): ?><small class="due">Done by <?= e($it['done_name']) ?></small><?php endif; ?>
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
