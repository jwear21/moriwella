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
<p class="subtle"><a class="link" href="<?= url('/admin/demo') ?>">Demo accounts for testing</a></p>
<div class="tablewrap">
<table class="users">
  <thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Status</th><th>Last sign-in</th><th><span class="sr-only">Actions</span></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td data-l="Name"><?= e($u['name']) ?></td>
      <td data-l="Email"><?= e($u['email']) ?></td>
      <td data-l="Type"><?= $u['role'] === 'admin' ? 'Administrator' : ($u['plan'] === 'demo' ? 'Demo' : 'Free beta') ?></td>
      <td data-l="Status"><?= e(ucfirst($u['status'] === 'invited' ? 'waiting for password' : $u['status'])) ?></td>
      <td data-l="Last sign-in"><?= $u['last_login_at'] ? e(date('M j, g:i a', (int)$u['last_login_at'])) : 'Never' ?></td>
      <td class="acts"><div>
        <?php if ($u['role'] !== 'admin' && $u['status'] === 'active'): ?>
        <form method="post" action="<?= url('/admin/users/' . $u['id'] . '/act') ?>"><?= csrf_field() ?><button class="linkbtn" type="submit">Act as</button></form>
        <?php endif; ?>
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

<h2 class="section-h">Movie credits</h2>
<p class="subtle">Give movie credits to anyone, including yourself. They never expire and are recorded with your name.</p>
<form method="post" action="<?= url('/admin/credits') ?>" class="card" style="display:grid;gap:.6rem;max-width:30rem">
  <?= csrf_field() ?>
  <label>Who gets them
    <select name="user_id" required>
      <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"<?= (int)$u['id'] === (int)$user['id'] ? ' selected' : '' ?>><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option><?php endforeach; ?>
    </select>
  </label>
  <label>How many <input type="number" name="amount" min="1" max="500" value="5" required></label>
  <label>Reason (optional) <input type="text" name="reason" maxlength="120" placeholder="Testing, thank-you, support"></label>
  <button class="btn" type="submit">Give credits</button>
</form>
<?php if (!empty($grants)): ?>
<div class="tablewrap"><table class="users"><thead><tr><th>When</th><th>Who</th><th>Credits</th><th>Note</th></tr></thead><tbody>
<?php foreach ($grants as $g): ?><tr><td data-l="When"><?= e(date('M j, g:i a', (int)$g['created_at'])) ?></td><td data-l="Who"><?= e($g['name']) ?></td><td data-l="Credits"><?= (int)$g['delta'] ?></td><td data-l="Note"><?= e($g['note']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<h2 class="section-h">Feedback from testers</h2>
<?php if (empty($feedback)): ?><p class="subtle">Nothing yet. Testers can send feedback from Me.</p><?php endif; ?>
<?php foreach ($feedback as $f): ?>
  <article class="card">
    <small class="due"><?= e($f['name']) ?> · <?= e(date('M j, g:i a', (int)$f['created_at'])) ?><?= $f['page'] !== '' ? ' · ' . e($f['page']) : '' ?></small>
    <p class="entry-body"><?= nl2br(e($f['message'])) ?></p>
  </article>
<?php endforeach; ?>
