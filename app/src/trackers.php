<?php
/* Track: small things worth noticing each day. Quiet by design: they sit under the journal, never above it. */

function tracker_catalog(): array {
    $out = [];
    foreach (tracker_catalog_en() as $g => $items) {
        foreach ($items as $k => $it) foreach (['label', 'hint', 'unit', 'ph', 'low', 'high'] as $f) if (isset($it[$f])) $items[$k][$f] = tl($it[$f]);
        $out[tl($g)] = $items;
    }
    return $out;
}
function tracker_catalog_en(): array {
    $c = fn($l, $t, $x = []) => array_merge(['label' => $l, 'type' => $t], $x);
    return [
      'Food and drink' => [
        'water'    => $c('Water', 'water', ['hint' => 'Eight cups, one tap at a time']),
        'produce'  => $c('Fruit and vegetables', 'count', ['unit' => 'servings', 'max' => 12]),
        'caffeine' => $c('Coffee and tea', 'count', ['unit' => 'cups', 'max' => 12]),
        'alcohol'  => $c('Drinks with alcohol', 'count', ['unit' => 'drinks', 'max' => 12]),
        'cooked'   => $c('Cooked at home', 'check'),
        'meals'    => $c('What I ate', 'note', ['ph' => 'Breakfast: oatmeal and berries']),
      ],
      'Body and care' => [
        'meds'      => $c('Took my medicine or vitamins', 'check'),
        'walked'    => $c('Went for a walk', 'check'),
        'exercised' => $c('Exercised', 'check'),
        'stretched' => $c('Stretched', 'check'),
        'steps'     => $c('Steps', 'number', ['unit' => 'steps', 'step' => 1]),
        'exmin'     => $c('Exercise', 'number', ['unit' => 'minutes', 'step' => 1]),
        'sleep'     => $c('Sleep', 'number', ['unit' => 'hours', 'step' => 0.5]),
        'weight'    => $c('Weight', 'number', ['unit' => 'lb', 'step' => 0.1]),
      ],
      'Heart and mind' => [
        'energy'   => $c('Energy', 'scale', ['low' => 'Drained', 'high' => 'Full']),
        'stress'   => $c('Stress', 'scale', ['low' => 'Calm', 'high' => 'Heavy']),
        'meditate' => $c('Meditated or prayed', 'check'),
        'read'     => $c('Read for a while', 'check'),
        'pages'    => $c('Pages read', 'number', ['unit' => 'pages', 'step' => 1]),
        'outside'  => $c('Time outside', 'check'),
        'called'   => $c('Called someone I love', 'check'),
        'noscreen' => $c('Put screens away before bed', 'check'),
      ],
      'Health notes' => [
        'pain'     => $c('Pain or discomfort', 'scale', ['low' => 'None', 'high' => 'A lot']),
        'symptoms' => $c('Symptoms', 'note', ['ph' => 'Headache after lunch']),
        'medsnote' => $c('Medicine and doses', 'note', ['ph' => 'Ibuprofen 200 mg']),
      ],
      'Money' => [
        'spent'    => $c('Spent today', 'number', ['unit' => 'dollars', 'step' => 0.01]),
      ],
    ];
}
/** The signed-in person's own trackers, shaped like catalog items (number type, so any amount can be counted). */
function tracker_custom_defs(?int $uid = null): array {
    if ($uid === null) { $u = function_exists('current_user') ? current_user() : null; $uid = $u ? (int)$u['id'] : 0; }
    if ($uid <= 0) return [];
    $q = db()->prepare('SELECT id, label, unit FROM tracker_custom WHERE user_id = ? ORDER BY id'); $q->execute([$uid]);
    $o = [];
    foreach ($q->fetchAll() as $r) $o['c' . $r['id']] = ['label' => $r['label'], 'type' => 'number', 'unit' => $r['unit'], 'step' => 1, 'custom' => true];
    return $o;
}
function tracker_flat(): array { $o = []; foreach (tracker_catalog() as $g) foreach ($g as $k => $v) $o[$k] = $v; return $o + tracker_custom_defs(); }
function tracker_enabled(int $uid): array {
    $q = db()->prepare('SELECT tkey FROM tracker_on WHERE user_id = ?'); $q->execute([$uid]);
    $on = $q->fetchAll(PDO::FETCH_COLUMN);
    if (water_on($uid)) $on[] = 'water';
    return $on;
}
/* Enabled trackers (water excluded: it has its own card) for one day, in catalog order, with today's values */
function trackers_for_day(int $uid, string $day): array {
    $on = array_flip(tracker_enabled($uid)); $flat = tracker_flat(); $goals = tracker_goals($uid);
    $vals = []; $q = db()->prepare('SELECT tkey, value FROM tracker_log WHERE user_id = ? AND day = ?'); $q->execute([$uid, $day]);
    foreach ($q->fetchAll() as $r) $vals[$r['tkey']] = (float)$r['value'];
    $notes = []; $q = db()->prepare('SELECT id, tkey, text, created_at FROM tracker_notes WHERE user_id = ? AND day = ? ORDER BY id'); $q->execute([$uid, $day]);
    foreach ($q->fetchAll() as $r) $notes[$r['tkey']][] = $r;
    $out = [];
    foreach ($flat as $k => $t) {
        if ($k === 'water' || !isset($on[$k])) continue;
        $t['key'] = $k; $t['value'] = $vals[$k] ?? null; $t['notes'] = $notes[$k] ?? [];
        $t['goal'] = (in_array($t['type'], ['count', 'number'], true) && isset($goals[$k])) ? $goals[$k] : null;
        $out[] = $t;
    }
    return $out;
}
function fmt_num(float $v): string { return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'); }



/* ---------- goals on trackers: a target broken into milestones, each shown as an icon bullet ---------- */
/** Icons a person can pick for the bullets. 'glass' is drawn as a glass that fills; the rest are symbols that light up. */
function tracker_icons(): array {
    return ['glass' => "\u{1F95B}", 'drop' => "\u{1F4A7}", 'cup' => "\u{2615}", 'apple' => "\u{1F34E}", 'walk' => "\u{1F463}", 'run' => "\u{1F3C3}", 'pill' => "\u{1F48A}",
            'book' => "\u{1F4D6}", 'heart' => "\u{2764}\u{FE0F}", 'star' => "\u{2B50}", 'check' => "\u{2705}", 'moon' => "\u{1F319}", 'smile' => "\u{1F60A}", 'poop' => "\u{1F4A9}"];
}
/** Suggested goal for a tracker that has none saved: water is 64 oz in 8 oz glasses; others have no goal until the person sets one. */
function tracker_goal_default(string $k): ?array {
    return $k === 'water' ? ['target' => 64.0, 'per' => 8.0, 'icon' => 'glass'] : null;
}
/** The person's goals as tkey => [target, per, icon], with defaults filled in. */
function tracker_goals(int $uid): array {
    $out = []; $q = db()->prepare('SELECT tkey, target, per, icon FROM tracker_goal WHERE user_id = ?'); $q->execute([$uid]);
    foreach ($q->fetchAll() as $r) $out[$r['tkey']] = ['target' => (float)$r['target'], 'per' => (float)$r['per'], 'icon' => (string)$r['icon']];
    if (!isset($out['water'])) $out['water'] = tracker_goal_default('water');
    return $out;
}
/** How many bullets a goal makes: target divided by milestone size, rounded up, 1 to 24. */
function tracker_bullets(array $g): int { return (int)max(1, min(24, ceil($g['target'] / max(0.0001, $g['per'])))); }
/** True when every goal the person has set on an enabled tracker is reached for that day (used with "all goals done"). */
function tracker_goals_done(int $uid, string $day): bool {
    $on = array_flip(tracker_enabled($uid)); $goals = tracker_goals($uid); $flat = tracker_flat();
    $vals = []; $q = db()->prepare('SELECT tkey, value FROM tracker_log WHERE user_id = ? AND day = ?'); $q->execute([$uid, $day]);
    foreach ($q->fetchAll() as $r) $vals[$r['tkey']] = (float)$r['value'];
    foreach ($goals as $k => $g) {
        if ($k === 'water' || !isset($on[$k]) || !isset($flat[$k]) || !in_array($flat[$k]['type'], ['count', 'number'], true)) continue;
        if (($vals[$k] ?? 0) < $g['target']) return false;
    }
    return true;
}

/** True when the person has a goal on any tracker they have turned on (so finishing it can count as "all goals done"). */
function tracker_has_goals(int $uid): bool {
    $on = array_flip(tracker_enabled($uid)); $flat = tracker_flat();
    foreach (tracker_goals($uid) as $k => $g) if ($k !== 'water' && isset($on[$k]) && isset($flat[$k]) && in_array($flat[$k]['type'], ['count', 'number'], true)) return true;
    return false;
}
