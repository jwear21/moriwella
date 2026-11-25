<?php $tab = $tab ?? 'people';
$sections = ['people' => 'People', 'credits' => 'Credits', 'languages' => 'Languages', 'feedback' => 'Feedback', 'backups' => 'Backups'];
$badge = ['people' => (int)($counts['people'] ?? 0), 'feedback' => (int)($counts['feedback'] ?? 0)]; ?>
<link rel="stylesheet" href="/app/static/admin.css?v=<?= asset_v('static/admin.css') ?>">
<div class="adm">
<header class="adm-top">
  <h1 class="adm-title">Administration</h1>
  <p class="adm-lead">Accounts, credits, translations, feedback and backups for Moriwella.</p>
</header>
<nav class="adm-tabs" aria-label="Administration sections">
<?php foreach ($sections as $k => $label): ?>
  <a href="<?= url('/admin?tab=' . $k) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if (!empty($badge[$k])): ?><span class="adm-n"><?= (int)$badge[$k] ?></span><?php endif; ?></a>
<?php endforeach; ?>
</nav>

<?php if ($tab === 'people'): ?>
<?php if ($newLink): ?>
<section class="card linkbox" role="status">
  <h2>Sign-in link for <?= e($newLink['name']) ?></h2>
  <p>Send them this link. They'll choose their own password. It works for 7 days.</p>
  <div class="copyrow"><input readonly id="newlink" value="<?= e($newLink['link']) ?>"><button type="button" class="btn" data-copy="#newlink">Copy link</button></div>
</section>
<?php endif; ?>

<details class="adm-add card"<?= $newLink ? " open" : "" ?>>
  <summary>Add a beta tester</summary>
  <form method="post" class="inline-form">
    <?= csrf_field() ?><input type="hidden" name="create" value="1">
    <label>Name<input name="name" required maxlength="100"></label>
    <label>Email<input type="email" name="email" required></label>
    <button class="btn" type="submit">Create free account</button>
  </form>
</details>

<span id="access"></span>
<p class="adm-count"><?= (int)$total ?> <?= $total === 1 ? 'account' : 'accounts' ?><?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?>. Click a person to manage their plan, role and sign-in.</p>
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
      <p class="person-meta"><?= e($type) ?><?php if (!empty($a['stats'])): ?> · <?= (int)$a['stats']['entries'] ?> entries, <?= (int)$a['stats']['photos'] ?> photos, <?= (int)$a['stats']['movies'] ?> movies<?php endif; ?> · Last sign-in <?= $u['last_login_at'] ? e(date('M j, g:i a', (int)$u['last_login_at'])) : 'never' ?><?= isset($a['used']) ? ' · ' . (int)$a['used'] . ' in household' : '' ?></p>
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
        <?php if (!$isMe && !$isAdmin): ?><button class="btn btn-quiet btn-del" type="button" data-del="<?= $id ?>">Delete…</button><?php endif; ?>
      </div>
      <?php if (!$isMe && !$isAdmin): ?>
      <details class="person-del" id="del-<?= $id ?>">
        <summary>Delete this account</summary>
        <form method="post" action="<?= url('/admin/users/' . $id . '/delete') ?>">
          <?= $hidden ?>
          <p class="subtle">This permanently deletes <?= e($u['name']) ?> and everything they own: entries, photos, movies and settings. It cannot be undone. Type their email to confirm.</p>
          <input type="text" name="confirm" required autocomplete="off" placeholder="<?= e($u['email']) ?>" aria-label="Type the email to confirm">
          <button class="btn btn-danger" type="submit">Delete permanently</button>
        </form>
      </details>
      <?php endif; ?>
    </div>
  </details>
<?php endforeach; ?>
</div>
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-del]'); if (!b) return;
  var d = document.getElementById('del-' + b.getAttribute('data-del')); if (!d) return;
  d.open = true; var i = d.querySelector('input[name=confirm]'); d.scrollIntoView({ block: 'center', behavior: 'smooth' }); if (i) i.focus();
});
</script>
<?php if ($pages > 1): $qs = fn($n) => url('/admin?' . http_build_query(array_filter(['q' => $q, 'page' => $n > 1 ? $n : null]))); ?>
<nav class="pager" aria-label="Pages">
  <?php if ($page > 1): ?><a class="btn btn-quiet" href="<?= e($qs($page - 1)) ?>">Previous</a><?php endif; ?>
  <span>Page <?= (int)$page ?> of <?= (int)$pages ?></span>
  <?php if ($page < $pages): ?><a class="btn btn-quiet" href="<?= e($qs($page + 1)) ?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php if ($tab === 'credits'): ?>
<p class="adm-sub">Give movie credits to anyone, including yourself. They never expire and are recorded with your name.</p>
<form method="post" action="<?= url('/admin/credits') ?>" class="card" style="display:grid;gap:.6rem;max-width:30rem">
  <?= csrf_field() ?>
  <label>Who gets them (pick from the list or type a name or email)
    <input type="text" name="email" required value="<?= e($user['email']) ?>" list="credit-people" autocomplete="off" inputmode="email" spellcheck="false" placeholder="Start typing a name or email">
    <datalist id="credit-people"><?php foreach (($allUsers ?? []) as $p): ?><option value="<?= e($p['email']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?></datalist>
  </label>
  <small class="subtle">Click the box and clear it to see everyone, or type to narrow the list.</small>
  <label>How many <input type="number" name="amount" min="1" max="500" value="5" required></label>
  <label>Reason (optional) <input type="text" name="reason" maxlength="120" placeholder="Testing, thank-you, support"></label>
  <button class="btn" type="submit">Give credits</button>
</form>
<?php if (!empty($grants)): ?>
<div class="tablewrap"><table class="users"><thead><tr><th>When</th><th>Who</th><th>Credits</th><th>Note</th></tr></thead><tbody>
<?php foreach ($grants as $g): ?><tr><td data-l="When"><?= e(date('M j, g:i a', (int)$g['created_at'])) ?></td><td data-l="Who"><?= e($g['name']) ?></td><td data-l="Credits"><?= (int)$g['delta'] ?></td><td data-l="Note"><?= e($g['note']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php endif; ?>

<?php if ($tab === 'languages'): ?>
<?php $lgTitles = ['privacy' => 'Privacy policy', 'terms' => 'Terms of service', 'delete-account' => 'Delete account page']; $nCodes = count(tr_codes());
  $nLang = count(array_filter($tr['rows'], fn($r) => $r['n'] > 0)); ?>
<section class="card" id="tr-box" data-csrf="<?= e(csrf_token()) ?>" data-url="<?= url('/admin/translate') ?>" data-legal-url="<?= url('/admin/legal') ?>" data-pieces='<?= e(json_encode(array_combine(array_keys(legal_docs()), $legal['pieces']))) ?>'>
  <?php if ($tr['total'] === 0 && $legal['todo'] === 0): ?>
    <p>Everything is translated: all phrases and all three legal pages, in all <?= (int)$nCodes + 1 ?> languages. Nothing is waiting.</p>
  <?php else: ?>
    <p>Waiting to translate: <b><?= (int)$tr['total'] ?></b> new phrases across <?= (int)$nLang ?> languages and <b><?= (int)$legal['todo'] ?></b> legal page translations. Roughly <b>$<?= number_format($tr['cost'] + ($legal['todo'] ? 2 : 0), 2) ?></b> with Claude.</p>
  <?php endif; ?>
  <?php if (!$trKey): ?><p class="note note--err">Add <code>'anthropic_api_key' =&gt; 'sk-ant-...'</code> to <code>app/src/config.php</code> on the server first (that file is never in git).</p><?php endif; ?>
  <p><button class="btn" type="button" id="tr-go" data-langs='<?= e(json_encode(array_keys($tr['rows']))) ?>'<?= $trKey ? '' : ' disabled' ?>>Run language sweep now</button> <span id="tr-status" role="status"></span></p>
  <p class="hint">One button does everything. It checks every language against the English phrases, then checks the privacy policy, terms and delete-account pages, and translates only what is missing or changed. It never replaces a translation you wrote by hand. Run it after each deploy that adds new text.</p>
  <?php if ($tr['total'] > 0): ?>
    <details><summary>Which phrases are missing</summary><ul>
      <?php foreach ($tr['rows'] as $c => $r): if (!$r['n']) continue; ?><li><code><?= e($c) ?></code> (<?= e(lang_english_name($c)) ?>): <span data-tr-n="<?= e($c) ?>"><?= (int)$r['n'] ?></span> missing, e.g. <?= e(implode(', ', $r['sample'])) ?></li><?php endforeach; ?>
    </ul></details>
  <?php endif; ?>
  <details><summary>Legal pages (<?= $legal['todo'] === 0 ? 'all current' : (int)$legal['todo'] . ' to translate' ?>)</summary>
    <table class="adm-table">
      <thead><tr><th>Page</th><th>Official English</th><th>Translations</th></tr></thead>
      <tbody>
      <?php foreach (legal_docs() as $d => $file): $ok = count(array_filter($legal['rows'][$d] ?? [])); ?>
        <tr><td><b><?= e($lgTitles[$d] ?? $d) ?></b></td><td><a href="<?= e('/' . $file) ?>" target="_blank" rel="noopener">Open</a></td><td><?= (int)$ok ?> of <?= (int)$nCodes ?> current</td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </details>
  <script type="application/json" id="lg-todo"><?= json_encode(array_map(fn($r) => array_keys(array_filter($r, fn($ok) => !$ok)), $legal['rows'])) ?></script>
  <p class="hint">Spent so far: $<?= number_format($trSpent['cost'], 2) ?> over <?= (int)$trSpent['calls'] ?> requests. Translations are kept in the private data folder, so a hand-written translation always wins and deploys never erase them.</p>
</section>
<script src="/app/static/admin-translate.js?v=<?= asset_v('static/admin-translate.js') ?>"></script>
<?php endif; ?>

<?php if ($tab === 'feedback'): ?>
<?php if (empty($feedback)): ?><p class="adm-sub">Nothing yet. Testers can send feedback from Me.</p><?php endif; ?>
<?php foreach ($feedback as $f): ?>
  <article class="card">
    <small class="due"><?= e($f['name']) ?> · <?= e(date('M j, g:i a', (int)$f['created_at'])) ?><?= $f['page'] !== '' ? ' · ' . e($f['page']) : '' ?></small>
    <p class="entry-body"><?= nl2br(e($f['message'])) ?></p>
  </article>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($tab === 'backups'): $bst = $backup['status']; $running = ($bst['state'] ?? '') === 'running'; ?>
<p class="adm-sub">One archive holds the whole site: the database, every photo, avatar and movie, all website files, and the passwords, keys and settings. Download a copy now and then so it also lives somewhere other than this server.</p>
<section class="card adm-bk" id="bk-box" data-status="<?= url('/admin/backup/status') ?>" data-running="<?= $running ? '1' : '0' ?>">
  <div class="adm-bk-state">
    <?php if ($running): ?><p><b>A backup is running.</b> This page will update when it finishes.</p>
    <?php elseif (($bst['state'] ?? '') === 'error'): ?><p class="note note--err"><?= e((string)($bst['message'] ?? 'The backup failed.')) ?></p>
    <?php elseif (($bst['state'] ?? '') === 'done'): ?><p class="note note--ok">Last backup finished <?= e(date('M j, g:i a', (int)$bst['finished'])) ?>: <?= e(backup_size((int)$bst['size'])) ?>, <?= (int)($bst['files'] ?? 0) ?> files.</p>
    <?php else: ?><p>No backups yet.</p><?php endif; ?>
  </div>
  <form method="post" action="<?= url('/admin/backup/start') ?>" id="bk-start" class="adm-bk-form">
    <?= csrf_field() ?>
    <p class="adm-warn">Archives contain passwords and keys. Keep downloads private.</p>
    <button class="btn" type="submit"<?= $running ? ' disabled' : '' ?>>Back up now</button>
  </form>
  <?php if ($running || ($bst['state'] ?? '') === 'error'): ?>
  <form method="post" action="<?= url('/admin/backup/reset') ?>" class="adm-reset"><?= csrf_field() ?><button class="btn btn-quiet" type="submit">Backup stuck? Reset it</button></form>
  <?php endif; ?>
</section>

<h2 class="adm-h">Saved backups</h2>
<?php if (!$backup['list']): ?><p class="adm-sub">Nothing saved yet.</p><?php else: ?>
<div class="adm-table"><table>
  <thead><tr><th>Backup</th><th>Made</th><th class="num">Size</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($backup['list'] as $b): ?>
    <tr>
      <td data-l="Backup"><?= e($b['name']) ?></td>
      <td data-l="Made"><?= e(date('M j, Y g:i a', $b['time'])) ?></td>
      <td class="num" data-l="Size"><?= e(backup_size($b['size'])) ?></td>
      <td class="adm-acts">
        <form method="post" action="<?= url('/admin/backup/download') ?>"><?= csrf_field() ?><input type="hidden" name="name" value="<?= e($b['name']) ?>"><button class="btn btn-quiet" type="submit">Download</button></form>
        <form method="post" action="<?= url('/admin/backup/delete') ?>" onsubmit="return confirm('Delete this backup?')"><?= csrf_field() ?><input type="hidden" name="name" value="<?= e($b['name']) ?>"><button class="btn btn-quiet" type="submit">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<p class="hint">Saved in the private folder <code><?= e($backup['dir']) ?></code>, which cannot be opened from the web. Only the newest <?= (int)BACKUP_KEEP ?> are kept; each new backup replaces the oldest.</p>
<script src="/app/static/admin-backup.js?v=<?= asset_v('static/admin-backup.js') ?>"></script>
<?php endif; ?>
</div>
