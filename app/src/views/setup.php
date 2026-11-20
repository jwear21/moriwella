<section class="card narrow">
  <h1><?= t('setup.set_up_your_administrator') ?></h1>
  <p><?= t('setup.this_creates_the_first') ?></p>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <p class="fixed"><b><?= e($name) ?></b><br><?= e($email) ?></p>
    <label><?= t('setup.setup_code') ?><input name="setup_code" required autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="<?= t('setup.xxxx_xxxx_xxxx') ?>"></label>
    <label><?= t('common.choose_a_password') ?><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label><?= t('common.type_it_again') ?><input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
    <p class="hint"><?= t('setup.at_least_10_characters') ?></p>
    <button class="btn" type="submit"><?= t('setup.create_administrator_account') ?></button>
  </form>
</section>
<?= lang_picker('lang-pick lang-pick--bare') ?>
