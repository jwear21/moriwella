<?php
declare(strict_types=1);
/* Translates phrases that exist in English but are missing in another language, using the Claude API.
   The API key lives only in src/config.php ('anthropic_api_key'). Results go to a separate file per language in the private data folder
   (lang-auto/<code>.php), never into the website's own language files, so hand-written translations always win and a deploy never erases them.
   Copyright (c) JSW Software Designs */

const TR_BATCH = 40;                       /* phrases per request */
const TR_IN_PER_M = 3.0;  const TR_OUT_PER_M = 15.0;   /* dollars per million tokens, for the estimate (Sonnet class) */

function tr_auto_dir(): string { $d = data_dir() . '/lang-auto'; if (!is_dir($d)) @mkdir($d, 0700, true); return $d; }
function tr_auto_file(string $code): string { return tr_auto_dir() . '/' . $code . '.php'; }
function tr_auto_load(string $code): array {
    $f = tr_auto_file($code); if (!is_file($f)) return [];
    $a = @include $f; return is_array($a) ? $a : [];
}
function tr_codes(): array { $c = array_keys(langs_available()); return array_values(array_filter($c, fn($x) => $x !== 'en')); }
/** English phrases a language lacks: [key => english]. */
function tr_missing(string $code): array {
    $en = lang_load('en'); $have = lang_load($code); $out = [];
    foreach ($en as $k => $v) {
        if (!is_string($v) || $v === '' || $k[0] === '_') continue;
        if (!array_key_exists($k, $have)) $out[$k] = $v;
    }
    return $out;
}
function tr_overview(): array {
    $rows = []; $chars = 0; $n = 0;
    foreach (tr_codes() as $c) { $m = tr_missing($c); $rows[$c] = ['n' => count($m), 'sample' => array_slice(array_keys($m), 0, 3)]; $n += count($m); foreach ($m as $v) $chars += strlen($v); }
    /* tokens: English in ~chars/4 plus instructions per batch; output is about 1.4x in other scripts */
    $inTok = $chars / 4 + (int)ceil($n / TR_BATCH) * 350; $outTok = $chars / 4 * 1.6 + $n * 8;
    return ['rows' => $rows, 'total' => $n, 'cost' => round($inTok / 1e6 * TR_IN_PER_M + $outTok / 1e6 * TR_OUT_PER_M, 3)];
}
function tr_placeholders(string $s): array { preg_match_all('/\{[a-z0-9_]+\}|%[sd]|<[^>]+>|&[a-z#0-9]+;/i', $s, $m); sort($m[0]); return $m[0]; }
function tr_log(array $usage): void {
    $f = tr_auto_dir() . '/usage.json'; $u = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $u['in'] = ($u['in'] ?? 0) + (int)($usage['input_tokens'] ?? 0); $u['out'] = ($u['out'] ?? 0) + (int)($usage['output_tokens'] ?? 0); $u['calls'] = ($u['calls'] ?? 0) + 1;
    file_put_contents($f, json_encode($u), LOCK_EX);
}
function tr_spent(): array { $f = tr_auto_dir() . '/usage.json'; $u = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : []; $u += ['in' => 0, 'out' => 0, 'calls' => 0]; $u['cost'] = round($u['in'] / 1e6 * TR_IN_PER_M + $u['out'] / 1e6 * TR_OUT_PER_M, 3); return $u; }

/** One API request: a batch of [key => english] into one language. Returns [key => translation] (only those that pass the checks). */
function tr_request(string $code, array $batch): array {
    $key = (string)cfg('anthropic_api_key', ''); if ($key === '') throw new RuntimeException('No anthropic_api_key in src/config.php.');
    $name = lang_english_name($code) ?: $code;
    $extra = $code === 'zh-HK' ? ' Use Traditional Chinese characters and Hong Kong Cantonese-speaking usage.' : ($code === 'zh' ? ' Use Simplified Chinese.' : '');
    $prompt = "You translate the interface text of Moriwella, a warm family journal and memory app, from English into $name.$extra\n"
        . "Rules: keep every placeholder exactly as written ({name}, {n}, %s, HTML tags, &entities;); keep the friendly, plain, kind tone; keep brand and product names (Moriwella) unchanged; do not add or drop sentences; use natural everyday wording a native speaker would use; "
        . "keep it about as short as the English so it fits on buttons. Reply with ONLY a JSON object mapping each key to its translation, nothing else.\n\n" . json_encode($batch, JSON_UNESCAPED_UNICODE);
    $body = json_encode(['model' => (string)cfg('anthropic_model', 'claude-sonnet-5-5'), 'max_tokens' => 8000, 'messages' => [['role' => 'user', 'content' => $prompt]]]);
    $ch = curl_init((string)cfg('anthropic_url', 'https://api.anthropic.com/v1/messages'));
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 110,
        CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01']]);
    $raw = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($raw === false) throw new RuntimeException('Could not reach the Claude API: ' . $err);
    $j = json_decode((string)$raw, true);
    if ($http !== 200 || !is_array($j)) throw new RuntimeException('Claude API error ' . $http . ': ' . substr((string)($j['error']['message'] ?? $raw), 0, 200));
    tr_log((array)($j['usage'] ?? []));
    $text = ''; foreach ((array)($j['content'] ?? []) as $b) if (($b['type'] ?? '') === 'text') $text .= $b['text'];
    if (preg_match('/\{.*\}/s', $text, $m)) $text = $m[0];
    $got = json_decode($text, true); if (!is_array($got)) throw new RuntimeException('The reply was not valid JSON.');
    $ok = [];
    foreach ($batch as $k => $en) {
        $t = $got[$k] ?? null; if (!is_string($t) || trim($t) === '') continue;
        if (tr_placeholders($t) !== tr_placeholders($en)) continue;     /* a lost or changed placeholder would break the page */
        $ok[$k] = $t;
    }
    return $ok;
}
/** Translates up to $limit missing phrases for one language and saves them. Returns [done, left]. */
function tr_run(string $code, int $limit = 200): array {
    $miss = tr_missing($code); $done = 0;
    foreach (array_chunk(array_slice($miss, 0, $limit, true), TR_BATCH, true) as $batch) {
        $ok = tr_request($code, $batch); if (!$ok) continue;
        $cur = tr_auto_load($code) + []; foreach ($ok as $k => $v) $cur[$k] = $v;
        $tmp = tr_auto_file($code) . '.tmp'; file_put_contents($tmp, "<?php\n/* Machine-translated by the Claude API. Add a phrase to the language's own file to override it. */\nreturn " . var_export($cur, true) . ";\n", LOCK_EX);
        rename($tmp, tr_auto_file($code)); $done += count($ok);
    }
    return ['done' => $done, 'left' => max(0, count($miss) - $done)];
}
