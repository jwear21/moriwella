<?php
/* Memory-movie worker. Run from cron every minute:  php /path/to/app/worker.php
   Renders queued movies one at a time. It refuses to run from the web.
   "php worker.php --selftest" checks that this server can render (fonts, GD, FFmpeg) without touching any data.
   Copyright (c) JSW Software Designs */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/src/lib.php';
require __DIR__ . '/src/photos.php';
require __DIR__ . '/src/movie_render.php';
require __DIR__ . '/src/albums.php';
require __DIR__ . '/src/scrapbooks.php';
require_once __DIR__ . '/src/referrals.php';
require_once __DIR__ . '/src/stripe.php';

if (in_array('--selftest', $argv ?? [], true)) {
    $t0 = microtime(true); $dir = sys_get_temp_dir() . '/mw-selftest-' . getmypid(); @mkdir($dir, 0700, true);
    echo 'php ' . PHP_VERSION . ' gd=' . (function_exists('imagettftext') ? 'ok' : 'MISSING') . ' ffmpeg=' . trim((string)shell_exec(escapeshellarg(mv_ffmpeg()) . ' -version 2>&1 | head -1')) . "\n";
    try {
        $files = [];
        foreach ([0, 1, 2] as $k) {
            $im = imagecreatetruecolor(1600, 1000); $c = [[200, 90, 60], [60, 140, 200], [90, 170, 90]][$k];
            for ($y = 0; $y < 1000; $y += 4) imagefilledrectangle($im, 0, $y, 1600, $y + 3, imagecolorallocate($im, (int)($c[0] * (1 - $y / 1600)), (int)($c[1] * (1 - $y / 1600)), (int)($c[2] * (1 - $y / 1600))));
            imagefilledellipse($im, 800, 500, 380, 380, imagecolorallocate($im, 250, 230, 170)); $f = "$dir/src$k.jpg"; imagejpeg($im, $f, 90); imagedestroy($im); $files[] = $f;
        }
        $th = MV_THEMES['polaroid']; $group = [];
        foreach ($files as $k => $f) $group[] = ['file' => $f, 'label' => 'Day ' . ($k + 1) . '  ·  Self test', 'line' => 'Photo number ' . ($k + 1), 'date' => '2026-01-0' . ($k + 1)];
        $s = microtime(true); mv_polaroid_clip($th, mv_kraft(), $group, $dir, 'g', "$dir/g.mp4"); echo 'collage clip ' . round(microtime(true) - $s, 1) . "s\n";
        $im = mv_load($files[0]); mv_save_jpg(mv_scene_frame($im), "$dir/p.jpg"); mv_caption(MV_THEMES['evergreen'], 'Day 1', 'A line from the journal', "$dir/c.png");
        $s = microtime(true); mv_photo_clip("$dir/p.jpg", "$dir/c.png", "$dir/s.mp4", 0); echo 'photo clip ' . round(microtime(true) - $s, 1) . "s\n";
        mv_title_card(MV_THEMES['evergreen'], $im, 'A memory by Self Test', 'Moriwella self test', 'Anywhere', 'January 1 – 3, 2026', '', "$dir/t.png");
        $s = microtime(true); mv_still_clip("$dir/t.png", "$dir/t.mp4", 4.0); echo 'card clip ' . round(microtime(true) - $s, 1) . "s\n";
        echo 'OK total ' . round(microtime(true) - $t0, 1) . "s, collage " . round(filesize("$dir/g.mp4") / 1024) . " KB\n";
    } catch (Throwable $e) { echo 'FAILED: ' . $e->getMessage() . "\n"; }
    foreach (glob("$dir/*") ?: [] as $f) @unlink($f); @rmdir($dir); exit;
}
mv_worker_run();
sb_worker_run();
try { referrals_worker_run(); } catch (Throwable $e) { billing_log('referral worker: ' . $e->getMessage()); }
