<?php
/* Import and export.
   Every importer turns its file into the same simple shape (a "manifest"), shown to the person as a preview,
   then saved by one shared function. Adding an app later means writing one reader. */

const TRANSFER_MAX_ENTRIES = 30000;
const TRANSFER_MAX_PHOTOS = 6000;

/* ---------- the list of apps ---------- */
function transfer_sources(): array {
    return [
        'moriwella' => ['name' => 'Moriwella archive', 'ready' => true, 'accept' => '.zip',
            'help' => 'The ZIP you get from Export on this page. Good for restoring a backup or moving between accounts.'],
        'daylio'    => ['name' => 'Daylio', 'ready' => false, 'accept' => '.csv', 'help' => 'In Daylio: More, Export Entries, CSV (table).'],
        'journey'   => ['name' => 'Journey', 'ready' => false, 'accept' => '.zip', 'help' => 'Journey can archive your entries to a ZIP file.'],
        'diaro'     => ['name' => 'Diaro', 'ready' => false, 'accept' => '.zip', 'help' => 'In Diaro, make a backup and use the ZIP.'],
        'evernote'  => ['name' => 'Evernote', 'ready' => false, 'accept' => '.enex', 'help' => 'In the Evernote desktop app, export a notebook as ENEX.'],
        'dayone'    => ['name' => 'Day One', 'ready' => false, 'accept' => '.zip', 'help' => 'In Day One, export your journal as JSON with photos.'],
        'apple'     => ['name' => 'Apple Journal', 'ready' => false, 'accept' => '.zip', 'help' => 'In Settings, Apps, Journal, choose Export All Journal Entries.'],
    ];
}

function transfer_tmp(int $uid, string $token = ''): string {
    $d = data_dir() . '/imports/' . $uid . ($token !== '' ? '/' . $token : '');
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}
function transfer_rmdir(string $d): void {
    if (!is_dir($d)) return;
    foreach (scandir($d) ?: [] as $f) { if ($f === '.' || $f === '..') continue; $p = $d . '/' . $f; is_dir($p) ? transfer_rmdir($p) : @unlink($p); }
    @rmdir($d);
}
function transfer_sweep(int $uid): void {
    $root = transfer_tmp($uid);
    foreach (scandir($root) ?: [] as $f) { if ($f === '.' || $f === '..') continue; if (@filemtime($root . '/' . $f) < time() - 86400) transfer_rmdir($root . '/' . $f); }
}
function transfer_safe_name(string $n): string {
    $n = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($n)); return trim((string)$n, '._') !== '' ? mb_substr($n, 0, 80) : 'file';
}

/* ---------- export: Moriwella archive ---------- */
function transfer_export_moriwella(int $uid, array $user): string {
    $zipPath = transfer_tmp($uid) . '/export-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not start the archive.');
    $q = db()->prepare('SELECT e.*, ev.title AS event_title FROM entries e LEFT JOIN events ev ON ev.id = e.event_id WHERE e.user_id = ? ORDER BY e.entry_date, e.id');
    $q->execute([$uid]); $rows = $q->fetchAll();
    $photos = photos_for_entries(array_column($rows, 'id'));
    $out = []; $days = []; $nPhotos = 0;
    foreach ($rows as $r) {
        $parts = body_parts($r['body'], (int)$r['created_at']);
        $pj = [];
        foreach ($photos[$r['id']] ?? [] as $p) {
            $src = photo_path($p, 'orig'); if (!$src) $src = photo_path($p, 'web'); if (!$src) continue;
            $name = 'photos/' . $p['id'] . '_' . transfer_safe_name($p['orig_name'] !== '' ? $p['orig_name'] : ('photo.' . $p['ext']));
            $zip->addFile($src, $name); $pj[] = ['file' => $name, 'name' => $p['orig_name']]; $nPhotos++;
        }
        $out[] = ['date' => $r['entry_date'], 'created' => (int)$r['created_at'], 'title' => $r['title'],
                  'paragraphs' => array_map(fn($b) => ['time' => (int)$b['ts'], 'text' => $b['text']], $parts),
                  'experience' => $r['event_title'], 'photos' => $pj];
        $md = ($r['title'] !== '' ? '# ' . $r['title'] . "\n\n" : '');
        if ($r['event_title']) $md .= '*From the experience: ' . $r['event_title'] . "*\n\n";
        foreach ($parts as $b) { if ($b['ts']) $md .= '*' . date('g:i a', $b['ts']) . "*\n\n"; $md .= $b['text'] . "\n\n"; }
        foreach ($pj as $ph) $md .= '![' . $ph['name'] . '](../../' . $ph['file'] . ")\n\n";
        $days[$r['entry_date']][] = rtrim($md);
    }
    foreach ($days as $d => $chunks) $zip->addFromString('journal/' . substr($d, 0, 4) . '/' . $d . '.md', implode("\n\n---\n\n", $chunks) . "\n");
    $zip->addFromString('moriwella.json', json_encode(['format' => 'moriwella-archive', 'version' => 1, 'exported' => gmdate('c'), 'name' => $user['name'], 'timezone' => date_default_timezone_get(), 'entries' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $zip->addFromString('README.txt', "Your Moriwella journal\n\nThe journal folder has one readable Markdown file for each day.\nThe photos folder has your original photos.\nmoriwella.json holds everything with exact dates and times; Moriwella can import this ZIP again.\n");
    $zip->close();
    return $zipPath;
}

/* ---------- readers: each returns a manifest ---------- */
/* manifest = ['source'=>, 'entries'=>[ ['date','ts','title','paragraphs'=>[['ts','text']],'photos'=>[['file','name']],'key'] ] ] ; photo files live in the work folder */
function transfer_open_zip(string $file): ZipArchive {
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) throw new RuntimeException('That file does not look like a ZIP.');
    $total = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) { $total += (int)($zip->statIndex($i)['size'] ?? 0); }
    if ($total > 4 * 1024 * 1024 * 1024) throw new RuntimeException('That archive is larger than we can read here.');
    return $zip;
}
function transfer_read_moriwella(string $file, string $work): array {
    $zip = transfer_open_zip($file);
    $raw = $zip->getFromName('moriwella.json');
    $j = $raw === false ? null : json_decode($raw, true);
    if (!is_array($j) || ($j['format'] ?? '') !== 'moriwella-archive') throw new RuntimeException('This is not a Moriwella archive. It should contain a file called moriwella.json.');
    $entries = []; $nPhotos = 0; $n = 0;
    foreach (($j['entries'] ?? []) as $e) {
        if (++$n > TRANSFER_MAX_ENTRIES) throw new RuntimeException('That archive has more entries than we can import at once.');
        $date = (string)($e['date'] ?? ''); if (!valid_date($date)) continue;
        $paras = [];
        foreach (($e['paragraphs'] ?? []) as $p) { $t = trim((string)($p['text'] ?? '')); if ($t !== '') $paras[] = ['ts' => (int)($p['time'] ?? 0), 'text' => $t]; }
        $photos = [];
        foreach (($e['photos'] ?? []) as $ph) {
            $name = (string)($ph['file'] ?? ''); if ($name === '' || strpos($name, 'photos/') !== 0 || strpos($name, '..') !== false) continue;
            if (++$nPhotos > TRANSFER_MAX_PHOTOS) break;
            $data = $zip->getFromName($name); if ($data === false) continue;
            $local = 'p' . $nPhotos . '_' . transfer_safe_name(basename($name));
            file_put_contents($work . '/' . $local, $data); unset($data);
            $photos[] = ['file' => $local, 'name' => (string)($ph['name'] ?? '')];
        }
        $title = mb_substr(trim((string)($e['title'] ?? '')), 0, 200);
        if (!$paras && !$photos && $title === '') continue;
        $ts = (int)($e['created'] ?? 0);
        $entries[] = ['date' => $date, 'ts' => $ts, 'title' => $title, 'paragraphs' => $paras, 'photos' => $photos,
                      'key' => 'mw:' . sha1($date . '|' . $ts . '|' . $title . '|' . implode("\n", array_column($paras, 'text')))];
    }
    $zip->close();
    return ['source' => 'moriwella', 'entries' => $entries];
}
function transfer_read(string $source, string $file, string $work): array {
    switch ($source) {
        case 'moriwella': return transfer_read_moriwella($file, $work);
    }
    throw new RuntimeException('That app is not supported yet.');
}

/* ---------- saving ---------- */
function transfer_commit(int $uid, array $manifest, string $work): array {
    $batch = bin2hex(random_bytes(6)); $nE = 0; $nP = 0; $skipped = 0; $photoErrs = 0;
    $has = db()->prepare('SELECT 1 FROM entries WHERE user_id = ? AND source_key = ?');
    $ins = db()->prepare('INSERT INTO entries (user_id, entry_date, title, body, created_at, updated_at, import_batch, source_key) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($manifest['entries'] as $e) {
        $has->execute([$uid, $e['key']]); if ($has->fetch()) { $skipped++; continue; }
        $parts = [];
        foreach ($e['paragraphs'] as $p) $parts[] = stamp_text((int)$p['ts'], $p['text']);
        $ts = $e['ts'] ?: (strtotime($e['date'] . ' 12:00:00') ?: time());
        $ins->execute([$uid, $e['date'], $e['title'], mb_substr(implode("\n\n", $parts), 0, 50000), $ts, $ts, $batch, $e['key']]);
        $id = (int)db()->lastInsertId(); $nE++;
        foreach ($e['photos'] as $ph) {
            $path = $work . '/' . $ph['file']; if (!is_file($path)) continue;
            $err = save_photo($id, $uid, ['name' => $ph['name'] !== '' ? $ph['name'] : $ph['file'], 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path), '_copy' => true]);
            $err ? $photoErrs++ : $nP++;
        }
    }
    if ($nE > 0) db()->prepare('INSERT INTO imports (user_id, batch, source, entries, photos, created_at) VALUES (?,?,?,?,?,?)')->execute([$uid, $batch, $manifest['source'], $nE, $nP, time()]);
    return ['entries' => $nE, 'photos' => $nP, 'skipped' => $skipped, 'photoErrors' => $photoErrs];
}

/* ---------- pages ---------- */
if ($path === '/transfer') {
    transfer_sweep($uid);
    $q = db()->prepare('SELECT * FROM imports WHERE user_id = ? ORDER BY id DESC LIMIT 10'); $q->execute([$uid]);
    $c = db()->prepare('SELECT COUNT(*) FROM entries WHERE user_id = ?'); $c->execute([$uid]);
    render('transfer', ['sources' => transfer_sources(), 'imports' => $q->fetchAll(), 'entryCount' => (int)$c->fetchColumn(), 'maxUpload' => ini_get('upload_max_filesize')], 'Import and export');
    exit;
}
if ($path === '/transfer/export' && $post) {
    @set_time_limit(0);
    try { $zip = transfer_export_moriwella($uid, $user); }
    catch (Throwable $e) { flash('err', 'Sorry, the export could not be made. Please try again.'); redirect(url('/transfer')); }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="moriwella-journal-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($zip)); header('Cache-Control: private, no-store');
    readfile($zip); @unlink($zip); exit;
}
if ($path === '/transfer/upload' && $post) {
    @set_time_limit(0);
    $src = (string)($_POST['source'] ?? ''); $S = transfer_sources();
    if (!isset($S[$src]) || !$S[$src]['ready']) { flash('err', 'Choose which app your file came from.'); redirect(url('/transfer') . '#import'); }
    $f = $_FILES['file'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $big = $f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        flash('err', $big ? 'That file is larger than this server accepts (' . ini_get('upload_max_filesize') . ').' : 'Choose the file to bring in.'); redirect(url('/transfer') . '#import');
    }
    $token = bin2hex(random_bytes(8)); $work = transfer_tmp($uid, $token);
    try {
        $manifest = transfer_read($src, $f['tmp_name'], $work);
        if (!$manifest['entries']) throw new RuntimeException('We did not find any entries in that file.');
        file_put_contents($work . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE));
    } catch (Throwable $e) {
        transfer_rmdir($work); flash('err', $e instanceof RuntimeException ? $e->getMessage() : 'We could not read that file.'); redirect(url('/transfer') . '#import');
    }
    redirect(url('/transfer/preview/' . $token));
}
if (preg_match('#^/transfer/(preview|import|cancel)/([a-f0-9]{16})$#', $path, $mm)) {
    $work = transfer_tmp($uid, $mm[2]);
    $manifest = is_file($work . '/manifest.json') ? json_decode((string)file_get_contents($work . '/manifest.json'), true) : null;
    if (!is_array($manifest)) { flash('err', 'That import has expired. Please upload the file again.'); redirect(url('/transfer')); }
    if ($mm[1] === 'cancel' && $post) { transfer_rmdir($work); flash('ok', 'Import cancelled. Nothing was added.'); redirect(url('/transfer')); }
    if ($mm[1] === 'import' && $post) {
        @set_time_limit(0);
        db()->beginTransaction();
        try { $r = transfer_commit($uid, $manifest, $work); db()->commit(); }
        catch (Throwable $e) { db()->rollBack(); flash('err', 'The import stopped partway and nothing was added. Please try again.'); redirect(url('/transfer/preview/' . $mm[2])); }
        transfer_rmdir($work);
        $msg = $r['entries'] ? 'Imported ' . $r['entries'] . ($r['entries'] === 1 ? ' entry' : ' entries') . ($r['photos'] ? ' and ' . $r['photos'] . ' photos' : '') . '.' : 'Nothing new to add. Everything in that file was already here.';
        if ($r['skipped'] && $r['entries']) $msg .= ' ' . $r['skipped'] . ' already here, skipped.';
        if ($r['photoErrors']) $msg .= ' ' . $r['photoErrors'] . ' photos could not be read.';
        flash('ok', $msg); redirect(url('/transfer'));
    }
    $has = db()->prepare('SELECT 1 FROM entries WHERE user_id = ? AND source_key = ?');
    $dupes = 0; $photos = 0; $dates = [];
    foreach ($manifest['entries'] as $e) { $has->execute([$uid, $e['key']]); if ($has->fetch()) $dupes++; $photos += count($e['photos']); $dates[] = $e['date']; }
    sort($dates);
    render('transfer_preview', ['token' => $mm[2], 'source' => transfer_sources()[$manifest['source']]['name'] ?? $manifest['source'], 'entries' => $manifest['entries'], 'photos' => $photos, 'dupes' => $dupes, 'from' => $dates[0] ?? '', 'to' => end($dates) ?: ''], 'Check before importing');
    exit;
}
if (preg_match('#^/transfer/undo/([a-f0-9]{12})$#', $path, $mm) && $post) {
    $q = db()->prepare('SELECT id FROM imports WHERE batch = ? AND user_id = ?'); $q->execute([$mm[1], $uid]);
    if ($q->fetch()) {
        $es = db()->prepare('SELECT id FROM entries WHERE user_id = ? AND import_batch = ?'); $es->execute([$uid, $mm[1]]);
        foreach ($es->fetchAll(PDO::FETCH_COLUMN) as $eid) { delete_entry_photos((int)$eid, $uid); db()->prepare('DELETE FROM entries WHERE id = ? AND user_id = ?')->execute([$eid, $uid]); }
        db()->prepare('DELETE FROM imports WHERE batch = ? AND user_id = ?')->execute([$mm[1], $uid]);
        flash('ok', 'That import was undone.');
    }
    redirect(url('/transfer'));
}
