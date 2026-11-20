<h1 class="page-title"><?= t('common.lists') ?></h1>
<p class="subtle"><?= t('lists.tasks_shopping_and_projects') ?></p>
<?php if (!$lists): ?><p class="subtle empty"><?= t('lists.no_lists_yet_start') ?></p>
<?php else: foreach ($lists as $l): $pct = $l['total'] ? (int)round(($l['total'] - $l['open']) / $l['total'] * 100) : 0; ?>
  <a class="card entry-link list-card" href="<?= url('/lists/' . $l['id']) ?>">
    <b><?= e($l['name']) ?></b>
    <span class="snippet"><?= e(t('lists.summary_open', ['n' => $l['open']])) ?><?= $l['total'] - $l['open'] ? e(t('lists.summary_done', ['n' => $l['total'] - $l['open']])) : '' ?><?= $l['shared'] ? e(t('lists.summary_shared')) : '' ?><?= !$l['is_owner'] ? e(t('lists.summary_from', ['name' => $l['owner_name']])) : '' ?></span>
    <?php if ($l['total']): ?><span class="meter" aria-hidden="true"><i style="width:<?= $pct ?>%"></i></span><?php endif; ?>
  </a>
<?php endforeach; endif; ?>
<details class="newlist"<?= !$lists ? ' open' : '' ?>>
  <summary class="btn btn--quiet"><?= t('lists.new_list') ?></summary>
  <section class="card write">
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <label class="sr-only" for="lname"><?= t('lists.list_name') ?></label>
      <input id="lname" name="name" maxlength="100" placeholder="<?= t('lists.new_list_like_groceries') ?>" required class="title-in">
      <label class="sr-only" for="lkind"><?= t('lists.type') ?></label>
      <select id="lkind" name="kind"><option value="todo"><?= t('lists.to_do') ?></option><option value="shopping"><?= t('lists.shopping') ?></option><option value="chore"><?= t('lists.chores') ?></option><option value="project"><?= t('lists.project') ?></option></select>
      <button class="btn" type="submit"><?= t('lists.create_list') ?></button>
    </form>
  </section>
</details>
