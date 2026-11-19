<h1 class="page-title">Settings</h1>
<section class="card write">
  <h2 class="section-h">Celebrations</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="save_prefs" value="1">
    <label class="check-row"><input type="checkbox" name="celebrations" value="1"<?= $celebrations ? ' checked' : '' ?>> Show sparkles and fireworks when I finish things</label>
    <p class="hint">Phones and computers set to reduce motion always get the quiet version.</p>
    <button class="btn" type="submit">Save</button>
  </form>
</section>
<section class="card write">
  <h2 class="section-h">Change password</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="change_password" value="1">
    <label>Current password<input type="password" name="current" autocomplete="current-password" required></label>
    <label>New password<input type="password" name="password" autocomplete="new-password" minlength="10" required></label>
    <label>Confirm new password<input type="password" name="password2" autocomplete="new-password" minlength="10" required></label>
    <button class="btn" type="submit">Change password</button>
  </form>
</section>
<section class="card"><p class="subtle">Signed in as <?= e($user['email']) ?>. Plan: <?= e($user['plan']) ?>.</p></section>
