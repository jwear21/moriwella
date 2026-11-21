<?php
/* Track routes (functions live in trackers.php) */
if ($path === '/track/feelings') {
    $d = (int)($_GET['d'] ?? 30); if (!in_array($d, [30, 90, 365], true)) $d = 30;
    render('track_feelings', ['sum' => feelings_summary($uid, $d)], 'How you have been feeling');
    exit;
}
if ($path === '/track') {
    render('track', ['groups' => tracker_catalog(), 'on' => array_flip(tracker_enabled($uid)), 'goals' => goals_for_day($uid, date('Y-m-d')), 'goalsT' => tracker_goals($uid), 'iconsT' => tracker_icons()], 'Track');
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
    $prevQ = db()->prepare('SELECT value FROM tracker_log WHERE user_id = ? AND tkey = ? AND day = ?'); $prevQ->execute([$uid, $k, $day]); $prev = (float)($prevQ->fetchColumn() ?: 0);
    if ($v === null) db()->prepare('DELETE FROM tracker_log WHERE user_id = ? AND tkey = ? AND day = ?')->execute([$uid, $k, $day]);
    else db()->prepare('INSERT INTO tracker_log (user_id, tkey, day, value) VALUES (?, ?, ?, ?) ON CONFLICT(user_id, tkey, day) DO UPDATE SET value = excluded.value')->execute([$uid, $k, $day, $v]);
    $g = tracker_goals($uid)[$k] ?? null; $info = [];
    if ($g && in_array($t['type'], ['count', 'number'], true)) {
        $now = (float)($v ?? 0); $info = ['reached' => $now >= $g['target'] && $prev < $g['target'], 'mile' => floor($now / $g['per']) > floor($prev / $g['per'])];
        $info['allDone'] = $info['reached'] && all_goals_done($uid, $day);
        if ($info['reached']) $info['unlocked'] = achievements_eval($uid, ['day' => $day]);
    }
    json_out(['ok' => true, 'value' => $v] + $info);
}
if ($path === '/track/goal' && $post) {
    $k = (string)($_POST['key'] ?? ''); $flat = tracker_flat(); $icons = tracker_icons();
    if (!isset($flat[$k]) || !in_array($flat[$k]['type'], ['water', 'count', 'number'], true)) { redirect(url('/track')); }
    $target = (float)($_POST['target'] ?? 0); $per = (float)($_POST['per'] ?? 0); $icon = (string)($_POST['icon'] ?? 'star');
    if (!isset($icons[$icon])) $icon = 'star';
    if (($_POST['clear'] ?? '') === '1' || $target <= 0) {
        db()->prepare('DELETE FROM tracker_goal WHERE user_id = ? AND tkey = ?')->execute([$uid, $k]);
        flash('ok', tt('msg.goal_cleared'));
    } else {
        $target = min($target, 1000000.0); if ($per <= 0 || $per > $target) $per = $target;
        if ($target / $per > 24) $per = $target / 24; /* at most 24 bullets */
        db()->prepare('INSERT INTO tracker_goal (user_id, tkey, target, per, icon) VALUES (?, ?, ?, ?, ?) ON CONFLICT(user_id, tkey) DO UPDATE SET target = excluded.target, per = excluded.per, icon = excluded.icon')->execute([$uid, $k, round($target, 2), round($per, 2), $icon]);
        flash('ok', tt('msg.goal_saved'));
    }
    redirect(url('/track') . '#t-' . preg_replace('/[^a-z]/', '', $k));
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
