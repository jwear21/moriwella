<p><a class="link" href="<?= url('/settings') ?>"><?= t('common.account') ?></a></p>
<h1 class="page-title"><?= t('common.send_feedback') ?></h1>
<p class="subtle"><?= t('feedback.what_worked_what_confused') ?></p>
<section class="card write">
  <form method="post">
    <?= csrf_field() ?>
    <label for="fpage"><?= t('feedback.which_screen_optional') ?></label>
    <input id="fpage" name="page" maxlength="120" placeholder="<?= t('feedback.for_example_today_lists') ?>">
    <label for="fmsg"><?= t('feedback.your_feedback') ?></label>
    <textarea id="fmsg" name="message" rows="7" required></textarea>
    <button class="btn" type="submit"><?= t('feedback.send') ?></button>
  </form>
</section>
