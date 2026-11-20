<h1 class="page-title">Account</h1>
<p class="subtle"><?= e($user['name']) ?> · <?= e($user['email']) ?></p>
<nav class="menu-rows" aria-label="More">
  <a class="row" href="<?= url('/achievements') ?>"><span><b>Achievements</b><small>Milestones you have earned</small></span><i aria-hidden="true">&rsaquo;</i></a>
  <a class="row" href="<?= url('/feedback') ?>"><span><b>Send feedback</b><small>Something confusing or missing? Tell Jeff.</small></span><i aria-hidden="true">&rsaquo;</i></a>
</nav>
<section class="card write">
  <h2 class="section-h">Preferences</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="save_prefs" value="1">
    <label class="check-row"><input type="checkbox" name="celebrations" value="1"<?= $celebrations ? ' checked' : '' ?>> Show sparkles and fireworks when I finish things</label>
    <fieldset class="size-pick">
      <legend>Celebration size</legend>
      <label class="check-row"><input type="radio" name="celebrate_size" value="big"<?= $size === 'big' ? ' checked' : '' ?>> Big: splashes, confetti and fireworks</label>
      <label class="check-row"><input type="radio" name="celebrate_size" value="gentle"<?= $size === 'gentle' ? ' checked' : '' ?>> Gentle: smaller and quieter</label>
    </fieldset>
    <p class="hint">Phones and computers set to reduce motion always get the quiet version.</p>
    <button class="btn" type="submit">Save</button>
  </form>
</section>
<details class="card write pw">
  <summary>Change password</summary>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="change_password" value="1">
    <label>Current password<input type="password" name="current" autocomplete="current-password" required></label>
    <label>New password<input type="password" name="password" autocomplete="new-password" minlength="10" required></label>
    <label>Confirm new password<input type="password" name="password2" autocomplete="new-password" minlength="10" required></label>
    <button class="btn" type="submit">Change password</button>
  </form>
</details>
<section class="card write">
  <h2 class="section-h">Account</h2>
  <div class="acct-actions">
    <?php if ($user['role'] === 'admin'): ?><a class="btn btn--quiet" href="<?= url('/admin') ?>">Admin</a><?php endif; ?>
    <form method="post" action="<?= url('/logout') ?>" class="inline"><?= csrf_field() ?><button type="submit" class="btn btn--quiet">Sign out</button></form>
  </div>
</section>
