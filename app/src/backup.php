<?php
declare(strict_types=1);
/* Full site backup: the whole private data folder (database, photos, avatars, movies, translations, passwords, keys and settings)
   plus every website file including its config. One archive per backup, kept in the private data folder (outside the website), newest 10 only.   Copyright (c) JSW Software Designs */

const BACKUP_KEEP = 10;

function backup_dir(): string {
    $d = data_dir() . '/backups';
    if (!is_dir($d)) { @mkdir($d, 0700, true); }
    return $d;
}
function backup_name_ok(string $n): bool { return (bool)preg_match('/^moriwella-backup-\d{8}-\d{6}\.zip$/', $n); }
function backup_size(int $b): string {
    if ($b >= 1073741824) return number_format($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576) return number_format($b / 1048576, 1) . ' MB';
    return max(1, (int)round($b / 1024)) . ' KB';
}

function backup_status(): array {
    $f = backup_dir() . '/.status.json';
    $s = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    if (!is_array($s)) return ['state' => 'idle'];
    if (($s['state'] ?? '') === 'running' && time() - (int)($s['updated'] ?? 0) > 1500) { $s['state'] = 'error'; $s['message'] = 'The last backup stopped without finishing. Reset it and start again.'; }
    return $s;
}
function backup_set(array $s): void {
    $s['updated'] = time();
    @file_put_contents(backup_dir() . '/.status.json', json_encode($s), LOCK_EX);
}

function backup_list(): array {
    $out = [];
    foreach (glob(backup_dir() . '/moriwella-backup-*.zip') ?: [] as $f) {
        $n = basename($f); if (!backup_name_ok($n)) continue;
        $out[] = ['name' => $n, 'size' => (int)filesize($f), 'time' => (int)filemtime($f)];
    }
    usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
    return $out;
}
function backup_prune(): void {
    foreach (array_slice(backup_list(), BACKUP_KEEP) as $old) @unlink(backup_dir() . '/' . $old['name']);
}

/** Start a backup in the background so a big photo library cannot time the page out. */
function backup_start(): ?string {
    $st = backup_status();
    if (($st['state'] ?? '') === 'running') return 'A backup is already running.';
    if (!function_exists('exec')) return 'This server does not allow background jobs. Use the cron command below instead.';
    $php = is_executable('/usr/local/bin/php') ? '/usr/local/bin/php' : 'php';
    backup_set(['state' => 'running', 'started' => time(), 'message' => 'Starting']);
    $cmd = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/backup.php') . ' >/dev/null 2>&1 &';
    @exec($cmd);
    return null;
}

/** Make one archive. Returns its file name. */
function backup_run(): string {
    @set_time_limit(0); @ignore_user_abort(true);
    $dir = backup_dir(); $data = data_dir(); $stamp = date('Ymd-His');
    $started = (int)(backup_status()['started'] ?? time());
    $say = function (string $m) use ($started) { backup_set(['state' => 'running', 'started' => $started, 'message' => $m]); };
    if (!class_exists('ZipArchive')) throw new RuntimeException('This server cannot make zip files.');

    $say('Copying the database');
    $snap = $dir . '/.snapshot-' . $stamp . '.sqlite';
    try { db()->exec('VACUUM INTO ' . db()->quote($snap)); }
    catch (Throwable $e) { if (!@copy($data . '/moriwella.sqlite', $snap)) throw new RuntimeException('Could not copy the database.'); }

    $skipTop = ['backups', 'sessions', 'imports', 'moriwella.sqlite', 'moriwella.sqlite-wal', 'moriwella.sqlite-shm'];
    $part = $dir . '/moriwella-backup-' . $stamp . '.zip.part';
    $zip = new ZipArchive();
    if ($zip->open($part, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { @unlink($snap); throw new RuntimeException('Could not start the archive.'); }
    $zip->addFile($snap, 'data/moriwella.sqlite');

    $say('Collecting the data folder and website files');
    $n = 0; $bytes = 0; $store = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'mp4', 'mov', 'mp3', 'zip'];
    $add = function (string $path, string $rel) use ($zip, $store, &$n, &$bytes) {
        $zip->addFile($path, $rel);
        if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $store, true)) $zip->setCompressionName($rel, ZipArchive::CM_STORE);
        $n++; $bytes += (int)@filesize($path);
    };
    foreach (scandir($data) ?: [] as $top) {
        if ($top === '.' || $top === '..' || in_array($top, $skipTop, true) || substr($top, -5) === '.lock') continue;
        $p = $data . '/' . $top;
        if (is_file($p)) { $add($p, 'data/' . $top); continue; }
        if (!is_dir($p)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if ($f->isFile()) $add($f->getPathname(), 'data/' . $top . '/' . substr($f->getPathname(), strlen($p) + 1));
    }
    $site = realpath(dirname(__DIR__, 2));
    if ($site) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $rel = substr($f->getPathname(), strlen($site) + 1);
            if (preg_match('#(^|/)(error_log|\.git)(/|$)#', $rel)) continue;
            $add($f->getPathname(), 'site/' . $rel);
        }
    }
    $zip->addFromString('RESTORE.txt',
        "Moriwella full site backup, made " . date('c') . "\n\n"
        . "data/   the private data folder (moriwella_data): the database (moriwella.sqlite), photos, avatars, movies, translations,\n"
        . "        and the files holding passwords, API keys and payment settings\n"
        . "site/   every website file, including app/src/config.php\n\n"
        . "THIS ARCHIVE CONTAINS PASSWORDS AND KEYS. Keep it private and never share or email it.\n\n"
        . "To rebuild after a crash: put the site/ files in the website folder (moriwella.com) and the data/ files in the moriwella_data folder next to it,\n"
        . "then check the folder permissions (data folder 0700). Nothing else needs to be re-entered.\n");
    $zip->addFromString('manifest.json', json_encode(['format' => 'moriwella-full-backup', 'version' => 2, 'created' => gmdate('c'), 'files' => $n, 'bytes' => $bytes]));

    $say('Writing the archive (' . $n . ' files)');
    if (!$zip->close()) { @unlink($snap); @unlink($part); throw new RuntimeException('Could not finish the archive. The disk may be full.'); }
    @unlink($snap);
    $name = 'moriwella-backup-' . $stamp . '.zip';
    if (!@rename($part, $dir . '/' . $name)) { @unlink($part); throw new RuntimeException('Could not save the archive.'); }
    @chmod($dir . '/' . $name, 0600);
    backup_prune();
    backup_set(['state' => 'done', 'started' => $started, 'finished' => time(), 'file' => $name, 'size' => (int)filesize($dir . '/' . $name), 'files' => $n, 'message' => 'Finished']);
    return $name;
}
