<?php
declare(strict_types=1);

/* Profile pictures. One small square JPEG per person, kept in the private data folder and served only to
   the person and to people in the same household. Copyright (c) JSW Software Designs */

const AVATAR_MAX_BYTES = 15 * 1024 * 1024;
const AVATAR_MAX_PIXELS = 50000000;
const AVATAR_SIZE = 320;

function avatar_file(int $uid): string {
    $d = data_dir() . '/avatars';
    if (!is_dir($d)) { @mkdir($d, 0700, true); }
    return $d . '/' . $uid . '.jpg';
}
function avatar_has(int $uid): bool { return is_file(avatar_file($uid)); }

/** May this viewer see that person's picture? Themselves, or someone in the same household. */
function avatar_viewable(int $viewer, int $target): bool {
    if ($viewer === $target) return true;
    $q = db()->prepare("SELECT 1 FROM household_members a JOIN household_members b ON a.household_id = b.household_id
        WHERE a.user_id = ? AND b.user_id = ? AND a.status = 'active' AND b.status = 'active' LIMIT 1");
    $q->execute([$viewer, $target]);
    return (bool)$q->fetchColumn();
}

/** Round picture, or the first letter of the name when there is none. */
function avatar_html(int $uid, string $name, string $cls = ''): string {
    $letter = e(mb_strtoupper(mb_substr(trim($name) ?: '?', 0, 1)));
    $f = avatar_file($uid);
    if (is_file($f)) {
        return '<span class="avatar ' . e($cls) . '"><img src="' . e(url('/avatar/' . $uid)) . '?v=' . (int)filemtime($f) . '" alt="" width="64" height="64" loading="lazy"></span>';
    }
    return '<span class="avatar avatar--letter ' . e($cls) . '" aria-hidden="true">' . $letter . '</span>';
}

/** Returns an error message, or null when saved. */
function avatar_save(int $uid, array $f): ?string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return tt('profile.err_choose');
    if ($f['error'] !== UPLOAD_ERR_OK) return in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? tt('profile.err_too_big') : tt('profile.err_failed');
    if ($f['size'] > AVATAR_MAX_BYTES) return tt('profile.err_too_big');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) return tt('profile.err_type');
    if ($info[0] * $info[1] > AVATAR_MAX_PIXELS) return tt('profile.err_too_big');
    if (!function_exists('imagecreatefromstring')) return tt('profile.err_failed');
    $img = @imagecreatefromstring((string)@file_get_contents($f['tmp_name']));
    if (!$img) return tt('profile.err_type');
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($f['tmp_name']);
        $deg = [3 => 180, 6 => -90, 8 => 90][(int)($ex['Orientation'] ?? 1)] ?? 0;
        if ($deg) { $r = @imagerotate($img, $deg, 0); if ($r) { imagedestroy($img); $img = $r; } }
    }
    $w = imagesx($img); $h = imagesy($img); $side = min($w, $h);
    $out = imagecreatetruecolor(AVATAR_SIZE, AVATAR_SIZE);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $img, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), AVATAR_SIZE, AVATAR_SIZE, $side, $side);
    imagedestroy($img);
    $dest = avatar_file($uid); $tmp = $dest . '.tmp';
    $ok = imagejpeg($out, $tmp, 86); imagedestroy($out);
    if (!$ok || !@rename($tmp, $dest)) { @unlink($tmp); return tt('profile.err_failed'); }
    @chmod($dest, 0600);
    return null;
}
function avatar_delete(int $uid): void { $f = avatar_file($uid); if (is_file($f)) @unlink($f); }
