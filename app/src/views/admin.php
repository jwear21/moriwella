<h1 class="page-title">Administration</h1>
<p class="subtle">Create free beta accounts for testers, and manage everyone below.</p>

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

<h2 class="section-h" id="access">People</h2>
<p class="subtle"><?= (int)$total ?> <?= $total === 1 ? 'account' : 'accounts' ?><?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?>. Click a person to manage their plan, role and sign-in.</p>
<form method="get" action="<?= url('/admin') ?>" class="people-search" role="search">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name or email" aria-label="Search people" autocomplete="off">
  <button class="btn" type="submit">Search</button>
  <?php if ($q !== ''): ?><a class="btn btn-quiet" href="<?= url('/admin') ?>">Clear</a><?php endif; ?>
</form>
<?php if (!$access): ?><p class="subtle">Nobody matches that search.</p><?php endif; ?>
<div class="people">
<?php foreach ($access as $a): $u = $a['u']; $sub = $a['sub'] ?? null;
  $isAdmin = $u['role'] === 'admin'; $isMe = (int)$u['id'] === (int)$user['id'];
  $type = $isAdmin ? 'Administrator' : ($u['plan'] === 'demo' ? 'Demo' : ($u['plan'] === 'beta_free' ? 'Free beta' : 'Customer'));
  if (isset($a['member_of'])) { $label = 'In ' . $a['member_of'] . "'s household"; $comped = false; }
  else {
    $comped = $sub && !empty($sub['comped']);
    $label = $comped ? 'Free ' . (plan_for($sub['plan_key'])['name'] ?? '') . ', ' . (int)$sub['seat_limit'] . ' seat' . ((int)$sub['seat_limit'] === 1 ? '' : 's') : ($sub && in_array($sub['status'], ['active', 'trialing', 'past_due'], true) ? 'Paid ' . (plan_for($sub['plan_key'])['name'] ?? '') : ($sub && (int)$sub['trial_end'] > time() ? 'Free trial' : 'No plan yet'));
  }
  $hidden = csrf_field() . '<input type="hidden" name="q" value="' . e($q) . '"><input type="hidden" name="page" value="' . (int)$page . '">'; ?>
  <details class="person">
    <summary>
      <span class="person-name"><?= e($u['name']) ?><?php if ($isAdmin): ?> <span class="tag">Admin</span><?php endif; ?><?php if ($u['status'] !== 'active'): ?> <span class="tag tag-warn"><?= e($u['status'] === 'invited' ? 'Invited' : 'Disabled') ?></span><?php endif; ?></span>
      <span class="person-email"><?= e($u['email']) ?></span>
      <span class="person-plan"><?= e($label) ?></span>
    </summary>
    <div class="person-body">
      <p class="person-meta"><?= e($type) ?> · Last sign-in <?= $u['last_login_at'] ? e(date('M j, g:i a', (int)$u['last_login_at'])) : 'never' ?><?= isset($a['used']) ? ' · ' . (int)$a['used'] . ' in household' : '' ?></p>
      <?php if (!isset($a['member_of'])): ?>
      <form method="post" action="<?= url('/admin/users/' . $u['id'] . '/access') ?>" class="access-form" id="acc-<?= (int)$u['id'] ?>">
        <?= csrf_field() ?>
        <label>Plan <select name="plan">
          <?php foreach (plans() as $k => $p): ?><option value="<?= e($k) ?>"<?= ($comped ? $sub['plan_key'] : 'individual') === $k ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
        </select></label>
        <label>Seats <input type="number" name="seats" min="1" max="500" value="<?= $comped ? (int)$sub['seat_limit'] : 1 ?>"></label>
        <label>Credits a month each <input type="number" name="credits" min="0" max="100" value="<?= $comped ? (int)($sub['comp_credits'] ?? 0) : 0 ?>"></label>
      </form>
      <?php endif; ?>
      <?php $id = (int)$u['id'];
        echo '<form method="post" id="role-' . $id . '" action="' . url('/admin/users/' . $id . '/role') . '">' . $hidden . '<input type="hidden" name="role" value="' . ($isAdmin ? 'member' : 'admin') . '"></form>';
        echo '<form method="post" id="act-' . $id . '" action="' . url('/admin/users/' . $id . '/act') . '">' . csrf_field() . '</form>';
        echo '<form method="post" id="lnk-' . $id . '" action="' . url('/admin/users/' . $id . '/link') . '">' . csrf_field() . '</form>';
        echo '<form method="post" id="tog-' . $id . '" action="' . url('/admin/users/' . $id . '/toggle') . '">' . csrf_field() . '</form>'; ?>
      <div class="access-btns person-acts">
        <?php if (!isset($a['member_of'])): ?>
        <button class="btn" type="submit" form="acc-<?= $id ?>" name="mode" value="comped"><?= $comped ? 'Save changes' : 'Give free plan' ?></button>
        <?php if ($comped): ?><button class="btn btn-quiet" type="submit" form="acc-<?= $id ?>" name="mode" value="standard">Normal billing</button><?php endif; ?>
        <?php endif; ?>
        <?php if (!($isAdmin && $isMe)): ?><button class="btn btn-quiet" type="submit" form="role-<?= $id ?>"><?= $isAdmin ? 'Remove admin' : 'Make admin' ?></button><?php endif; ?>
        <?php if (!$isMe && $u['status'] === 'active'): ?><button class="btn btn-quiet" type="submit" form="act-<?= $id ?>">Act as <?= e(strtok((string)$u['name'], ' ')) ?></button><?php endif; ?>
        <button class="btn btn-quiet" type="submit" form="lnk-<?= $id ?>"><?= $u['status'] === 'invited' ? 'New sign-in link' : 'Reset password' ?></button>
        <?php if (!$isMe): ?><button class="btn btn-quiet" type="submit" form="tog-<?= $id ?>"><?= $u['status'] === 'disabled' ? 'Enable account' : 'Disable account' ?></button><?php endif; ?>
      </div>
    </div>
  </details>
<?php endforeach; ?>
</div>
<?php if ($pages > 1): $qs = fn($n) => url('/admin?' . http_build_query(array_filter(['q' => $q, 'page' => $n > 1 ? $n : null]))); ?>
<nav class="pager" aria-label="Pages">
  <?php if ($page > 1): ?><a class="btn btn-quiet" href="<?= e($qs($page - 1)) ?>">Previous</a><?php endif; ?>
  <span>Page <?= (int)$page ?> of <?= (int)$pages ?></span>
  <?php if ($page < $pages): ?><a class="btn btn-quiet" href="<?= e($qs($page + 1)) ?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>

<h2 class="section-h">Movie credits</h2>
<p class="subtle">Give movie credits to anyone, including yourself. They never expire and are recorded with your name.</p>
<form method="post" action="<?= url('/admin/credits') ?>" class="card" style="display:grid;gap:.6rem;max-width:30rem">
  <?= csrf_field() ?>
  <label>Who gets them (email)
    <input type="email" name="email" required value="<?= e($user['email']) ?>" autocomplete="off">
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
