<?php
/* Demo accounts for testing. A seed ZIP holds seed.json plus each account's files. Demo accounts have no usable password:
   an administrator steps into them with "Act as". Each file goes through the same readers a real import uses. */

if ($path === '/admin/demo') {
    $admin = require_admin();
    $result = null;
    if ($post && isset($_POST['remove'])) {
        $q = db()->query("SELECT id FROM users WHERE plan = 'demo' AND role <> 'admin'"); $n = 0;
        foreach ($q->fetchAll() as $u) {
            $pq = db()->prepare('SELECT p.* FROM entry_photos p JOIN entries e ON e.id = p.entry_id WHERE e.user_id = ?'); $pq->execute([$u['id']]);
            foreach ($pq->fetchAll() as $p) { $p['user_id'] = $u['id']; delete_photo_files($p); }
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$u['id']]); $n++;
        }
        flash('ok', $n ? 'Removed ' . $n . ' demo ' . ($n === 1 ? 'account' : 'accounts') . ' and their photos.' : 'There were no demo accounts to remove.');
        redirect(url('/admin/demo'));
    }
    if ($post && isset($_POST['seed'])) {
        @set_time_limit(0); @ini_set('memory_limit', '512M');
        $f = $_FILES['seed'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { flash('err', 'Choose the seed ZIP first.'); redirect(url('/admin/demo')); }
        $zip = new ZipArchive();
        $raw = $zip->open($f['tmp_name']) === true ? $zip->getFromName('seed.json') : false;
        $seed = $raw === false ? null : json_decode($raw, true);
        if (!is_array($seed) || empty($seed['accounts'])) { flash('err', 'That does not look like a demo seed ZIP. It needs a seed.json file.'); redirect(url('/admin/demo')); }
        $result = [];
        foreach ($seed['accounts'] as $a) {
            $name = mb_substr(trim((string)($a['name'] ?? '')), 0, 100); $email = strtolower(trim((string)($a['email'] ?? '')));
            $row = ['name' => $name, 'email' => $email, 'note' => '', 'entries' => 0, 'photos' => 0, 'moods' => 0, 'problems' => []];
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $row['note'] = 'Skipped: needs a name and email.'; $result[] = $row; continue; }
            $dup = db()->prepare('SELECT id FROM users WHERE email = ?'); $dup->execute([$email]);
            if ($dup->fetch()) { $row['note'] = 'Skipped: this email already has an account. Remove demo accounts first to start over.'; $result[] = $row; continue; }
            db()->prepare("INSERT INTO users (email, name, role, plan, status, password_hash, created_at) VALUES (?, ?, 'member', 'demo', 'active', ?, ?)")
                ->execute([$email, $name, hash_pw(bin2hex(random_bytes(24))), time()]);
            $duid = (int)db()->lastInsertId(); mark_welcomed($duid);
            foreach (($a['files'] ?? []) as $file) {
                $src = (string)($file['source'] ?? ''); $inner = (string)($file['path'] ?? '');
                if (!isset(transfer_sources()[$src]) || $inner === '' || strpos($inner, '..') !== false) { $row['problems'][] = 'Unknown file entry.'; continue; }
                $data = $zip->getFromName($inner); if ($data === false) { $row['problems'][] = 'Missing ' . basename($inner); continue; }
                $token = bin2hex(random_bytes(8)); $work = transfer_tmp($duid, $token); $tmp = $work . '/source.bin'; file_put_contents($tmp, $data); unset($data);
                try {
                    $m = transfer_read($src, $tmp, $work); @unlink($tmp);
                    $r = transfer_commit($duid, $m, $work);
                    $row['entries'] += $r['entries']; $row['photos'] += $r['photos']; $row['moods'] += $r['moods'];
                    if (!empty($r['photoErrors'])) $row['problems'][] = $r['photoErrors'] . ' photos in ' . basename($inner) . ' could not be read.';
                } catch (Throwable $e) { $row['problems'][] = basename($inner) . ': ' . $e->getMessage(); }
                transfer_rmdir($work);
            }
            foreach (($a['experiences'] ?? []) as $ex) {
                $tpl = starter_templates()[(string)($ex['template'] ?? '')] ?? null;
                $title = mb_substr(trim((string)($ex['title'] ?? '')), 0, 150); $start = (string)($ex['start'] ?? ''); $end = (string)($ex['end'] ?? $start);
                if (!$tpl || $title === '' || !valid_date($start) || !valid_date($end)) { $row['problems'][] = 'An experience was skipped (needs template, title, dates).'; continue; }
                db()->prepare('INSERT INTO events (user_id, template_key, title, start_date, end_date, location, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$duid, (string)$ex['template'], $title, $start, $end, mb_substr((string)($ex['location'] ?? ''), 0, 150), time()]);
                $eid = (int)db()->lastInsertId(); $secIds = [];
                foreach ($tpl['sections'] as $i => $sec) { db()->prepare('INSERT INTO event_sections (event_id, name, prompt, position) VALUES (?, ?, ?, ?)')->execute([$eid, $sec['name'], $sec['prompt'], $i]); $secIds[$sec['name']] = (int)db()->lastInsertId(); }
                $work = transfer_tmp($duid, bin2hex(random_bytes(8)));
                foreach (($ex['entries'] ?? []) as $en) {
                    $d = (string)($en['date'] ?? ''); if (!valid_date($d)) continue;
                    $parts = []; $first = 0;
                    foreach (($en['paragraphs'] ?? []) as $pp) { $t = (int)($pp['time'] ?? 0); if (!$first && $t) $first = $t; $parts[] = stamp_text($t, (string)$pp['text']); }
                    $ts = $first ?: (strtotime($d . ' 12:00:00') ?: time());
                    db()->prepare('INSERT INTO entries (user_id, entry_date, title, body, created_at, updated_at, event_id, section_id) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$duid, $d, mb_substr((string)($en['title'] ?? ''), 0, 200), implode("\n\n", $parts), $ts, $ts, $eid, $secIds[(string)($en['section'] ?? '')] ?? null]);
                    $entryId = (int)db()->lastInsertId(); $row['entries']++;
                    foreach (($en['photos'] ?? []) as $ph) {
                        $inner = (string)($ph['path'] ?? ''); if ($inner === '' || strpos($inner, '..') !== false) continue;
                        $data = $zip->getFromName($inner); if ($data === false) continue;
                        $tmp = $work . '/' . bin2hex(random_bytes(4)) . '.jpg'; file_put_contents($tmp, $data); unset($data);
                        $err = save_photo($entryId, $duid, ['name' => (string)($ph['name'] ?? basename($inner)), 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), '_copy' => true]);
                        if ($err) $row['problems'][] = $err; else $row['photos']++;
                    }
                    if (!empty($en['moods'])) foreach ($en['moods'] as $mo) db()->prepare('INSERT INTO mood_log (user_id, day, ts, value) VALUES (?, ?, ?, ?)')->execute([$duid, $d, (int)$mo['time'], (int)$mo['value']]);
                }
                transfer_rmdir($work);
            }
            $result[] = $row;
        }
        $zip->close();
    }
    $demo = db()->query("SELECT u.id, u.name, u.email, (SELECT COUNT(*) FROM entries e WHERE e.user_id = u.id) AS entries, (SELECT COUNT(*) FROM entry_photos p JOIN entries e ON e.id = p.entry_id WHERE e.user_id = u.id) AS photos FROM users u WHERE u.plan = 'demo' AND u.role <> 'admin' ORDER BY u.name")->fetchAll();
    render('admin_demo', ['result' => $result, 'demo' => $demo, 'maxUpload' => ini_get('upload_max_filesize')], 'Demo accounts');
    exit;
}
