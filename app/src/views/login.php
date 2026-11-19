<section class="card narrow">
  <h1>Welcome back</h1>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit">Sign in</button>
  </form>
</section>
