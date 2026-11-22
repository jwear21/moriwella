<?php
require_once __DIR__ . '/credits.php';
/* Memory movies: renderer. Plain PHP + GD (for title cards and captions) + FFmpeg (for motion and encoding).
   Safe to include from the web app and from the command-line worker: it defines functions only.
   Copyright (c) JSW Software Designs */

require_once __DIR__ . '/i18n.php';

const MV_W = 1920;
const MV_H = 1080;
const MV_FPS = 30;
const MV_XF = 0.6;        /* crossfade seconds */
const MV_SCENE = 3.6;     /* seconds per photo */
const MV_THEMES = [
    'evergreen' => ['name' => 'Evergreen', 'bg' => [22, 62, 48],    'ink' => [250, 246, 236], 'accent' => [214, 170, 84], 'veil' => 24],
    'polaroid'  => ['name' => 'Polaroid scrapbook', 'bg' => [48, 37, 28], 'ink' => [250, 244, 230], 'accent' => [232, 192, 112], 'veil' => 22, 'layout' => 'polaroid'],
    'parchment' => ['name' => 'Parchment', 'bg' => [247, 241, 228], 'ink' => [28, 58, 46],    'accent' => [150, 98, 28],  'veil' => 18],
];
const MV_LENGTHS = ['short' => ['name' => 'Short, about 40 seconds', 'max' => 9], 'full' => ['name' => 'Full, about 80 seconds', 'max' => 21]];

function mv_dir(): string { $d = data_dir() . '/movies'; if (!is_dir($d)) { @mkdir($d, 0700, true); } return $d; }
function mv_font(string $n): string { return dirname(__DIR__) . '/fonts/' . $n . '.ttf'; }
function mv_ffmpeg(): string { return (string)cfg('ffmpeg_path', 'ffmpeg'); }

/* ---------- GD helpers ---------- */
function mv_col($im, array $c, int $alpha = 0): int { return imagecolorallocatealpha($im, $c[0], $c[1], $c[2], $alpha); }
/* Japanese, Korean and Chinese text: Latin-only fonts have no such glyphs, so each string is drawn with a CJK font chosen by script.
   Kana means Japanese. Chinese is Simplified or Traditional by the person's language, else by telling the two apart. */
function mv_is_cjk(string $t): bool { return (bool)preg_match('/[\x{1100}-\x{11FF}\x{3040}-\x{30FF}\x{3130}-\x{318F}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{AC00}-\x{D7AF}\x{F900}-\x{FAFF}]/u', $t); }
function mv_cjk_region(string $t): string {
    if (preg_match('/[\x{1100}-\x{11FF}\x{3130}-\x{318F}\x{AC00}-\x{D7AF}]/u', $t)) return 'KR';
    if (preg_match('/[\x{3040}-\x{30FF}]/u', $t)) return 'JP';
    $l = strtolower(current_lang());
    if (strpos($l, 'ko') === 0) return 'KR';
    if (strpos($l, 'ja') === 0) return 'JP';
    if (in_array($l, ['zh-hk', 'zh-tw', 'zh-hant', 'zh-mo'], true)) return 'TC';
    if (in_array($l, ['zh', 'zh-cn', 'zh-hans', 'zh-sg'], true)) return 'SC';
    return preg_match('/[们这个来时会过对说学国发后么书见长门开关车东马鸟鱼风飞电话谢礼爱听让总没经现还为与产业兴师专乐医务华单参历压变员图场声处头实写应带虽乡际该战无张岁气汉龙]/u', $t) ? 'SC' : 'TC';
}
/* Other scripts the Latin fonts lack: one serif and one handwriting face each (Caveat means handwriting).
   Third value: true when the script needs a shaping engine (joined letters, marks that reorder, right-to-left). PHP's GD cannot do that
   on this host, so those strings are drawn by ffmpeg's drawtext (HarfBuzz) and pasted in. Thai needs no shaping. */
function mv_script_fonts(string $t): ?array {
    static $map = [
        ['/[\x{0E00}-\x{0E7F}]/u', 'NotoSerifThai', 'Mali', false],
        ['/[\x{0900}-\x{097F}]/u', 'NotoSerifDevanagari', 'Kalam', true],
        ['/[\x{0980}-\x{09FF}]/u', 'NotoSerifBengali', 'Galada', true],
        ['/[\x{0C00}-\x{0C7F}]/u', 'NotoSerifTelugu', 'Gidugu', true],
        ['/[\x{0B80}-\x{0BFF}]/u', 'NotoSerifTamil', 'Kavivanar', true],
    ];
    foreach ($map as $m) if (preg_match($m[0], $t)) return [$m[1], $m[2], $m[3]];
    return null;
}
/* Draw one shaped string with pango-view (full HarfBuzz shaping: Indic conjuncts, Arabic joining, right-to-left) into a transparent PNG,
   trimmed to its ink. Returns [GD image, bottom row of ink, left bearing] or null so the caller can fall back to plain GD. */
const MV_FAMILY = ['NotoSerifDevanagari' => 'Noto Serif Devanagari', 'Kalam' => 'Kalam', 'NotoSerifBengali' => 'Noto Serif Bengali', 'Galada' => 'Galada', 'NotoSerifTelugu' => 'Noto Serif Telugu', 'Gidugu' => 'Gidugu', 'NotoSerifTamil' => 'Noto Serif Tamil', 'Kavivanar' => 'Kavivanar'];
function mv_fontconf(): string {
    $f = sys_get_temp_dir() . '/mw-fonts-' . md5(dirname(__DIR__)) . '.conf';
    if (!is_file($f)) {
        $x = '<?xml version="1.0"?><!DOCTYPE fontconfig SYSTEM "fonts.dtd"><fontconfig><dir>' . htmlspecialchars(dirname(__DIR__) . '/fonts') . '</dir><cachedir>' . htmlspecialchars(sys_get_temp_dir() . '/mw-fc-cache') . '</cachedir></fontconfig>';
        @file_put_contents($f, $x);
    }
    return $f;
}
function mv_shape(string $t, float $size, string $fontFile, array $rgba): ?array {
    static $cache = [];
    $key = md5($t . '|' . $size . '|' . $fontFile . '|' . implode(',', $rgba));
    if (array_key_exists($key, $cache)) return $cache[$key];
    $fam = MV_FAMILY[basename($fontFile, '.ttf')] ?? null;
    if ($fam === null) return $cache[$key] = null;
    $png = sys_get_temp_dir() . '/mvsh_' . $key . '.png'; $txt = sys_get_temp_dir() . '/mvsh_' . $key . '.txt';
    @file_put_contents($txt, $t);
    $col = sprintf('#%02X%02X%02X', $rgba[0], $rgba[1], $rgba[2]);
    $cmd = 'LC_ALL=C.UTF-8 FONTCONFIG_FILE=' . escapeshellarg(mv_fontconf()) . ' pango-view --no-display --dpi=72 --margin=' . (int)ceil($size * 0.3)
         . ' --font=' . escapeshellarg($fam . ' ' . (int)round($size)) . ' --foreground=' . escapeshellarg($col) . ' --background=transparent'
         . ' --output=' . escapeshellarg($png) . ' ' . escapeshellarg($txt) . ' 2>&1';
    @exec($cmd, $out, $rc);
    @unlink($txt);
    $im = ($rc === 0 && is_file($png)) ? @imagecreatefrompng($png) : false; @unlink($png);
    if (!$im) return $cache[$key] = null;
    imagesavealpha($im, true);
    $w = imagesx($im); $h = imagesy($im); $x0 = $w; $x1 = -1; $y1 = -1;
    for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) { if ((imagecolorat($im, $x, $y) >> 24) < 120) { if ($x < $x0) $x0 = $x; if ($x > $x1) $x1 = $x; if ($y > $y1) $y1 = $y; } }
    if ($x1 < 0) return $cache[$key] = null;
    $crop = imagecrop($im, ['x' => $x0, 'y' => 0, 'width' => $x1 - $x0 + 1, 'height' => $h]);
    $alpha = 1 - $rgba[3];
    if ($crop && $rgba[3] < 1) { imagealphablending($crop, false); imagefilter($crop, IMG_FILTER_COLORIZE, 0, 0, 0, (int)round(127 * $alpha)); }
    return $cache[$key] = $crop ? [$crop, $y1, 0] : null;
}
function mv_shaped_script(string $t): bool { $sf = mv_script_fonts($t); return $sf !== null && $sf[2]; }
function mv_pick(string $font, string $t): string {
    if ($sf = mv_script_fonts($t)) return mv_font(strpos(basename($font, '.ttf'), 'Caveat') === 0 ? $sf[1] : $sf[0]);
    if (!mv_is_cjk($t)) return $font;
    $r = mv_cjk_region($t); $b = basename($font, '.ttf');
    $hand = ['JP' => 'KleeOne', 'TC' => 'Iansui', 'SC' => 'MaShanZheng', 'KR' => 'NanumPenScript']; $serif = ['JP' => 'NotoSerifJP', 'TC' => 'NotoSerifTC', 'SC' => 'NotoSerifSC', 'KR' => 'NotoSerifKR'];
    return mv_font(strpos($b, 'Caveat') === 0 ? $hand[$r] : $serif[$r]);
}
/* Shaped scripts: the glyph image is measured and pasted; ink width is the advance. Baseline is taken from the bottom of an "H" in the same font. */
function mv_shaped_draw($im, float $size, int $x, int $y, int $col, string $font, string $t): bool {
    $f = mv_pick($font, $t); $c = imagecolorsforindex($im, $col);
    $g = mv_shape($t, $size, $f, [$c['red'], $c['green'], $c['blue'], 1 - $c['alpha'] / 127]);
    if (!$g) return false;
    $h = mv_shape('H', $size, $f, [255, 255, 255, 1]); $base = $h ? $h[1] + 1 : (int)round($size * 1.2);
    imagealphablending($im, true);
    imagecopy($im, $g[0], $x + $g[2], $y - $base, 0, 0, imagesx($g[0]), imagesy($g[0]));
    return true;
}
function mv_text($im, float $size, float $angle, int $x, int $y, int $col, string $font, string $t) {
    if (mv_shaped_script($t) && mv_shaped_draw($im, $size, $x, $y, $col, $font, $t)) return true;
    return imagettftext($im, $size, $angle, $x, $y, $col, mv_pick($font, $t), $t);
}

function mv_w(float $size, string $font, string $t): int {
    if (mv_shaped_script($t) && ($g = mv_shape($t, $size, mv_pick($font, $t), [255, 255, 255, 1]))) return imagesx($g[0]);
    $b = imagettfbbox($size, 0, mv_pick($font, $t), $t); return (int)($b[2] - $b[0]);
}
/* Words are split at spaces; Japanese and Chinese have none, so each character is a break point (never starting a line with closing punctuation). */
function mv_wrap(string $t, float $size, string $font, int $maxW, int $maxLines = 6): array {
    $lines = []; $cur = ''; $noStart = '、。，．！？）」』】〉》,.!?:;)…・ー';
    preg_match_all('/[\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{F900}-\x{FAFF}\x{3000}-\x{303F}\x{FF00}-\x{FFEF}]|[^\s\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{F900}-\x{FAFF}\x{3000}-\x{303F}\x{FF00}-\x{FFEF}]+/u', trim($t), $m);
    $tokens = $m[0]; $prevCjk = false; $prevEnd = -1; $pos = 0;
    // re-find spaces: a space between two tokens is kept only between non-CJK tokens
    $src = trim($t); $offset = 0;
    foreach ($tokens as $tok) {
        $at = mb_strpos($src, $tok, $offset); $gap = $at > $offset ? ' ' : ''; $offset = $at + mb_strlen($tok);
        $isCjk = mv_is_cjk($tok) || preg_match('/^[\x{3000}-\x{303F}\x{FF00}-\x{FFEF}]$/u', $tok);
        $glue = ($cur === '') ? '' : (($isCjk && $prevCjk && $gap === '') ? '' : ($gap !== '' ? ' ' : ''));
        $try = $cur . $glue . $tok;
        if ($cur !== '' && mv_w($size, $font, $try) > $maxW && !(mb_strpos($noStart, $tok) !== false && mb_strlen($tok) === 1)) { $lines[] = $cur; $cur = $tok; } else { $cur = $try; }
        $prevCjk = $isCjk;
    }
    if ($cur !== '') $lines[] = $cur;
    if (count($lines) > $maxLines) { $lines = array_slice($lines, 0, $maxLines); $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], ' ,.;:、。，') . '…'; }
    return $lines;
}
function mv_center($im, int $y, string $t, float $size, string $font, int $col, int $w = MV_W): void {
    mv_text($im, $size, 0, (int)(($w - mv_w($size, $font, $t)) / 2), $y, $col, $font, $t);
}
function mv_load(string $path) {
    $d = @file_get_contents($path); if ($d === false) return null;
    $im = @imagecreatefromstring($d); return $im ?: null;
}
function mv_cover($src, int $w, int $h) {
    $sw = imagesx($src); $sh = imagesy($src); $s = max($w / $sw, $h / $sh);
    $dw = (int)ceil($sw * $s); $dh = (int)ceil($sh * $s);
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $src, (int)(($w - $dw) / 2), (int)(($h - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
    return $out;
}
/* A frame for the camera to move across. Wide photos fill it; tall ones sit on a soft blurred copy of themselves. */
function mv_scene_frame($src, int $w = 2400, int $h = 1350) {
    $sw = imagesx($src); $sh = imagesy($src);
    if ($sw / $sh >= 1.25) return mv_cover($src, $w, $h);
    $small = mv_cover($src, 96, 54); $bg = imagecreatetruecolor($w, $h);
    imagecopyresampled($bg, $small, 0, 0, 0, 0, $w, $h, 96, 54); imagedestroy($small);
    imagefilledrectangle($bg, 0, 0, $w, $h, mv_col($bg, [10, 24, 19], 70));
    $fh = $h; $fw = (int)round($sw * $fh / $sh);
    if ($fw > $w) { $fw = $w; $fh = (int)round($sh * $fw / $sw); }
    imagecopyresampled($bg, $src, (int)(($w - $fw) / 2), (int)(($h - $fh) / 2), 0, 0, $fw, $fh, $sw, $sh);
    return $bg;
}
function mv_save_jpg($im, string $path, int $q = 92): void { imagejpeg($im, $path, $q); imagedestroy($im); }
function mv_save_png($im, string $path): void { imagesavealpha($im, true); imagepng($im, $path, 3); imagedestroy($im); }

/* Card canvas: the theme colour, or a photo under a veil of the theme colour. */
function mv_canvas(array $th, $photo = null) {
    if ($photo) { $im = mv_cover($photo, MV_W, MV_H); } else { $im = imagecreatetruecolor(MV_W, MV_H); }
    imagealphablending($im, true);
    imagefilledrectangle($im, 0, 0, MV_W, MV_H, mv_col($im, $th['bg'], $photo ? $th['veil'] : 0));
    imagesetthickness($im, 2); imagerectangle($im, 44, 44, MV_W - 45, MV_H - 45, mv_col($im, $th['accent'], 60));
    return $im;
}
function mv_brand($im, array $th, string $tag = ''): void {
    $acc = mv_col($im, $th['accent']);
    mv_center($im, MV_H - 118, 'Moriwella', 38, mv_font('PlayfairDisplay-Italic'), $acc);
    if ($tag !== '') mv_center($im, MV_H - 76, $tag, 22, mv_font('Lora-Italic'), mv_col($im, $th['ink'], 40));
}
function mv_rule($im, int $y, array $th): void { imagesetthickness($im, 3); imageline($im, MV_W / 2 - 80, $y, MV_W / 2 + 80, $y, mv_col($im, $th['accent'])); }

function mv_title_card(array $th, $photo, string $by, string $title, string $where, string $dates, string $opening, string $path): void {
    $im = mv_canvas($th, $photo); $ink = mv_col($im, $th['ink']); $acc = mv_col($im, $th['accent']);
    $pf = mv_font('PlayfairDisplay'); $lo = mv_font('Lora'); $li = mv_font('Lora-Italic');
    $size = 112; $lines = mv_wrap($title, $size, $pf, MV_W - 360, 3);
    if (mv_is_cjk($title)) {
        /* CJK glyphs are visually larger and have no spaces: step the size down until it is one or two lines with no lone character at the end */
        foreach ([96, 84, 72, 60] as $size) {
            $lines = mv_wrap($title, $size, $pf, MV_W - 360, 3);
            if (count($lines) === 1 || (count($lines) === 2 && mb_strlen($lines[1]) >= 3)) break;
        }
    } elseif (count($lines) > 2) { $size = 84; $lines = mv_wrap($title, $size, $pf, MV_W - 360, 3); }
    $block = count($lines) * ($size + 14);
    $y = (int)(MV_H / 2 - $block / 2 + 10);
    if ($by !== '') mv_center($im, $y - 70, $by, 38, $li, $acc);
    foreach ($lines as $l) { $y += $size; mv_center($im, $y, $l, $size, $pf, $ink); $y += 14; }
    mv_rule($im, $y + 30, $th);
    $y += 100;
    $sub = trim($where); if ($sub !== '') { mv_center($im, $y, $sub, 44, $lo, $ink); $y += 58; }
    mv_center($im, $y, $dates, 36, $li, $acc); $y += 62;
    if ($opening !== '') foreach (mv_wrap($opening, 36, $li, 1300, 2) as $l) { mv_center($im, $y, $l, 36, $li, $ink); $y += 50; }
    mv_brand($im, $th);
    mv_save_png($im, $path);
}
function mv_day_card(array $th, int $dayNo, string $date, string $note, string $path): void {
    $im = mv_canvas($th); $ink = mv_col($im, $th['ink']); $acc = mv_col($im, $th['accent']);
    $pf = mv_font('PlayfairDisplay'); $li = mv_font('Lora-Italic');
    mv_center($im, 500, tt('movie.day_n', ['n' => $dayNo]), 132, $pf, $acc);
    mv_rule($im, 548, $th);
    mv_center($im, 628, $date, 48, mv_font('Lora'), $ink);
    $y = 704; foreach (mv_wrap($note, 36, $li, 1300, 2) as $l) { mv_center($im, $y, $l, 36, $li, mv_col($im, $th['ink'], 25)); $y += 50; }
    mv_save_png($im, $path);
}
function mv_quote_card(array $th, string $label, string $text, string $path): void {
    $im = mv_canvas($th); $ink = mv_col($im, $th['ink']); $acc = mv_col($im, $th['accent']);
    $li = mv_font('Lora-Italic'); $size = 54;
    $lines = mv_wrap($text, $size, $li, 1360, 7);
    if (count($lines) > 5) { $size = 46; $lines = mv_wrap($text, $size, $li, 1400, 7); }
    $lh = (int)($size * 1.5); $y = (int)(MV_H / 2 - count($lines) * $lh / 2 + $size * 0.4) + 20;
    mv_center($im, $y - $lh - 20, $label, 32, mv_font('Lora'), $acc);
    foreach ($lines as $l) { mv_center($im, $y, $l, $size, $li, $ink); $y += $lh; }
    mv_save_png($im, $path);
}
function mv_end_card(array $th, $photo, string $closing, string $stats, string $path): void {
    $im = mv_canvas($th, $photo); $ink = mv_col($im, $th['ink']); $acc = mv_col($im, $th['accent']);
    $y = 470;
    mv_center($im, $y, tt('movie.until_next_time'), 104, mv_font('PlayfairDisplay-Italic'), $ink); $y += 54;
    mv_rule($im, $y, $th); $y += 84;
    if ($closing !== '') foreach (mv_wrap($closing, 42, mv_font('Lora'), 1300, 3) as $l) { mv_center($im, $y, $l, 42, mv_font('Lora'), $ink); $y += 58; }
    if ($stats !== '') { $y += 16; mv_center($im, $y, $stats, 34, mv_font('Lora-Italic'), $acc); }
    mv_brand($im, $th, tt('movie.tagline'));
    mv_save_png($im, $path);
}
/* Transparent overlay: dark gradient at the foot of the frame, with the day and a line from the entry. */
function mv_caption(array $th, string $label, string $line, string $path): void {
    $im = imagecreatetruecolor(MV_W, MV_H); imagealphablending($im, false); imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    $gh = 440;
    for ($i = 0; $i < $gh; $i++) {
        $a = (int)round(127 - 127 * 0.92 * pow($i / ($gh - 1), 1.25));
        imageline($im, 0, MV_H - $gh + $i, MV_W, MV_H - $gh + $i, imagecolorallocatealpha($im, 10, 24, 19, $a));
    }
    imagealphablending($im, true);
    $acc = mv_col($im, [214, 170, 84]); $cream = mv_col($im, [250, 246, 236]);
    mv_text($im, 30, 0, 110, MV_H - 222, $acc, mv_font('Lora'), $label);
    $y = MV_H - 160; foreach (mv_wrap($line, 46, mv_font('PlayfairDisplay'), MV_W - 260, 2) as $l) { mv_text($im, 46, 0, 110, $y + 40, $cream, mv_font('PlayfairDisplay'), $l); $y += 64; }
    mv_save_png($im, $path);
}

/* ---------- FFmpeg ---------- */
function mv_run(array $cmd, string $what): void {
    /* Shared hosting caps threads per account, and FFmpeg starts one thread per CPU core for every input.
       So: one decoder thread per input, a small fixed number for filters and the encoder. */
    $full = ['nice', '-n', '19', mv_ffmpeg(), '-y', '-loglevel', 'error', '-filter_threads', '1', '-filter_complex_threads', '1'];
    $last = count($cmd) - 1;
    foreach ($cmd as $i => $tok) {
        if ($tok === '-i') { $full[] = '-threads'; $full[] = '1'; }
        if ($i === $last) { $full[] = '-threads'; $full[] = '2'; }
        $full[] = $tok;
    }
    $p = @proc_open($full, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) throw new RuntimeException('Could not start FFmpeg for ' . $what);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($p) !== 0) {
        @file_put_contents(data_dir() . '/error.log', date('c') . ' ffmpeg ' . $what . ' stderr: ' . substr($err ?: $out, 0, 3000) . "\n", FILE_APPEND);
        throw new RuntimeException($what . ' failed: ' . substr($err ?: $out, 0, 500));
    }
}
function mv_still_clip(string $png, string $out, float $dur): void {
    mv_run(['-loop', '1', '-t', (string)$dur, '-i', $png, '-vf', 'fade=in:0:15,fade=out:st=' . ($dur - 0.7) . ':d=0.7,format=yuv420p,fps=' . MV_FPS,
        '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '19', $out], 'card');
}
function mv_photo_clip(string $jpg, string $cap, string $out, int $i): void {
    $frames = (int)(MV_SCENE * MV_FPS); [$z0, $z1] = $i % 2 === 0 ? [1.0, 1.12] : [1.12, 1.0];
    $px = [0.4, 0.5, 0.6][$i % 3]; $py = [0.5, 0.4, 0.6][($i + 1) % 3];
    $fc = "[0:v]zoompan=z='{$z0}+({$z1}-{$z0})*on/{$frames}':x='(iw-iw/zoom)*{$px}':y='(ih-ih/zoom)*{$py}':d={$frames}:s=" . MV_W . 'x' . MV_H . ':fps=' . MV_FPS . '[v];'
        . '[1:v]fade=in:st=0.5:d=0.7:alpha=1,fade=out:st=' . (MV_SCENE - 0.9) . ':d=0.6:alpha=1[c];[v][c]overlay,format=yuv420p';
    mv_run(['-i', $jpg, '-loop', '1', '-t', (string)MV_SCENE, '-i', $cap, '-filter_complex', $fc, '-t', (string)MV_SCENE, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '19', $out], 'photo scene');
}


/* ---------- Polaroid scrapbook look ---------- */
function mv_kraft() {
    $im = imagecreatetruecolor(MV_W, MV_H); imagefill($im, 0, 0, imagecolorallocate($im, 204, 174, 132));
    mt_srand(11); imagealphablending($im, true);
    for ($i = 0; $i < 60000; $i++) { $v = mt_rand(0, 1) ? 255 : 60; imagesetpixel($im, mt_rand(0, MV_W - 1), mt_rand(0, MV_H - 1), imagecolorallocatealpha($im, $v, $v - 20, $v - 60 > 0 ? $v - 60 : 0, mt_rand(110, 124))); }
    for ($i = 0; $i < 260; $i++) { $x = mt_rand(0, MV_W); $y = mt_rand(0, MV_H); imageline($im, $x, $y, $x + mt_rand(-90, 90), $y + mt_rand(-6, 6), imagecolorallocatealpha($im, 120, 90, 55, mt_rand(118, 124))); }
    return $im;
}
/* One print: white border, square photo, a line of handwriting, a soft shadow and a strip of tape, tilted. Returns a transparent GD image. */
function mv_polaroid($src, string $note, float $angle, int $scale = 100) {
    $pw = (int)(520 * $scale / 100); $ph = (int)(640 * $scale / 100); $pad = (int)(30 * $scale / 100); $sq = $pw - 2 * $pad; $m = 70;
    $im = imagecreatetruecolor($pw + 2 * $m, $ph + 2 * $m); imagealphablending($im, false); imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127)); imagealphablending($im, true);
    for ($i = 22; $i >= 0; $i--) { imagefilledrectangle($im, $m - $i + 10, $m - $i + 18, $m + $pw + $i + 10, $m + $ph + $i + 18, imagecolorallocatealpha($im, 20, 12, 4, 123)); }
    imagefilledrectangle($im, $m, $m, $m + $pw, $m + $ph, imagecolorallocate($im, 252, 250, 243));
    $c = mv_cover($src, $sq, $sq); imagecopy($im, $c, $m + $pad, $m + $pad, 0, 0, $sq, $sq); imagedestroy($c);
    imagesetthickness($im, 1); imagerectangle($im, $m + $pad, $m + $pad, $m + $pad + $sq, $m + $pad + $sq, imagecolorallocatealpha($im, 0, 0, 0, 100));
    $ink = imagecolorallocate($im, 52, 44, 38); $f = mv_font('Caveat'); $fs = 40 * $scale / 100;
    $lines = mv_wrap($note, $fs, $f, $sq - 10, 2); $y = $m + $pad + $sq + (int)(62 * $scale / 100);
    foreach ($lines as $l) { mv_text($im, $fs, 0, $m + $pad + (int)(($sq - mv_w($fs, $f, $l)) / 2), $y, $ink, $f, $l); $y += (int)(46 * $scale / 100); }
    $tw = 170; $th = 46; $tx = $m + (int)($pw / 2) - $tw / 2; $ty = $m - 20;
    imagefilledpolygon($im, [$tx, $ty + 3, $tx + $tw, $ty, $tx + $tw - 4, $ty + $th, $tx + 3, $ty + $th - 3], imagecolorallocatealpha($im, 240, 232, 196, 38));
    imagealphablending($im, false);
    $rot = imagerotate($im, $angle, imagecolorallocatealpha($im, 0, 0, 0, 127)); imagedestroy($im);
    imagesavealpha($rot, true); return $rot;
}
function mv_polaroid_clip(array $th, $kraft, array $group, string $work, string $tag, string $out): float {
    $n = count($group); $dur = 3.6 + 0.9 * $n;
    $bg = imagecreatetruecolor(MV_W, MV_H); imagecopy($bg, $kraft, 0, 0, 0, 0, MV_W, MV_H);
    mv_text($bg, 62, -2, 96, 150, imagecolorallocate($bg, 70, 48, 28), mv_font('Caveat'), $group[0]['label']);
    imagejpeg($bg, "$work/$tag-bg.jpg", 92); imagedestroy($bg);
    $slots = $n === 1 ? [[960, 590, -3, 118]] : ($n === 2 ? [[640, 590, -5, 108], [1280, 570, 4, 108]] : [[420, 600, -6, 100], [1500, 585, 5, 100], [960, 545, -1.5, 100]]);
    if ($n === 3) $slots = [$slots[0], $slots[1], $slots[2]];
    $inputs = ['-loop', '1', '-t', (string)$dur, '-i', "$work/$tag-bg.jpg"]; $fc = '[0:v]vignette=angle=0.55,format=rgba[b0]'; $k = 0;
    foreach ($group as $g) {
        [$cx, $cy, $ang, $sc] = $slots[$k]; $src = mv_load($g['file']); $note = $g['line'] !== '' ? $g['line'] : date('F j', strtotime($g['date']));
        $pi = mv_polaroid($src, mv_first_sentence($note, 40), (float)$ang, $sc); imagedestroy($src);
        $w = imagesx($pi); $h = imagesy($pi); mv_save_png($pi, "$work/$tag-p$k.png");
        array_push($inputs, '-loop', '1', '-t', (string)$dur, '-i', "$work/$tag-p$k.png");
        $d = number_format(0.35 + $k * 0.7, 2, '.', ''); $x = (int)($cx - $w / 2); $y = (int)($cy - $h / 2);
        $fc .= ";[" . ($k + 1) . ":v]format=rgba[q$k];[b$k][q$k]overlay=x={$x}:y='{$y}-pow(1-clip((t-{$d})/0.7,0,1),3)*1000+5*sin(t*1.1+{$k})':eval=frame:format=auto[b" . ($k + 1) . ']';
        $k++;
    }
    $fc .= ";[b{$n}]fade=in:0:12,fade=out:st=" . ($dur - 0.6) . ":d=0.6,format=yuv420p,fps=" . MV_FPS . '[v]';
    mv_run(array_merge($inputs, ['-filter_complex', $fc, '-map', '[v]', '-t', (string)$dur, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', $out]), 'collage');
    return $dur;
}

/* ---------- gathering the story ---------- */
function mv_first_sentence(string $t, int $limit = 96): string {
    $t = trim(preg_replace('/\s+/', ' ', $t)); if ($t === '') return '';
    $s = preg_split('/(?<=[.!?])\s|(?<=[。！？])/u', $t)[0];
    return mb_strlen($s) <= $limit ? $s : rtrim(mb_substr($s, 0, $limit - 1), ' ,;:') . '…';
}
function mv_story(int $uid, int $eventId, string $length, bool $collage = false): array {
    $q = db()->prepare('SELECT * FROM events WHERE id = ? AND user_id = ?'); $q->execute([$eventId, $uid]); $ev = $q->fetch();
    if (!$ev) throw new RuntimeException('Experience not found.');
    $secs = []; $q = db()->prepare('SELECT id, name FROM event_sections WHERE event_id = ?'); $q->execute([$eventId]);
    foreach ($q->fetchAll() as $s) $secs[(int)$s['id']] = $s['name'];
    $q = db()->prepare('SELECT * FROM entries WHERE user_id = ? AND event_id = ? ORDER BY entry_date, id'); $q->execute([$uid, $eventId]); $entries = $q->fetchAll();
    $ph = photos_for_entries(array_column($entries, 'id'));
    $multi = ((strtotime($ev['end_date']) - strtotime($ev['start_date'])) / 86400) >= 1;
    $cand = []; $photoCount = 0;
    foreach ($entries as $en) {
        $parts = body_parts((string)$en['body'], (int)$en['created_at']); $text = trim((string)($parts[0]['text'] ?? ''));
        $day = (int)round((strtotime($en['entry_date']) - strtotime($ev['start_date'])) / 86400) + 1;
        $label = ($multi ? tt('movie.day_n', ['n' => $day]) . '  ·  ' : '') . ldate('F j', strtotime($en['entry_date'])) . (!empty($en['section_id']) && isset($secs[(int)$en['section_id']]) ? '  ·  ' . $secs[(int)$en['section_id']] : '');
        $line = trim((string)$en['title']) !== '' ? trim((string)$en['title']) : mv_first_sentence($text);
        $photos = $ph[$en['id']] ?? []; $photoCount += count($photos);
        $base = ['date' => $en['entry_date'], 'day' => $day, 'label' => $label, 'line' => $line, 'dayTitle' => $line];
        if ($photos) {
            foreach ($photos as $k => $p) { $f = photo_path($p + ['user_id' => $uid], 'web'); if ($f) $cand[] = $base + ['kind' => 'photo', 'file' => $f, 'first' => $k === 0, 'line' => $k === 0 ? $line : '']; }
        } elseif (mb_strlen($text) >= 80) {
            $cand[] = $base + ['kind' => 'quote', 'text' => mb_strlen($text) > 260 ? rtrim(mb_substr($text, 0, 257), ' ,;:') . '…' : $text, 'first' => true];
        }
    }
    $max = MV_LENGTHS[$length]['max'] ?? 21; if ($collage) $max = (int)(round($max * 1.4 / 3) * 3);
    $must = array_values(array_filter($cand, fn($c) => $c['first']));
    $rest = array_values(array_filter($cand, fn($c) => !$c['first']));
    $pick = $must;
    if (count($pick) > $max) { $n = count($pick); $keep = []; for ($i = 0; $i < $max; $i++) $keep[] = $pick[(int)floor($i * $n / $max)]; $pick = $keep; }
    elseif ($rest && count($pick) < $max) {
        $need = $max - count($pick); $n = count($rest); $take = min($need, $n);
        for ($i = 0; $i < $take; $i++) $pick[] = $rest[(int)floor($i * $n / $take)];
    }
    usort($pick, fn($a, $b) => [$a['date'], $a['kind'] === 'quote' ? 0 : 1] <=> [$b['date'], $b['kind'] === 'quote' ? 0 : 1]);
    return ['event' => $ev, 'scenes' => $pick, 'photos' => $photoCount, 'entries' => count($entries), 'multi' => $multi];
}

/* ---------- the whole render ---------- */
function mv_render(array $mv, callable $progress): array {
    @ini_set('memory_limit', '768M'); @set_time_limit(0);
    $uid = (int)$mv['user_id']; $th = MV_THEMES[$mv['theme']] ?? MV_THEMES['evergreen'];
    $u = db()->prepare('SELECT name, lang FROM users WHERE id = ?'); $u->execute([$uid]); $ur = $u->fetch(); $name = trim((string)($ur['name'] ?? '')); lang_force((string)($ur['lang'] ?? '') ?: 'en');
    $collage = ($th['layout'] ?? '') === 'polaroid';
    $story = mv_story($uid, (int)$mv['event_id'], (string)$mv['length'], $collage); $ev = $story['event']; $scenes = $story['scenes'];
    if (count(array_filter($scenes, fn($s) => $s['kind'] === 'photo')) < 3) throw new RuntimeException('Add a few more photos to this experience first.');
    $work = mv_dir() . '/work-' . (int)$mv['id']; @mkdir($work, 0700, true);
    try {
        $clips = []; $total = count($scenes) + 3; $done = 0; $lastDay = 0;
        $firstPhoto = null; $lastPhoto = null;
        foreach ($scenes as $s) if ($s['kind'] === 'photo') { $firstPhoto = $firstPhoto ?? $s['file']; $lastPhoto = $s['file']; }
        $span = $ev['end_date'] !== $ev['start_date'] ? ldate('F j', strtotime($ev['start_date'])) . ' – ' . ldate('F j, Y', strtotime($ev['end_date'])) : ldate('F j, Y', strtotime($ev['start_date']));
        $hero = mv_load($firstPhoto); mv_title_card($th, $hero, $name !== '' ? tt('movie.memory_by', ['name' => $name]) : '', $ev['title'], (string)$ev['location'], $span, (string)$mv['subtitle'], "$work/title.png"); imagedestroy($hero);
        mv_still_clip("$work/title.png", "$work/c0.mp4", 4.6); $clips[] = ["$work/c0.mp4", 4.6]; $progress((int)(++$done * 90 / $total));
        $i = 0;
        if ($collage) {
            $kraft = mv_kraft(); $buf = [];
            $flush = function () use (&$buf, &$clips, &$i, $th, $kraft, $work) {
                if (!$buf) return; $i++; $out = "$work/g$i.mp4";
                $d = mv_polaroid_clip($th, $kraft, $buf, $work, "g$i", $out); $clips[] = [$out, $d]; $buf = [];
            };
            foreach ($scenes as $s) {
                if ($s['kind'] === 'photo') { $buf[] = $s; if (count($buf) === 3) $flush(); $progress((int)(++$done * 90 / $total)); continue; }
                $flush(); $i++; $out = "$work/q$i.mp4"; mv_quote_card($th, $s['label'], $s['text'], "$work/q$i.png"); mv_still_clip("$work/q$i.png", $out, 5.0); $clips[] = [$out, 5.0];
                $progress((int)(++$done * 90 / $total));
            }
            $flush(); imagedestroy($kraft); $scenes = [];
        }
        foreach ($scenes as $s) {
            $i++;
            if ($story['multi'] && $s['day'] !== $lastDay && count($scenes) >= 12) {
                $lastDay = $s['day']; $dc = "$work/d$i.mp4";
                mv_day_card($th, $s['day'], ldate('l, F j', strtotime($s['date'])), $s['dayTitle'], "$work/d$i.png"); mv_still_clip("$work/d$i.png", $dc, 2.6); $clips[] = [$dc, 2.6];
            }
            $out = "$work/s$i.mp4";
            if ($s['kind'] === 'photo') {
                $img = mv_load($s['file']); if (!$img) { $progress((int)(++$done * 90 / $total)); continue; }
                mv_save_jpg(mv_scene_frame($img), "$work/p$i.jpg"); imagedestroy($img);
                mv_caption($th, $s['label'], $s['line'], "$work/cap$i.png");
                mv_photo_clip("$work/p$i.jpg", "$work/cap$i.png", $out, $i); $clips[] = [$out, MV_SCENE];
            } else {
                mv_quote_card($th, $s['label'], $s['text'], "$work/q$i.png"); mv_still_clip("$work/q$i.png", $out, 5.0); $clips[] = [$out, 5.0];
            }
            @unlink("$work/p$i.jpg"); $progress((int)(++$done * 90 / $total));
        }
        $stats = ($story['multi'] ? tn('movie.stat_days', (int)round((strtotime($ev['end_date']) - strtotime($ev['start_date'])) / 86400) + 1) . '  ·  ' : '') . tn('movie.stat_moments', (int)$story['entries']) . '  ·  ' . tn('msg.n_photos', (int)$story['photos']);
        $endImg = mv_load($lastPhoto); mv_end_card($th, $endImg, (string)$mv['closing'], $stats, "$work/end.png"); imagedestroy($endImg);
        mv_still_clip("$work/end.png", "$work/cz.mp4", 5.0); $clips[] = ["$work/cz.mp4", 5.0];

        $inputs = []; $parts = []; $off = 0.0; $last = '[0:v]';
        foreach ($clips as [$f]) { $inputs[] = '-i'; $inputs[] = $f; }
        for ($k = 1; $k < count($clips); $k++) { $off += $clips[$k - 1][1] - MV_XF; $parts[] = "{$last}[{$k}:v]xfade=transition=fade:duration=" . MV_XF . ":offset=" . number_format($off, 2, '.', '') . "[x{$k}]"; $last = "[x{$k}]"; }
        $seconds = $off + $clips[count($clips) - 1][1];
        $music = data_dir() . '/movie_music/' . $mv['theme'] . '.mp3'; if (!is_file($music)) $music = data_dir() . '/movie_music/default.mp3';
        $args = $inputs; $map = ['-map', $last];
        if (is_file($music)) {
            array_push($args, '-stream_loop', '-1', '-i', $music);
            $parts[] = '[' . count($clips) . ':a]afade=t=in:st=0:d=2,afade=t=out:st=' . number_format($seconds - 3, 2, '.', '') . ':d=3[a]'; $map = ['-map', $last, '-map', '[a]'];
        }
        $final = mv_dir() . '/' . $uid; if (!is_dir($final)) @mkdir($final, 0700, true); $file = $final . '/' . (int)$mv['id'] . '.mp4';
        $cmd = array_merge($args, ['-filter_complex', implode(';', $parts)], $map, ['-t', number_format($seconds, 2, '.', ''), '-c:v', 'libx264', '-preset', 'fast', '-crf', '25', '-pix_fmt', 'yuv420p']);
        if (is_file($music)) array_push($cmd, '-c:a', 'aac', '-b:a', '160k');
        array_push($cmd, '-movflags', '+faststart', $file);
        mv_run($cmd, 'final movie');
        return ['file' => $file, 'bytes' => filesize($file), 'seconds' => round($seconds, 1)];
    } finally {
        foreach (glob($work . '/*') ?: [] as $f) @unlink($f); @rmdir($work);
    }
}

/* ---------- queue ---------- */
function mv_worker_run(): int {
    $lock = fopen(data_dir() . '/movie.lock', 'c'); if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return 0;
    $pdo = db(); $n = 0;
    credits_reconcile_after_stale();
    $pdo->exec("UPDATE movies SET status = 'failed', error = 'The render was interrupted. Please try again.' WHERE status = 'rendering' AND started_at < " . (time() - 3600));
    while (true) {
        $mv = $pdo->query("SELECT * FROM movies WHERE status = 'queued' ORDER BY id LIMIT 1")->fetch(); if (!$mv) break;
        $id = (int)$mv['id']; $pdo->prepare("UPDATE movies SET status = 'rendering', started_at = ?, progress = 1 WHERE id = ?")->execute([time(), $id]);
        try {
            $r = mv_render($mv, function (int $p) use ($pdo, $id) { $pdo->prepare('UPDATE movies SET progress = ? WHERE id = ?')->execute([min(99, $p), $id]); });
            $pdo->prepare("UPDATE movies SET status = 'done', progress = 100, file = ?, bytes = ?, seconds = ?, finished_at = ?, error = '' WHERE id = ?")->execute([$r['file'], $r['bytes'], $r['seconds'], time(), $id]);
            credits_commit((int)$id);
        } catch (Throwable $t) {
            @file_put_contents(data_dir() . '/error.log', date('c') . ' movie ' . $id . ' ' . $t->getMessage() . "\n", FILE_APPEND);
            $msg = $t instanceof RuntimeException && stripos($t->getMessage(), 'failed') === false ? $t->getMessage() : 'Something went wrong while making the movie. Please try again.';
            $pdo->prepare("UPDATE movies SET status = 'failed', error = ?, finished_at = ? WHERE id = ?")->execute([$msg, time(), $id]);
            credits_release((int)$id);
        }
        $n++;
    }
    flock($lock, LOCK_UN); return $n;
}
function mv_kick(): void {
    if (!function_exists('exec')) return;
    @exec('nohup ' . escapeshellarg((string)cfg('php_cli', 'php')) . ' ' . escapeshellarg(dirname(__DIR__) . '/worker.php') . ' >/dev/null 2>&1 &');
}
function mv_delete_files(array $m): void { if (!empty($m['file']) && is_file($m['file'])) @unlink($m['file']); }
