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
        'daylio'    => ['name' => 'Daylio', 'ready' => true, 'accept' => '.csv', 'help' => 'In Daylio, tap More, then Export Entries, then CSV (table). Notes become entries and moods go to Track.'],
        'journey'   => ['name' => 'Journey', 'ready' => true, 'accept' => '.zip', 'help' => 'In Journey, use Settings, Data and Cloud Services, Export/Backup to make a ZIP. Entries, photos, tags and dates come along.'],
        'diaro'     => ['name' => 'Diaro', 'ready' => false, 'accept' => '.zip', 'help' => 'In Diaro, make a backup and use the ZIP.'],
        'evernote'  => ['name' => 'Evernote', 'ready' => true, 'accept' => '.enex,.xml', 'help' => 'In the Evernote desktop app, select a notebook or notes, then File, Export, and choose ENEX. Titles, dates, tags and images come along.'],
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
function transfer_read_daylio(string $file, string $work): array {
    $h = @fopen($file, 'r'); if (!$h) throw new RuntimeException('That file could not be opened.');
    $first = fgets($h); if ($first === false) throw new RuntimeException('That file is empty.');
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
    $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    $cols = array_map(fn($c) => strtolower(trim((string)$c)), str_getcsv(trim($first), $delim));
    $idx = array_flip($cols);
    $hasDate = isset($idx['full_date']) || (isset($idx['year']) && isset($idx['date']));
    if (!$hasDate || !isset($idx['time']) || !isset($idx['mood'])) { fclose($h); throw new RuntimeException('This does not look like a Daylio CSV. Export with More, Export Entries, CSV (table).'); }
    rewind($h); fgets($h);
    $moodMap = ['rad' => 5, 'good' => 4, 'meh' => 3, 'bad' => 2, 'awful' => 1];
    $entries = []; $moodSum = []; $noteless = 0; $rows = 0;
    $get = function (array $r, string $k) use ($idx): string { return isset($idx[$k], $r[$idx[$k]]) ? trim((string)$r[$idx[$k]]) : ''; };
    while (($r = fgetcsv($h, 0, $delim)) !== false) {
        if (!$r || (count($r) === 1 && trim((string)$r[0]) === '')) continue;
        if (++$rows > TRANSFER_MAX_ENTRIES) { fclose($h); throw new RuntimeException('That file has more rows than we can import at once.'); }
        $time = $get($r, 'time');
        $ts = 0; $date = '';
        if (isset($idx['full_date']) && $get($r, 'full_date') !== '') $stamp = strtotime($get($r, 'full_date') . ' ' . $time);
        else $stamp = strtotime($get($r, 'date') . ', ' . $get($r, 'year') . ' ' . $time);
        if ($stamp === false || $stamp < 0) { $stamp = strtotime((isset($idx['full_date']) ? $get($r, 'full_date') : $get($r, 'date') . ', ' . $get($r, 'year'))); }
        if ($stamp === false || $stamp < 0) continue;
        $ts = (int)$stamp; $date = date('Y-m-d', $ts);
        $mood = strtolower($get($r, 'mood'));
        if (isset($moodMap[$mood])) { $moodSum[$date][] = $moodMap[$mood]; }
        $note = isset($idx['note']) ? str_replace(["\r\n", "\r"], "\n", $get($r, 'note')) : '';
        $title = mb_substr($get($r, 'note_title'), 0, 200);
        $acts = $get($r, 'activities');
        if ($note === '' && $title === '') { $noteless++; continue; }
        $paras = [];
        foreach (preg_split('/\n{2,}/', $note) as $i => $t) { $t = trim($t); if ($t !== '') $paras[] = ['ts' => $i === 0 ? $ts : 0, 'text' => $t]; }
        if (!$paras) $paras[] = ['ts' => $ts, 'text' => $title]; 
        if ($acts !== '') $paras[] = ['ts' => 0, 'text' => 'Activities: ' . implode(', ', array_map('trim', explode('|', $acts)))];
        $entries[] = ['date' => $date, 'ts' => $ts, 'title' => $title, 'paragraphs' => $paras, 'photos' => [], 'key' => 'dl:' . sha1($ts . '|' . $note . '|' . $title)];
    }
    fclose($h);
    $moods = [];
    foreach ($moodSum as $d => $vals) $moods[$d] = (int)round(array_sum($vals) / count($vals));
    return ['source' => 'daylio', 'entries' => $entries, 'moods' => $moods, 'noteless' => $noteless];
}
function transfer_html_to_text(string $html): string {
    $t = preg_replace('#<img\b[^>]*>#i', '', $html);
    $t = preg_replace('#<\s*br\s*/?>#i', "\n", $t);
    $t = preg_replace('#</(p|div|h[1-6]|blockquote)>#i', "\n\n", $t);
    $t = preg_replace('#<li\b[^>]*>#i', "\n- ", $t);
    $t = preg_replace('#</(ul|ol)>#i', "\n", $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $t);
    return trim(preg_replace("/\n{3,}/", "\n\n", $t));
}
function transfer_read_journey(string $file, string $work): array {
    $zip = transfer_open_zip($file);
    if ($zip->numFiles > 60000) throw new RuntimeException('That archive has more files than we can read at once.');
    $byBase = []; $jsons = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $st = $zip->statIndex($i); $n = $st['name'] ?? ''; if ($n === '' || substr($n, -1) === '/' || strpos($n, '__MACOSX') !== false) continue;
        $b = strtolower(basename($n));
        if (substr($b, -5) === '.json') { if (($st['size'] ?? 0) < 4 * 1024 * 1024) $jsons[] = $i; }
        else $byBase[$b] = $i;
    }
    $entries = []; $nPhotos = 0; $n = 0; $skipped = 0;
    foreach ($jsons as $i) {
        $j = json_decode((string)$zip->getFromIndex($i), true);
        if (!is_array($j) || !isset($j['date_journal']) || !array_key_exists('text', $j)) { $skipped++; continue; }
        if (++$n > TRANSFER_MAX_ENTRIES) { $zip->close(); throw new RuntimeException('That archive has more entries than we can import at once.'); }
        $ms = (float)$j['date_journal']; if ($ms <= 0) { $skipped++; continue; }
        $ts = (int)floor($ms / 1000);
        $tzName = (string)($j['timezone'] ?? ''); $date = '';
        try { $date = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone($tzName !== '' ? $tzName : date_default_timezone_get()))->format('Y-m-d'); }
        catch (Throwable $e) { $date = date('Y-m-d', $ts); }
        $text = (string)$j['text'];
        if (($j['type'] ?? 'html') === 'markdown') $text = trim(preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', str_replace(["\r\n", "\r"], "\n", $text)));
        else $text = transfer_html_to_text($text);
        $paras = [];
        foreach (preg_split('/\n{2,}/', $text) as $k => $t) { $t = trim($t); if ($t !== '') $paras[] = ['ts' => $k === 0 ? $ts : 0, 'text' => $t]; }
        $tags = array_values(array_filter(array_map(fn($t) => trim((string)$t), is_array($j['tags'] ?? null) ? $j['tags'] : [])));
        if ($tags && $paras) $paras[] = ['ts' => 0, 'text' => 'Tags: ' . implode(', ', $tags)];
        $photos = [];
        foreach ((is_array($j['photos'] ?? null) ? $j['photos'] : []) as $ph) {
            $name = is_string($ph) ? $ph : (string)($ph['filename'] ?? $ph['name'] ?? ''); $b = strtolower(basename($name));
            if ($b === '' || !isset($byBase[$b]) || $nPhotos >= TRANSFER_MAX_PHOTOS) continue;
            $data = $zip->getFromIndex($byBase[$b]); if ($data === false) continue;
            $nPhotos++; $local = 'p' . $nPhotos . '_' . transfer_safe_name($b);
            file_put_contents($work . '/' . $local, $data); unset($data);
            $photos[] = ['file' => $local, 'name' => basename($name)];
        }
        if (!$paras && !$photos) { $skipped++; continue; }
        $entries[] = ['date' => $date, 'ts' => $ts, 'title' => '', 'paragraphs' => $paras, 'photos' => $photos, 'key' => 'jn:' . sha1((string)($j['id'] ?? '') . '|' . $ms)];
    }
    $zip->close();
    if (!$entries) throw new RuntimeException('We did not find any Journey entries in that ZIP. It should hold one .json file per entry.');
    usort($entries, fn($a, $b) => $a['ts'] <=> $b['ts']);
    return ['source' => 'journey', 'entries' => $entries, 'skipped' => $skipped];
}
function transfer_enex_time(string $t): int {
    $d = DateTimeImmutable::createFromFormat('Ymd\THis\Z', trim($t), new DateTimeZone('UTC'));
    return $d ? $d->getTimestamp() : 0;
}
function transfer_read_evernote(string $file, string $work): array {
    $r = new XMLReader();
    if (!@$r->open($file, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) throw new RuntimeException('That file could not be opened.');
    $entries = []; $nPhotos = 0; $n = 0; $skipped = 0; $otherFiles = 0; $sawExport = false;
    libxml_use_internal_errors(true);
    while (@$r->read()) {
        if ($r->nodeType !== XMLReader::ELEMENT) continue;
        if ($r->name === 'en-export') { $sawExport = true; continue; }
        if ($r->name !== 'note') continue;
        $node = @$r->expand(); if (!$node) { $skipped++; continue; }
        $doc = new DOMDocument(); $doc->appendChild($doc->importNode($node, true));
        $x = simplexml_import_dom($doc); if (!$x) { $skipped++; continue; }
        if (++$n > TRANSFER_MAX_ENTRIES) { $r->close(); throw new RuntimeException('That file has more notes than we can import at once.'); }
        $title = mb_substr(trim((string)$x->title), 0, 200);
        $ts = transfer_enex_time((string)$x->created) ?: transfer_enex_time((string)$x->updated);
        if (!$ts) { $skipped++; continue; }
        $enml = (string)$x->content;
        $enml = preg_replace('#<en-todo\s+checked="true"\s*/?>#i', '[x] ', $enml);
        $enml = preg_replace('#<en-todo\b[^>]*/?>#i', '[ ] ', $enml);
        $enml = preg_replace('#<en-media\b[^>]*/?>#i', '', $enml);
        $enml = preg_replace('#<\?xml[^>]*\?>|<!DOCTYPE[^>]*>#i', '', $enml);
        $text = transfer_html_to_text($enml);
        $paras = [];
        foreach (preg_split('/\n{2,}/', $text) as $k => $t) { $t = trim($t); if ($t !== '') $paras[] = ['ts' => $k === 0 ? $ts : 0, 'text' => $t]; }
        $tags = []; foreach ($x->tag as $tg) { $tg = trim((string)$tg); if ($tg !== '') $tags[] = $tg; }
        if ($tags && $paras) $paras[] = ['ts' => 0, 'text' => 'Tags: ' . implode(', ', $tags)];
        $photos = [];
        foreach ($x->resource as $res) {
            $mime = strtolower(trim((string)$res->mime));
            if (strpos($mime, 'image/') !== 0) { $otherFiles++; continue; }
            if ($nPhotos >= TRANSFER_MAX_PHOTOS) continue;
            $bin = base64_decode(preg_replace('/\s+/', '', (string)$res->data), true); if ($bin === false || $bin === '') continue;
            $fname = trim((string)$res->{'resource-attributes'}->{'file-name'});
            $nPhotos++; $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? 'jpg';
            $local = 'p' . $nPhotos . '_' . transfer_safe_name($fname !== '' ? $fname : ('image.' . $ext));
            file_put_contents($work . '/' . $local, $bin); unset($bin);
            $photos[] = ['file' => $local, 'name' => $fname !== '' ? $fname : 'image.' . $ext];
        }
        if (!$paras && !$photos && $title === '') { $skipped++; continue; }
        $date = date('Y-m-d', $ts);
        $entries[] = ['date' => $date, 'ts' => $ts, 'title' => $title, 'paragraphs' => $paras, 'photos' => $photos, 'key' => 'en:' . sha1($title . '|' . $ts . '|' . $text)];
    }
    $r->close(); libxml_clear_errors();
    if (!$sawExport && !$entries) throw new RuntimeException('This does not look like an Evernote export. In Evernote, choose File, Export, and pick ENEX.');
    if (!$entries) throw new RuntimeException('We did not find any notes in that file.');
    usort($entries, fn($a, $b) => $a['ts'] <=> $b['ts']);
    return ['source' => 'evernote', 'entries' => $entries, 'skipped' => $skipped, 'otherFiles' => $otherFiles];
}
function transfer_read(string $source, string $file, string $work): array {
    switch ($source) {
        case 'moriwella': return transfer_read_moriwella($file, $work);
        case 'daylio': return transfer_read_daylio($file, $work);
        case 'journey': return transfer_read_journey($file, $work);
        case 'evernote': return transfer_read_evernote($file, $work);
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
    $nM = 0;
    if (!empty($manifest['moods'])) {
        $mi = db()->prepare("INSERT OR IGNORE INTO tracker_log (user_id, tkey, day, value, batch) VALUES (?, 'mood', ?, ?, ?)");
        foreach ($manifest['moods'] as $d => $v) { if (valid_date((string)$d) && $v >= 1 && $v <= 5) { $mi->execute([$uid, $d, $v, $batch]); $nM += $mi->rowCount(); } }
        if ($nM > 0) db()->prepare("INSERT OR IGNORE INTO tracker_on (user_id, tkey) VALUES (?, 'mood')")->execute([$uid]);
    }
    if ($nE > 0 || $nM > 0) db()->prepare('INSERT INTO imports (user_id, batch, source, entries, photos, moods, created_at) VALUES (?,?,?,?,?,?,?)')->execute([$uid, $batch, $manifest['source'], $nE, $nP, $nM, time()]);
    return ['entries' => $nE, 'photos' => $nP, 'moods' => $nM, 'skipped' => $skipped, 'photoErrors' => $photoErrs];
}

/* ---------- pages ---------- */
if ($path === '/loader-demo') { render('loader_demo', [], 'Loader preview'); exit; }
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
        if (!$manifest['entries'] && empty($manifest['moods'])) throw new RuntimeException('We did not find any entries in that file.');
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
        $bits = [];
        if ($r['entries']) $bits[] = $r['entries'] . ($r['entries'] === 1 ? ' entry' : ' entries');
        if ($r['photos']) $bits[] = $r['photos'] . ' photos';
        if ($r['moods']) $bits[] = $r['moods'] . ' days of mood';
        $msg = $bits ? 'Imported ' . implode(', ', $bits) . '.' : 'Nothing new to add. Everything in that file was already here.';
        if ($r['skipped'] && $r['entries']) $msg .= ' ' . $r['skipped'] . ' already here, skipped.';
        if ($r['photoErrors']) $msg .= ' ' . $r['photoErrors'] . ' photos could not be read.';
        flash('ok', $msg); redirect(url('/transfer'));
    }
    $has = db()->prepare('SELECT 1 FROM entries WHERE user_id = ? AND source_key = ?');
    $dupes = 0; $photos = 0; $dates = [];
    foreach ($manifest['entries'] as $e) { $has->execute([$uid, $e['key']]); if ($has->fetch()) $dupes++; $photos += count($e['photos']); $dates[] = $e['date']; }
    sort($dates);
    render('transfer_preview', ['token' => $mm[2], 'source' => transfer_sources()[$manifest['source']]['name'] ?? $manifest['source'], 'entries' => $manifest['entries'], 'photos' => $photos, 'moods' => count($manifest['moods'] ?? []), 'noteless' => (int)($manifest['noteless'] ?? 0), 'otherFiles' => (int)($manifest['otherFiles'] ?? 0), 'dupes' => $dupes, 'from' => $dates[0] ?? '', 'to' => end($dates) ?: ''], 'Check before importing');
    exit;
}
if (preg_match('#^/transfer/undo/([a-f0-9]{12})$#', $path, $mm) && $post) {
    $q = db()->prepare('SELECT id FROM imports WHERE batch = ? AND user_id = ?'); $q->execute([$mm[1], $uid]);
    if ($q->fetch()) {
        $es = db()->prepare('SELECT id FROM entries WHERE user_id = ? AND import_batch = ?'); $es->execute([$uid, $mm[1]]);
        foreach ($es->fetchAll(PDO::FETCH_COLUMN) as $eid) { delete_entry_photos((int)$eid, $uid); db()->prepare('DELETE FROM entries WHERE id = ? AND user_id = ?')->execute([$eid, $uid]); }
        db()->prepare('DELETE FROM tracker_log WHERE user_id = ? AND batch = ?')->execute([$uid, $mm[1]]);
        db()->prepare('DELETE FROM imports WHERE batch = ? AND user_id = ?')->execute([$mm[1], $uid]);
        flash('ok', 'That import was undone.');
    }
    redirect(url('/transfer'));
}
