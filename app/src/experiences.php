<?php
/* Experiences: special events and trips built from a template. Entries made here are normal journal entries tied to an event. */

function event_for_user(int $id, int $uid): ?array {
    $q = db()->prepare('SELECT * FROM events WHERE id = ? AND user_id = ?'); $q->execute([$id, $uid]);
    return $q->fetch() ?: null;
}
function event_sections(int $eventId): array {
    $q = db()->prepare('SELECT * FROM event_sections WHERE event_id = ? ORDER BY position, id'); $q->execute([$eventId]);
    return $q->fetchAll();
}
function my_templates(int $uid): array {
    $q = db()->prepare('SELECT * FROM event_templates WHERE user_id = ? ORDER BY name'); $q->execute([$uid]);
    $out = [];
    foreach ($q->fetchAll() as $r) { $r['sections'] = json_decode($r['sections'], true) ?: []; $out[] = $r; }
    return $out;
}
/* Resolve "starter:cruise" or "mine:12" to [name, sections] */
function resolve_template(string $key, int $uid): ?array {
    if (strpos($key, 'starter:') === 0) {
        $all = starter_templates(); $k = substr($key, 8);
        return isset($all[$k]) ? ['name' => $all[$k]['name'], 'sections' => $all[$k]['sections'], 'key' => $k] : null;
    }
    if (strpos($key, 'mine:') === 0) {
        $q = db()->prepare('SELECT * FROM event_templates WHERE id = ? AND user_id = ?'); $q->execute([(int)substr($key, 5), $uid]);
        $r = $q->fetch();
        return $r ? ['name' => $r['name'], 'sections' => json_decode($r['sections'], true) ?: [], 'key' => 'custom'] : null;
    }
    return null;
}
function days_between(string $a, string $b): int { return (int)round((strtotime($b) - strtotime($a)) / 86400); }

if ($path === '/experiences') {
    $q = db()->prepare('SELECT e.*, (SELECT COUNT(*) FROM entries n WHERE n.event_id = e.id) entry_count FROM events e WHERE e.user_id = ? ORDER BY e.start_date DESC');
    $q->execute([$uid]);
    $today = date('Y-m-d'); $now = $soon = $past = [];
    foreach ($q->fetchAll() as $ev) {
        if ($ev['end_date'] < $today) $past[] = $ev; elseif ($ev['start_date'] > $today) $soon[] = $ev; else $now[] = $ev;
    }
    $soon = array_reverse($soon);
    render('experiences', ['now' => $now, 'soon' => $soon, 'past' => $past], 'Experiences');
    exit;
}

if ($path === '/experiences/new') {
    $mine = my_templates($uid);
    $key = (string)($_GET['t'] ?? $_POST['t'] ?? '');
    $tpl = $key !== '' ? resolve_template($key, $uid) : null;
    if ($post) {
        if (!$tpl) { flash('err', 'Pick a template first.'); redirect(url('/experiences/new')); }
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 150);
        $start = (string)($_POST['start_date'] ?? ''); $end = (string)($_POST['end_date'] ?? '');
        $loc = mb_substr(trim((string)($_POST['location'] ?? '')), 0, 150);
        if ($end === '' && valid_date($start)) $end = $start;
        $err = '';
        if ($title === '') $err = 'Give it a name.';
        elseif (!valid_date($start) || !valid_date($end)) $err = 'Pick a start and end date.';
        elseif ($end < $start) $err = 'The end date can\'t be before the start date.';
        elseif (days_between($start, $end) > 365) $err = 'An experience can run up to a year.';
        if ($err) { flash('err', $err); redirect(url('/experiences/new') . '?t=' . urlencode($key)); }
        db()->beginTransaction();
        db()->prepare('INSERT INTO events (user_id, template_key, title, start_date, end_date, location, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $tpl['key'], $title, $start, $end, $loc, time()]);
        $eid = (int)db()->lastInsertId();
        foreach ($tpl['sections'] as $i => $sec) {
            db()->prepare('INSERT INTO event_sections (event_id, name, prompt, position) VALUES (?, ?, ?, ?)')
                ->execute([$eid, mb_substr((string)$sec['name'], 0, 80), mb_substr((string)($sec['prompt'] ?? ''), 0, 200), $i]);
        }
        db()->commit();
        redirect(url('/experiences/' . $eid));
    }
    render('experience_new', ['starters' => starter_templates(), 'mine' => $mine, 'tpl' => $tpl, 'key' => $key], 'Start an experience');
    exit;
}

if (preg_match('#^/experiences/(\d+)(?:/([a-z-]+)(?:/(\d+))?(?:/([a-z]+))?)?$#', $path, $mm)) {
    $ev = event_for_user((int)$mm[1], $uid);
    if (!$ev) { http_response_code(404); render('message', ['heading' => 'Experience not found', 'text' => 'It may have been deleted.'], 'Not found'); exit; }
    $act = $mm[2] ?? ''; $back = url('/experiences/' . $ev['id']);
    if ($post) {
        if ($act === 'update') {
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 150);
            $start = (string)($_POST['start_date'] ?? ''); $end = (string)($_POST['end_date'] ?? '');
            $loc = mb_substr(trim((string)($_POST['location'] ?? '')), 0, 150);
            if ($title === '' || !valid_date($start) || !valid_date($end) || $end < $start || days_between($start, $end) > 365) { flash('err', 'Check the name and dates.'); redirect($back); }
            db()->prepare('UPDATE events SET title = ?, start_date = ?, end_date = ?, location = ? WHERE id = ? AND user_id = ?')->execute([$title, $start, $end, $loc, $ev['id'], $uid]);
            flash('ok', 'Saved.'); redirect($back);
        }
        if ($act === 'delete') {
            db()->prepare('UPDATE entries SET event_id = NULL, section_id = NULL WHERE event_id = ? AND user_id = ?')->execute([$ev['id'], $uid]);
            db()->prepare('DELETE FROM events WHERE id = ? AND user_id = ?')->execute([$ev['id'], $uid]);
            flash('ok', 'Experience removed. Your entries are still in your journal.'); redirect(url('/experiences'));
        }
        if ($act === 'sections') {
            $sub = $mm[3] ?? ''; /* /sections add */
            $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80);
            $c = db()->prepare('SELECT COUNT(*) FROM event_sections WHERE event_id = ?'); $c->execute([$ev['id']]);
            if ($name === '') flash('err', 'Give the section a name.');
            elseif ((int)$c->fetchColumn() >= 30) flash('err', 'That\'s plenty of sections.');
            else { db()->prepare('INSERT INTO event_sections (event_id, name, prompt, position) VALUES (?, ?, ?, ?)')->execute([$ev['id'], $name, mb_substr(trim((string)($_POST['prompt'] ?? '')), 0, 200), (int)$c->fetchColumn()]); flash('ok', 'Section added.'); }
            redirect($back . '#sections');
        }
        if ($act === 'section' && isset($mm[3])) {
            $sid = (int)$mm[3]; $what = $mm[4] ?? '';
            $own = db()->prepare('SELECT id FROM event_sections WHERE id = ? AND event_id = ?'); $own->execute([$sid, $ev['id']]);
            if ($own->fetch()) {
                if ($what === 'rename') {
                    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80);
                    if ($name !== '') db()->prepare('UPDATE event_sections SET name = ?, prompt = ? WHERE id = ?')->execute([$name, mb_substr(trim((string)($_POST['prompt'] ?? '')), 0, 200), $sid]);
                } elseif ($what === 'delete') {
                    db()->prepare('UPDATE entries SET section_id = NULL WHERE section_id = ? AND user_id = ?')->execute([$sid, $uid]);
                    db()->prepare('DELETE FROM event_sections WHERE id = ?')->execute([$sid]);
                }
            }
            redirect($back . '#sections');
        }
        if ($act === 'save-template') {
            $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80) ?: $ev['title'];
            $secs = array_map(fn($s) => ['name' => $s['name'], 'prompt' => $s['prompt']], event_sections((int)$ev['id']));
            db()->prepare('INSERT INTO event_templates (user_id, name, sections, created_at) VALUES (?, ?, ?, ?)')->execute([$uid, $name, json_encode($secs), time()]);
            flash('ok', 'Saved as your template "' . $name . '". You will find it when you start a new experience.'); redirect($back);
        }
        redirect($back);
    }
    if ($act === '') {
        $sections = event_sections((int)$ev['id']);
        $filter = (int)($_GET['s'] ?? 0);
        $sql = 'SELECT * FROM entries WHERE user_id = ? AND event_id = ?' . ($filter ? ' AND section_id = ?' : '') . ' ORDER BY entry_date, id';
        $q = db()->prepare($sql); $q->execute($filter ? [$uid, $ev['id'], $filter] : [$uid, $ev['id']]);
        $entries = $q->fetchAll();
        $ph = photos_for_entries(array_column($entries, 'id'));
        foreach ($entries as &$r) { $r['photos'] = $ph[$r['id']] ?? []; } unset($r);
        $today = date('Y-m-d');
        $addDate = $today < $ev['start_date'] ? $ev['start_date'] : ($today > $ev['end_date'] ? $ev['end_date'] : $today);
        $total = days_between($ev['start_date'], $ev['end_date']) + 1;
        render('experience', ['ev' => $ev, 'sections' => $sections, 'entries' => $entries, 'filter' => $filter, 'addDate' => $addDate, 'total' => $total,
            'dayNo' => ($today >= $ev['start_date'] && $today <= $ev['end_date']) ? days_between($ev['start_date'], $today) + 1 : 0,
            'upTo' => $today < $ev['start_date'] ? days_between($today, $ev['start_date']) : 0], $ev['title']);
        exit;
    }
}
