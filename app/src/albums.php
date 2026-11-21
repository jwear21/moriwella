<?php
/* Shared albums: a private link to a hand-picked set of photos from one experience.
   The link is a long random token. The owner picks when it expires and can switch it off at any time.
   Only photos are shared, never journal text. People in the owner's household can also open albums inside the app.
   Copyright (c) JSW Software Designs */

const ALBUM_EXPIRY_DAYS = [7, 30, 90, 0];   /* 0 = never */
const ALBUM_MAX_PHOTOS = 300;

function album_new_token(): string { return bin2hex(random_bytes(16)); }
function album_link(array $a): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'moriwella.com') . url('/a/' . $a['token']);
}
/** 'on', 'off' (switched off by the owner) or 'expired'. */
function album_state(array $a): string {
    if ((int)$a['revoked_at'] > 0) return 'off';
    if ((int)$a['expires_at'] > 0 && (int)$a['expires_at'] < time()) return 'expired';
    return 'on';
}
function album_photos(int $albumId): array {
    $q = db()->prepare('SELECT p.*, e.entry_date FROM album_photos ap JOIN entry_photos p ON p.id = ap.photo_id JOIN entries e ON e.id = p.entry_id WHERE ap.album_id = ? ORDER BY ap.pos, p.id');
    $q->execute([$albumId]); return $q->fetchAll();
}
function albums_for_event(int $eventId, int $uid): array {
    $q = db()->prepare('SELECT a.*, (SELECT COUNT(*) FROM album_photos WHERE album_id = a.id) AS n FROM albums a WHERE a.event_id = ? AND a.user_id = ? ORDER BY a.id DESC');
    $q->execute([$eventId, $uid]); return $q->fetchAll();
}
function event_photo_list(int $eventId, int $uid): array {
    $q = db()->prepare('SELECT p.*, e.entry_date FROM entry_photos p JOIN entries e ON e.id = p.entry_id WHERE e.event_id = ? AND e.user_id = ? ORDER BY e.entry_date, e.id, p.id');
    $q->execute([$eventId, $uid]); return $q->fetchAll();
}
/** Albums the person made, plus albums made by anyone in their household. */
function albums_visible_to(int $uid): array {
    $q = db()->prepare("SELECT a.*, u.name AS owner_name, (SELECT COUNT(*) FROM album_photos WHERE album_id = a.id) AS n FROM albums a JOIN users u ON u.id = a.user_id
        WHERE a.user_id = ? OR a.user_id IN (SELECT m2.user_id FROM household_members m1 JOIN household_members m2 ON m2.household_id = m1.household_id AND m2.status = 'active' WHERE m1.user_id = ? AND m1.status = 'active')
        ORDER BY a.id DESC LIMIT 200");
    $q->execute([$uid, $uid]); return $q->fetchAll();
}
function album_can_view(array $a, int $uid): bool {
    if ((int)$a['user_id'] === $uid) return true;
    $q = db()->prepare("SELECT 1 FROM household_members m1 JOIN household_members m2 ON m2.household_id = m1.household_id AND m2.status = 'active' WHERE m1.user_id = ? AND m1.status = 'active' AND m2.user_id = ?");
    $q->execute([$uid, (int)$a['user_id']]); return (bool)$q->fetchColumn();
}
function album_send_photo(array $p, string $size, bool $download, string $name): never {
    $file = photo_path($p, $size);
    if (!$file) { http_response_code(404); exit; }
    $isJpg = substr($file, -4) === '.jpg';
    header('Content-Type: ' . ($isJpg ? 'image/jpeg' : $p['mime']));
    header('Cache-Control: private, max-age=3600'); header('X-Content-Type-Options: nosniff'); header('Content-Length: ' . filesize($file));
    if ($download) header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) . '.' . ($isJpg ? 'jpg' : $p['ext']) . '"');
    readfile($file); exit;
}
function album_inactive_page(): never {
    http_response_code(410);
    header('X-Robots-Tag: noindex, nofollow'); header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'none'");
    $bare = true; $title = tt('album.inactive_title'); $heading = tt('album.inactive_title'); $text = tt('album.inactive_text');
    require __DIR__ . '/views/album_inactive.php'; exit;
}
/** The viewer page, shared by the public link and by household members inside the app. $photoUrl(array $photo, string $size): string */
function album_viewer(array $a, array $photos, callable $photoUrl, bool $public): never {
    $o = db()->prepare('SELECT name FROM users WHERE id = ?'); $o->execute([(int)$a['user_id']]);
    $owner = explode(' ', trim((string)$o->fetchColumn()))[0] ?? '';
    header('X-Robots-Tag: noindex, nofollow'); header('Referrer-Policy: no-referrer'); header('Cache-Control: private, no-store');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'none'");
    require __DIR__ . '/views/album_view.php'; exit;
}

/** Public: /a/<token> and /a/<token>/p/<photo>/<size>. No sign-in. Anything wrong looks the same: an unavailable link. */
function album_public(array $m): never {
    $q = db()->prepare('SELECT * FROM albums WHERE token = ?'); $q->execute([$m[1]]); $a = $q->fetch();
    if (!$a || album_state($a) !== 'on') album_inactive_page();
    if (isset($m[2]) && $m[2] !== '') {
        $s = db()->prepare('SELECT p.* FROM album_photos ap JOIN entry_photos p ON p.id = ap.photo_id WHERE ap.album_id = ? AND p.id = ?'); $s->execute([(int)$a['id'], (int)$m[2]]); $p = $s->fetch();
        $size = $m[3]; if (!$p || ($size === 'orig' && !(int)$a['allow_download'])) { http_response_code(404); exit; }
        album_send_photo($p, $size, $size === 'orig' || !empty($_GET['dl']), 'moriwella-' . $p['id']);
    }
    $mine = isset($_SESSION['uid']) && (int)$_SESSION['uid'] === (int)$a['user_id'];
    if (!$mine) db()->prepare('UPDATE albums SET views = views + 1, last_view = ? WHERE id = ?')->execute([time(), $a['id']]);
    $photos = album_photos((int)$a['id']);
    album_viewer($a, $photos, fn($p, $size) => url('/a/' . $a['token'] . '/p/' . $p['id'] . '/' . $size), true);
}

/** Signed-in routes. Called from index.php once the member is known. */
function albums_member_routes(int $uid, array $user, bool $post, string $path): void {
    if ($post && preg_match('#^/experiences/(\d+)/album$#', $path, $mm)) {
        $ev = db()->prepare('SELECT * FROM events WHERE id = ? AND user_id = ?'); $ev->execute([(int)$mm[1], $uid]); $ev = $ev->fetch();
        if (!$ev) redirect(url('/experiences'));
        $back = url('/experiences/' . $ev['id']) . '#album';
        if (!billing_can($uid, 'scrapbook')) { flash('err', tt('billing.err_limited')); redirect(url('/billing')); }
        $own = array_column(event_photo_list((int)$ev['id'], $uid), 'id');
        $want = array_values(array_unique(array_map('intval', (array)($_POST['photos'] ?? []))));
        $ids = array_values(array_filter($want, fn($i) => in_array($i, array_map('intval', $own), true)));
        if (!$ids) { flash('err', tt('album.pick_one')); redirect($back); }
        $ids = array_slice($ids, 0, ALBUM_MAX_PHOTOS);
        $days = (int)($_POST['expires'] ?? 30); if (!in_array($days, ALBUM_EXPIRY_DAYS, true)) $days = 30;
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 100); if ($title === '') $title = (string)$ev['title'];
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 400);
        db()->prepare('INSERT INTO albums (user_id, event_id, title, note, token, expires_at, allow_download, created_at) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$uid, $ev['id'], $title, $note, album_new_token(), $days > 0 ? time() + $days * 86400 : 0, !empty($_POST['downloads']) ? 1 : 0, time()]);
        $aid = (int)db()->lastInsertId(); $ins = db()->prepare('INSERT INTO album_photos (album_id, photo_id, pos) VALUES (?,?,?)');
        foreach ($ids as $k => $pid) $ins->execute([$aid, $pid, $k]);
        flash('ok', tt('album.created')); redirect($back);
    }
    if (preg_match('#^/albums/(\d+)/(off|on|expiry|delete)$#', $path, $mm) && $post) {
        $q = db()->prepare('SELECT * FROM albums WHERE id = ? AND user_id = ?'); $q->execute([(int)$mm[1], $uid]); $a = $q->fetch();
        if (!$a) { http_response_code(404); exit; }
        $back = $a['event_id'] ? url('/experiences/' . $a['event_id']) . '#album' : url('/albums');
        if ($mm[2] === 'off') { db()->prepare('UPDATE albums SET revoked_at = ? WHERE id = ?')->execute([time(), $a['id']]); flash('ok', tt('album.turned_off')); }
        if ($mm[2] === 'on') { db()->prepare('UPDATE albums SET revoked_at = 0 WHERE id = ?')->execute([$a['id']]); flash('ok', tt('album.turned_on')); }
        if ($mm[2] === 'expiry') {
            $days = (int)($_POST['expires'] ?? 30); if (!in_array($days, ALBUM_EXPIRY_DAYS, true)) $days = 30;
            db()->prepare('UPDATE albums SET expires_at = ? WHERE id = ?')->execute([$days > 0 ? time() + $days * 86400 : 0, $a['id']]); flash('ok', tt('album.expiry_saved'));
        }
        if ($mm[2] === 'delete') { db()->prepare('DELETE FROM albums WHERE id = ?')->execute([$a['id']]); flash('ok', tt('album.deleted')); }
        redirect($back);
    }
    if ($path === '/albums') { render('albums', ['albums' => albums_visible_to($uid)], tt('album.heading')); exit; }
    if (preg_match('#^/albums/(\d+)(?:/p/(\d+)/(thumb|web|orig))?$#', $path, $mm)) {
        $q = db()->prepare('SELECT * FROM albums WHERE id = ?'); $q->execute([(int)$mm[1]]); $a = $q->fetch();
        if (!$a || !album_can_view($a, $uid)) { http_response_code(404); exit; }
        if (!empty($mm[2])) {
            $s = db()->prepare('SELECT p.* FROM album_photos ap JOIN entry_photos p ON p.id = ap.photo_id WHERE ap.album_id = ? AND p.id = ?'); $s->execute([(int)$a['id'], (int)$mm[2]]); $p = $s->fetch();
            if (!$p || ($mm[3] === 'orig' && !(int)$a['allow_download'] && (int)$a['user_id'] !== $uid)) { http_response_code(404); exit; }
            album_send_photo($p, $mm[3], $mm[3] === 'orig' || !empty($_GET['dl']), 'moriwella-' . $p['id']);
        }
        album_viewer($a, album_photos((int)$a['id']), fn($p, $size) => url('/albums/' . $a['id'] . '/p/' . $p['id'] . '/' . $size), false);
    }
}
