<?php
/* Track: small things worth noticing each day. Quiet by design: they sit under the journal, never above it. */

function tracker_catalog(): array {
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
function tracker_flat(): array { $o = []; foreach (tracker_catalog() as $g) foreach ($g as $k => $v) $o[$k] = $v; return $o; }
function tracker_enabled(int $uid): array {
    $q = db()->prepare('SELECT tkey FROM tracker_on WHERE user_id = ?'); $q->execute([$uid]);
    $on = $q->fetchAll(PDO::FETCH_COLUMN);
    if (water_on($uid)) $on[] = 'water';
    return $on;
}
/* Enabled trackers (water excluded: it has its own card) for one day, in catalog order, with today's values */
function trackers_for_day(int $uid, string $day): array {
    $on = array_flip(tracker_enabled($uid)); $flat = tracker_flat();
    $vals = []; $q = db()->prepare('SELECT tkey, value FROM tracker_log WHERE user_id = ? AND day = ?'); $q->execute([$uid, $day]);
    foreach ($q->fetchAll() as $r) $vals[$r['tkey']] = (float)$r['value'];
    $notes = []; $q = db()->prepare('SELECT id, tkey, text, created_at FROM tracker_notes WHERE user_id = ? AND day = ? ORDER BY id'); $q->execute([$uid, $day]);
    foreach ($q->fetchAll() as $r) $notes[$r['tkey']][] = $r;
    $out = [];
    foreach ($flat as $k => $t) {
        if ($k === 'water' || !isset($on[$k])) continue;
        $t['key'] = $k; $t['value'] = $vals[$k] ?? null; $t['notes'] = $notes[$k] ?? [];
        $out[] = $t;
    }
    return $out;
}
function fmt_num(float $v): string { return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'); }

