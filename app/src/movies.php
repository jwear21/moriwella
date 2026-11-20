<?php
require_once __DIR__ . '/credits.php';
/* Memory movies: request, watch progress, play, download, delete.
   Copyright (c) JSW Software Designs */
require_once __DIR__ . '/movie_render.php';

function movie_event(int $id, int $uid): ?array {
    $q = db()->prepare('SELECT * FROM events WHERE id = ? AND user_id = ?'); $q->execute([$id, $uid]); return $q->fetch() ?: null;
}
function movies_for_event(int $eventId, int $uid): array {
    $q = db()->prepare('SELECT * FROM movies WHERE event_id = ? AND user_id = ? ORDER BY id DESC LIMIT 3'); $q->execute([$eventId, $uid]);
    return $q->fetchAll();
}
function movies_purge_event(int $eventId, int $uid): void {
    $q = db()->prepare('SELECT * FROM movies WHERE event_id = ? AND user_id = ?'); $q->execute([$eventId, $uid]);
    foreach ($q->fetchAll() as $m) mv_delete_files($m);
}

if ($post && preg_match('#^/experiences/(\d+)/movie$#', $path, $mm)) {
    $ev = movie_event((int)$mm[1], $uid); if (!$ev) redirect(url('/experiences'));
    $back = url('/experiences/' . $ev['id']) . '#movie';
    $n = db()->prepare('SELECT COUNT(*) FROM entry_photos p JOIN entries e ON e.id = p.entry_id WHERE e.event_id = ? AND e.user_id = ?'); $n->execute([$ev['id'], $uid]);
    $act = db()->prepare("SELECT COUNT(*) FROM movies WHERE user_id = ? AND status IN ('queued','rendering')"); $act->execute([$uid]);
    $day = db()->prepare('SELECT COUNT(*) FROM movies WHERE user_id = ? AND created_at > ?'); $day->execute([$uid, time() - 86400]);
    if ((int)$n->fetchColumn() < 3) flash('err', tt('msg.add_at_least_three'));
    elseif ((int)$act->fetchColumn() > 0) flash('err', tt('msg.your_other_movie_is'));
    elseif ((int)$day->fetchColumn() >= 6) flash('err', tt('msg.that_is_plenty_of'));
    else {
        $theme = isset(MV_THEMES[$_POST['theme'] ?? '']) ? $_POST['theme'] : 'evergreen';
        $len = isset(MV_LENGTHS[$_POST['length'] ?? '']) ? $_POST['length'] : 'full';
        $old = db()->prepare("SELECT * FROM movies WHERE event_id = ? AND user_id = ? AND status IN ('done','failed') ORDER BY id DESC"); $old->execute([$ev['id'], $uid]);
        foreach (array_slice($old->fetchAll(), 2) as $m) { mv_delete_files($m); db()->prepare('DELETE FROM movies WHERE id = ?')->execute([$m['id']]); }
        /* one transaction: check access, hold the credit, queue the movie. Two quick clicks can never spend the same credit. */
        $res = billing_tx(function () use ($uid, $ev, $theme, $len) {
            if (!billing_can($uid, 'movies')) return 'billing.err_limited';
            db()->prepare('INSERT INTO movies (user_id, event_id, theme, length, subtitle, closing, cost, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$uid, $ev['id'], $theme, $len, mb_substr(trim((string)($_POST['subtitle'] ?? '')), 0, 90), mb_substr(trim((string)($_POST['closing'] ?? '')), 0, 160), MV_CREDIT_COST, time()]);
            $mid = (int)db()->lastInsertId();
            if (credits_reserve($uid, $mid, MV_CREDIT_COST) === null) { db()->prepare('DELETE FROM movies WHERE id = ?')->execute([$mid]); return 'billing.err_no_credits'; }
            return '';
        });
        if ($res !== '') { flash('err', tt($res)); redirect(url('/billing') . '#credits'); }
        mv_kick(); flash('ok', tt('msg.your_movie_is_in'));
    }
    redirect($back);
}
if (preg_match('#^/movies/(\d+)/(status|file|delete)$#', $path, $mm)) {
    $q = db()->prepare('SELECT * FROM movies WHERE id = ? AND user_id = ?'); $q->execute([(int)$mm[1], $uid]); $m = $q->fetch();
    if (!$m) { http_response_code(404); exit; }
    if ($mm[2] === 'status') json_out(['ok' => true, 'status' => $m['status'], 'progress' => (int)$m['progress'], 'error' => $m['error']]);
    if ($mm[2] === 'delete' && $post) {
        mv_delete_files($m); db()->prepare('DELETE FROM movies WHERE id = ?')->execute([$m['id']]);
        flash('ok', tt('msg.movie_deleted')); redirect(url('/experiences/' . $m['event_id']) . '#movie');
    }
    if ($mm[2] === 'file') {
        if ($m['status'] !== 'done' || !is_file($m['file'])) { http_response_code(404); exit; }
        $size = filesize($m['file']); $start = 0; $end = $size - 1;
        $ev = movie_event((int)$m['event_id'], $uid); $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ev['title'] ?? 'movie')), '-') ?: 'movie';
        header('Content-Type: video/mp4'); header('Accept-Ranges: bytes'); header('Cache-Control: private, max-age=3600');
        if (!empty($_GET['dl'])) header('Content-Disposition: attachment; filename="moriwella-' . $slug . '.mp4"');
        if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'] ?? '', $r)) {
            if ($r[1] !== '') { $start = (int)$r[1]; if ($r[2] !== '') $end = min($end, (int)$r[2]); } elseif ($r[2] !== '') { $start = max(0, $size - (int)$r[2]); }
            if ($start > $end || $start >= $size) { http_response_code(416); header('Content-Range: bytes */' . $size); exit; }
            http_response_code(206); header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        header('Content-Length: ' . ($end - $start + 1));
        $f = fopen($m['file'], 'rb'); fseek($f, $start); $left = $end - $start + 1;
        while ($left > 0 && !feof($f)) { $c = fread($f, min(65536, $left)); echo $c; $left -= strlen($c); }
        fclose($f); exit;
    }
}
