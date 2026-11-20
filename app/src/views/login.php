<section class="card narrow">
  <h1><?= t('login.welcome_back') ?></h1>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label><?= t('login.email') ?><input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus></label>
    <label><?= t('login.password') ?><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit"><?= t('login.sign_in') ?></button>
  </form>
</section>
<?= lang_picker('lang-pick lang-pick--bare') ?>
