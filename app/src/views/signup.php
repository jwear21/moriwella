<section class="card narrow">
  <h1><?= t('signup.title') ?></h1>
<?php if ($done): ?>
  <h2><?= t('signup.done_h') ?></h2>
  <p><?= t('signup.done_p', ['email' => new Raw('<b>' . e($email) . '</b>')]) ?></p>
  <p><a class="link" href="<?= url('/login') ?>"><?= t('signup.back') ?></a></p>
<?php else: ?>
  <p><?= t('signup.intro') ?></p>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <label class="hp" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    <label><?= t('signup.name') ?><input type="text" name="name" value="<?= e($name) ?>" required maxlength="100" autocomplete="name" autofocus></label>
    <label><?= t('signup.email') ?><input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email"></label>
    <label class="check"><input type="checkbox" name="agree" value="1" required> <span><?= t('signup.agree', ['terms' => new Raw('<a href="/terms.html" target="_blank" rel="noopener">' . t('signup.terms') . '</a>'), 'privacy' => new Raw('<a href="/privacy.html" target="_blank" rel="noopener">' . t('signup.privacy') . '</a>')]) ?></span></label>
    <button class="btn" type="submit"><?= t('signup.btn') ?></button>
  </form>
  <p class="hint"><?= t('signup.have_account') ?> <a class="link" href="<?= url('/login') ?>"><?= t('signup.sign_in') ?></a></p>
<?php endif; ?>
</section>
<?= lang_picker('lang-pick lang-pick--bare') ?>
