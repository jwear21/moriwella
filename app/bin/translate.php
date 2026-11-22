<?php
/* Command line / cron: php app/bin/translate.php [--check] [code ...]
   --check  only lists missing phrases (exit code 1 if any); otherwise translates what is missing with the Claude API.
   Suggested cron (weekly, Sunday 3:30): /usr/local/bin/php /home/coyotejo/moriwella.com/app/bin/translate.php   Copyright (c) JSW Software Designs */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/lib.php'; require __DIR__ . '/../src/i18n.php'; require __DIR__ . '/../src/translate.php';
$args = array_slice($argv, 1); $check = in_array('--check', $args, true); $codes = array_values(array_filter($args, fn($a) => $a[0] !== '-')) ?: tr_codes();
$bad = 0;
if (in_array('--legal', $args, true) || !$args) {      /* legal pages: only those whose English changed or that are missing */
    require __DIR__ . '/../src/legal.php';
    foreach (array_keys(legal_docs()) as $d) foreach (tr_codes() as $c) {
        if (legal_load($d, $c) !== null) continue; $bad++;
        if ($check) { echo "legal $d/$c: needs translation\n"; continue; }
        try { $n = count(legal_pieces($d)); for ($i = 0; $i < $n; $i++) legal_run($d, $c, $i); echo "legal $d/$c: translated\n"; } catch (Throwable $e) { echo "legal $d/$c: " . $e->getMessage() . "\n"; exit(2); }
    }
    $args = array_values(array_filter($args, fn($a) => $a !== '--legal')); $codes = array_values(array_filter($args, fn($a) => $a[0] !== '-')) ?: tr_codes();
}
foreach ($codes as $c) {
    $n = count(tr_missing($c)); if (!$n) continue; $bad += $n;
    if ($check) { echo "$c: $n missing\n"; continue; }
    try { $r = tr_run($c, 2000); echo "$c: translated {$r['done']}, left {$r['left']}\n"; } catch (Throwable $e) { echo "$c: " . $e->getMessage() . "\n"; exit(2); }
}
if ($check) exit($bad ? 1 : 0);
