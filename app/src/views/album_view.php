<?php /* Standalone album page: no app chrome. Used by the public link and by household members. Variables: $a, $photos, $photoUrl, $public, $owner */
$exp = (int)$a['expires_at']; $n = count($photos); ?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#1B4D3E">
<title><?= e($a['title']) ?> | Moriwella</title>
<link rel="icon" href="/assets/favicon.ico" sizes="any">
<link rel="stylesheet" href="/app/static/album.css?v=<?= asset_v('static/album.css') ?>">
</head>
<body>
<header class="alb-top">
  <a class="alb-logo" href="https://moriwella.com/" rel="noopener"><img src="/assets/logo_horizontal_inverse.svg" alt="Moriwella"></a>
</header>
<main class="alb-main">
  <h1><?= e($a['title']) ?></h1>
  <p class="alb-sub"><?= $owner !== '' ? e(tt('album.shared_by', ['name' => $owner])) . ' · ' : '' ?><?= e(tn('album.photo_count', $n)) ?><?php if ($exp > 0): ?> · <?= e(tt('album.open_until', ['date' => ldate('F j, Y', $exp)])) ?><?php endif; ?></p>
  <?php if ($a['note'] !== ''): ?><p class="alb-note"><?= nl2br(e($a['note'])) ?></p><?php endif; ?>
  <?php if (!$public): ?><p class="alb-back"><a href="<?= url('/albums') ?>">&larr; <?= e(tt('album.all_albums')) ?></a></p><?php endif; ?>
  <div class="alb-grid" id="alb-grid" data-dl="<?= (int)$a['allow_download'] || !$public ? 1 : 0 ?>" data-close="<?= e(tt('album.close')) ?>" data-prev="<?= e(tt('album.prev')) ?>" data-next="<?= e(tt('album.next')) ?>" data-save="<?= e(tt('album.save_photo')) ?>">
    <?php foreach ($photos as $i => $p): ?>
    <a class="alb-ph" href="<?= e($photoUrl($p, 'web')) ?>" data-full="<?= e($photoUrl($p, 'orig')) ?>" data-i="<?= $i ?>">
      <img src="<?= e($photoUrl($p, 'thumb')) ?>" width="<?= (int)$p['width'] ?>" height="<?= (int)$p['height'] ?>" alt="<?= e(tt('album.photo_n', ['n' => $i + 1])) ?>" loading="<?= $i < 8 ? 'eager' : 'lazy' ?>">
    </a>
    <?php endforeach; ?>
  </div>
</main>
<footer class="alb-foot"><?= e(tt('album.made_with')) ?> <a href="https://moriwella.com/" rel="noopener">Moriwella</a></footer>
<script src="/app/static/album.js?v=<?= asset_v('static/album.js') ?>" defer></script>
</body>
</html>
