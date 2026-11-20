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
      <td data-l="Name"><?= e($u['name']) ?></td>
      <td data-l="Email"><?= e($u['email']) ?></td>
      <td data-l="Type"><?= $u['role'] === 'admin' ? 'Administrator' : 'Free beta' ?></td>
      <td data-l="Status"><?= e(ucfirst($u['status'] === 'invited' ? 'waiting for password' : $u['status'])) ?></td>
      <td data-l="Last sign-in"><?= $u['last_login_at'] ? e(date('M j, g:i a', (int)$u['last_login_at'])) : 'Never' ?></td>
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

<h2 class="section-h">Feedback from testers</h2>
<?php if (empty($feedback)): ?><p class="subtle">Nothing yet. Testers can send feedback from Me.</p><?php endif; ?>
<?php foreach ($feedback as $f): ?>
  <article class="card">
    <small class="due"><?= e($f['name']) ?> · <?= e(date('M j, g:i a', (int)$f['created_at'])) ?><?= $f['page'] !== '' ? ' · ' . e($f['page']) : '' ?></small>
    <p class="entry-body"><?= nl2br(e($f['message'])) ?></p>
  </article>
<?php endforeach; ?>
