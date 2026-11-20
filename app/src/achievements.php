<?php
/* Achievements: definitions live in achievements.json. Rules are evaluated after things happen; unlocked badges are permanent. */
function achievement_defs(): array {
    static $defs = null;
    if ($defs === null) { $j = json_decode((string)file_get_contents(__DIR__ . '/achievements.json'), true); $defs = $j['achievements'] ?? []; }
    return $defs;
}
function achievement_period(array $a, string $day, array $ctx): string {
    $rep = $a['repeatable'] ?? false;
    if ($rep === 'daily') return $day . (isset($ctx['list_id']) ? '#' . (int)$ctx['list_id'] : '');
    if ($rep === 'weekly') return date('o-\WW', strtotime($day));
    if ($rep === true) return $day . (isset($ctx['list_id']) ? '#' . (int)$ctx['list_id'] : '');
    return '';
}
function longest_journal_run(int $uid): int {
    $q = db()->prepare('SELECT DISTINCT entry_date FROM entries WHERE user_id = ? ORDER BY entry_date DESC LIMIT 400'); $q->execute([$uid]);
    $best = 0; $run = 0; $prev = null;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $d) {
        $run = ($prev !== null && date('Y-m-d', strtotime($d . ' +1 day')) === $prev) ? $run + 1 : 1;
        $best = max($best, $run); $prev = $d;
    }
    return $best;
}
function achievement_rule_met(array $a, int $uid, string $day, array $ctx): bool {
    $t = $a['trigger']; $rule = $t['rule'] ?? '';
    switch ($a['id']) {
        case 'hydration-goal': return water_on($uid) && water_oz($uid, $day) >= 64;
        case 'daily-checklist': return count(goals_for_day($uid, $day)) > 0 && all_goals_done($uid, $day);
    }
    if ($rule === 'count' && ($t['event'] ?? '') === 'entry.saved') {
        $q = db()->prepare('SELECT COUNT(*) FROM entries WHERE user_id = ?'); $q->execute([$uid]); return (int)$q->fetchColumn() >= (int)$t['min'];
    }
    if ($rule === 'consecutive-days') return longest_journal_run($uid) >= (int)$t['min'];
    if (($t['event'] ?? '') === 'list.shared') {
        $q = db()->prepare('SELECT COUNT(*) FROM list_members m JOIN lists l ON l.id = m.list_id WHERE l.owner_id = ? OR m.user_id = ?'); $q->execute([$uid, $uid]); return (int)$q->fetchColumn() >= (int)$t['min'];
    }
    if ($rule === 'list-type') {
        if (empty($ctx['list_id']) || empty($ctx['list_done'])) return false;
        $q = db()->prepare('SELECT kind, (SELECT COUNT(*) FROM list_members WHERE list_id = lists.id) members FROM lists WHERE id = ?'); $q->execute([(int)$ctx['list_id']]); $l = $q->fetch();
        return $l && $l['kind'] === $t['type'] && (int)$l['members'] > 0;
    }
    if (($t['event'] ?? '') === 'event.completed') {
        $travel = ['cruise', 'vacation', 'vegas', 'roadtrip', 'camping'];
        $sql = 'SELECT COUNT(*) FROM events e WHERE e.user_id = ? AND e.end_date < ? AND EXISTS (SELECT 1 FROM entries n WHERE n.event_id = e.id)';
        $args = [$uid, date('Y-m-d')];
        if (($t['template'] ?? '') === 'vacation') { $sql .= ' AND e.template_key IN (' . implode(',', array_fill(0, count($travel), '?')) . ')'; $args = array_merge($args, $travel); }
        $q = db()->prepare($sql); $q->execute($args); return (int)$q->fetchColumn() >= (int)$t['min'];
    }
    return false;
}
/* Check every achievement; record and return the ones newly unlocked. */
function achievements_eval(int $uid, array $ctx = []): array {
    $day = (isset($ctx['day']) && valid_date((string)$ctx['day'])) ? $ctx['day'] : date('Y-m-d');
    $new = [];
    foreach (achievement_defs() as $a) {
        $period = achievement_period($a, $day, $ctx);
        $has = db()->prepare('SELECT 1 FROM achievements_unlocked WHERE user_id = ? AND achievement_id = ? AND period = ?'); $has->execute([$uid, $a['id'], $period]);
        if ($has->fetch()) continue;
        if (!achievement_rule_met($a, $uid, $day, $ctx)) continue;
        db()->prepare('INSERT OR IGNORE INTO achievements_unlocked (user_id, achievement_id, period, unlocked_at) VALUES (?, ?, ?, ?)')->execute([$uid, $a['id'], $period, time()]);
        if (($a['celebrate'] ?? 'milestone') !== 'none') $new[] = ['id' => $a['id'], 'title' => $a['title'], 'sub' => $a['description'], 'level' => $a['celebrate'] ?? 'milestone'];
    }
    return $new;
}
function queue_unlocked(array $list): void { if ($list) $_SESSION['mw_unlocked'] = array_merge($_SESSION['mw_unlocked'] ?? [], $list); }
function take_unlocked(): array { $u = $_SESSION['mw_unlocked'] ?? []; unset($_SESSION['mw_unlocked']); return $u; }
function achievements_for_user(int $uid): array {
    $q = db()->prepare('SELECT achievement_id, COUNT(*) n, MAX(unlocked_at) last_at, MIN(unlocked_at) first_at FROM achievements_unlocked WHERE user_id = ? GROUP BY achievement_id'); $q->execute([$uid]);
    $got = []; foreach ($q->fetchAll() as $r) $got[$r['achievement_id']] = $r;
    $out = [];
    foreach (achievement_defs() as $a) { $a['got'] = $got[$a['id']] ?? null; $out[$a['category']][] = $a; }
    return $out;
}
