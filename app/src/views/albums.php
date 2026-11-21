<h1 class="page-title"><?= t('album.heading') ?></h1>
<p class="subtle"><?= t('album.intro') ?></p>
<?php if (!$albums): ?><p class="subtle"><?= t('album.none') ?></p>
<?php else: foreach ($albums as $al): $st = album_state($al); $mine = (int)$al['user_id'] === (int)$user['id']; ?>
  <a class="card entry-link" href="<?= url('/albums/' . $al['id']) ?>">
    <b><?= e($al['title']) ?></b>
    <span class="snippet"><?= e(tn('album.photo_count', (int)$al['n'])) ?><?= $mine ? '' : ' · ' . e(t('album.by', ['name' => explode(' ', trim($al['owner_name']))[0]])) ?> · <?= t('album.state_' . $st) ?></span>
  </a>
<?php endforeach; endif; ?>
