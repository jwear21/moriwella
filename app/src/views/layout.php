<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Moriwella</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1B4D3E">
<link rel="icon" href="/assets/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="/styles.css">
<link rel="stylesheet" href="/kit/kit.css">
<link rel="stylesheet" href="/app/static/app.css">
<link rel="stylesheet" href="/app/static/editor.css">
</head>
<body class="app<?= $bare ? ' app--bare' : '' ?>" data-celebrate-on="<?= ($user && !celebrations_on((int)$user['id'])) ? '0' : '1' ?>">
<?php if (!$bare && $user): ?>
<header class="appbar">
  <div class="wrap appbar-in">
    <a href="<?= url('/') ?>" aria-label="Moriwella home"><img class="logo" src="/assets/logo_horizontal_inverse.svg" alt="Moriwella"></a>
    <nav aria-label="Main">
      <a href="<?= url('/') ?>">Today</a>
      <a href="<?= url('/journal') ?>">Journal</a>
      <a href="<?= url('/calendar') ?>">Calendar</a>
      <a href="<?= url('/lists') ?>">Lists</a>
      <a href="<?= url('/settings') ?>">Settings</a>
      <?php if ($user['role'] === 'admin'): ?><a href="<?= url('/admin') ?>">Admin</a><?php endif; ?>
      <form method="post" action="<?= url('/logout') ?>" class="inline"><?= csrf_field() ?><button type="submit" class="navbtn">Sign out</button></form>
    </nav>
  </div>
</header>
<?php endif; ?>
<main class="<?= $bare ? 'bare-main' : 'wrap app-main' ?>" id="main">
<?php if ($bare): ?><a href="<?= url('/') ?>" class="bare-logo" aria-label="Moriwella"><img src="/assets/logo_horizontal.svg" alt="Moriwella"></a><?php endif; ?>
<?php if ($flash && in_array($flash['type'], ['ok', 'err'], true)): ?>
  <p class="note note--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></p>
<?php endif; ?>
<?= $content ?>
</main>
<?php if ($flash && in_array($flash['type'], ['saved', 'first'], true)): ?>
  <p class="note note--ok" role="status" id="saved-note" data-celebrate="<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></p>
<?php endif; ?>
<script src="/kit/celebrate.js"></script>
<script src="/app/static/app.js"></script>
<?php if ($user): ?><script src="/app/static/editor.js" data-base="<?= e(url("")) ?>" data-csrf="<?= e(csrf_token()) ?>"></script><?php endif; ?>
</body>
</html>
