<section class="card narrow">
  <h1><?= t('login.welcome_back') ?></h1>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label><?= t('login.email') ?><input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus></label>
    <label><?= t('login.password') ?><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit"><?= t('login.sign_in') ?></button>
  </form>
  <?php if (in_app()): ?>
  <div class="bio" data-bio="login" hidden data-title="<?= e(tt('bio.prompt_title')) ?>" data-reason="<?= e(tt('bio.prompt_reason')) ?>" data-cancel="<?= e(tt('bio.cancel')) ?>" data-failed="<?= e(tt('bio.failed')) ?>">
    <button class="btn btn--quiet" type="button" data-bio-go><?= t('bio.sign_in') ?></button>
    <p class="note note--err" data-bio-msg role="alert" hidden></p>
  </div>
  <?php endif; ?>
  <p class="hint"><a class="link" href="<?= url('/forgot') ?>"><?= t('forgot.link') ?></a> &middot; <a class="link" href="<?= url('/signup') ?>"><?= t('forgot.create') ?></a></p>
</section>
<?= lang_picker('lang-pick lang-pick--bare') ?>
