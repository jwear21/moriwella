<h1 class="page-title"><?= t('common.account_2') ?></h1>
<p class="subtle"><?= e($user['name']) ?> · <?= e($user['email']) ?></p>
<nav class="menu-rows" aria-label="<?= t('settings.more') ?>">
  <a class="row" href="<?= url('/achievements') ?>"><span><b><?= t('common.achievements') ?></b><small><?= t('settings.milestones_you_have_earned') ?></small></span><i aria-hidden="true">&rsaquo;</i></a>
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
    <p class="hint"><?= t('settings.phones_and_computers_set') ?></p>
    <button class="btn" type="submit"><?= t('common.save') ?></button>
  </form>
</section>
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
<section class="card write">
  <h2 class="section-h"><?= t('common.account_2') ?></h2>
  <div class="acct-actions">
    <?php if ($user['role'] === 'admin'): ?><a class="btn btn--quiet" href="<?= url('/admin') ?>"><?= t('common.admin') ?></a><?php endif; ?>
    <form method="post" action="<?= url('/logout') ?>" class="inline"><?= csrf_field() ?><button type="submit" class="btn btn--quiet"><?= t('common.sign_out') ?></button></form>
  </div>
</section>
