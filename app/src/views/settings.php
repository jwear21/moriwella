<h1 class="page-title"><?= t('common.account_2') ?></h1>
<section class="card write profile">
  <div class="profile-head">
    <?= avatar_html((int)$user['id'], $user['name'], 'avatar--xl') ?>
    <div class="profile-who"><b><?= e($user['name']) ?></b><small><?= e($user['email']) ?></small></div>
  </div>
  <form method="post" enctype="multipart/form-data" class="profile-form">
    <?= csrf_field() ?><input type="hidden" name="save_avatar" value="1">
    <label class="filebtn"><span class="btn btn--quiet"><?= t(avatar_has((int)$user['id']) ? 'profile.change' : 'profile.add') ?></span>
      <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" required data-autosubmit data-busy="<?= e(t('profile.uploading')) ?>"></label>
  </form>
  <?php if (avatar_has((int)$user['id'])): ?>
  <form method="post" class="profile-form"><?= csrf_field() ?><input type="hidden" name="remove_avatar" value="1"><button type="submit" class="linkbtn"><?= t('profile.remove') ?></button></form>
  <?php endif; ?>
  <p class="hint"><?= t('profile.hint') ?></p>
</section>
<nav class="menu-rows" aria-label="<?= t('settings.more') ?>">
  <a class="row" href="<?= url('/achievements') ?>"><span><b><?= t('common.achievements') ?></b><small><?= t('settings.milestones_you_have_earned') ?></small></span><i aria-hidden="true">&rsaquo;</i></a>
  <a class="row" href="<?= url('/billing') ?>"><span><b><?= t('billing.title') ?></b><small><?= t('billing.settings_hint') ?></small></span><i aria-hidden="true">&rsaquo;</i></a>
  <a class="row" href="<?= url('/transfer') ?>"><span><b><?= t('common.import_and_export') ?></b><small><?= t('settings.take_your_journal_with') ?></small></span><i aria-hidden="true">&rsaquo;</i></a>
  <a class="row" href="<?= url('/feedback') ?>"><span><b><?= t('common.send_feedback') ?></b><small><?= t('settings.something_confusing_or_missing') ?></small></span><i aria-hidden="true">&rsaquo;</i></a>
</nav>
<?php if ($lp = lang_picker()): ?><section class="card write">
  <h2 class="section-h"><?= t('settings.language') ?></h2>
  <?= $lp ?>
</section><?php endif; ?>
<section class="card write">
  <h2 class="section-h"><?= t('settings.preferences') ?></h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="save_prefs" value="1">
    <label class="check-row"><input type="checkbox" name="celebrations" value="1"<?= $celebrations ? ' checked' : '' ?>> <?= t('settings.show_sparkles_and_fireworks') ?></label>
    <fieldset class="size-pick">
      <legend><?= t('settings.celebration_size') ?></legend>
      <label class="check-row"><input type="radio" name="celebrate_size" value="big"<?= $size === 'big' ? ' checked' : '' ?>> <?= t('settings.big_splashes_confetti_and') ?></label>
      <label class="check-row"><input type="radio" name="celebrate_size" value="gentle"<?= $size === 'gentle' ? ' checked' : '' ?>> <?= t('settings.gentle_smaller_and_quieter') ?></label>
    </fieldset>
    <fieldset class="size-pick">
      <legend><?= t('settings.celebration_style') ?></legend>
      <?php foreach ([['fireworks', 'svg'], ['kitten', 'webp'], ['baboon', 'webp'], ['rocket', 'svg'], ['balloons', 'svg'], ['puppy', 'webp'], ['bunny', 'webp'], ['owl', 'webp'], ['penguin', 'webp']] as [$sv, $ext]): ?>
      <label class="style-opt"><input type="radio" name="celebrate_style" value="<?= $sv ?>"<?= ($style ?? 'fireworks') === $sv ? ' checked' : '' ?>><img src="/kit/thumbs/<?= $sv ?>.<?= $ext ?>" width="48" height="48" alt="" loading="lazy"><span><b><?= t('settings.style_' . $sv . '_name') ?></b><small><?= t('settings.style_' . $sv) ?></small></span></label>
      <?php endforeach; ?>
      <button type="button" class="textbtn" id="style-preview"><?= t('settings.preview_celebration') ?></button>
    </fieldset>
    <p class="hint"><?= t('settings.phones_and_computers_set') ?></p>
    <button class="btn" type="submit"><?= t('common.save') ?></button>
  </form>
</section>
<?php if (!in_app()): ?>
<section class="card bio-card" data-pk="settings" hidden data-enable-err="<?= e(tt('pk.enable_err')) ?>">
  <h2><?= t('pk.settings_h') ?></h2>
  <p class="hint"><?= t('pk.settings_intro') ?></p>
  <?php foreach (passkey_list((int)$user['id']) as $pk): ?>
  <p><?= e($pk['label'] !== '' ? $pk['label'] : t('pk.this_device')) ?> &middot; <?= e(date('Y-m-d', (int)$pk['created_at'])) ?> <button class="textbtn" type="button" data-pk-del="<?= (int)$pk['id'] ?>"><?= t('pk.remove') ?></button></p>
  <?php endforeach; ?>
  <button class="btn" type="button" data-pk-add><?= t('pk.add') ?></button>
  <p class="note" data-pk-msg role="status" hidden></p>
</section>
<?php endif; ?>
<?php if (in_app()): ?>
<section class="card bio-card" data-bio="settings" hidden data-title="<?= e(tt('bio.prompt_title')) ?>" data-cancel="<?= e(tt('bio.cancel')) ?>" data-is-on="<?= e(tt('bio.is_on')) ?>" data-is-off="<?= e(tt('bio.is_off')) ?>" data-unavailable="<?= e(tt('bio.unavailable')) ?>" data-enabled-ok="<?= e(tt('bio.enabled_ok')) ?>" data-disabled-ok="<?= e(tt('bio.disabled_ok')) ?>" data-enable-err="<?= e(tt('bio.enable_err')) ?>">
  <h2><?= t('bio.settings_h') ?></h2>
  <p class="hint"><?= t('bio.settings_intro') ?></p>
  <p data-bio-state></p>
  <button class="btn" type="button" data-bio-on><?= t('bio.enable') ?></button>
  <button class="btn btn--quiet" type="button" data-bio-off hidden><?= t('bio.disable') ?></button>
  <p class="note" data-bio-msg role="status" hidden></p>
</section>
<?php endif; ?>
<details class="card write pw">
  <summary><?= t('settings.change_password') ?></summary>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="change_password" value="1">
    <label><?= t('settings.current_password') ?><input type="password" name="current" autocomplete="current-password" required></label>
    <label><?= t('settings.new_password') ?><input type="password" name="password" autocomplete="new-password" minlength="10" required></label>
    <label><?= t('settings.confirm_new_password') ?><input type="password" name="password2" autocomplete="new-password" minlength="10" required></label>
    <button class="btn" type="submit"><?= t('settings.change_password') ?></button>
  </form>
</details>
<details class="card write pw">
  <summary><?= t('account.delete_h') ?></summary>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="request_delete" value="1">
    <p><?= t('account.delete_intro') ?> <?= t('account.delete_paid') ?></p>
    <label><?= t('account.delete_password') ?><input type="password" name="current" autocomplete="current-password" required></label>
    <button class="btn" type="submit"><?= t('account.delete_btn') ?></button>
  </form>
</details>
<section class="card write">
  <h2 class="section-h"><?= t('common.account_2') ?></h2>
  <div class="acct-actions">
    <?php if ($user['role'] === 'admin'): ?><a class="btn btn--quiet" href="<?= url('/admin') ?>"><?= t('common.admin') ?></a><?php endif; ?>
    <form method="post" action="<?= url('/logout') ?>" class="inline"><?= csrf_field() ?><button type="submit" class="btn btn--quiet"><?= t('common.sign_out') ?></button></form>
  </div>
</section>
