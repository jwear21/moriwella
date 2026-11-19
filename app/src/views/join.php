<section class="card narrow">
  <h1>Choose your password</h1>
  <p>Hello, <?= e($target['name']) ?>. You'll sign in with <b><?= e($target['email']) ?></b>.</p>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>Choose a password<input type="password" name="password" required minlength="10" autocomplete="new-password" autofocus></label>
    <label>Type it again<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
    <p class="hint">At least 10 characters.</p>
    <button class="btn" type="submit">Save password and sign in</button>
  </form>
</section>
