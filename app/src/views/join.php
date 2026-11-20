<section class="card narrow">
  <h1><?= t('join.choose_your_password') ?></h1>
  <p><?= t('join.hello', ['name' => $target['name'], 'email' => new Raw('<b>' . e($target['email']) . '</b>')]) ?></p>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label><?= t('common.choose_a_password') ?><input type="password" name="password" required minlength="10" autocomplete="new-password" autofocus></label>
    <label><?= t('common.type_it_again') ?><input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
    <p class="hint"><?= t('join.at_least_10_characters') ?></p>
    <button class="btn" type="submit"><?= t('join.save_password_and_sign') ?></button>
  </form>
</section>
<?= lang_picker('lang-pick lang-pick--bare') ?>
