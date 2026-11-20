<?php
/* How you feel: one face for the day (stored with the other daily numbers as "mood"), plus any number of named feelings. */

function feelings_faces(): array {
    return [1 => ['e' => "\u{1F61E}", 'l' => 'Rough'], 2 => ['e' => "\u{1F641}", 'l' => 'Low'], 3 => ['e' => "\u{1F610}", 'l' => 'Okay'],
            4 => ['e' => "\u{1F642}", 'l' => 'Good'], 5 => ['e' => "\u{1F604}", 'l' => 'Great']];
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
function feelings_for_day(int $uid, string $day): array {
    $q = db()->prepare("SELECT value FROM tracker_log WHERE user_id = ? AND tkey = 'mood' AND day = ?"); $q->execute([$uid, $day]);
    $mood = (int)round((float)($q->fetchColumn() ?: 0));
    $q = db()->prepare('SELECT feeling FROM day_feelings WHERE user_id = ? AND day = ? ORDER BY rowid'); $q->execute([$uid, $day]);
    return ['mood' => ($mood >= 1 && $mood <= 5) ? $mood : 0, 'feelings' => array_column($q->fetchAll(), 'feeling')];
}
function feelings_moods_between(int $uid, string $from, string $to): array {
    $q = db()->prepare("SELECT day, value FROM tracker_log WHERE user_id = ? AND tkey = 'mood' AND day BETWEEN ? AND ?"); $q->execute([$uid, $from, $to]);
    $o = []; foreach ($q->fetchAll() as $r) { $v = (int)round((float)$r['value']); if ($v >= 1 && $v <= 5) $o[$r['day']] = $v; } return $o;
}

