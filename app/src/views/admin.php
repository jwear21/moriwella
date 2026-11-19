<h1 class="page-title">Administration</h1>
<p class="subtle">Create free beta accounts for testers. They never see a payment screen.</p>

<?php if ($newLink): ?>
<section class="card linkbox" role="status">
  <h2>Sign-in link for <?= e($newLink['name']) ?></h2>
  <p>Send them this link. They'll choose their own password. It works for 7 days.</p>
  <div class="copyrow"><input readonly id="newlink" value="<?= e($newLink['link']) ?>"><button type="button" class="btn" data-copy="#newlink">Copy link</button></div>
</section>
<?php endif; ?>

<section class="card">
  <h2>Add a beta tester</h2>
  <form method="post" class="inline-form">
    <?= csrf_field() ?><input type="hidden" name="create" value="1">
    <label>Name<input name="name" required maxlength="100"></label>
    <label>Email<input type="email" name="email" required></label>
    <button class="btn" type="submit">Create free account</button>
  </form>
</section>

<h2 class="section-h">Accounts</h2>
<div class="tablewrap">
<table class="users">
  <thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Status</th><th>Last sign-in</th><th><span class="sr-only">Actions</span></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e($u['name']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= $u['role'] === 'admin' ? 'Administrator' : 'Free beta' ?></td>
      <td><?= e(ucfirst($u['status'] === 'invited' ? 'waiting for password' : $u['status'])) ?></td>
      <td><?= $u['last_login_at'] ? e(date('M j, g:i a', (int)$u['last_login_at'])) : 'Never' ?></td>
      <td class="acts"><div>
        <form method="post" action="<?= url('/admin/users/' . $u['id'] . '/link') ?>"><?= csrf_field() ?><button class="linkbtn" type="submit"><?= $u['status'] === 'invited' ? 'New link' : 'Reset password' ?></button></form>
        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
        <form method="post" action="<?= url('/admin/users/' . $u['id'] . '/toggle') ?>"><?= csrf_field() ?><button class="linkbtn" type="submit"><?= $u['status'] === 'disabled' ? 'Enable' : 'Disable' ?></button></form>
        <?php endif; ?>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
