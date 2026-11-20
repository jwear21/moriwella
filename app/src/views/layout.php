<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
<body class="app<?= $bare ? ' app--bare' : '' ?>" data-celebrate-on="<?= ($user && !celebrations_on((int)$user['id'])) ? '0' : '1' ?>" data-celebrate-size="<?= $user ? celebrate_size((int)$user['id']) : 'big' ?>"<?php if ($user && ($un = take_unlocked())): ?> data-unlocked="<?= e(json_encode($un)) ?>"<?php endif; ?>>
<?php if (!$bare && $user): ?>
<header class="appbar">
  <div class="wrap appbar-in">
    <a href="<?= url('/') ?>" aria-label="Moriwella home"><img class="logo" src="/assets/logo_horizontal_inverse.svg" alt="Moriwella"></a>
    <nav aria-label="Main">
      <a href="<?= url('/') ?>">Today</a>
      <a href="<?= url('/journal') ?>">Journal</a>
      <a href="<?= url('/calendar') ?>">Calendar</a>
      <a href="<?= url('/lists') ?>">Lists</a>
      <a href="<?= url('/experiences') ?>">Experiences</a>
      <a href="<?= url('/settings') ?>">Settings</a>
      <?php if ($user['role'] === 'admin'): ?><a href="<?= url('/admin') ?>">Admin</a><?php endif; ?>
      <form method="post" action="<?= url('/logout') ?>" class="inline"><?= csrf_field() ?><button type="submit" class="navbtn">Sign out</button></form>
    </nav>
  </div>
</header>
<nav class="tabbar" aria-label="Main">
  <?php $cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); $tabs = [['/', 'Today', 'today'], ['/journal', 'Journal', 'journal'], ['/lists', 'Lists', 'lists'], ['/experiences', 'Experiences', 'experiences'], ['/settings', 'Me', 'settings']];
  foreach ($tabs as $t): $href = url($t[0]); $on = $t[0] === '/' ? ($cur === MW_BASE || $cur === MW_BASE . '/' || strpos($cur, MW_BASE . '/day/') === 0) : strpos($cur, $href) === 0; ?>
    <a href="<?= $href ?>"<?= $on ? ' aria-current="page"' : '' ?>><?php
      echo ['today' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/></svg>', 'journal' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5c3-1 6-1 8 1 2-2 5-2 8-1v13c-3-1-6-1-8 1-2-2-5-2-8-1z"/><path d="M12 6v13"/></svg>', 'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>', 'lists' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/></svg>', 'experiences' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>', 'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/></svg>'][$t[2]]; ?><span><?= $t[1] ?></span></a>
  <?php endforeach; ?>
</nav>
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
