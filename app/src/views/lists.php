<h1 class="page-title">Lists</h1>
<p class="subtle">Tasks, shopping and projects. Share any list with family.</p>
<?php if (!$lists): ?><p class="subtle empty">No lists yet. Start one below, then share it with family.</p>
<?php else: foreach ($lists as $l): $pct = $l['total'] ? (int)round(($l['total'] - $l['open']) / $l['total'] * 100) : 0; ?>
  <a class="card entry-link list-card" href="<?= url('/lists/' . $l['id']) ?>">
    <b><?= e($l['name']) ?></b>
    <span class="snippet"><?= $l['open'] ?> to do<?= $l['total'] - $l['open'] ? ', ' . ($l['total'] - $l['open']) . ' done' : '' ?><?= $l['shared'] ? ' · shared' : '' ?><?= !$l['is_owner'] ? ' · from ' . e($l['owner_name']) : '' ?></span>
    <?php if ($l['total']): ?><span class="meter" aria-hidden="true"><i style="width:<?= $pct ?>%"></i></span><?php endif; ?>
  </a>
<?php endforeach; endif; ?>
<details class="newlist"<?= !$lists ? ' open' : '' ?>>
  <summary class="btn btn--quiet">New list</summary>
  <section class="card write">
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <label class="sr-only" for="lname">List name</label>
      <input id="lname" name="name" maxlength="100" placeholder="New list, like Groceries" required class="title-in">
      <label class="sr-only" for="lkind">Type</label>
      <select id="lkind" name="kind"><option value="todo">To-do</option><option value="shopping">Shopping</option><option value="chore">Chores</option><option value="project">Project</option></select>
      <button class="btn" type="submit">Create list</button>
    </form>
  </section>
</details>
