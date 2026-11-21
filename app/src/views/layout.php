<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(page_title($title)) ?> | Moriwella</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1B4D3E">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/assets/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="/styles.css">
<link rel="stylesheet" href="/kit/kit.css">
<link rel="stylesheet" href="/app/static/app.css?v=<?= asset_v('static/app.css') ?>">
<link rel="stylesheet" href="/app/static/editor.css?v=<?= asset_v('static/editor.css') ?>">
</head>
<body class="app<?= $bare ? ' app--bare' : '' ?>" data-celebrate-on="<?= ($user && !celebrations_on((int)$user['id'])) ? '0' : '1' ?>" data-celebrate-size="<?= $user ? celebrate_size((int)$user['id']) : 'big' ?>"<?php if ($user && ($un = take_unlocked())): ?> data-unlocked="<?= e(json_encode($un)) ?>"<?php endif; ?>>
<?php if (!$bare && $user && !empty($_SESSION['acting_admin'])): ?>
<div class="acting-bar" role="status"><span><?= t('layout.acting_as', ['name' => new Raw('<b>' . e($user['name']) . '</b>')]) ?></span>
  <form method="post" action="<?= url('/acting/stop') ?>"><?= csrf_field() ?><button type="submit"><?= t('layout.return_to_admin') ?></button></form></div>
<?php endif; ?>
<?php if (!$bare && $user): ?>
<header class="appbar">
  <div class="wrap appbar-in">
    <a href="<?= url('/') ?>" aria-label="<?= t('layout.moriwella_home') ?>"><img class="logo" src="/assets/logo_horizontal_inverse.svg" alt="<?= t('layout.moriwella') ?>"></a>
    <nav aria-label="<?= t('layout.main') ?>">
      <a href="<?= url('/') ?>"><?= t('common.today') ?></a>
      <a href="<?= url('/journal') ?>"><?= t('layout.journal') ?></a>
      <a href="<?= url('/calendar') ?>"><?= t('common.calendar') ?></a>
      <a href="<?= url('/lists') ?>"><?= t('common.lists') ?></a>
      <a href="<?= url('/experiences') ?>"><?= t('common.experiences') ?></a>
      <a href="<?= url('/track') ?>"><?= t('common.track') ?></a>
      <?php if ($user['role'] === 'admin'): ?><a href="<?= url('/admin') ?>"><?= t('common.admin') ?></a><?php endif; ?>
      <form method="post" action="<?= url('/logout') ?>" class="inline"><?= csrf_field() ?><button type="submit" class="navbtn"><?= t('common.sign_out') ?></button></form>
    </nav>
    <?php
      /* Everything a member needs about their plan and movie credits lives in this one menu. A hiccup here must never break a page. */
      $am = null;
      try { $amb = billing_for((int)$user['id']); $amc = credits_balance((int)$user['id']); $am = ['state' => $amb['state'], 'plan' => $amb['plan'], 'sub' => $amb['sub'], 'owner' => $amb['is_owner'], 'n' => (int)$amc['available']]; } catch (Throwable $e) { $am = null; @file_put_contents(data_dir() . '/error.log', date('c') . ' menu billing: ' . $e->getMessage() . "\n", FILE_APPEND); }
    ?>
    <div class="acct" data-acct>
      <button class="acct-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="acct-panel" aria-label="<?= $am ? t('menu.open_n', ['n' => $am['n']]) : t('menu.open') ?>">
        <span class="acct-chip"><?= avatar_html((int)$user['id'], $user['name'], 'avatar--chip') ?><?php if ($am): ?><span class="acct-count<?= $am['n'] < 1 ? ' acct-count--zero' : '' ?>" aria-hidden="true"><?= $am['n'] ?></span><?php endif; ?></span>
        <svg class="acct-bars" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <div class="acct-panel" id="acct-panel" role="menu" hidden>
        <div class="acct-who"><b><?= e($user['name']) ?></b><small><?= e($user['email']) ?></small></div>
        <?php if ($am): $st = $am['state']; ?>
        <div class="acct-credits">
          <div><span><?= t('menu.movies_left') ?></span><strong><?= $am['n'] > 0 ? t('menu.n_left', ['n' => $am['n']]) : t('menu.none_left') ?></strong></div>
          <a class="btn acct-buy" role="menuitem" href="<?= url('/billing') ?>#credits"><?= $am['n'] > 0 ? t('menu.buy_credits') : t('menu.get_credit') ?></a>
        </div>
        <div class="acct-plan"><span><?= t('menu.plan') ?></span>
          <strong><?php
            if ($st === 'comped') echo e(t('menu.plan_free', ['plan' => $am['plan']['name']]));
            elseif ($st === 'trial') echo t('menu.plan_trial', ['date' => ldate('F j', (int)$am['sub']['trial_end'])]);
            elseif ($st === 'active') echo e($am['plan']['name']);
            else echo t('menu.plan_none'); ?></strong></div>
        <a role="menuitem" href="<?= url('/pricing') ?>"><?= $st === 'active' ? t('menu.change') : ($st === 'limited' ? t('menu.choose') : t('menu.upgrade')) ?></a>
        <a role="menuitem" href="<?= url('/billing') ?>"><?= t('menu.plan_and_credits') ?></a>
        <?php if ($am['owner']): ?><a role="menuitem" href="<?= url('/billing') ?>#members"><?= t('menu.people') ?></a><?php endif; ?>
        <?php endif; ?>
        <a role="menuitem" href="<?= url('/invite') ?>"><?= t('menu.invite') ?></a>
        <a role="menuitem" href="<?= url('/settings') ?>"><?= t('menu.settings') ?></a>
        <?php if ($user['role'] === 'admin'): ?><a role="menuitem" href="<?= url('/admin') ?>"><?= t('common.admin') ?></a><?php endif; ?>
        <form method="post" action="<?= url('/logout') ?>"><?= csrf_field() ?><button type="submit" role="menuitem" class="acct-out"><?= t('common.sign_out') ?></button></form>
      </div>
    </div>
  </div>
</header>
<nav class="tabbar" aria-label="<?= t('layout.main') ?>">
  <?php $cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); $tabs = [['/', t('common.today'), 'today'], ['/journal', t('layout.journal'), 'journal'], ['/lists', t('common.lists'), 'lists'], ['/experiences', t('common.experiences'), 'experiences'], ['/track', t('common.track'), 'track']];
  foreach ($tabs as $t): $href = url($t[0]); $on = $t[0] === '/' ? ($cur === MW_BASE || $cur === MW_BASE . '/' || strpos($cur, MW_BASE . '/day/') === 0) : strpos($cur, $href) === 0; ?>
    <a href="<?= $href ?>"<?= $on ? ' aria-current="page"' : '' ?>><?php
      echo ['today' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/></svg>', 'journal' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5c3-1 6-1 8 1 2-2 5-2 8-1v13c-3-1-6-1-8 1-2-2-5-2-8-1z"/><path d="M12 6v13"/></svg>', 'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>', 'lists' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/></svg>', 'experiences' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>', 'track' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.5-7-10a4 4 0 017-2.5A4 4 0 0119 10c0 5.500-7 10-7 10z"/></svg>'][$t[2]]; ?><span><?= $t[1] ?></span></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<main class="<?= $bare ? 'bare-main' : 'wrap app-main' ?>" id="main">
<?php if ($bare): ?><a href="<?= url('/') ?>" class="bare-logo" aria-label="<?= t('layout.moriwella') ?>"><img src="/assets/logo_horizontal.svg" alt="<?= t('layout.moriwella') ?>"></a><?php endif; ?>
<?php if ($flash && in_array($flash['type'], ['ok', 'err'], true)): ?>
  <p class="note note--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></p>
<?php endif; ?>
<?= $content ?>
</main>
<?php if ($flash && in_array($flash['type'], ['saved', 'first'], true)): ?>
  <p class="note note--ok" role="status" id="saved-note" data-celebrate="<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></p>
<?php endif; ?>
<script type="application/json" id="mw-i18n"><?= json_encode(js_strings(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="/app/static/i18n.js?v=<?= asset_v('static/i18n.js') ?>"></script>
<script src="/kit/celebrate.js"></script>
<script src="/app/static/app.js?v=<?= asset_v('static/app.js') ?>"></script>
<?php if ($user): ?><script src="/app/static/editor.js?v=<?= asset_v('static/editor.js') ?>" data-base="<?= e(url("")) ?>" data-csrf="<?= e(csrf_token()) ?>"></script><?php endif; ?>
</body>
</html>
