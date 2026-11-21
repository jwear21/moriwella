<?php
/* Printable scrapbooks: a PDF for one experience, with a cover, a page for each day's moments, captions, dates and a closing page.
   Pages are drawn with GD at 300 dpi and placed into the PDF as images, so any language prints correctly. Safe to include from the web app and
   from the command-line worker: it defines functions only. Copyright (c) JSW Software Designs */
require_once __DIR__ . '/movie_render.php';

const SB_DPI = 300;
const SB_SOFT_DPI = 150;          /* below this a photo looks soft in print */
const SB_SMALL_EDGE = 1500;       /* a photo with a shorter long edge than this will not print sharply on a full page */
const SB_MAX_PAGES = 120;
const SB_SIZES = ['letter' => ['name' => 'US Letter', 'w' => 2550, 'h' => 3300], 'a4' => ['name' => 'A4', 'w' => 2480, 'h' => 3508]];
const SB_STYLES = [
    'classic'  => ['name' => 'Classic', 'paper' => [250, 246, 236], 'ink' => [28, 58, 46], 'accent' => [150, 98, 28], 'frame' => 'plain'],
    'polaroid' => ['name' => 'Polaroid scrapbook', 'paper' => [244, 236, 220], 'ink' => [52, 40, 30], 'accent' => [140, 92, 40], 'frame' => 'polaroid'],
];

function sb_dir(): string { $d = data_dir() . '/scrapbooks'; if (!is_dir($d)) { @mkdir($d, 0700, true); } return $d; }
function scrapbooks_for_event(int $eventId, int $uid): array {
    $q = db()->prepare('SELECT * FROM scrapbooks WHERE event_id = ? AND user_id = ? ORDER BY id DESC LIMIT 3'); $q->execute([$eventId, $uid]); return $q->fetchAll();
}
function sb_delete_files(array $s): void { if (!empty($s['file']) && is_file($s['file'])) @unlink($s['file']); }
function scrapbooks_purge_event(int $eventId, int $uid): void {
    $q = db()->prepare('SELECT * FROM scrapbooks WHERE event_id = ? AND user_id = ?'); $q->execute([$eventId, $uid]);
    foreach ($q->fetchAll() as $s) sb_delete_files($s);
}
/** How many of an experience's photos are too small to print sharply. Reads image headers only. */
function sb_preflight(int $eventId, int $uid): array {
    $total = 0; $small = 0;
    foreach (event_photo_list($eventId, $uid) as $p) {
        $total++; $f = photo_path($p + ['user_id' => $uid], 'orig'); $i = $f ? @getimagesize($f) : false;
        if ($i && max($i[0], $i[1]) < SB_SMALL_EDGE) $small++;
    }
    return ['total' => $total, 'small' => $small];
}

/* ---------- a minimal PDF writer: one JPEG per page ---------- */
final class SbPdf {
    private $fh; private array $off = []; private int $n = 2; private array $pages = []; private string $title;
    public function __construct(string $path, string $title) {
        $this->fh = fopen($path, 'wb'); if (!$this->fh) throw new RuntimeException('Could not write the PDF.');
        $this->title = $title; fwrite($this->fh, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n");
    }
    private function begin(int $num): void { $this->off[$num] = ftell($this->fh); fwrite($this->fh, $num . " 0 obj\n"); }
    public function addJpeg(string $jpg, int $pxW, int $pxH): void {
        $wpt = round($pxW * 72 / SB_DPI, 2); $hpt = round($pxH * 72 / SB_DPI, 2);
        $img = ++$this->n; $con = ++$this->n; $pg = ++$this->n;
        $this->begin($img); fwrite($this->fh, "<< /Type /XObject /Subtype /Image /Width $pxW /Height $pxH /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpg) . " >>\nstream\n");
        fwrite($this->fh, $jpg); fwrite($this->fh, "\nendstream\nendobj\n");
        $c = "q $wpt 0 0 $hpt 0 0 cm /Im0 Do Q";
        $this->begin($con); fwrite($this->fh, '<< /Length ' . strlen($c) . " >>\nstream\n$c\nendstream\nendobj\n");
        $this->begin($pg); fwrite($this->fh, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $wpt $hpt] /Resources << /XObject << /Im0 $img 0 R >> >> /Contents $con 0 R >>\nendobj\n");
        $this->pages[] = $pg;
    }
    public function pageCount(): int { return count($this->pages); }
    public function finish(): void {
        $kids = implode(' ', array_map(fn($n) => "$n 0 R", $this->pages));
        $info = ++$this->n;
        $this->begin($info); fwrite($this->fh, '<< /Title <' . bin2hex("\xFE\xFF" . mb_convert_encoding($this->title, 'UTF-16BE', 'UTF-8')) . '> /Producer (Moriwella) /Creator (Moriwella) >>' . "\nendobj\n");
        $this->begin(1); fwrite($this->fh, "<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");
        $this->begin(2); fwrite($this->fh, '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($this->pages) . " >>\nendobj\n");
        $xref = ftell($this->fh); $total = $this->n + 1;
        fwrite($this->fh, "xref\n0 $total\n0000000000 65535 f \n");
        for ($i = 1; $i < $total; $i++) fwrite($this->fh, sprintf("%010d 00000 n \n", $this->off[$i]));
        fwrite($this->fh, "trailer\n<< /Size $total /Root 1 0 R /Info $info 0 R >>\nstartxref\n$xref\n%%EOF\n");
        fclose($this->fh);
    }
}

/* ---------- drawing ---------- */
function sb_load_photo(array $p, int $uid) {
    $f = photo_path($p + ['user_id' => $uid], 'orig'); $im = $f ? mv_load($f) : null;
    if ($im && $f && function_exists('exif_read_data') && preg_match('/\.jpe?g$/i', $f)) {
        $o = (int)(@exif_read_data($f)['Orientation'] ?? 1); $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($deg) { $r = @imagerotate($im, $deg, 0); if ($r) { imagedestroy($im); $im = $r; } }
    }
    if (!$im) { $w = photo_path($p + ['user_id' => $uid], 'web'); $im = $w ? mv_load($w) : null; }
    return $im;
}
function sb_canvas(int $W, int $H, array $paper) { $im = imagecreatetruecolor($W, $H); imagefill($im, 0, 0, imagecolorallocate($im, $paper[0], $paper[1], $paper[2])); imagealphablending($im, true); return $im; }
function sb_center($im, int $W, int $y, string $t, float $size, string $font, int $col): void { mv_text($im, $size, 0, (int)(($W - mv_w($size, $font, $t)) / 2), $y, $col, $font, $t); }
function sb_footer($im, array $c, string $left, int $no): void {
    $f = mv_font('Lora'); $sz = 36 * $c['k']; $col = mv_col($im, $c['ink'], 55); $y = $c['H'] - (int)($c['M'] * 0.55);
    mv_text($im, $sz, 0, $c['M'], $y, $col, $f, $left); $t = (string)$no; mv_text($im, $sz, 0, $c['W'] - $c['M'] - mv_w($sz, $f, $t), $y, $col, $f, $t);
}
/** Draws one photo, whole and uncropped, centred in a cell, with a mat and a soft shadow. Polaroid style tilts it and adds tape. Counts photos that will print soft. */
function sb_draw_photo($page, $src, array $cell, array $c, int &$soft, float $angle = 0.0): void {
    [$cx, $cy, $cw, $ch] = $cell; $k = $c['k']; $mat = (int)(($c['style']['frame'] === 'polaroid' ? 30 : 20) * $k);
    $sw = imagesx($src); $sh = imagesy($src); $pad = $angle != 0.0 ? (int)(60 * $k) : 0;
    $scale = min(($cw - 2 * $mat - $pad) / $sw, ($ch - 2 * $mat - $pad) / $sh); if ($scale <= 0) return;
    if ((SB_DPI / $scale) < SB_SOFT_DPI) $soft++;
    $pw = max(1, (int)round($sw * $scale)); $ph = max(1, (int)round($sh * $scale));
    $tw = $pw + 2 * $mat; $th = $ph + 2 * $mat + ($c['style']['frame'] === 'polaroid' ? (int)(70 * $k) : 0);
    if ($angle == 0.0) {
        $x = $cx + (int)(($cw - $tw) / 2); $y = $cy + (int)(($ch - $th) / 2);
        for ($i = 14; $i >= 0; $i--) imagefilledrectangle($page, $x - $i + 6, $y - $i + 10, $x + $tw + $i + 6, $y + $th + $i + 10, imagecolorallocatealpha($page, 40, 30, 15, 124));
        imagefilledrectangle($page, $x, $y, $x + $tw, $y + $th, imagecolorallocate($page, 255, 253, 248));
        imagecopyresampled($page, $src, $x + $mat, $y + $mat, 0, 0, $pw, $ph, $sw, $sh);
        return;
    }
    $m = (int)(50 * $k); $tile = imagecreatetruecolor($tw + 2 * $m, $th + 2 * $m); imagealphablending($tile, false); imagesavealpha($tile, true);
    imagefill($tile, 0, 0, imagecolorallocatealpha($tile, 0, 0, 0, 127)); imagealphablending($tile, true);
    for ($i = 16; $i >= 0; $i--) imagefilledrectangle($tile, $m - $i + 7, $m - $i + 12, $m + $tw + $i + 7, $m + $th + $i + 12, imagecolorallocatealpha($tile, 30, 20, 8, 124));
    imagefilledrectangle($tile, $m, $m, $m + $tw, $m + $th, imagecolorallocate($tile, 252, 250, 243));
    imagecopyresampled($tile, $src, $m + $mat, $m + $mat, 0, 0, $pw, $ph, $sw, $sh);
    $tp = (int)(190 * $k); $tq = (int)(52 * $k); $tx = $m + (int)($tw / 2) - (int)($tp / 2); $ty = $m - (int)(22 * $k);
    imagefilledpolygon($tile, [$tx, $ty + 3, $tx + $tp, $ty, $tx + $tp - 4, $ty + $tq, $tx + 3, $ty + $tq - 3], imagecolorallocatealpha($tile, 236, 224, 176, 36));
    imagealphablending($tile, false);
    $rot = imagerotate($tile, $angle, imagecolorallocatealpha($tile, 0, 0, 0, 127)); imagedestroy($tile); imagesavealpha($rot, true); imagealphablending($rot, true);
    imagecopy($page, $rot, $cx + (int)(($cw - imagesx($rot)) / 2), $cy + (int)(($ch - imagesy($rot)) / 2), 0, 0, imagesx($rot), imagesy($rot)); imagedestroy($rot);
}
/** Cells for 1 to 4 photos inside an area. $ars are the photos' width/height ratios. */
function sb_cells(array $ars, int $x, int $y, int $w, int $h, int $gap): array {
    $n = count($ars);
    if ($n === 1) return [[$x, $y, $w, $h]];
    if ($n === 2) {
        if ($ars[0] > 1.15 && $ars[1] > 1.15) { $hh = (int)(($h - $gap) / 2); return [[$x, $y, $w, $hh], [$x, $y + $hh + $gap, $w, $hh]]; }
        $ww = (int)(($w - $gap) / 2); return [[$x, $y, $ww, $h], [$x + $ww + $gap, $y, $ww, $h]];
    }
    if ($n === 3) {
        $top = (int)($h * 0.55); $bh = $h - $top - $gap; $ww = (int)(($w - $gap) / 2);
        return [[$x, $y, $w, $top], [$x, $y + $top + $gap, $ww, $bh], [$x + $ww + $gap, $y + $top + $gap, $ww, $bh]];
    }
    $ww = (int)(($w - $gap) / 2); $hh = (int)(($h - $gap) / 2);
    return [[$x, $y, $ww, $hh], [$x + $ww + $gap, $y, $ww, $hh], [$x, $y + $hh + $gap, $ww, $hh], [$x + $ww + $gap, $y + $hh + $gap, $ww, $hh]];
}
function sb_header($im, array $c, string $label): int {
    $acc = mv_col($im, $c['accent']); $f = mv_font('Lora'); $k = $c['k'];
    $y = $c['M'] + (int)(44 * $k); $lab = mv_wrap($label, 44 * $k, $f, $c['W'] - 2 * $c['M'], 2);
    foreach ($lab as $l) { mv_text($im, 44 * $k, 0, $c['M'], $y, $acc, $f, $l); $y += (int)(60 * $k); }
    imagesetthickness($im, max(2, (int)(3 * $k))); imageline($im, $c['M'], $y - (int)(30 * $k), $c['W'] - $c['M'], $y - (int)(30 * $k), mv_col($im, $c['accent'], 70));
    return $y + (int)(30 * $k);
}
function sb_title($im, array $c, string $title, int $y): int {
    if ($title === '') return $y; $k = $c['k']; $pf = mv_font('PlayfairDisplay'); $ink = mv_col($im, $c['ink']); $sz = 92 * $k;
    foreach (mv_wrap($title, $sz, $pf, $c['W'] - 2 * $c['M'], 2) as $l) { $y += (int)($sz * 1.05); mv_text($im, $sz, 0, $c['M'], $y, $ink, $pf, $l); $y += (int)($sz * 0.2); }
    return $y + (int)(30 * $k);
}
function sb_text_lines(array $c, string $text, string $mode, bool $roomy): array {
    $k = $c['k']; $text = trim(preg_replace('/[ \t]+/', ' ', $text)); if ($text === '') return ['lines' => [], 'size' => 0, 'lh' => 0, 'font' => ''];
    if ($mode === 'excerpt' || $roomy) { $sz = ($roomy ? 62 : 48) * $k; $font = mv_font($roomy ? 'Lora-Italic' : 'Lora'); $max = $roomy ? 16 : 14; $t = mb_strlen($text) > 1100 ? rtrim(mb_substr($text, 0, 1097), ' ,;:') . '…' : $text; $lh = 1.5; }
    else { $sz = 54 * $k; $font = mv_font('Lora-Italic'); $max = 4; $t = mv_first_sentence($text, 240); $lh = 1.45; }
    return ['lines' => $t === '' ? [] : mv_wrap($t, $sz, $font, $c['W'] - 2 * $c['M'], $max), 'size' => $sz, 'lh' => (int)($sz * $lh), 'font' => $font];
}
function sb_cover(array $c, $hero, string $by, string $title, string $where, string $dates, string $opening) {
    $W = $c['W']; $H = $c['H']; $k = $c['k']; $im = sb_canvas($W, $H, $c['style']['paper']);
    if ($hero) { $cv = mv_cover($hero, $W, $H); imagecopy($im, $cv, 0, 0, 0, 0, $W, $H); imagedestroy($cv); }
    $gh = (int)($H * 0.62); imagealphablending($im, true);
    for ($i = 0; $i < $gh; $i++) { $a = (int)round(127 - 127 * 0.93 * pow($i / ($gh - 1), 1.3)); imageline($im, 0, $H - $gh + $i, $W, $H - $gh + $i, imagecolorallocatealpha($im, 10, 28, 22, $a)); }
    $cream = mv_col($im, [250, 246, 236]); $gold = mv_col($im, [222, 182, 96]); $pf = mv_font('PlayfairDisplay'); $lo = mv_font('Lora'); $li = mv_font('Lora-Italic');
    $M = $c['M']; $sz = 150 * $k; $lines = mv_wrap($title, $sz, $pf, $W - 2 * $M, 3);
    if (mv_is_cjk($title)) { $sz = 130 * $k; $lines = mv_wrap($title, $sz, $pf, $W - 2 * $M, 3); }
    $open = $opening !== '' ? mv_wrap($opening, 50 * $k, $li, $W - 2 * $M, 2) : [];
    $tlh = (int)($sz * 1.14); $total = ($by !== '' ? (int)(110 * $k) : 0) + count($lines) * $tlh + (int)(40 * $k) + ($where !== '' ? (int)(84 * $k) : 0) + (int)(84 * $k) + ($open ? (int)(30 * $k) + count($open) * (int)(68 * $k) : 0);
    $y = $H - $M - $total;
    if ($by !== '') { $y += (int)(60 * $k); mv_text($im, 46 * $k, 0, $M, $y, $gold, $lo, $by); $y += (int)(50 * $k); }
    foreach ($lines as $l) { $y += (int)($sz * 0.9); mv_text($im, $sz, 0, $M, $y, $cream, $pf, $l); $y += $tlh - (int)($sz * 0.9); }
    $y += (int)(40 * $k);
    if ($where !== '') { $y += (int)(54 * $k); mv_text($im, 54 * $k, 0, $M, $y, $cream, $lo, $where); $y += (int)(30 * $k); }
    $y += (int)(54 * $k); mv_text($im, 54 * $k, 0, $M, $y, $gold, $li, $dates); $y += (int)(30 * $k);
    foreach ($open as $l) { $y += (int)(52 * $k); mv_text($im, 50 * $k, 0, $M, $y, $cream, $li, $l); $y += (int)(16 * $k); }
    return $im;
}
function sb_entry_page(array $c, array $it, array $photos, string $mode, int $no, int &$soft, int $uid) {
    $W = $c['W']; $H = $c['H']; $M = $c['M']; $k = $c['k']; $im = sb_canvas($W, $H, $c['style']['paper']);
    $y = sb_title($im, $c, $it['title'], sb_header($im, $c, $it['label']));
    $tx = sb_text_lines($c, $it['text'], $mode, false); $textH = $tx['lines'] ? count($tx['lines']) * $tx['lh'] + (int)(60 * $k) : 0;
    $y1 = $H - $M - (int)(100 * $k) - $textH; $gap = (int)(50 * $k);
    $imgs = []; $ars = [];
    foreach ($photos as $p) { $g = sb_load_photo($p, $uid); if ($g) { $imgs[] = $g; $ars[] = imagesx($g) / max(1, imagesy($g)); } }
    $cells = $imgs ? sb_cells($ars, $M, $y, $W - 2 * $M, $y1 - $y, $gap) : [];
    $tilts = [-2.2, 2.0, -1.6, 2.4];
    foreach ($imgs as $i => $g) { sb_draw_photo($im, $g, $cells[$i], $c, $soft, $c['style']['frame'] === 'polaroid' ? $tilts[$i % 4] : 0.0); imagedestroy($g); }
    if ($tx['lines']) { $ink = mv_col($im, $c['ink']); $ty = $y1 + (int)(40 * $k); foreach ($tx['lines'] as $l) { $ty += $tx['lh']; mv_text($im, $tx['size'], 0, $M, $ty - (int)($tx['lh'] * 0.2), $ink, $tx['font'], $l); } }
    sb_footer($im, $c, $c['title'], $no); return $im;
}
function sb_text_page(array $c, array $it, int $no) {
    $W = $c['W']; $H = $c['H']; $M = $c['M']; $im = sb_canvas($W, $H, $c['style']['paper']);
    $y = sb_title($im, $c, $it['title'], sb_header($im, $c, $it['label'])); $tx = sb_text_lines($c, $it['text'], 'excerpt', true); $ink = mv_col($im, $c['ink']);
    $y += (int)(60 * $c['k']); foreach ($tx['lines'] as $l) { $y += $tx['lh']; mv_text($im, $tx['size'], 0, $M, $y, $ink, $tx['font'], $l); }
    sb_footer($im, $c, $c['title'], $no); return $im;
}
function sb_more_page(array $c, array $it, array $photos, int $no, int &$soft, int $uid) {
    $W = $c['W']; $H = $c['H']; $M = $c['M']; $k = $c['k']; $im = sb_canvas($W, $H, $c['style']['paper']);
    $y = sb_header($im, $c, tt('scrapbook.more_from', ['label' => $it['short']])) + (int)(20 * $k); $y1 = $H - $M - (int)(100 * $k);
    $imgs = []; $ars = []; foreach ($photos as $p) { $g = sb_load_photo($p, $uid); if ($g) { $imgs[] = $g; $ars[] = imagesx($g) / max(1, imagesy($g)); } }
    $cells = $imgs ? sb_cells($ars, $M, $y, $W - 2 * $M, $y1 - $y, (int)(50 * $k)) : []; $tilts = [-2.0, 1.8, 1.5, -2.2];
    foreach ($imgs as $i => $g) { sb_draw_photo($im, $g, $cells[$i], $c, $soft, $c['style']['frame'] === 'polaroid' ? $tilts[$i % 4] : 0.0); imagedestroy($g); }
    sb_footer($im, $c, $c['title'], $no); return $im;
}
function sb_end_page(array $c, string $closing, string $stats) {
    $W = $c['W']; $H = $c['H']; $k = $c['k']; $im = sb_canvas($W, $H, $c['style']['paper']); $ink = mv_col($im, $c['ink']); $acc = mv_col($im, $c['accent']);
    $y = (int)($H * 0.38); sb_center($im, $W, $y, tt('movie.until_next_time'), 130 * $k, mv_font('PlayfairDisplay-Italic'), $ink); $y += (int)(80 * $k);
    imagesetthickness($im, max(2, (int)(4 * $k))); imageline($im, (int)($W / 2 - 110 * $k), $y, (int)($W / 2 + 110 * $k), $y, $acc); $y += (int)(120 * $k);
    if ($closing !== '') foreach (mv_wrap($closing, 54 * $k, mv_font('Lora'), (int)($W * 0.62), 4) as $l) { sb_center($im, $W, $y, $l, 54 * $k, mv_font('Lora'), $ink); $y += (int)(76 * $k); }
    if ($stats !== '') { $y += (int)(30 * $k); sb_center($im, $W, $y, $stats, 44 * $k, mv_font('Lora-Italic'), $acc); }
    sb_center($im, $W, $H - $c['M'], tt('movie.tagline'), 38 * $k, mv_font('Lora-Italic'), mv_col($im, $c['ink'], 45));
    return $im;
}

/* ---------- the whole render ---------- */
function sb_story(int $uid, int $eventId): array {
    $q = db()->prepare('SELECT * FROM events WHERE id = ? AND user_id = ?'); $q->execute([$eventId, $uid]); $ev = $q->fetch();
    if (!$ev) throw new RuntimeException('Experience not found.');
    $secs = []; $q = db()->prepare('SELECT id, name FROM event_sections WHERE event_id = ?'); $q->execute([$eventId]);
    foreach ($q->fetchAll() as $s) $secs[(int)$s['id']] = $s['name'];
    $q = db()->prepare('SELECT * FROM entries WHERE user_id = ? AND event_id = ? ORDER BY entry_date, id'); $q->execute([$uid, $eventId]); $entries = $q->fetchAll();
    $ph = photos_for_entries(array_column($entries, 'id'));
    $multi = ((strtotime($ev['end_date']) - strtotime($ev['start_date'])) / 86400) >= 1; $items = []; $photoTotal = 0;
    foreach ($entries as $en) {
        $day = (int)round((strtotime($en['entry_date']) - strtotime($ev['start_date'])) / 86400) + 1;
        $label = ($multi ? tt('movie.day_n', ['n' => $day]) . '  ·  ' : '') . ldate('l, F j', strtotime($en['entry_date'])) . (!empty($en['section_id']) && isset($secs[(int)$en['section_id']]) ? '  ·  ' . $secs[(int)$en['section_id']] : '');
        $text = trim(implode("\n\n", array_column(body_parts((string)$en['body'], (int)$en['created_at']), 'text')));
        $photos = $ph[$en['id']] ?? []; $photoTotal += count($photos);
        $items[] = ['label' => $label, 'short' => ($multi ? tt('movie.day_n', ['n' => $day]) : ldate('F j', strtotime($en['entry_date']))), 'title' => trim((string)$en['title']), 'text' => $text, 'photos' => $photos];
    }
    return ['event' => $ev, 'items' => $items, 'multi' => $multi, 'photos' => $photoTotal, 'entries' => count($entries)];
}
function sb_render(array $sb, callable $progress): array {
    @ini_set('memory_limit', '768M'); @set_time_limit(0);
    $uid = (int)$sb['user_id']; $style = SB_STYLES[$sb['style']] ?? SB_STYLES['classic']; $size = SB_SIZES[$sb['size']] ?? SB_SIZES['letter'];
    $u = db()->prepare('SELECT name, lang FROM users WHERE id = ?'); $u->execute([$uid]); $ur = $u->fetch(); $name = trim((string)($ur['name'] ?? '')); lang_force((string)($ur['lang'] ?? '') ?: 'en');
    $story = sb_story($uid, (int)$sb['event_id']); $ev = $story['event']; if ($story['photos'] < 1) throw new RuntimeException('Add a photo to this experience first.');
    $c = ['W' => $size['w'], 'H' => $size['h'], 'k' => $size['w'] / 2550, 'M' => (int)(170 * $size['w'] / 2550), 'style' => $style, 'ink' => $style['ink'], 'accent' => $style['accent'], 'title' => (string)$ev['title']];
    $plan = [['cover']];
    foreach ($story['items'] as $it) {
        $ph = $it['photos'];
        if ($ph) {
            $np = (int)ceil(count($ph) / 4); $per = (int)ceil(count($ph) / $np); $chunks = array_chunk($ph, $per);
            $plan[] = ['entry', $it, array_shift($chunks)];
            foreach ($chunks as $chunk) $plan[] = ['more', $it, $chunk];
        } elseif ($it['title'] !== '' || mb_strlen($it['text']) >= 40) $plan[] = ['text', $it];
    }
    $plan[] = ['end'];
    if (count($plan) > SB_MAX_PAGES) throw new RuntimeException(tt('scrapbook.too_long', ['n' => SB_MAX_PAGES]));
    $final = sb_dir() . '/sb-' . (int)$sb['id'] . '.pdf'; $tmp = $final . '.part'; $pdf = new SbPdf($tmp, (string)$ev['title']); $jpg = sb_dir() . '/work-' . (int)$sb['id'] . '.jpg';
    $soft = 0; $no = 0; $total = count($plan);
    try {
        foreach ($plan as $i => $pg) {
            $no++; $im = null;
            if ($pg[0] === 'cover') {
                $first = null; foreach ($story['items'] as $it) if ($it['photos']) { $first = $it['photos'][0]; break; }
                $hero = $first ? sb_load_photo($first, $uid) : null; $sp = $ev['end_date'] !== $ev['start_date'] ? ldate('F j', strtotime($ev['start_date'])) . ' – ' . ldate('F j, Y', strtotime($ev['end_date'])) : ldate('F j, Y', strtotime($ev['start_date']));
                $im = sb_cover($c, $hero, $name !== '' ? tt('movie.memory_by', ['name' => $name]) : '', (string)$ev['title'], (string)$ev['location'], $sp, (string)$sb['subtitle']); if ($hero) imagedestroy($hero);
            } elseif ($pg[0] === 'entry') $im = sb_entry_page($c, $pg[1], $pg[2], (string)$sb['text_mode'], $no, $soft, $uid);
            elseif ($pg[0] === 'text') $im = sb_text_page($c, $pg[1], $no);
            elseif ($pg[0] === 'more') $im = sb_more_page($c, $pg[1], $pg[2], $no, $soft, $uid);
            else {
                $stats = ($story['multi'] ? tn('movie.stat_days', (int)round((strtotime($ev['end_date']) - strtotime($ev['start_date'])) / 86400) + 1) . '  ·  ' : '') . tn('movie.stat_moments', (int)$story['entries']) . '  ·  ' . tn('msg.n_photos', (int)$story['photos']);
                $im = sb_end_page($c, (string)$sb['closing'], $stats);
            }
            imageinterlace($im, false); imagejpeg($im, $jpg, 88); imagedestroy($im);
            $pdf->addJpeg((string)file_get_contents($jpg), $c['W'], $c['H']); $progress((int)(($i + 1) * 97 / $total));
        }
        $pdf->finish(); @unlink($jpg); rename($tmp, $final); @chmod($final, 0600);
    } catch (Throwable $t) { @unlink($jpg); @unlink($tmp); throw $t; }
    return ['file' => $final, 'bytes' => filesize($final), 'pages' => $total, 'soft' => $soft];
}
function sb_worker_run(): int {
    $lock = fopen(data_dir() . '/scrapbook.lock', 'c'); if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return 0;
    $pdo = db(); $n = 0;
    $pdo->exec("UPDATE scrapbooks SET status = 'failed', error = 'The scrapbook was interrupted. Please try again.' WHERE status = 'rendering' AND started_at < " . (time() - 3600));
    while (true) {
        $sb = $pdo->query("SELECT * FROM scrapbooks WHERE status = 'queued' ORDER BY id LIMIT 1")->fetch(); if (!$sb) break;
        $id = (int)$sb['id']; $pdo->prepare("UPDATE scrapbooks SET status = 'rendering', started_at = ?, progress = 1 WHERE id = ?")->execute([time(), $id]);
        try {
            $r = sb_render($sb, function (int $p) use ($pdo, $id) { $pdo->prepare('UPDATE scrapbooks SET progress = ? WHERE id = ?')->execute([min(99, $p), $id]); });
            $pdo->prepare("UPDATE scrapbooks SET status = 'done', progress = 100, file = ?, bytes = ?, pages = ?, soft = ?, finished_at = ?, error = '' WHERE id = ?")->execute([$r['file'], $r['bytes'], $r['pages'], $r['soft'], time(), $id]);
        } catch (Throwable $t) {
            @file_put_contents(data_dir() . '/error.log', date('c') . ' scrapbook ' . $id . ' ' . $t->getMessage() . "\n", FILE_APPEND);
            $msg = $t instanceof RuntimeException ? $t->getMessage() : 'Something went wrong while making the scrapbook. Please try again.';
            $pdo->prepare("UPDATE scrapbooks SET status = 'failed', error = ?, finished_at = ? WHERE id = ?")->execute([$msg, time(), $id]);
        }
        $n++;
    }
    flock($lock, LOCK_UN); return $n;
}

/** Signed-in routes. Called from index.php once the member is known. */
function scrapbooks_member_routes(int $uid, array $user, bool $post, string $path): void {
    if ($post && preg_match('#^/experiences/(\d+)/scrapbook$#', $path, $mm)) {
        $ev = db()->prepare('SELECT * FROM events WHERE id = ? AND user_id = ?'); $ev->execute([(int)$mm[1], $uid]); $ev = $ev->fetch(); if (!$ev) redirect(url('/experiences'));
        $back = url('/experiences/' . $ev['id']) . '#scrapbook';
        $n = db()->prepare('SELECT COUNT(*) FROM entry_photos p JOIN entries e ON e.id = p.entry_id WHERE e.event_id = ? AND e.user_id = ?'); $n->execute([$ev['id'], $uid]);
        $act = db()->prepare("SELECT COUNT(*) FROM scrapbooks WHERE user_id = ? AND status IN ('queued','rendering')"); $act->execute([$uid]);
        $day = db()->prepare('SELECT COUNT(*) FROM scrapbooks WHERE user_id = ? AND created_at > ?'); $day->execute([$uid, time() - 86400]);
        if (!billing_can($uid, 'scrapbook')) { flash('err', tt('billing.err_limited')); redirect(url('/billing')); }
        if ((int)$n->fetchColumn() < 1) flash('err', tt('scrapbook.need_photo'));
        elseif ((int)$act->fetchColumn() > 0) flash('err', tt('scrapbook.one_at_a_time'));
        elseif ((int)$day->fetchColumn() >= 6) flash('err', tt('scrapbook.plenty_today'));
        else {
            $style = isset(SB_STYLES[$_POST['style'] ?? '']) ? $_POST['style'] : 'classic'; $size = isset(SB_SIZES[$_POST['size'] ?? '']) ? $_POST['size'] : 'letter';
            $mode = ($_POST['text_mode'] ?? '') === 'excerpt' ? 'excerpt' : 'captions';
            $old = db()->prepare("SELECT * FROM scrapbooks WHERE event_id = ? AND user_id = ? AND status IN ('done','failed') ORDER BY id DESC"); $old->execute([$ev['id'], $uid]);
            foreach (array_slice($old->fetchAll(), 2) as $s) { sb_delete_files($s); db()->prepare('DELETE FROM scrapbooks WHERE id = ?')->execute([$s['id']]); }
            db()->prepare('INSERT INTO scrapbooks (user_id, event_id, style, size, text_mode, subtitle, closing, created_at) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$uid, $ev['id'], $style, $size, $mode, mb_substr(trim((string)($_POST['subtitle'] ?? '')), 0, 90), mb_substr(trim((string)($_POST['closing'] ?? '')), 0, 160), time()]);
            mv_kick(); flash('ok', tt('scrapbook.queued'));
        }
        redirect($back);
    }
    if (preg_match('#^/scrapbooks/(\d+)/(status|file|delete)$#', $path, $mm)) {
        $q = db()->prepare('SELECT * FROM scrapbooks WHERE id = ? AND user_id = ?'); $q->execute([(int)$mm[1], $uid]); $s = $q->fetch();
        if (!$s) { http_response_code(404); exit; }
        if ($mm[2] === 'status') json_out(['ok' => true, 'status' => $s['status'], 'progress' => (int)$s['progress'], 'error' => $s['error']]);
        if ($mm[2] === 'delete' && $post) { sb_delete_files($s); db()->prepare('DELETE FROM scrapbooks WHERE id = ?')->execute([$s['id']]); flash('ok', tt('scrapbook.deleted')); redirect(url('/experiences/' . $s['event_id']) . '#scrapbook'); }
        if ($mm[2] === 'file') {
            if ($s['status'] !== 'done' || !is_file($s['file'])) { http_response_code(404); exit; }
            $ev = db()->prepare('SELECT title FROM events WHERE id = ?'); $ev->execute([$s['event_id']]); $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$ev->fetchColumn())), '-') ?: 'scrapbook';
            header('Content-Type: application/pdf'); header('Content-Length: ' . filesize($s['file'])); header('Cache-Control: private, max-age=3600'); header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="moriwella-' . $slug . '-scrapbook.pdf"');
            readfile($s['file']); exit;
        }
    }
}
