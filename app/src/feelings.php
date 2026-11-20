<?php
/* How you feel: one face for the day (stored with the other daily numbers as "mood"), plus any number of named feelings. */

function feelings_faces(): array {
    return [1 => ['e' => "\u{1F61E}", 'l' => tl('Rough')], 2 => ['e' => "\u{1F641}", 'l' => tl('Low')], 3 => ['e' => "\u{1F610}", 'l' => tl('Okay')],
            4 => ['e' => "\u{1F642}", 'l' => tl('Good')], 5 => ['e' => "\u{1F604}", 'l' => tl('Great')]];
}
function feelings_catalog(): array {
    return [
      'Light and lively' => ['happy', 'grateful', 'excited', 'proud', 'hopeful', 'loved', 'playful', 'inspired', 'energized', 'confident'],
      'Calm and steady' => ['calm', 'content', 'relaxed', 'peaceful', 'rested', 'focused', 'safe', 'connected'],
      'Heavy or hard' => ['tired', 'anxious', 'stressed', 'overwhelmed', 'sad', 'lonely', 'angry', 'frustrated', 'restless', 'bored', 'worried', 'hurt'],
      'Somewhere in between' => ['nostalgic', 'curious', 'surprised', 'uncertain', 'emotional', 'numb'],
    ];
}
function feelings_valid(string $f): bool { foreach (feelings_catalog() as $l) if (in_array($f, $l, true)) return true; return false; }
function feelings_part(int $ts): string { $h = (int)date('G', $ts); return $h >= 5 && $h < 12 ? 'morning' : ($h < 17 && $h >= 12 ? 'afternoon' : ($h >= 17 && $h < 21 ? 'evening' : 'night')); }
/* Every mood for a day, oldest first. A mood with ts 0 has no time (an older one, or one added to a past day). */
function feelings_for_day(int $uid, string $day): array {
    $q = db()->prepare('SELECT id, value AS v, ts FROM mood_log WHERE user_id = ? AND day = ? ORDER BY ts, id'); $q->execute([$uid, $day]);
    $moods = array_map(fn($r) => ['id' => (int)$r['id'], 'v' => (int)$r['v'], 'ts' => (int)$r['ts']], $q->fetchAll());
    $q = db()->prepare('SELECT feeling FROM day_feelings WHERE user_id = ? AND day = ? ORDER BY rowid'); $q->execute([$uid, $day]);
    return ['moods' => $moods, 'mood' => $moods ? end($moods)['v'] : 0, 'feelings' => array_column($q->fetchAll(), 'feeling')];
}
/* The latest mood of each day, for the calendar. */
function feelings_moods_between(int $uid, string $from, string $to): array {
    $q = db()->prepare('SELECT day, value FROM mood_log WHERE user_id = ? AND day BETWEEN ? AND ? ORDER BY ts, id'); $q->execute([$uid, $from, $to]);
    $o = []; foreach ($q->fetchAll() as $r) { $v = (int)$r['value']; if ($v >= 1 && $v <= 5) $o[$r['day']] = $v; } return $o;
}

/* Looking back: the average mood of each day, a count per face, and the feelings named most often. */
function feelings_summary(int $uid, int $days): array {
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days')); $to = date('Y-m-d');
    $q = db()->prepare('SELECT day, AVG(value) a FROM mood_log WHERE user_id = ? AND day BETWEEN ? AND ? GROUP BY day'); $q->execute([$uid, $from, $to]);
    $byDay = []; foreach ($q->fetchAll() as $r) $byDay[$r['day']] = max(1, min(5, (int)round((float)$r['a'])));
    $cells = []; $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    for ($i = $days - 1; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $v = $byDay[$d] ?? 0; $cells[] = ['day' => $d, 'v' => $v]; if ($v) $counts[$v]++; }
    $logged = array_sum($counts); $avg = $logged ? array_sum(array_map(fn($v, $c) => $v * $c, array_keys($counts), $counts)) / $logged : 0;
    $q = db()->prepare('SELECT feeling, COUNT(*) c FROM day_feelings WHERE user_id = ? AND day BETWEEN ? AND ? GROUP BY feeling ORDER BY c DESC, feeling LIMIT 10'); $q->execute([$uid, $from, $to]);
    return ['cells' => $cells, 'counts' => $counts, 'logged' => $logged, 'avg' => $avg, 'feelings' => $q->fetchAll(), 'days' => $days];
}
