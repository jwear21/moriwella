<section class="card narrow">
  <h1>Set up your administrator account</h1>
  <p>This creates the first account for Moriwella. It only works once.</p>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <p class="fixed"><b><?= e($name) ?></b><br><?= e($email) ?></p>
    <label>Setup code<input name="setup_code" required autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX-XXXX"></label>
    <label>Choose a password<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label>Type it again<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
    <p class="hint">At least 10 characters. A few random words works well.</p>
    <button class="btn" type="submit">Create administrator account</button>
  </form>
</section>
