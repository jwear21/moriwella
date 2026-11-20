<h1 class="page-title">Settings</h1>
<section class="card write" id="goals">
  <h2 class="section-h">Daily goals</h2>
  <p class="subtle">Pick what you'd like to check off each day. They show up at the top of Today.</p>
  <?php foreach ($goals as $g): ?>
    <form method="post" action="<?= url('/goals/' . $g['id'] . '/delete') ?>" class="member"><?= csrf_field() ?><span><?= e($g['name']) ?></span><button class="btn btn--quiet" type="submit">Remove</button></form>
  <?php endforeach; ?>
  <form method="post" action="<?= url('/goals') ?>" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="gname">New goal</label>
    <input id="gname" name="name" maxlength="100" placeholder="For example: Take a walk" required class="title-in">
    <button class="btn" type="submit">Add goal</button>
  </form>
</section>
<section class="card write">
  <h2 class="section-h">Preferences</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="save_prefs" value="1">
    <label class="check-row"><input type="checkbox" name="water_on" value="1"<?= $waterOn ? ' checked' : '' ?>> Track water (64 oz a day, one cup at a time)</label>
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
<section class="card write">
  <h2 class="section-h">Account</h2>
  <div class="acct-actions">
    <?php if ($user['role'] === 'admin'): ?><a class="btn btn--quiet" href="<?= url('/admin') ?>">Admin</a><?php endif; ?>
    <form method="post" action="<?= url('/logout') ?>" class="inline"><?= csrf_field() ?><button type="submit" class="btn btn--quiet">Sign out</button></form>
  </div>
</section>
