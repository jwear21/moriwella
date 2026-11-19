<?php
declare(strict_types=1);

/* Photo storage. Originals are kept untouched in a private folder (needed for print quality).
   Smaller copies are made for screens. Nothing here is reachable from the web except through /photo/ with a sign-in check. */

const PHOTO_MAX_BYTES = 40 * 1024 * 1024;
const PHOTO_MAX_PIXELS = 50000000;
const PHOTO_MAX_PER_UPLOAD = 12;

function photo_dir(int $uid): string {
    $d = data_dir() . '/photos/' . $uid;
    if (!is_dir($d)) { @mkdir($d, 0700, true); }
    return $d;
}
function photo_path(array $p, string $size): ?string {
    $base = photo_dir((int)$p['user_id']) . '/' . $p['id'];
    $cands = $size === 'thumb' ? [$base . '_t.jpg', $base . '_w.jpg'] : ($size === 'web' ? [$base . '_w.jpg'] : []);
    foreach ($cands as $c) { if (is_file($c)) return $c; }
    $o = $base . '_o.' . $p['ext'];
    return is_file($o) ? $o : null;
}
function upload_error_text(array $f): string {
    $n = $f['name'] !== '' ? $f['name'] : 'A photo';
    switch ($f['error']) {
        case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: return $n . ' is larger than this server accepts (' . ini_get('upload_max_filesize') . ').';
        case UPLOAD_ERR_NO_FILE: return '';
        default: return $n . ' could not be uploaded. Please try again.';
    }
}
function normalize_files(string $key): array {
    if (empty($_FILES[$key]) || !is_array($_FILES[$key]['name'])) return [];
    $out = [];
    foreach ($_FILES[$key]['name'] as $i => $name) {
        $out[] = ['name' => (string)$name, 'tmp_name' => $_FILES[$key]['tmp_name'][$i], 'error' => (int)$_FILES[$key]['error'][$i], 'size' => (int)$_FILES[$key]['size'][$i]];
    }
    return $out;
}

function make_derivative(string $src, int $type, int $maxDim, string $dest, int $quality): ?array {
    if (!function_exists('imagecreatefromstring')) return null;
    if ($type === IMAGETYPE_WEBP && !function_exists('imagecreatefromwebp')) return null;
    $data = @file_get_contents($src);
    $img = $data === false ? false : @imagecreatefromstring($data);
    unset($data);
    if (!$img) return null;
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($src);
        $o = (int)($ex['Orientation'] ?? 1);
        $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($deg) { $r = @imagerotate($img, $deg, 0); if ($r) { imagedestroy($img); $img = $r; } }
    }
    $w = imagesx($img); $h = imagesy($img);
    $scale = min(1.0, $maxDim / max($w, $h));
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    $ok = imagejpeg($out, $dest, $quality);
    imagedestroy($out);
    if (!$ok) return null;
    @chmod($dest, 0600);
    return [$nw, $nh];
}

/* Returns an error message, or null when the photo was saved. */
function save_photo(int $entryId, int $uid, array $f): ?string {
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) return upload_error_text($f);
    if ($f['size'] > PHOTO_MAX_BYTES) return $f['name'] . ' is too large (limit 40 MB).';
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) return $f['name'] . ' isn\'t a JPG, PNG, GIF or WebP photo.';
    if ($info[0] * $info[1] > PHOTO_MAX_PIXELS) return $f['name'] . ' has too many pixels to process.';
    $type = $info[2]; $ext = $types[$type];
    $pdo = db();
    $pdo->prepare('INSERT INTO entry_photos (entry_id, user_id, orig_name, ext, mime, width, height, bytes, created_at) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$entryId, $uid, mb_substr(basename($f['name']), 0, 200), $ext, image_type_to_mime_type($type), $info[0], $info[1], $f['size'], time()]);
    $id = (int)$pdo->lastInsertId();
    $base = photo_dir($uid) . '/' . $id;
    if (!move_uploaded_file($f['tmp_name'], $base . '_o.' . $ext)) {
        $pdo->prepare('DELETE FROM entry_photos WHERE id = ?')->execute([$id]);
        return $f['name'] . ' could not be saved.';
    }
    @chmod($base . '_o.' . $ext, 0600);
    $src = $base . '_o.' . $ext;
    $web = make_derivative($src, $type, 2000, $base . '_w.jpg', 85);
    $thumb = $web ? make_derivative($src, $type, 520, $base . '_t.jpg', 80) : null;
    if ($web) {
        $pdo->prepare('UPDATE entry_photos SET width = ?, height = ?, has_derived = 1 WHERE id = ?')->execute([$web[0], $web[1], $id]);
    }
    return null;
}
function save_uploaded_photos(int $entryId, int $uid): array {
    $errs = []; $n = 0;
    foreach (normalize_files('photos') as $f) {
        if ($f['error'] === UPLOAD_ERR_NO_FILE) continue;
        if (++$n > PHOTO_MAX_PER_UPLOAD) { $errs[] = 'Only ' . PHOTO_MAX_PER_UPLOAD . ' photos can be added at a time.'; break; }
        $e = save_photo($entryId, $uid, $f);
        if ($e) $errs[] = $e;
    }
    return $errs;
}
function delete_photo_files(array $p): void {
    $base = photo_dir((int)$p['user_id']) . '/' . $p['id'];
    foreach ([$base . '_t.jpg', $base . '_w.jpg', $base . '_e.jpg', $base . '_o.' . $p['ext']] as $f) { if (is_file($f)) @unlink($f); }
}
function delete_entry_photos(int $entryId, int $uid): void {
    $s = db()->prepare('SELECT * FROM entry_photos WHERE entry_id = ? AND user_id = ?');
    $s->execute([$entryId, $uid]);
    foreach ($s->fetchAll() as $p) delete_photo_files($p);
}
function photos_for_entries(array $ids): array {
    if (!$ids) return [];
    $in = implode(',', array_map('intval', $ids));
    $out = [];
    foreach (db()->query("SELECT * FROM entry_photos WHERE entry_id IN ($in) ORDER BY id")->fetchAll() as $p) { $out[$p['entry_id']][] = $p; }
    return $out;
}


/* Save an edited version made in the photo studio. The untouched original stays on disk. */
function save_photo_edit(array $p, array $f): ?string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return 'The edited photo did not arrive. Please try again.';
    if ($f['size'] > 25 * 1024 * 1024) return 'The edited photo is too large.';
    $info = @getimagesize($f['tmp_name']);
    if (!$info || $info[2] !== IMAGETYPE_JPEG) return 'The edited photo was not a valid image.';
    if ($info[0] * $info[1] > PHOTO_MAX_PIXELS) return 'The edited photo has too many pixels.';
    $base = photo_dir((int)$p['user_id']) . '/' . $p['id'];
    $edited = $base . '_e.jpg';
    if (!move_uploaded_file($f['tmp_name'], $edited)) return 'The edited photo could not be saved.';
    @chmod($edited, 0600);
    $web = make_derivative($edited, IMAGETYPE_JPEG, 2000, $base . '_w.jpg', 85);
    $thumb = $web ? make_derivative($edited, IMAGETYPE_JPEG, 520, $base . '_t.jpg', 80) : null;
    if (!$web) { @unlink($edited); return 'The edited photo could not be processed.'; }
    db()->prepare('UPDATE entry_photos SET width = ?, height = ?, has_derived = 1, edited_at = ? WHERE id = ?')->execute([$web[0], $web[1], time(), $p['id']]);
    return null;
}
function revert_photo_edit(array $p): void {
    $base = photo_dir((int)$p['user_id']) . '/' . $p['id'];
    $src = $base . '_o.' . $p['ext'];
    $type = ['jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP][$p['ext']] ?? IMAGETYPE_JPEG;
    if (is_file($src)) {
        $web = make_derivative($src, $type, 2000, $base . '_w.jpg', 85);
        if ($web) { make_derivative($src, $type, 520, $base . '_t.jpg', 80); db()->prepare('UPDATE entry_photos SET width = ?, height = ? WHERE id = ?')->execute([$web[0], $web[1], $p['id']]); }
    }
    if (is_file($base . '_e.jpg')) @unlink($base . '_e.jpg');
    db()->prepare('UPDATE entry_photos SET edited_at = 0 WHERE id = ?')->execute([$p['id']]);
}
