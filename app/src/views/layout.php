<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>" dir="<?= text_dir() ?>" translate="no">
<head><meta name="google" content="notranslate">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(page_title($title)) ?> | Moriwella</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1B4D3E">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/assets/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="/styles.css">
<link rel="stylesheet" href="/kit/kit.css?v=<?= kit_v('kit.css') ?>">
<link rel="stylesheet" href="/app/static/app.css?v=<?= asset_v('static/app.css') ?>">
<link rel="stylesheet" href="/app/static/editor.css?v=<?= asset_v('static/editor.css') ?>">
<link rel="stylesheet" href="/app/static/textscale.css?v=<?= asset_v('static/textscale.css') ?>">
<?php if (text_dir() === 'rtl'): ?><link rel="stylesheet" href="/app/static/rtl.css?v=<?= asset_v('static/rtl.css') ?>">
<?php endif; ?>
<script src="/app/static/textscale.js?v=<?= asset_v('static/textscale.js') ?>"></script>
</head>
<body class="app<?= $bare ? ' app--bare' : '' ?>" data-celebrate-on="<?= ($user && !celebrations_on((int)$user['id'])) ? '0' : '1' ?>" data-celebrate-size="<?= $user ? celebrate_size((int)$user['id']) : 'big' ?>" data-celebrate-style="<?= $user ? celebrate_style((int)$user['id']) : 'fireworks' ?>"<?php if ($user && ($un = take_unlocked())): ?> data-unlocked="<?= e(json_encode($un)) ?>"<?php endif; ?>>
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
      <a href="<?= url('/journal/book') ?>"><?= t('layout.journal') ?></a>
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
        <?= lang_menu_picker() ?>
        <?php if ($user['role'] === 'admin'): ?><a role="menuitem" href="<?= url('/admin') ?>"><?= t('common.admin') ?></a><?php endif; ?>
        <form method="post" action="<?= url('/logout') ?>"><?= csrf_field() ?><button type="submit" role="menuitem" class="acct-out"><?= t('common.sign_out') ?></button></form>
      </div>
    </div>
  </div>
</header>
<?php require __DIR__ . '/tabbar.php'; ?>
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
<?php if ($user && in_app()): ?><div class="bio-offer" data-bio="offer" hidden data-title="<?= e(tt('bio.prompt_title')) ?>" data-cancel="<?= e(tt('bio.cancel')) ?>" data-csrf="<?= e(csrf_token()) ?>"><span><?= t('bio.offer') ?></span> <button class="btn" type="button" data-bio-yes><?= t('bio.enable') ?></button> <button class="textbtn" type="button" data-bio-no><?= t('bio.not_now') ?></button></div><?php endif; ?>
<script type="application/json" id="mw-i18n"><?= json_encode(js_strings(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="/app/static/i18n.js?v=<?= asset_v('static/i18n.js') ?>"></script>
<script src="/kit/celebrate.js?v=<?= kit_v('celebrate.js') ?>"></script>
<script src="/app/static/app.js?v=<?= asset_v('static/app.js') ?>"></script>
<script src="/app/static/camera.js?v=<?= asset_v('static/camera.js') ?>"></script>
<script src="/app/static/biometric.js?v=<?= asset_v('static/biometric.js') ?>"></script>
<script src="/app/static/passkey.js?v=<?= asset_v('static/passkey.js') ?>"></script>
<?php if ($user): ?><script src="/app/static/editor.js?v=<?= asset_v('static/editor.js') ?>" data-base="<?= e(url("")) ?>" data-csrf="<?= e(csrf_token()) ?>"></script><?php endif; ?>
</body>
</html>
