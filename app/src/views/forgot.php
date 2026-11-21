<section class="card narrow">
  <h1><?= t('forgot.title') ?></h1>
<?php if ($done): ?>
  <p><?= t('forgot.done_p', ['email' => new Raw('<b>' . e($email) . '</b>')]) ?></p>
  <p><a class="link" href="<?= url('/login') ?>"><?= t('signup.back') ?></a></p>
<?php else: ?>
  <p><?= t('forgot.intro') ?></p>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label><?= t('signup.email') ?><input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus></label>
    <button class="btn" type="submit"><?= t('forgot.btn') ?></button>
  </form>
<?php endif; ?>
</section>
<?= lang_picker('lang-pick lang-pick--bare') ?>
