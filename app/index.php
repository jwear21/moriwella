<?php
declare(strict_types=1);
require __DIR__ . '/src/lib.php';
require __DIR__ . '/src/credits.php';
require __DIR__ . '/src/i18n.php';
require __DIR__ . '/src/photos.php';
require __DIR__ . '/src/avatar.php';
require __DIR__ . '/src/templates.php';
require __DIR__ . '/src/achievements.php';
require __DIR__ . '/src/trackers.php';
require __DIR__ . '/src/feelings.php';
require __DIR__ . '/src/albums.php';
require __DIR__ . '/src/scrapbooks.php';
require __DIR__ . '/src/referrals.php';

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

if ($path === '/webhooks/stripe' && $method === 'POST') { require __DIR__ . '/src/billing.php'; stripe_webhook(); }

boot();
$post = $method === 'POST';
if ($post && !$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('err', tt('msg.post_too_large', ['size' => ini_get('post_max_size')]));
    redirect($_SERVER['HTTP_REFERER'] ?? url('/'));
}
if ($post) csrf_check();

/* ---------- language choice (works signed out, too) ---------- */
if ($path === '/lang' && $post) {
    set_lang((string)($_POST['lang'] ?? ''));
    $back = (string)($_POST['back'] ?? '');
    redirect(strpos($back, MW_BASE) === 0 && strpos($back, '//') === false ? $back : url('/'));
}

/* ---------- first-run admin setup ---------- */
if ($path === '/setup') {
    if (user_count() > 0) redirect(url('/login'));
    $err = null;
    if ($post) {
        $code = strtoupper(trim((string)($_POST['setup_code'] ?? '')));
        $err = check_password_rules((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''));
        if (!$err && !hash_equals((string)cfg('setup_code_hash'), hash('sha256', $code))) { $err = tt('msg.that_setup_code_isn'); }
        if (!$err) {
            db()->prepare("INSERT INTO users (email, name, role, plan, status, password_hash, created_at) VALUES (?, ?, 'admin', 'admin', 'active', ?, ?)")
                ->execute([cfg('admin_email'), cfg('admin_name'), hash_pw((string)$_POST['password']), time()]);
            $u = db()->query('SELECT * FROM users WHERE id = ' . (int)db()->lastInsertId())->fetch();
            login_user($u);
            flash('ok', tt('msg.your_administrator_account_is'));
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
            $err = tt('msg.too_many_attempts_please');
        } else {
            $s = db()->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $s->execute([$email]);
            $u = $s->fetch();
            static $dummy = null;
            $hash = $u['password_hash'] ?? ($dummy ??= hash_pw('not-a-real-password'));
            $ok = password_verify((string)($_POST['password'] ?? ''), $hash) && $u && $u['password_hash'];
            if ($ok) { login_user($u); redirect(url('/')); }
            record_failed_attempt();
            $err = tt('msg.that_email_and_password');
        }
    }
    render('login', ['err' => $err, 'email' => $email], 'Sign in', true);
    exit;
}
/* ---------- public sign-up and forgotten password: both email a link that lets the person choose a password ---------- */
referrals_public_route($path);
if ($path === '/signup') {
    if (current_user()) redirect(url('/'));
    $err = null; $name = ''; $email = ''; $done = false;
    if ($post) {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100); $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!empty($_POST['website'])) { $done = true; }
        elseif (signup_throttled()) { $err = tt('signup.err_slow'); }
        elseif ($name === '') { $err = tt('signup.err_name'); }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) { $err = tt('signup.err_email'); }
        elseif (empty($_POST['agree'])) { $err = tt('signup.err_agree'); }
        else {
            signup_record();
            $q = db()->prepare('SELECT * FROM users WHERE lower(email) = ?'); $q->execute([$email]); $t = $q->fetch();
            if (!$t) {
                db()->prepare("INSERT INTO users (email, name, role, plan, status, created_at) VALUES (?, ?, 'member', 'member', 'invited', ?)")->execute([$email, $name, time()]);
                $nid = (int)db()->lastInsertId(); referral_record_signup($nid, $email); signup_email($name, $email, make_invite($nid));
            } elseif ($t['status'] === 'invited') { signup_email((string)$t['name'], (string)$t['email'], make_invite((int)$t['id'])); }
            elseif ($t['status'] === 'active') { reset_email((string)$t['name'], (string)$t['email'], make_invite((int)$t['id']), true); }
            $done = true;
        }
    }
    render('signup', ['err' => $err, 'name' => $name, 'email' => $email, 'done' => $done], tt('signup.title'), true);
    exit;
}
if ($path === '/forgot') {
    if (current_user()) redirect(url('/'));
    $err = null; $email = ''; $done = false;
    if ($post) {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $err = tt('signup.err_email'); }
        elseif (signup_throttled()) { $err = tt('signup.err_slow'); }
        else {
            signup_record();
            $q = db()->prepare("SELECT * FROM users WHERE lower(email) = ? AND status IN ('active','invited')"); $q->execute([$email]); $t = $q->fetch();
            if ($t) reset_email((string)$t['name'], (string)$t['email'], make_invite((int)$t['id']));
            $done = true;
        }
    }
    render('forgot', ['err' => $err, 'email' => $email, 'done' => $done], tt('forgot.title'), true);
    exit;
}
if ($path === '/logout' && $post) { $_SESSION = []; session_destroy(); redirect(url('/login')); }

/* ---------- invitation / password link ---------- */
if (preg_match('#^/join/([a-f0-9]{48})$#', $path, $m)) {
    $target = find_by_invite($m[1]);
    if (!$target) { render('message', ['heading' => tt('msg.this_link_has_expired'), 'text' => tt('msg.ask_jeff_for_a')], 'Link expired', true); exit; }
    $err = null;
    if ($post) {
        $err = check_password_rules((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''));
        if (!$err) {
            db()->prepare("UPDATE users SET password_hash = ?, status = 'active', invite_hash = NULL, invite_expires = NULL WHERE id = ?")
                ->execute([hash_pw((string)$_POST['password']), $target['id']]);
            login_user($target);
            flash('ok', tt('msg.welcome_name', ['name' => $target['name']]));
            redirect(url('/'));
        }
    }
    render('join', ['err' => $err, 'target' => $target], 'Choose your password', true);
    exit;
}

/* ---------- shared album links: no sign-in needed ---------- */
if (preg_match('#^/a/([a-f0-9]{32})(?:/p/(\d+)/(thumb|web|orig))?$#', $path, $albumM)) { album_public($albumM); }

/* ---------- signed-in pages ---------- */
$user = require_login();
$uid = (int)$user['id'];

/* A new account that has not yet picked a plan and added a card only sees the plan page (and Settings, to sign out or delete the account). */
if (!$post || $path === '/billing/subscribe') {
    $gh = household_ensure($uid);
    if ($gh && (int)$gh['owner_user_id'] === $uid && card_trial_pending((int)$gh['id'])
        && !preg_match('#^(/pricing|/billing|/settings|/logout|/lang|/help)#', $path) && !preg_match('#^/(static|kit)/#', $path)) { redirect(url('/pricing')); }
}

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
    $s = db()->prepare('SELECT i.id, i.text, i.due_date, i.assigned_to, a.name assigned_name, l.id list_id, l.name list_name FROM list_items i JOIN lists l ON l.id = i.list_id LEFT JOIN users a ON a.id = i.assigned_to
        WHERE i.done_at IS NULL AND i.due_date IS NOT NULL AND i.due_date <= ?
        AND (l.owner_id = ? OR EXISTS (SELECT 1 FROM list_members m WHERE m.list_id = l.id AND m.user_id = ?))
        ORDER BY i.due_date, i.id LIMIT 8');
    $s->execute([date('Y-m-d', strtotime($today . ' +7 days')), $uid, $uid]);
    return $s->fetchAll();
}
function render_day(array $user, string $day): void {
    $uid = (int)$user['id'];
    render('day', [
        'goals' => goals_for_day($uid, $day), 'trackers' => trackers_for_day($uid, $day), 'waterOn' => water_on($uid), 'showWelcome' => $day === date('Y-m-d') && !is_welcomed($uid),
        'upcoming' => $day === date('Y-m-d') ? upcoming_items($uid, $day) : [],
        'day' => $day, 'isToday' => $day === date('Y-m-d'),
        'entries' => entries_for_day($uid, $day), 'water' => water_oz($uid, $day), 'feel' => feelings_for_day($uid, $day),
    ], $day === date('Y-m-d') ? 'Today' : ldate('F j, Y', strtotime($day)));
}

if ($path === '/') { render_day($user, date('Y-m-d')); exit; }

if (preg_match('#^/day/(\d{4}-\d{2}-\d{2})/combine$#', $path, $m) && $post) {
    $d = $m[1]; if (!valid_date($d)) redirect(url('/'));
    $q = db()->prepare('SELECT * FROM entries WHERE user_id = ? AND entry_date = ? AND event_id IS NULL ORDER BY id'); $q->execute([$uid, $d]);
    $rows = $q->fetchAll();
    if (count($rows) > 1) {
        $keep = array_shift($rows); $title = $keep['title']; $parts = $keep['body'] === '' ? [] : [implode("\n\n", array_map(fn($b) => stamp_text($b['ts'], $b['text']), body_parts($keep['body'], (int)$keep['created_at'])))];
        foreach ($rows as $r) {
            $chunk = trim(($r['title'] !== '' && $title !== '' ? $r['title'] . "\n" : '') . implode("\n\n", array_map(fn($b) => stamp_text($b['ts'], $b['text']), body_parts($r['body'], (int)$r['created_at']))));
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
        flash('ok', tt('msg.combined_into_one_page'));
    }
    redirect(url('/day/' . $d));
}
if (preg_match('#^/day/(\d{4}-\d{2}-\d{2})$#', $path, $m)) {
    if (!valid_date($m[1])) redirect(url('/'));
    render_day($user, $m[1]); exit;
}

/* ----- profile pictures ----- */
if (preg_match('#^/avatar/(\d+)$#', $path, $m)) {
    $f = avatar_file((int)$m[1]);
    if (!avatar_viewable($uid, (int)$m[1]) || !is_file($f)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg'); header('Content-Length: ' . filesize($f));
    header('Cache-Control: private, max-age=86400'); header('X-Content-Type-Options: nosniff');
    readfile($f); exit;
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
    if (!$p) json_out(['ok' => false, 'error' => tt('msg.photo_not_found')], 404);
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
        flash('ok', tt('msg.photo_removed'));
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
    if ($title === '' && $body === '' && !$hasPhotos) { flash('err', tt('msg.write_something_or_add')); redirect(!empty($_POST['event_id']) ? url('/experiences/' . (int)$_POST['event_id']) : url('/day/' . $date)); }
    $now = time();
    $body = $body === '' ? '' : stamp_text($now, str_replace(["\r\n", "\r"], "\n", $body));
    $evId = (int)($_POST['event_id'] ?? 0); $ev = null;
    if ($evId) { $eq = db()->prepare('SELECT * FROM events WHERE id = ? AND user_id = ?'); $eq->execute([$evId, $uid]); $ev = $eq->fetch() ?: null; }
    if ($ev) {
        $secId = (int)($_POST['section_id'] ?? 0);
        $sq = db()->prepare('SELECT id FROM event_sections WHERE id = ? AND event_id = ?'); $sq->execute([$secId, $ev['id']]);
        $secId = $sq->fetchColumn() ?: null;
        db()->prepare('INSERT INTO entries (user_id, entry_date, title, body, created_at, updated_at, event_id, section_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $date, $title, $body, $now, $now, $ev['id'], $secId]);
        $errs = save_uploaded_photos((int)db()->lastInsertId(), $uid);
        flash($errs ? 'err' : 'ok', $errs ? tt('msg.saved_notes', ['notes' => implode(' ', $errs)]) : tt('msg.added_to', ['title' => $ev['title']]));
        queue_unlocked(achievements_eval($uid, ['day' => $date]));
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
    if ($errs) { flash('err', tt('msg.entry_saved_notes', ['notes' => implode(' ', $errs)])); }
    else { flash((int)$n->fetchColumn() === 1 ? 'first' : 'saved', tt('msg.saved')); }
    queue_unlocked(achievements_eval($uid, ['day' => $date]));
    redirect(url('/day/' . $date));
}

/* ----- writing in the journal book ----- */
/* Everything the writing page needs about one day: moods and feelings already chosen. */
if ($path === '/journal/book/feel') {
    $d = (string)($_GET['day'] ?? ''); if (!valid_date($d)) json_out(['ok' => false], 400);
    $f = feelings_for_day($uid, $d);
    header('Cache-Control: no-store');
    json_out(['ok' => true, 'mood' => $f['mood'], 'feelings' => $f['feelings']]);
}
if ($path === '/journal/book/save' && $post) {
    $cid = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['cid'] ?? '')), 0, 64);
    if ($cid !== '') {
        $dq = db()->prepare('SELECT entry_id FROM entry_saves WHERE user_id = ? AND cid = ?'); $dq->execute([$uid, $cid]);
        if ($did = $dq->fetchColumn()) json_out(['ok' => true, 'id' => (int)$did, 'dup' => true]);
    }
    $date = (string)($_POST['entry_date'] ?? ''); if (!valid_date($date)) $date = date('Y-m-d');
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
    $body = mb_substr(trim(str_replace(["\r\n", "\r"], "\n", (string)($_POST['body'] ?? ''))), 0, 50000);
    $hasPhotos = false; foreach (normalize_files('photos') as $f) { if ($f['error'] !== UPLOAD_ERR_NO_FILE) $hasPhotos = true; }
    $mood = (int)($_POST['mood'] ?? 0); $setFeel = isset($_POST['feelings_set']);
    if ($title === '' && $body === '' && !$hasPhotos && !$mood && !$setFeel) json_out(['ok' => false, 'error' => tt('msg.write_something_or_add')], 400);
    $now = time(); $ts = (int)($_POST['ts'] ?? 0); if ($ts < $now - 30 * 86400 || $ts > $now + 120) $ts = $now; /* a save that waited offline keeps the time it was written */
    $stamped = $body === '' ? '' : stamp_text($ts, $body);
    $evId = (int)($_POST['event_id'] ?? 0); $ev = null;
    if ($evId) { $eq = db()->prepare('SELECT * FROM events WHERE id = ? AND user_id = ?'); $eq->execute([$evId, $uid]); $ev = $eq->fetch() ?: null; }
    $entryId = 0;
    if ($title !== '' || $body !== '' || $hasPhotos) {
        if ($ev) {
            db()->prepare('INSERT INTO entries (user_id, entry_date, title, body, created_at, updated_at, event_id, section_id) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)')->execute([$uid, $date, $title, $stamped, $ts, $now, $ev['id']]);
            $entryId = (int)db()->lastInsertId();
        } else {
            $mq = db()->prepare('SELECT * FROM entries WHERE user_id = ? AND entry_date = ? AND event_id IS NULL ORDER BY id LIMIT 1'); $mq->execute([$uid, $date]); $main = $mq->fetch();
            if ($main) {
                $joined = $main['body'] === '' ? $stamped : ($stamped === '' ? $main['body'] : $main['body'] . "\n\n" . $stamped);
                db()->prepare('UPDATE entries SET body = ?, title = CASE WHEN title = \'\' THEN ? ELSE title END, updated_at = ? WHERE id = ? AND user_id = ?')->execute([mb_substr($joined, 0, 50000), $title, $now, $main['id'], $uid]);
                $entryId = (int)$main['id'];
            } else {
                db()->prepare('INSERT INTO entries (user_id, entry_date, title, body, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$uid, $date, $title, $stamped, $ts, $now]);
                $entryId = (int)db()->lastInsertId();
            }
        }
        $errs = save_uploaded_photos($entryId, $uid);
    } else { $errs = []; }
    if ($mood >= 1 && $mood <= 5) {
        $lq = db()->prepare('SELECT value FROM mood_log WHERE user_id = ? AND day = ? ORDER BY ts DESC, id DESC LIMIT 1'); $lq->execute([$uid, $date]); $lastMood = $lq->fetchColumn();
        if ((int)$lastMood !== $mood) db()->prepare('INSERT INTO mood_log (user_id, day, ts, value) VALUES (?, ?, ?, ?)')->execute([$uid, $date, $date === date('Y-m-d') ? $ts : 0, $mood]);
    }
    if ($setFeel) {
        $want = array_values(array_unique(array_filter((array)($_POST['feelings'] ?? []), fn($f) => is_string($f) && feelings_valid($f))));
        db()->prepare('DELETE FROM day_feelings WHERE user_id = ? AND day = ?')->execute([$uid, $date]);
        foreach ($want as $f) db()->prepare('INSERT OR IGNORE INTO day_feelings (user_id, day, feeling) VALUES (?, ?, ?)')->execute([$uid, $date, $f]);
    }
    if ($cid !== '') db()->prepare('INSERT OR IGNORE INTO entry_saves (user_id, cid, entry_id, created_at) VALUES (?, ?, ?, ?)')->execute([$uid, $cid, $entryId, $now]);
    queue_unlocked(achievements_eval($uid, ['day' => $date]));
    json_out(['ok' => true, 'id' => $entryId, 'notes' => $errs]);
}
if (preg_match('#^/journal/book/edit/(\d+)$#', $path, $m) && $post) {
    $s = db()->prepare('SELECT * FROM entries WHERE id = ? AND user_id = ?'); $s->execute([(int)$m[1], $uid]); $entry = $s->fetch();
    if (!$entry) json_out(['ok' => false, 'error' => tt('msg.entry_not_found')], 404);
    if (!empty($_POST['delete'])) {
        delete_entry_photos((int)$entry['id'], $uid);
        db()->prepare('DELETE FROM entry_photos WHERE entry_id = ? AND user_id = ?')->execute([$entry['id'], $uid]);
        db()->prepare('DELETE FROM entries WHERE id = ? AND user_id = ?')->execute([$entry['id'], $uid]);
        json_out(['ok' => true, 'deleted' => true]);
    }
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
    $plain = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 50000);
    $old = body_parts($entry['body'], (int)$entry['created_at']); $new = [];
    foreach (preg_split('/\n{2,}/', str_replace(["\r\n", "\r"], "\n", $plain)) as $i => $t) { $t = trim($t); if ($t !== '') $new[] = stamp_text((int)($old[$i]['ts'] ?? 0), $t); }
    db()->prepare('UPDATE entries SET title = ?, body = ?, updated_at = ? WHERE id = ? AND user_id = ?')->execute([$title, implode("\n\n", $new), time(), $entry['id'], $uid]);
    $errs = save_uploaded_photos((int)$entry['id'], $uid);
    json_out(['ok' => true, 'id' => (int)$entry['id'], 'notes' => $errs]);
}

/* The journal book's look, saved on the account. */
const BOOK_THEMES = ['classic','antique','midnight','garden','sea','notebook','blush','lavender','zen','sunrise','cabin'];
const BOOK_FONTS = ['auto','newsreader','garamond','merri','nunito','elite','fell','caveat','kalam'];
const BOOK_SIZES = ['s','m','l'];
if ($path === '/journal/book/prefs' && $post) {
    $t = (string)($_POST['theme'] ?? ''); $f = (string)($_POST['font'] ?? ''); $z = (string)($_POST['size'] ?? '');
    if (!in_array($t, BOOK_THEMES, true) || !in_array($f, BOOK_FONTS, true) || !in_array($z, BOOK_SIZES, true)) json_out(['ok' => false], 400);
    db()->prepare('INSERT INTO book_prefs (user_id, theme, font, size, updated_at) VALUES (?, ?, ?, ?, ?) ON CONFLICT(user_id) DO UPDATE SET theme = excluded.theme, font = excluded.font, size = excluded.size, updated_at = excluded.updated_at')->execute([$uid, $t, $f, $z, time()]);
    json_out(['ok' => true]);
}
/* The journal as an open book: a standalone page, plus the entries as JSON. */
if ($path === '/journal/book/data') {
    $skip = max(0, (int)($_GET['skip'] ?? 0));
    $s = db()->prepare('SELECT * FROM entries WHERE user_id = ? ORDER BY entry_date DESC, id DESC LIMIT 200 OFFSET ' . $skip); $s->execute([$uid]);
    $rows = array_reverse($s->fetchAll());
    $cq = db()->prepare('SELECT COUNT(*) FROM entries WHERE user_id = ?'); $cq->execute([$uid]); $more = max(0, (int)$cq->fetchColumn() - $skip - count($rows));
    $ph = photos_for_entries(array_column($rows, 'id'));
    $moods = $rows ? feelings_moods_between($uid, $rows[0]['entry_date'], end($rows)['entry_date']) : [];
    $faces = feelings_faces(); $out = []; $seen = [];
    $evt = []; foreach (db()->query('SELECT id, title FROM events WHERE user_id = ' . (int)$uid)->fetchAll() as $ev) $evt[(int)$ev['id']] = $ev['title'];
    foreach ($rows as $r) {
        $ts = strtotime($r['entry_date']); $d = $r['entry_date'];
        $mood = (empty($seen[$d]) && !empty($moods[$d])) ? $faces[$moods[$d]]['e'] : ''; $seen[$d] = true;
        $out[] = ['id' => (int)$r['id'], 'date' => $d, 'trip' => $r['event_id'] ? (string)($evt[(int)$r['event_id']] ?? '') : '', 'md' => ldate('F j', $ts), 'dow' => ldate('l', $ts), 'mood' => $mood, 'title' => (string)$r['title'], 'body' => body_plain((string)$r['body']),
                  'photos' => array_map(fn($p) => ['id' => (int)$p['id'], 'v' => (int)$p['edited_at']], $ph[$r['id']] ?? [])];
    }
    $first = $rows ? ldate('F Y', strtotime($rows[0]['entry_date'])) : ldate('F Y', time());
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    $bq = db()->prepare('SELECT theme, font, size FROM book_prefs WHERE user_id = ?'); $bq->execute([$uid]); $bp = $bq->fetch(PDO::FETCH_ASSOC) ?: null;
    $fl = []; foreach ($faces as $n => $f) $fl[] = ['v' => $n, 'e' => $f['e'], 'l' => $f['l']];
    $cat = []; foreach (feelings_catalog() as $g => $list) { $items = []; foreach ($list as $k) { $w = tl($k); $items[] = ['k' => $k, 'l' => mb_strtoupper(mb_substr($w, 0, 1)) . mb_substr($w, 1)]; } $cat[] = ['g' => tl($g), 'items' => $items]; }
    $tf = feelings_for_day($uid, date('Y-m-d'));
    $trips = []; foreach (db()->query('SELECT id, title FROM events WHERE user_id = ' . (int)$uid . ' ORDER BY start_date DESC LIMIT 40')->fetchAll() as $ev) $trips[] = ['id' => (int)$ev['id'], 'title' => (string)$ev['title']];
    echo json_encode(['entries' => $out, 'more' => $more, 'started' => $first, 'today' => date('Y-m-d'), 'todayMd' => ldate('F j', time()), 'todayDow' => ldate('l', time()),
        'csrf' => csrf_token(), 'prefs' => $bp, 'faces' => $fl, 'catalog' => $cat, 'mood' => $tf['mood'], 'feelings' => $tf['feelings'], 'trips' => $trips], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($path === '/journal/book') {
    $keys = ['title','home','cover_t1','cover_t2','cover_cap','belongs','started','my_journal','tagline','today','write_today','today_hint','continued','untitled','cover','title_page','back_page','next_page','zoom_page','zoom_both','scrub','customize','cust_cover','cust_style','cust_size','close','size_s','size_m','size_l','font_auto','font_newsreader','font_garamond','font_merri','font_nunito','font_elite','font_fell','font_caveat','font_kalam','theme_classic','theme_antique','theme_midnight','theme_garden','theme_sea','theme_notebook','theme_blush','theme_lavender','theme_zen','theme_sunrise','theme_cabin','empty','empty_link'];
    $keys = array_merge($keys, ['write','write_open','write_title','earlier','earlier_wait','w_today','w_day','w_edit','w_ph','w_ph_more','w_title','w_feel','w_more_feel','w_less_feel','w_photos','w_camera','w_trip','w_trip_none','w_save','w_save_edit','w_saving','w_saved','w_wait','w_offline_saved','w_draft','w_delete','w_delete_ask','w_cancel','w_empty','w_date','w_remove_photo','w_fail','w_edit_hint','edit_page','list_view','w_pending','w_photos_n','w_dictate','w_dictate_stop','w_mic']);
    $L = []; foreach ($keys as $k) $L[$k] = tt('book.' . $k);
    $cfg = ['L' => $L, 'name' => (string)$user['name'], 'data' => url('/journal/book/data'), 'day' => url('/day/'), 'photo' => url('/photo/'), 'home' => url('/'), 'save' => url('/journal/book/save'), 'edit' => url('/journal/book/edit/'), 'prefs' => url('/journal/book/prefs'), 'feel' => url('/journal/book/feel'), 'csrf' => csrf_token(), 'list' => url('/journal'), 'exp' => url('/experiences/')];
    ob_start(); require __DIR__ . '/src/views/tabbar.php'; $tabbarHtml = ob_get_clean();
    echo '<!DOCTYPE html><html lang="' . e(current_lang()) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#153a2f"><link rel="manifest" href="/manifest.webmanifest"><link rel="icon" href="/assets/favicon.ico" sizes="any"><link rel="apple-touch-icon" href="/assets/apple-touch-icon.png"><title>' . e(t('book.title')) . ' | Moriwella</title>'
       . '<link rel="stylesheet" href="/app/static/book-fonts.css?v=' . asset_v('static/book-fonts.css') . '"><link rel="stylesheet" href="/app/static/book.css?v=' . asset_v('static/book.css') . '"><link rel="stylesheet" href="/app/static/tabbar.css?v=' . asset_v('static/tabbar.css') . '"><script src="/app/static/textscale.js?v=' . asset_v('static/textscale.js') . '"></script></head>'
       . '<body style="margin:0"><h1 style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap">' . e(t('book.title')) . '</h1><div class="bk" id="bk" data-config="' . e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '"></div>' . $tabbarHtml . '<script src="/app/static/book.js?v=' . asset_v('static/book.js') . '"></script></body></html>';
    exit;
}
if ($path === '/journal') {
    /* The journal is always the book. The old list stays reachable only at /journal?list=1 (not linked anywhere). */
    if (!isset($_GET['list'])) redirect(url('/journal/book'));
    $s = db()->prepare('SELECT * FROM entries WHERE user_id = ? ORDER BY entry_date DESC, id DESC LIMIT 500');
    $s->execute([$uid]);
    $rows = $s->fetchAll();
    $ph = photos_for_entries(array_column($rows, 'id'));
    foreach ($rows as &$r) { $r['photos'] = $ph[$r['id']] ?? []; }
    $moods = $rows ? feelings_moods_between($uid, end($rows)['entry_date'], $rows[0]['entry_date']) : [];
    $feel = []; if ($rows) { $fq = db()->prepare('SELECT day, feeling FROM day_feelings WHERE user_id = ? AND day BETWEEN ? AND ? ORDER BY rowid'); $fq->execute([$uid, end($rows)['entry_date'], $rows[0]['entry_date']]); foreach ($fq->fetchAll() as $f) $feel[$f['day']][] = $f['feeling']; }
    render('journal', ['entries' => $rows, 'moods' => $moods, 'feel' => $feel], 'Journal');
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
        'month' => $month, 'first' => $first, 'counts' => $counts, 'pcounts' => $pcounts, 'moods' => feelings_moods_between($uid, $month . '-01', $month . '-31'),
        'prev' => date('Y-m', strtotime('-1 month', $first)), 'next' => date('Y-m', strtotime('+1 month', $first)),
    ], 'Calendar');
    exit;
}

if (preg_match('#^/entries/(\d+)$#', $path, $m)) {
    $s = db()->prepare('SELECT * FROM entries WHERE id = ? AND user_id = ?');
    $s->execute([(int)$m[1], $uid]);
    $entry = $s->fetch();
    if (!$entry) { http_response_code(404); render('message', ['heading' => tt('msg.entry_not_found'), 'text' => tt('msg.it_may_have_been')], 'Not found'); exit; }
    if ($post) {
        if (isset($_POST['delete'])) {
            delete_entry_photos((int)$entry['id'], $uid);
            db()->prepare('DELETE FROM entries WHERE id = ? AND user_id = ?')->execute([$entry['id'], $uid]);
            flash('ok', tt('msg.entry_deleted')); redirect($entry['event_id'] ? url('/experiences/' . $entry['event_id']) : url('/journal'));
        }
        $date = (string)($_POST['entry_date'] ?? ''); if (!valid_date($date)) $date = $entry['entry_date'];
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
        $plain = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 50000);
        $old = body_parts($entry['body'], (int)$entry['created_at']); $new = [];
        foreach (preg_split('/\n{2,}/', str_replace(["\r\n", "\r"], "\n", $plain)) as $i => $t) { $t = trim($t); if ($t !== '') $new[] = stamp_text((int)($old[$i]['ts'] ?? 0), $t); }
        $body = implode("\n\n", $new);
        db()->prepare('UPDATE entries SET entry_date = ?, title = ?, body = ?, updated_at = ? WHERE id = ? AND user_id = ?')
            ->execute([$date, $title, $body, time(), $entry['id'], $uid]);
        $errs = save_uploaded_photos((int)$entry['id'], $uid);
        flash($errs ? 'err' : 'ok', $errs ? tt('msg.changes_saved_notes', ['notes' => implode(' ', $errs)]) : tt('msg.changes_saved'));
        redirect($entry['event_id'] ? url('/experiences/' . $entry['event_id']) : url('/day/' . $date));
    }
    $entry['photos'] = photos_for_entries([$entry['id']])[$entry['id']] ?? [];
    render('entry', ['entry' => $entry], 'Edit entry');
    exit;
}

/* ----- water tracker ----- */
if ($path === '/water' && $post) {
    $day = (string)($_POST['day'] ?? ''); if (!valid_date($day)) json_out(['ok' => false], 400);
    $wg = tracker_goals($uid)['water']; $cap = (int)round($wg['target']);
    $oz = max(0, min($cap, (int)($_POST['oz'] ?? 0)));
    $before = water_oz($uid, $day);
    db()->prepare('INSERT INTO water_log (user_id, day, oz) VALUES (?, ?, ?) ON CONFLICT(user_id, day) DO UPDATE SET oz = excluded.oz')->execute([$uid, $day, $oz]);
    json_out(['ok' => true, 'oz' => $oz, 'goal' => $oz >= $cap && $before < $cap, 'allDone' => $oz >= $cap && $before < $cap && all_goals_done($uid, $day), 'unlocked' => $oz >= $cap ? achievements_eval($uid, ['day' => $day]) : []]);
}

require __DIR__ . '/src/billing.php';
require __DIR__ . '/src/movies.php';
albums_member_routes($uid, $user, $post, $path);
scrapbooks_member_routes($uid, $user, $post, $path);
referrals_member_routes($uid, $user, $path);
require __DIR__ . '/src/experiences.php';
require __DIR__ . '/src/track.php';
require __DIR__ . '/src/transfer.php';
require __DIR__ . '/src/demo.php';

/* ----- daily goals ----- */
function goals_for_day(int $uid, string $day): array {
    $s = db()->prepare('SELECT g.id, g.name, (l.goal_id IS NOT NULL) AS done FROM goals g LEFT JOIN goal_log l ON l.goal_id = g.id AND l.day = ? WHERE g.user_id = ? ORDER BY g.id');
    $s->execute([$day, $uid]);
    return $s->fetchAll();
}
function all_goals_done(int $uid, string $day): bool {
    $gs = goals_for_day($uid, $day);
    $waterDone = !water_on($uid) || water_oz($uid, $day) >= tracker_goals($uid)['water']['target'];
    if (!$gs && !water_on($uid) && !tracker_has_goals($uid)) return false;
    foreach ($gs as $g) if (!$g['done']) return false;
    return $waterDone && tracker_goals_done($uid, $day);
}
if ($path === '/goals' && $post) {
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
    $c = db()->prepare('SELECT COUNT(*) FROM goals WHERE user_id = ?'); $c->execute([$uid]);
    if ($name === '') flash('err', tt('msg.give_the_goal_a'));
    elseif ((int)$c->fetchColumn() >= 12) flash('err', tt('msg.that_s_plenty_of'));
    else { db()->prepare('INSERT INTO goals (user_id, name, created_at) VALUES (?, ?, ?)')->execute([$uid, $name, time()]); flash('ok', tt('msg.goal_added')); }
    redirect(url('/track') . '#goals');
}
if (preg_match('#^/goals/(\d+)/(toggle|delete)$#', $path, $mm) && $post) {
    $g = db()->prepare('SELECT id FROM goals WHERE id = ? AND user_id = ?'); $g->execute([(int)$mm[1], $uid]);
    if (!$g->fetch()) { if (wants_json()) json_out(['ok' => false], 404); redirect(url('/track') . '#goals'); }
    if ($mm[2] === 'delete') { db()->prepare('DELETE FROM goals WHERE id = ? AND user_id = ?')->execute([(int)$mm[1], $uid]); redirect(url('/track') . '#goals'); }
    $day = (string)($_POST['day'] ?? ''); if (!valid_date($day)) json_out(['ok' => false], 400);
    $q = db()->prepare('SELECT 1 FROM goal_log WHERE goal_id = ? AND day = ?'); $q->execute([(int)$mm[1], $day]);
    if ($q->fetch()) { db()->prepare('DELETE FROM goal_log WHERE goal_id = ? AND day = ?')->execute([(int)$mm[1], $day]); $done = false; }
    else { db()->prepare('INSERT INTO goal_log (goal_id, day, done_at) VALUES (?, ?, ?)')->execute([(int)$mm[1], $day, time()]); $done = true; }
    json_out(['ok' => true, 'done' => $done, 'allDone' => $done && all_goals_done($uid, $day), 'unlocked' => $done ? achievements_eval($uid, ['day' => $day]) : []]);
}

/* ----- lists ----- */
function list_people(array $list): array {
    $q = db()->prepare('SELECT id, name FROM users WHERE id = ? UNION SELECT u.id, u.name FROM list_members m JOIN users u ON u.id = m.user_id WHERE m.list_id = ?');
    $q->execute([$list['owner_id'], $list['id']]);
    return $q->fetchAll();
}
function clean_item_fields(array $people): array {
    $due = (string)($_POST['due_date'] ?? ''); if ($due !== '' && !valid_date($due)) $due = '';
    $rep = in_array($_POST['repeat'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $_POST['repeat'] : null;
    if ($rep && $due === '') $due = date('Y-m-d');
    $who = (int)($_POST['assigned_to'] ?? 0); $ok = false;
    foreach ($people as $pp) if ((int)$pp['id'] === $who) $ok = true;
    return [$due ?: null, $rep, $ok ? $who : null];
}
function next_due(string $due, string $repeat): string {
    $step = ['daily' => '+1 day', 'weekly' => '+1 week', 'monthly' => '+1 month'][$repeat];
    $n = date('Y-m-d', strtotime($due . ' ' . $step));
    for ($i = 0; $i < 400 && $n <= date('Y-m-d'); $i++) $n = date('Y-m-d', strtotime($n . ' ' . $step));
    return $n;
}
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
        if ($name === '') { flash('err', tt('msg.give_the_list_a')); redirect(url('/lists')); }
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
    if (!$list) { http_response_code(404); render('message', ['heading' => tt('msg.list_not_found'), 'text' => tt('msg.it_may_have_been_2')], 'Not found'); exit; }
    $isOwner = (bool)$list['is_owner'];
    if ($post) {
        if ($sub === 'items') {
            $text = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 300);
            [$due, $rep, $who] = clean_item_fields(list_people($list));
            if ($text !== '') db()->prepare('INSERT INTO list_items (list_id, text, due_date, created_by, created_at, assigned_to, repeat) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$listId, $text, $due, $uid, time(), $who, $rep]);
            redirect(url('/lists/' . $listId));
        }
        if (preg_match('#^items/(\d+)/edit$#', $sub, $mm)) {
            $text = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 300);
            [$due, $rep, $who] = clean_item_fields(list_people($list));
            if ($text !== '') db()->prepare('UPDATE list_items SET text = ?, due_date = ?, repeat = ?, assigned_to = ? WHERE id = ? AND list_id = ?')->execute([$text, $due, $rep, $who, (int)$mm[1], $listId]);
            else flash('err', tt('msg.an_item_needs_some'));
            redirect(url('/lists/' . $listId));
        }
        if (preg_match('#^items/(\d+)/toggle$#', $sub, $mm)) {
            $nextDue = null; $it = db()->prepare('SELECT * FROM list_items WHERE id = ? AND list_id = ?'); $it->execute([(int)$mm[1], $listId]); $item = $it->fetch();
            if ($item) {
                $nowDone = $item['done_at'] === null;
                db()->prepare('UPDATE list_items SET done_at = ?, done_by = ? WHERE id = ?')->execute([$nowDone ? time() : null, $nowDone ? $uid : null, $item['id']]);
                if ($nowDone && $item['repeat'] && $item['due_date']) {
                    $nd = next_due($item['due_date'], $item['repeat']);
                    $dup = db()->prepare('SELECT 1 FROM list_items WHERE list_id = ? AND text = ? AND repeat = ? AND due_date = ? AND done_at IS NULL'); $dup->execute([$listId, $item['text'], $item['repeat'], $nd]);
                    if (!$dup->fetch()) db()->prepare('INSERT INTO list_items (list_id, text, due_date, created_by, created_at, assigned_to, repeat) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$listId, $item['text'], $nd, $item['created_by'], time(), $item['assigned_to'], $item['repeat']]);
                    $nextDue = $nd;
                }
            }
            $c = list_counts($listId);
            if (wants_json()) json_out(['ok' => true, 'done' => $item ? $nowDone : false, 'open' => $c['open'], 'total' => $c['total'], 'allDone' => $c['total'] > 0 && $c['open'] === 0 && empty($nextDue), 'list' => $list['name'], 'next' => $nextDue ?? null, 'unlocked' => ($nowDone && $c['total'] > 0 && $c['open'] === 0) ? achievements_eval($uid, ['list_id' => $listId, 'list_done' => true]) : []]);
            redirect(url('/lists/' . $listId));
        }
        if (preg_match('#^items/(\d+)/delete$#', $sub, $mm)) {
            db()->prepare('DELETE FROM list_items WHERE id = ? AND list_id = ?')->execute([(int)$mm[1], $listId]);
            redirect(url('/lists/' . $listId));
        }
        if ($sub === 'clear') { db()->prepare('DELETE FROM list_items WHERE list_id = ? AND done_at IS NOT NULL')->execute([$listId]); redirect(url('/lists/' . $listId)); }
        if ($sub === 'share_family' && $isOwner) {
            /* tick the people in your household who should see this list; unticked household members lose access. People outside the household are left as they are. */
            $want = array_map('intval', (array)($_POST['people'] ?? []));
            foreach (list_family($uid) as $f) {
                if (in_array((int)$f['id'], $want, true)) db()->prepare('INSERT OR IGNORE INTO list_members (list_id, user_id, added_at) VALUES (?, ?, ?)')->execute([$listId, $f['id'], time()]);
                else db()->prepare('DELETE FROM list_members WHERE list_id = ? AND user_id = ?')->execute([$listId, $f['id']]);
            }
            flash('ok', tt('msg.sharing_saved')); queue_unlocked(achievements_eval($uid));
            redirect(url('/lists/' . $listId));
        }
        if (preg_match('#^members/(\d+)/remove$#', $sub, $mm)) {
            $mid = (int)$mm[1];
            if ($isOwner || $mid === $uid) db()->prepare('DELETE FROM list_members WHERE list_id = ? AND user_id = ?')->execute([$listId, $mid]);
            redirect($mid === $uid && !$isOwner ? url('/lists') : url('/lists/' . $listId));
        }
        if ($sub === 'delete' && $isOwner) { db()->prepare('DELETE FROM lists WHERE id = ?')->execute([$listId]); flash('ok', tt('msg.list_deleted')); redirect(url('/lists')); }
        redirect(url('/lists/' . $listId));
    }
    $mine = ($_GET['who'] ?? '') === 'me';
    $items = db()->prepare('SELECT i.*, u.name done_name, a.name assigned_name FROM list_items i LEFT JOIN users u ON u.id = i.done_by LEFT JOIN users a ON a.id = i.assigned_to WHERE i.list_id = ?' . ($mine ? ' AND i.assigned_to = ' . (int)$uid : '') . ' ORDER BY (i.done_at IS NOT NULL), COALESCE(i.due_date, "9999"), i.id');
    $items->execute([$listId]);
    $mem = db()->prepare('SELECT u.id, u.name, u.email FROM list_members m JOIN users u ON u.id = m.user_id WHERE m.list_id = ? ORDER BY u.name');
    $mem->execute([$listId]);
    $ow = db()->prepare('SELECT name FROM users WHERE id = ?'); $ow->execute([$list['owner_id']]);
    render('list', ['list' => $list, 'items' => $items->fetchAll(), 'members' => $mem->fetchAll(), 'ownerName' => $ow->fetchColumn(), 'isOwner' => $isOwner, 'people' => list_people($list), 'mine' => $mine, 'family' => $isOwner ? list_family($uid) : []], $list['name']);
    exit;
}

/* ----- welcome and feedback ----- */
if ($path === '/welcome/dismiss' && $post) { mark_welcomed($uid); redirect(url('/')); }
if ($path === '/feedback') {
    if ($post) {
        $msg = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 4000);
        if ($msg === '') { flash('err', tt('msg.write_a_few_words')); redirect(url('/feedback')); }
        db()->prepare('INSERT INTO feedback (user_id, message, page, created_at) VALUES (?, ?, ?, ?)')->execute([$uid, $msg, mb_substr(trim((string)($_POST['page'] ?? '')), 0, 120), time()]);
        flash('ok', tt('msg.thank_you_jeff_will')); redirect(url('/settings'));
    }
    render('feedback', [], 'Send feedback');
    exit;
}

/* ----- achievements ----- */
if ($path === '/achievements') {
    render('achievements', ['groups' => achievements_for_user($uid)], 'Achievements');
    exit;
}

/* ----- settings ----- */
if ($path === '/settings') {
    if ($post) {
        if (isset($_POST['save_prefs'])) {
            save_prefs($uid, !empty($_POST['celebrations']), water_on($uid), (string)($_POST['celebrate_size'] ?? 'big'));
            save_celebrate_style($uid, (string)($_POST['celebrate_style'] ?? 'fireworks'));
            flash('ok', tt('msg.preferences_saved')); redirect(url('/settings'));
        }
        if (isset($_POST['save_avatar'])) {
            $err = avatar_save($uid, $_FILES['avatar'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
            flash($err ? 'err' : 'ok', $err ?: tt('profile.saved')); redirect(url('/settings'));
        }
        if (isset($_POST['remove_avatar'])) { avatar_delete($uid); flash('ok', tt('profile.removed')); redirect(url('/settings')); }
        if (isset($_POST['request_delete'])) {
            if (!password_verify((string)($_POST['current'] ?? ''), (string)$user['password_hash'])) { flash('err', tt('account.delete_err_pw')); redirect(url('/settings')); }
            $stamp = date('c');
            $ok = send_mail('support@moriwella.com', 'Account deletion request: ' . $user['email'],
                "Delete this account within 30 days.\n\nName: " . $user['name'] . "\nEmail: " . $user['email'] . "\nUser id: " . $uid . "\nRequested: " . $stamp . "\n\nAlso stop any billing and remove the person from households.\n", (string)$user['email']);
            @file_put_contents(data_dir() . '/deletion_requests.log', $stamp . ' uid=' . $uid . ' email=' . $user['email'] . ' mailed=' . ($ok ? 'yes' : 'no') . "\n", FILE_APPEND);
            if ($ok) {
                send_mail((string)$user['email'], 'We received your Moriwella account deletion request',
                    "Hi " . $user['name'] . ",\n\nWe received your request to delete your Moriwella account and all of its content. We will finish within 30 days and email you when it is done.\n\nIf you did not ask for this, reply to this email right away.\n\nMoriwella\n", 'support@moriwella.com');
                flash('ok', tt('account.delete_sent'));
            } else { flash('err', tt('account.delete_err_mail')); }
            redirect(url('/settings'));
        }
        if (isset($_POST['change_password'])) {
            $err = check_password_rules((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''));
            if (!$err && !password_verify((string)($_POST['current'] ?? ''), (string)$user['password_hash'])) $err = tt('msg.your_current_password_isn');
            if ($err) { flash('err', $err); }
            else { db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([hash_pw((string)$_POST['password']), $uid]); flash('ok', tt('msg.password_changed')); }
            redirect(url('/settings'));
        }
    }
    render('settings', ['celebrations' => celebrations_on($uid), 'size' => celebrate_size($uid), 'style' => celebrate_style($uid)], 'Account');
    exit;
}

/* ---------- stop acting as another person ---------- */
if ($path === '/acting/stop' && $post) {
    $aid = (int)($_SESSION['acting_admin'] ?? 0); unset($_SESSION['acting_admin']);
    $s = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND status = 'active'"); $s->execute([$aid]);
    if ($s->fetch()) { $_SESSION['uid'] = $aid; flash('ok', 'You are back in your own account.'); redirect(url('/admin')); }
    unset($_SESSION['uid']); redirect(url('/login'));
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
    if (preg_match('#^/admin/users/(\d+)/act$#', $path, $m) && $post) {
        $s = db()->prepare("SELECT * FROM users WHERE id = ? AND status = 'active' AND id <> ?"); $s->execute([(int)$m[1], (int)$admin['id']]);
        if ($t = $s->fetch()) { $_SESSION['acting_admin'] = (int)($_SESSION['acting_admin'] ?? $admin['id']); $_SESSION['uid'] = (int)$t['id']; flash('ok', 'You are now acting as ' . $t['name'] . '.'); redirect(url('/')); }
        flash('err', 'You can act as other people with active accounts.'); redirect(url('/admin'));
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
                flash('ok', $t['name'] . ($new === 'disabled' ? "'s account is disabled. They cannot sign in." : "'s account is enabled again."));
            }
        }
        redirect(url('/admin'));
    }
    if (preg_match('#^/admin/users/(\d+)/access$#', $path, $m) && $post) {
        $s = db()->prepare('SELECT id, name FROM users WHERE id = ?'); $s->execute([(int)$m[1]]); $t = $s->fetch();
        if (!$t) { flash('err', 'Choose a person.'); redirect(url('/admin')); }
        $mode = ($_POST['mode'] ?? '') === 'standard' ? 'standard' : 'comped';
        $err = admin_set_access((int)$admin['id'], (int)$t['id'], $mode, (string)($_POST['plan'] ?? ''), (int)($_POST['seats'] ?? 1), (int)($_POST['credits'] ?? 0));
        if ($err) flash('err', $err); else flash('ok', $t['name'] . ($mode === 'standard' ? ' is back on standard billing.' : ' now has a free ' . plan_for((string)$_POST['plan'])['name'] . ' plan with ' . (int)$_POST['seats'] . ' seat' . ((int)$_POST['seats'] === 1 ? '' : 's') . '.'));
        redirect(url('/admin#access'));
    }
    if (preg_match('#^/admin/users/(\d+)/role$#', $path, $m) && $post) {
        $s = db()->prepare('SELECT * FROM users WHERE id = ?'); $s->execute([(int)$m[1]]); $t = $s->fetch();
        $make = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'member';
        if (!$t) { flash('err', 'Choose a person.'); redirect(url('/admin')); }
        if ($make === 'member' && (int)$t['id'] === (int)$admin['id']) { flash('err', 'You can\'t remove your own administrator access. Ask another administrator.'); redirect(url('/admin')); }
        if ($make === 'admin' && $t['status'] !== 'active') { flash('err', $t['name'] . ' needs to finish setting up their account first.'); redirect(url('/admin')); }
        if ($make === 'member' && (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn() < 2) { flash('err', 'There must always be at least one administrator.'); redirect(url('/admin')); }
        db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$make, $t['id']]);
        billing_log('admin ' . $admin['id'] . ' set user ' . $t['id'] . ' role to ' . $make);
        flash('ok', $t['name'] . ($make === 'admin' ? ' is now an administrator.' : ' is no longer an administrator.'));
        redirect(url('/admin?' . http_build_query(array_filter(['q' => (string)($_POST['q'] ?? ''), 'page' => (int)($_POST['page'] ?? 0) ?: null]))));
    }
    if ($path === '/admin/credits' && $post) {
        $em = strtolower(trim((string)($_POST['email'] ?? '')));
        $ok = db()->prepare('SELECT id, name FROM users WHERE email = ?'); $ok->execute([$em]); $t = $ok->fetch(); $uid = (int)($t['id'] ?? 0);
        if (!$t) { flash('err', 'No account has that email address.'); redirect(url('/admin')); }
        $n = (int)($_POST['amount'] ?? 0);
        if (credits_admin_grant((int)$admin['id'], $uid, $n, trim((string)($_POST['reason'] ?? ''))) !== null) flash('err', 'Enter a number from 1 to 500.');
        else flash('ok', $n . ($n === 1 ? ' movie credit' : ' movie credits') . ' given to ' . $t['name'] . '.');
        redirect(url('/admin'));
    }
    if ($path === '/admin') {
        $grants = db()->query("SELECT l.*, u.name FROM credit_ledger l JOIN users u ON u.id = l.user_id WHERE l.stripe_ref LIKE 'admin:%' ORDER BY l.id DESC LIMIT 15")->fetchAll();
        $newLink = $_SESSION['new_link'] ?? null; unset($_SESSION['new_link']);
        $fb = db()->query('SELECT f.*, u.name FROM feedback f JOIN users u ON u.id = f.user_id ORDER BY f.id DESC LIMIT 50')->fetchAll();
        $q = trim((string)($_GET['q'] ?? '')); $per = 25; $page = max(1, (int)($_GET['page'] ?? 1));
        $where = ''; $args = [];
        if ($q !== '') { $where = "WHERE name LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\'"; $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%'; $args = [$like, $like]; }
        $cnt = db()->prepare("SELECT COUNT(*) FROM users $where"); $cnt->execute($args); $total = (int)$cnt->fetchColumn();
        $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
        $lst = db()->prepare("SELECT * FROM users $where ORDER BY (role = 'admin') DESC, name COLLATE NOCASE LIMIT $per OFFSET " . (($page - 1) * $per)); $lst->execute($args);
        $access = [];
        foreach ($lst->fetchAll() as $u) {
            $h = household_of((int)$u['id']);
            if ($h && (int)$h['owner_user_id'] !== (int)$u['id']) { $o = db()->prepare('SELECT name FROM users WHERE id = ?'); $o->execute([$h['owner_user_id']]); $access[] = ['u' => $u, 'member_of' => (string)$o->fetchColumn()]; continue; }
            $sub = $h ? subscription_of((int)$h['id']) : null;
            $access[] = ['u' => $u, 'sub' => $sub, 'used' => $h ? household_active_count((int)$h['id']) : 1];
        }
        render('admin', ['access' => $access, 'q' => $q, 'page' => $page, 'pages' => $pages, 'total' => $total, 'grants' => $grants, 'newLink' => $newLink, 'feedback' => $fb], 'Administration');
        exit;
    }
}

http_response_code(404);
render('message', ['heading' => tt('msg.page_not_found'), 'text' => tt('msg.that_page_doesn_t')], 'Not found', !current_user());
