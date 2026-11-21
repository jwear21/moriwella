<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> | Moriwella</title>
<link rel="stylesheet" href="/app/static/album.css?v=<?= asset_v('static/album.css') ?>">
</head>
<body>
<header class="alb-top"><a class="alb-logo" href="https://moriwella.com/" rel="noopener"><img src="/assets/logo_horizontal_inverse.svg" alt="Moriwella"></a></header>
<main class="alb-main alb-gone"><h1><?= e($heading) ?></h1><p class="alb-sub"><?= e($text) ?></p></main>
</body>
</html>
