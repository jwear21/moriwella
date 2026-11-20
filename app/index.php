<?php
declare(strict_types=1);
require __DIR__ . '/src/lib.php';
require __DIR__ . '/src/photos.php';
require __DIR__ . '/src/templates.php';

set_exception_handler(function (Throwable $t) {
    @file_put_contents(data_dir() . '/error.log', date('c') . ' ' . $t . "\n", FILE_APPEND);
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Something went wrong</title><p style="font:18px Georgia,serif;margin:3rem auto;max-width:34rem">Something went wrong on our side. Please try again in a moment.</p>';
});

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (strpos($path, MW_BASE) === 0) { $path = substr($path, strlen(MW_BASE)); }
$path = '/' . trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/health') {
    header('Content-Type: application/json'); header('X-Robots-Tag: noindex');
    $ok = false; try { db(); $ok = true; } catch (Throwable $t) {}
    echo json_encode(['php' => PHP_VERSION, 'sqlite_driver' => in_array('sqlite', PDO::getAvailableDrivers(), true), 'database_ok' => $ok, 'data_dir_writable' => is_writable(data_dir()),
        'gd' => function_exists('imagecreatefromstring'), 'gd_webp' => function_exists('imagecreatefromwebp'), 'exif' => function_exists('exif_read_data'),
        'upload_max' => ini_get('upload_max_filesize'), 'post_max' => ini_get('post_max_size'), 'memory_limit' => ini_get('memory_limit')]);
    exit;
}

boot();
$post = $method === 'POST';
if ($post && !$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('err', 'That upload was larger than this server allows (' . ini_get('post_max_size') . ' per save). Try fewer or smaller photos.');
    redirect($_SERVER['HTTP_REFERER'] ?? url('/'));
}
if ($post) csrf_check();

/* ---------- first-run admin setup ---------- */
if ($path === '/setup') {
    if (user_count() > 0) redirect(url('/login'));
    $err = null;
    if ($post) {
        $code = strtoupper(trim((string)($_POST['setup_code'] ?? '')));
        $err = check_password_rules((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''));
        if (!$err && !hash_equals((string)cfg('setup_code_hash'), hash('sha256', $code))) { $err = 'That setup code isn\'t right.'; }
        if (!$err) {
            db()->prepare("INSERT INTO users (email, name, role, plan, status, password_hash, created_at) VALUES (?, ?, 'admin', 'admin', 'active', ?, ?)")
                ->execute([cfg('admin_email'), cfg('admin_name'), hash_pw((string)$_POST['password']), time()]);
            $u = db()->query('SELECT * FROM users WHERE id = ' . (int)db()->lastInsertId())->fetch();
            login_user($u);
            flash('ok', 'Your administrator account is ready. Next, add your beta testers.');
            redirect(url('/admin'));
        }
    }
    render('setup', ['err' => $err, 'email' => cfg('admin_email'), 'name' => cfg('admin_name')], 'Set up Moriwella', true);
    exit;
}

/* ---------- sign in / out ---------- */
if ($path === '/login') {
    if (current_user()) redirect(url('/'));
    if (user_count() === 0) redirect(url('/setup'));
    $err = null; $email = '';
    if ($post) {
        $email = trim((string)($_POST['email'] ?? ''));
        if (too_many_attempts()) {
            $err = 'Too many attempts. Please wait 15 minutes and try again.';
        } else {
            $s = db()->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $s->execute([$email]);
            $u = $s->fetch();
            static $dummy = null;
            $hash = $u['password_hash'] ?? ($dummy ??= hash_pw('not-a-real-password'));
            $ok = password_verify((string)($_POST['password'] ?? ''), $hash) && $u && $u['password_hash'];
            if ($ok) { login_user($u); redirect(url('/')); }
            record_failed_attempt();
            $err = 'That email and password don\'t match.';
        }
    }
    render('login', ['err' => $err, 'email' => $email], 'Sign in', true);
    exit;
}
if ($path === '/logout' && $post) { $_SESSION = []; session_destroy(); redirect(url('/login')); }

/* ---------- invitation / password link ---------- */
if (preg_match('#^/join/([a-f0-9]{48})$#', $path, $m)) {
    $target = find_by_invite($m[1]);
    if (!$target) { render('message', ['heading' => 'This link has expired', 'text' => 'Ask Jeff for a new sign-in link.'], 'Link expired', true); exit; }
    $err = null;
    if ($post) {
        $err = check_password_rules((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''));
        if (!$err) {
            db()->prepare("UPDATE users SET password_hash = ?, status = 'active', invite_hash = NULL, invite_expires = NULL WHERE id = ?")
                ->execute([hash_pw((string)$_POST['password']), $target['id']]);
            login_user($target);
            flash('ok', 'Welcome to Moriwella, ' . $target['name'] . '.');
            redirect(url('/'));
        }
    }
    render('join', ['err' => $err, 'target' => $target], 'Choose your password', true);
    exit;
}

/* ---------- signed-in pages ---------- */
$user = require_login();
$uid = (int)$user['id'];

function entries_for_day(int $uid, string $day): array {
    $s = db()->prepare('SELECT * FROM entries WHERE user_id = ? AND entry_date = ? ORDER BY id DESC');
    $s->execute([$uid, $day]);
    $rows = $s->fetchAll();
    $ph = photos_for_entries(array_column($rows, 'id'));
    foreach ($rows as &$r) { $r['photos'] = $ph[$r['id']] ?? []; }
    return $rows;
}
function water_oz(int $uid, string $day): int {
    $s = db()->prepare('SELECT oz FROM water_log WHERE user_id = ? AND day = ?'); $s->execute([$uid, $day]);
    return (int)($s->fetchColumn() ?: 0);
}
function upcoming_items(int $uid, string $today): array {
    $s = db()->prepare('SELECT i.id, i.text, i.due_date, l.id list_id, l.name list_name FROM list_items i JOIN lists l ON l.id = i.list_id
        WHERE i.done_at IS NULL AND i.due_date IS NOT NULL AND i.due_date <= ?
        AND (l.owner_id = ? OR EXISTS (SELECT 1 FROM list_members m WHERE m.list_id = l.id AND m.user_id = ?))
        ORDER BY i.due_date, i.id LIMIT 8');
    $s->execute([date('Y-m-d', strtotime($today . ' +7 days')), $uid, $uid]);
    return $s->fetchAll();
}
function render_day(array $user, string $day): void {
    $uid = (int)$user['id'];
    render('day', [
        'goals' => goals_for_day($uid, $day), 'waterOn' => water_on($uid),
        'upcoming' => $day === date('Y-m-d') ? upcoming_items($uid, $day) : [],
        'day' => $day, 'isToday' => $day === date('Y-m-d'),
        'entries' => entries_for_day($uid, $day), 'water' => water_oz($uid, $day),
    ], $day === date('Y-m-d') ? 'Today' : date('F j, Y', strtotime($day)));
}

if ($path === '/') { render_day($user, date('Y-m-d')); exit; }

if (preg_match('#^/day/(\d{4}-\d{2}-\d{2})/combine$#', $path, $m) && $post) {
    $d = $m[1]; if (!valid_date($d)) redirect(url('/'));
    $q = db()->prepare('SELECT * FROM entries WHERE user_id = ? AND entry_date = ? AND event_id IS NULL ORDER BY id'); $q->execute([$uid, $d]);
    $rows = $q->fetchAll();
    if (count($rows) > 1) {
        $keep = array_shift($rows); $title = $keep['title']; $parts = $keep['body'] === '' ? [] : [$keep['body']];
        foreach ($rows as $r) {
            $chunk = trim(($r['title'] !== '' && $title !== '' ? $r['title'] . "\n" : '') . $r['body']);
            if ($title === '' && $r['title'] !== '') $title = $r['title'];
            if ($chunk !== '') $parts[] = $chunk;
        }
        db()->beginTransaction();
        db()->prepare('UPDATE entries SET title = ?, body = ?, updated_at = ? WHERE id = ?')->execute([$title, mb_substr(implode("\n\n", $parts), 0, 50000), time(), $keep['id']]);
        foreach ($rows as $r) {
            db()->prepare('UPDATE entry_photos SET entry_id = ? WHERE entry_id = ? AND user_id = ?')->execute([$keep['id'], $r['id'], $uid]);
            db()->prepare('DELETE FROM entries WHERE id = ? AND user_id = ?')->execute([$r['id'], $uid]);
        }
        db()->commit();
        flash('ok', 'Combined into one page.');
    }
    redirect(url('/day/' . $d));
}
if (preg_match('#^/day/(\d{4}-\d{2}-\d{2})$#', $path, $m)) {
    if (!valid_date($m[1])) redirect(url('/'));
    render_day($user, $m[1]); exit;
}

/* ----- photos ----- */
if (preg_match('#^/photo/(\d+)/(thumb|web|orig)$#', $path, $m)) {
    $s = db()->prepare('SELECT * FROM entry_photos WHERE id = ? AND user_id = ?');
    $s->execute([(int)$m[1], $uid]);
    $p = $s->fetch();
    $file = $p ? photo_path($p, $m[2]) : null;
    if (!$file) { http_response_code(404); exit; }
    header('Content-Type: ' . (substr($file, -4) === '.jpg' && substr($file, -6, 2) !== '_o' ? 'image/jpeg' : $p['mime']));
    header('Cache-Control: private, max-age=86400');
    header('Content-Length: ' . filesize($file));
    readfile($file); exit;
}
if (preg_match('#^/photos/(\d+)/(edit|revert)$#', $path, $m) && $post) {
    $s = db()->prepare('SELECT * FROM entry_photos WHERE id = ? AND user_id = ?');
    $s->execute([(int)$m[1], $uid]);
    $p = $s->fetch();
    if (!$p) json_out(['ok' => false, 'error' => 'Photo not found.'], 404);
    if ($m[2] === 'revert') { revert_photo_edit($p); json_out(['ok' => true, 'v' => time()]); }
    $err = save_photo_edit($p, $_FILES['edited'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
    if ($err) json_out(['ok' => false, 'error' => $err], 400);
    json_out(['ok' => true, 'v' => time()]);
}
if (preg_match('#^/photos/(\d+)/delete$#', $path, $m) && $post) {
    $s = db()->prepare('SELECT * FROM entry_photos WHERE id = ? AND user_id = ?');
    $s->execute([(int)$m[1], $uid]);
    if ($p = $s->fetch()) {
        delete_photo_files($p);
        db()->prepare('DELETE FROM entry_photos WHERE id = ?')->execute([$p['id']]);
        flash('ok', 'Photo removed.');
        redirect(url('/entries/' . $p['entry_id']));
    }
    redirect(url('/journal'));
}

/* ----- entries ----- */
if ($path === '/entries' && $post) {
    $date = (string)($_POST['entry_date'] ?? ''); if (!valid_date($date)) $date = date('Y-m-d');
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
    $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 50000);
    $hasPhotos = false; foreach (normalize_files('photos') as $f) { if ($f['error'] !== UPLOAD_ERR_NO_FILE) $hasPhotos = true; }
    if ($title === '' && $body === '' && !$hasPhotos) { flash('err', 'Write something or add a photo first.'); redirect(!empty($_POST['event_id']) ? url('/experiences/' . (int)$_POST['event_id']) : url('/day/' . $date)); }
    $now = time();
    $evId = (int)($_POST['event_id'] ?? 0); $ev = null;
    if ($evId) { $eq = db()->prepare('SELECT * FROM events WHERE id = ? AND user_id = ?'); $eq->execute([$evId, $uid]); $ev = $eq->fetch() ?: null; }
    if ($ev) {
        $secId = (int)($_POST['section_id'] ?? 0);
        $sq = db()->prepare('SELECT id FROM event_sections WHERE id = ? AND event_id = ?'); $sq->execute([$secId, $ev['id']]);
        $secId = $sq->fetchColumn() ?: null;
        db()->prepare('INSERT INTO entries (user_id, entry_date, title, body, created_at, updated_at, event_id, section_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $date, $title, $body, $now, $now, $ev['id'], $secId]);
        $errs = save_uploaded_photos((int)db()->lastInsertId(), $uid);
        flash($errs ? 'err' : 'ok', $errs ? 'Saved. ' . implode(' ', $errs) : 'Added to ' . $ev['title'] . '.');
        redirect(url('/experiences/' . $ev['id']));
    }
    $mainQ = db()->prepare('SELECT * FROM entries WHERE user_id = ? AND entry_date = ? AND event_id IS NULL ORDER BY id LIMIT 1'); $mainQ->execute([$uid, $date]);
    $main = $mainQ->fetch();
    if ($main && empty($_POST['separate'])) {
        /* Add to the day's page as a new paragraph */
        $joined = $main['body'] === '' ? $body : ($body === '' ? $main['body'] : $main['body'] . "\n\n" . $body);
        db()->prepare('UPDATE entries SET body = ?, updated_at = ? WHERE id = ? AND user_id = ?')->execute([mb_substr($joined, 0, 50000), $now, $main['id'], $uid]);
        $entryId = (int)$main['id'];
    } else {
        db()->prepare('INSERT INTO entries (user_id, entry_date, title, body, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $date, $title, $body, $now, $now]);
        $entryId = (int)db()->lastInsertId();
    }
    $errs = save_uploaded_photos($entryId, $uid);
    $n = db()->prepare('SELECT COUNT(*) FROM entries WHERE user_id = ?'); $n->execute([$uid]);
    if ($errs) { flash('err', 'Entry saved. ' . implode(' ', $errs)); }
    else { flash((int)$n->fetchColumn() === 1 ? 'first' : 'saved', 'Saved.'); }
    redirect(url('/day/' . $date));
}

if ($path === '/journal') {
    $s = db()->prepare('SELECT * FROM entries WHERE user_id = ? ORDER BY entry_date DESC, id DESC LIMIT 500');
    $s->execute([$uid]);
    $rows = $s->fetchAll();
    $ph = photos_for_entries(array_column($rows, 'id'));
    foreach ($rows as &$r) { $r['photos'] = $ph[$r['id']] ?? []; }
    render('journal', ['entries' => $rows], 'Journal');
    exit;
}

if ($path === '/calendar') {
    $month = (string)($_GET['m'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $month) || !valid_date($month . '-01')) $month = date('Y-m');
    $first = strtotime($month . '-01');
    $s = db()->prepare("SELECT entry_date, COUNT(*) c FROM entries WHERE user_id = ? AND entry_date BETWEEN ? AND ? GROUP BY entry_date");
    $s->execute([$uid, $month . '-01', $month . '-31']);
    $counts = []; foreach ($s->fetchAll() as $r) $counts[$r['entry_date']] = (int)$r['c'];
    $p = db()->prepare("SELECT e.entry_date d, COUNT(p.id) c FROM entry_photos p JOIN entries e ON e.id = p.entry_id WHERE e.user_id = ? AND e.entry_date BETWEEN ? AND ? GROUP BY e.entry_date");
    $p->execute([$uid, $month . '-01', $month . '-31']);
    $pcounts = []; foreach ($p->fetchAll() as $r) $pcounts[$r['d']] = (int)$r['c'];
    render('calendar', [
        'month' => $month, 'first' => $first, 'counts' => $counts, 'pcounts' => $pcounts,
        'prev' => date('Y-m', strtotime('-1 month', $first)), 'next' => date('Y-m', strtotime('+1 month', $first)),
    ], 'Calendar');
    exit;
}

if (preg_match('#^/entries/(\d+)$#', $path, $m)) {
    $s = db()->prepare('SELECT * FROM entries WHERE id = ? AND user_id = ?');
    $s->execute([(int)$m[1], $uid]);
    $entry = $s->fetch();
    if (!$entry) { http_response_code(404); render('message', ['heading' => 'Entry not found', 'text' => 'It may have been deleted.'], 'Not found'); exit; }
    if ($post) {
        if (isset($_POST['delete'])) {
            delete_entry_photos((int)$entry['id'], $uid);
            db()->prepare('DELETE FROM entries WHERE id = ? AND user_id = ?')->execute([$entry['id'], $uid]);
            flash('ok', 'Entry deleted.'); redirect($entry['event_id'] ? url('/experiences/' . $entry['event_id']) : url('/journal'));
        }
        $date = (string)($_POST['entry_date'] ?? ''); if (!valid_date($date)) $date = $entry['entry_date'];
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
        $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 50000);
        db()->prepare('UPDATE entries SET entry_date = ?, title = ?, body = ?, updated_at = ? WHERE id = ? AND user_id = ?')
            ->execute([$date, $title, $body, time(), $entry['id'], $uid]);
        $errs = save_uploaded_photos((int)$entry['id'], $uid);
        flash($errs ? 'err' : 'ok', $errs ? 'Changes saved. ' . implode(' ', $errs) : 'Changes saved.');
        redirect($entry['event_id'] ? url('/experiences/' . $entry['event_id']) : url('/day/' . $date));
    }
    $entry['photos'] = photos_for_entries([$entry['id']])[$entry['id']] ?? [];
    render('entry', ['entry' => $entry], 'Edit entry');
    exit;
}

/* ----- water tracker ----- */
if ($path === '/water' && $post) {
    $day = (string)($_POST['day'] ?? ''); if (!valid_date($day)) json_out(['ok' => false], 400);
    $oz = max(0, min(64, (int)($_POST['oz'] ?? 0)));
    $before = water_oz($uid, $day);
    db()->prepare('INSERT INTO water_log (user_id, day, oz) VALUES (?, ?, ?) ON CONFLICT(user_id, day) DO UPDATE SET oz = excluded.oz')->execute([$uid, $day, $oz]);
    json_out(['ok' => true, 'oz' => $oz, 'goal' => $oz >= 64 && $before < 64, 'allDone' => $oz >= 64 && $before < 64 && all_goals_done($uid, $day)]);
}

require __DIR__ . '/src/experiences.php';

/* ----- daily goals ----- */
function goals_for_day(int $uid, string $day): array {
    $s = db()->prepare('SELECT g.id, g.name, (l.goal_id IS NOT NULL) AS done FROM goals g LEFT JOIN goal_log l ON l.goal_id = g.id AND l.day = ? WHERE g.user_id = ? ORDER BY g.id');
    $s->execute([$day, $uid]);
    return $s->fetchAll();
}
function all_goals_done(int $uid, string $day): bool {
    $gs = goals_for_day($uid, $day);
    $waterDone = !water_on($uid) || water_oz($uid, $day) >= 64;
    if (!$gs && !water_on($uid)) return false;
    foreach ($gs as $g) if (!$g['done']) return false;
    return $waterDone;
}
if ($path === '/goals' && $post) {
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
    $c = db()->prepare('SELECT COUNT(*) FROM goals WHERE user_id = ?'); $c->execute([$uid]);
    if ($name === '') flash('err', 'Give the goal a name.');
    elseif ((int)$c->fetchColumn() >= 12) flash('err', 'That\'s plenty of goals. Remove one before adding another.');
    else { db()->prepare('INSERT INTO goals (user_id, name, created_at) VALUES (?, ?, ?)')->execute([$uid, $name, time()]); flash('ok', 'Goal added.'); }
    redirect(url('/settings') . '#goals');
}
if (preg_match('#^/goals/(\d+)/(toggle|delete)$#', $path, $mm) && $post) {
    $g = db()->prepare('SELECT id FROM goals WHERE id = ? AND user_id = ?'); $g->execute([(int)$mm[1], $uid]);
    if (!$g->fetch()) { if (wants_json()) json_out(['ok' => false], 404); redirect(url('/settings') . '#goals'); }
    if ($mm[2] === 'delete') { db()->prepare('DELETE FROM goals WHERE id = ? AND user_id = ?')->execute([(int)$mm[1], $uid]); redirect(url('/settings') . '#goals'); }
    $day = (string)($_POST['day'] ?? ''); if (!valid_date($day)) json_out(['ok' => false], 400);
    $q = db()->prepare('SELECT 1 FROM goal_log WHERE goal_id = ? AND day = ?'); $q->execute([(int)$mm[1], $day]);
    if ($q->fetch()) { db()->prepare('DELETE FROM goal_log WHERE goal_id = ? AND day = ?')->execute([(int)$mm[1], $day]); $done = false; }
    else { db()->prepare('INSERT INTO goal_log (goal_id, day, done_at) VALUES (?, ?, ?)')->execute([(int)$mm[1], $day, time()]); $done = true; }
    json_out(['ok' => true, 'done' => $done, 'allDone' => $done && all_goals_done($uid, $day)]);
}

/* ----- lists ----- */
function list_access(int $listId, int $uid): ?array {
    $s = db()->prepare('SELECT l.*, (l.owner_id = ?) AS is_owner FROM lists l WHERE l.id = ? AND (l.owner_id = ? OR EXISTS (SELECT 1 FROM list_members m WHERE m.list_id = l.id AND m.user_id = ?))');
    $s->execute([$uid, $listId, $uid, $uid]);
    return $s->fetch() ?: null;
}
function list_counts(int $listId): array {
    $s = db()->prepare('SELECT COUNT(*) total, SUM(CASE WHEN done_at IS NULL THEN 1 ELSE 0 END) open FROM list_items WHERE list_id = ?');
    $s->execute([$listId]); $r = $s->fetch();
    return ['total' => (int)$r['total'], 'open' => (int)$r['open']];
}
if ($path === '/lists') {
    if ($post) {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
        $kind = in_array($_POST['kind'] ?? '', ['todo', 'shopping', 'project', 'chore'], true) ? $_POST['kind'] : 'todo';
        if ($name === '') { flash('err', 'Give the list a name.'); redirect(url('/lists')); }
        db()->prepare('INSERT INTO lists (owner_id, name, kind, created_at) VALUES (?, ?, ?, ?)')->execute([$uid, $name, $kind, time()]);
        redirect(url('/lists/' . db()->lastInsertId()));
    }
    $s = db()->prepare('SELECT l.*, (l.owner_id = ?) AS is_owner, u.name owner_name FROM lists l JOIN users u ON u.id = l.owner_id
        WHERE l.owner_id = ? OR EXISTS (SELECT 1 FROM list_members m WHERE m.list_id = l.id AND m.user_id = ?) ORDER BY l.name');
    $s->execute([$uid, $uid, $uid]);
    $lists = $s->fetchAll();
    foreach ($lists as &$l) {
        $l += list_counts((int)$l['id']);
        $c = db()->prepare('SELECT COUNT(*) FROM list_members WHERE list_id = ?'); $c->execute([$l['id']]); $l['shared'] = (int)$c->fetchColumn() > 0;
    }
    render('lists', ['lists' => $lists], 'Lists');
    exit;
}
if (preg_match('#^/lists/(\d+)(?:/(.*))?$#', $path, $m)) {
    $listId = (int)$m[1]; $sub = $m[2] ?? '';
    $list = list_access($listId, $uid);
    if (!$list) { http_response_code(404); render('message', ['heading' => 'List not found', 'text' => 'It may have been deleted or not shared with you.'], 'Not found'); exit; }
    $isOwner = (bool)$list['is_owner'];
    if ($post) {
        if ($sub === 'items') {
            $text = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 300);
            $due = (string)($_POST['due_date'] ?? ''); if ($due !== '' && !valid_date($due)) $due = '';
            if ($text !== '') db()->prepare('INSERT INTO list_items (list_id, text, due_date, created_by, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$listId, $text, $due ?: null, $uid, time()]);
            redirect(url('/lists/' . $listId));
        }
        if (preg_match('#^items/(\d+)/toggle$#', $sub, $mm)) {
            $it = db()->prepare('SELECT * FROM list_items WHERE id = ? AND list_id = ?'); $it->execute([(int)$mm[1], $listId]); $item = $it->fetch();
            if ($item) {
                $nowDone = $item['done_at'] === null;
                db()->prepare('UPDATE list_items SET done_at = ?, done_by = ? WHERE id = ?')->execute([$nowDone ? time() : null, $nowDone ? $uid : null, $item['id']]);
            }
            $c = list_counts($listId);
            if (wants_json()) json_out(['ok' => true, 'done' => $item ? $nowDone : false, 'open' => $c['open'], 'total' => $c['total'], 'allDone' => $c['total'] > 0 && $c['open'] === 0, 'list' => $list['name']]);
            redirect(url('/lists/' . $listId));
        }
        if (preg_match('#^items/(\d+)/delete$#', $sub, $mm)) {
            db()->prepare('DELETE FROM list_items WHERE id = ? AND list_id = ?')->execute([(int)$mm[1], $listId]);
            redirect(url('/lists/' . $listId));
        }
        if ($sub === 'clear') { db()->prepare('DELETE FROM list_items WHERE list_id = ? AND done_at IS NOT NULL')->execute([$listId]); redirect(url('/lists/' . $listId)); }
        if ($sub === 'share' && $isOwner) {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $t = db()->prepare("SELECT id, name FROM users WHERE email = ? AND status != 'disabled'"); $t->execute([$email]); $target = $t->fetch();
            if (!$target) flash('err', 'No Moriwella account uses ' . $email . ' yet. Ask Jeff to add them first.');
            elseif ((int)$target['id'] === $uid) flash('err', 'That\'s you. You already own this list.');
            else {
                db()->prepare('INSERT OR IGNORE INTO list_members (list_id, user_id, added_at) VALUES (?, ?, ?)')->execute([$listId, $target['id'], time()]);
                flash('ok', 'Shared with ' . $target['name'] . '.');
            }
            redirect(url('/lists/' . $listId));
        }
        if (preg_match('#^members/(\d+)/remove$#', $sub, $mm)) {
            $mid = (int)$mm[1];
            if ($isOwner || $mid === $uid) db()->prepare('DELETE FROM list_members WHERE list_id = ? AND user_id = ?')->execute([$listId, $mid]);
            redirect($mid === $uid && !$isOwner ? url('/lists') : url('/lists/' . $listId));
        }
        if ($sub === 'delete' && $isOwner) { db()->prepare('DELETE FROM lists WHERE id = ?')->execute([$listId]); flash('ok', 'List deleted.'); redirect(url('/lists')); }
        redirect(url('/lists/' . $listId));
    }
    $items = db()->prepare('SELECT i.*, u.name done_name FROM list_items i LEFT JOIN users u ON u.id = i.done_by WHERE i.list_id = ? ORDER BY (i.done_at IS NOT NULL), COALESCE(i.due_date, "9999"), i.id');
    $items->execute([$listId]);
    $mem = db()->prepare('SELECT u.id, u.name, u.email FROM list_members m JOIN users u ON u.id = m.user_id WHERE m.list_id = ? ORDER BY u.name');
    $mem->execute([$listId]);
    $ow = db()->prepare('SELECT name FROM users WHERE id = ?'); $ow->execute([$list['owner_id']]);
    render('list', ['list' => $list, 'items' => $items->fetchAll(), 'members' => $mem->fetchAll(), 'ownerName' => $ow->fetchColumn(), 'isOwner' => $isOwner], $list['name']);
    exit;
}

/* ----- settings ----- */
if ($path === '/settings') {
    if ($post) {
        if (isset($_POST['save_prefs'])) {
            save_prefs($uid, !empty($_POST['celebrations']), !empty($_POST['water_on']), (string)($_POST['celebrate_size'] ?? 'big'));
            flash('ok', 'Preferences saved.'); redirect(url('/settings'));
        }
        if (isset($_POST['change_password'])) {
            $err = check_password_rules((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''));
            if (!$err && !password_verify((string)($_POST['current'] ?? ''), (string)$user['password_hash'])) $err = 'Your current password isn\'t right.';
            if ($err) { flash('err', $err); }
            else { db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([hash_pw((string)$_POST['password']), $uid]); flash('ok', 'Password changed.'); }
            redirect(url('/settings'));
        }
    }
    render('settings', ['celebrations' => celebrations_on($uid), 'waterOn' => water_on($uid), 'size' => celebrate_size($uid), 'goals' => goals_for_day($uid, date('Y-m-d'))], 'Settings');
    exit;
}

/* ---------- administration ---------- */
if (strpos($path, '/admin') === 0) {
    $admin = require_admin();
    if ($path === '/admin' && $post && isset($_POST['create'])) {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('err', 'Enter a name and a valid email address.'); redirect(url('/admin')); }
        $dup = db()->prepare('SELECT id FROM users WHERE email = ?'); $dup->execute([$email]);
        if ($dup->fetch()) { flash('err', 'There is already an account for ' . $email . '.'); redirect(url('/admin')); }
        db()->prepare("INSERT INTO users (email, name, role, plan, status, created_at) VALUES (?, ?, 'member', 'beta_free', 'invited', ?)")
            ->execute([$email, $name, time()]);
        $_SESSION['new_link'] = ['name' => $name, 'link' => make_invite((int)db()->lastInsertId())];
        redirect(url('/admin'));
    }
    if (preg_match('#^/admin/users/(\d+)/(link|toggle)$#', $path, $m) && $post) {
        $s = db()->prepare('SELECT * FROM users WHERE id = ?'); $s->execute([(int)$m[1]]);
        $t = $s->fetch();
        if ($t) {
            if ($m[2] === 'link') {
                $_SESSION['new_link'] = ['name' => $t['name'], 'link' => make_invite((int)$t['id'])];
            } elseif ((int)$t['id'] !== (int)$admin['id']) {
                $new = $t['status'] === 'disabled' ? ($t['password_hash'] ? 'active' : 'invited') : 'disabled';
                db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $t['id']]);
                flash('ok', $t['name'] . ($new === 'disabled' ? ' can no longer sign in.' : ' can sign in again.'));
            }
        }
        redirect(url('/admin'));
    }
    if ($path === '/admin') {
        $newLink = $_SESSION['new_link'] ?? null; unset($_SESSION['new_link']);
        render('admin', ['users' => db()->query('SELECT * FROM users ORDER BY role DESC, name')->fetchAll(), 'newLink' => $newLink], 'Administration');
        exit;
    }
}

http_response_code(404);
render('message', ['heading' => 'Page not found', 'text' => 'That page doesn\'t exist.'], 'Not found', !current_user());
