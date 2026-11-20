<?php
/* Track routes (functions live in trackers.php) */
if ($path === '/track') {
    render('track', ['groups' => tracker_catalog(), 'on' => array_flip(tracker_enabled($uid)), 'goals' => goals_for_day($uid, date('Y-m-d'))], 'Track');
    exit;
}
if ($path === '/track/toggle' && $post) {
    $k = (string)($_POST['key'] ?? ''); $flat = tracker_flat();
    if (isset($flat[$k])) {
        if ($k === 'water') save_prefs($uid, celebrations_on($uid), !water_on($uid), celebrate_size($uid));
        else {
            $has = db()->prepare('SELECT 1 FROM tracker_on WHERE user_id = ? AND tkey = ?'); $has->execute([$uid, $k]);
            if ($has->fetch()) db()->prepare('DELETE FROM tracker_on WHERE user_id = ? AND tkey = ?')->execute([$uid, $k]);
            else db()->prepare('INSERT INTO tracker_on (user_id, tkey) VALUES (?, ?)')->execute([$uid, $k]);
        }
    }
    redirect(url('/track') . '#t-' . preg_replace('/[^a-z]/', '', $k));
}
if ($path === '/track/log' && $post) {
    $k = (string)($_POST['key'] ?? ''); $day = (string)($_POST['day'] ?? ''); $flat = tracker_flat();
    if (!isset($flat[$k]) || !valid_date($day)) json_out(['ok' => false], 400);
    $t = $flat[$k]; $raw = trim((string)($_POST['value'] ?? '')); $v = null;
    if ($raw !== '' && is_numeric($raw)) {
        $n = (float)$raw;
        if ($t['type'] === 'check') $v = $n > 0 ? 1.0 : null;
        elseif ($t['type'] === 'count') $v = $n > 0 ? min((float)($t['max'] ?? 20), floor($n)) : null;
        elseif ($t['type'] === 'scale') $v = ($n >= 1 && $n <= 5) ? floor($n) : null;
        elseif ($t['type'] === 'number') $v = ($n > 0 && $n < 1000000) ? round($n, 2) : null;
    }
    if ($v === null) db()->prepare('DELETE FROM tracker_log WHERE user_id = ? AND tkey = ? AND day = ?')->execute([$uid, $k, $day]);
    else db()->prepare('INSERT INTO tracker_log (user_id, tkey, day, value) VALUES (?, ?, ?, ?) ON CONFLICT(user_id, tkey, day) DO UPDATE SET value = excluded.value')->execute([$uid, $k, $day, $v]);
    json_out(['ok' => true, 'value' => $v]);
}
if ($path === '/track/note' && $post) {
    $k = (string)($_POST['key'] ?? ''); $day = (string)($_POST['day'] ?? ''); $flat = tracker_flat();
    $text = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 300);
    if (isset($flat[$k]) && $flat[$k]['type'] === 'note' && valid_date($day) && $text !== '')
        db()->prepare('INSERT INTO tracker_notes (user_id, tkey, day, text, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$uid, $k, $day, $text, time()]);
    redirect(url('/day/' . (valid_date($day) ? $day : date('Y-m-d'))) . '#little');
}
if (preg_match('#^/track/note/(\d+)/delete$#', $path, $mm) && $post) {
    $q = db()->prepare('SELECT day FROM tracker_notes WHERE id = ? AND user_id = ?'); $q->execute([(int)$mm[1], $uid]); $day = $q->fetchColumn();
    db()->prepare('DELETE FROM tracker_notes WHERE id = ? AND user_id = ?')->execute([(int)$mm[1], $uid]);
    redirect(url('/day/' . ($day ?: date('Y-m-d'))) . '#little');
}
if ($path === '/feel' && $post) {
    $day = (string)($_POST['day'] ?? ''); if (!valid_date($day)) json_out(['ok' => false], 400);
    if (isset($_POST['mood'])) {
        $n = (int)$_POST['mood'];
        if ($n >= 1 && $n <= 5) {
            if ($day === date('Y-m-d')) {
                /* tapping a different face within ten minutes corrects the last mood instead of adding another */
                $l = db()->prepare('SELECT id FROM mood_log WHERE user_id = ? AND day = ? AND ts >= ? ORDER BY ts DESC, id DESC LIMIT 1'); $l->execute([$uid, $day, time() - 600]); $last = $l->fetchColumn();
                if ($last) db()->prepare('UPDATE mood_log SET value = ? WHERE id = ? AND user_id = ?')->execute([$n, $last, $uid]);
                else db()->prepare('INSERT INTO mood_log (user_id, day, ts, value) VALUES (?, ?, ?, ?)')->execute([$uid, $day, time(), $n]);
            } else {
                $l = db()->prepare('SELECT id FROM mood_log WHERE user_id = ? AND day = ? AND ts = 0 ORDER BY id DESC LIMIT 1'); $l->execute([$uid, $day]); $last = $l->fetchColumn();
                if ($last) db()->prepare('UPDATE mood_log SET value = ? WHERE id = ? AND user_id = ?')->execute([$n, $last, $uid]);
                else db()->prepare('INSERT INTO mood_log (user_id, day, ts, value) VALUES (?, ?, 0, ?)')->execute([$uid, $day, $n]);
            }
        }
    }
    if (isset($_POST['remove_mood'])) db()->prepare('DELETE FROM mood_log WHERE id = ? AND user_id = ? AND day = ?')->execute([(int)$_POST['remove_mood'], $uid, $day]);
    if (isset($_POST['feeling'])) {
        $f = (string)$_POST['feeling'];
        if (feelings_valid($f)) {
            if (!empty($_POST['on'])) db()->prepare('INSERT OR IGNORE INTO day_feelings (user_id, day, feeling) VALUES (?, ?, ?)')->execute([$uid, $day, $f]);
            else db()->prepare('DELETE FROM day_feelings WHERE user_id = ? AND day = ? AND feeling = ?')->execute([$uid, $day, $f]);
        }
    }
    json_out(['ok' => true, 'moods' => feelings_for_day($uid, $day)['moods']]);
}
