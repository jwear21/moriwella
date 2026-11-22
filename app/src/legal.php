<?php
declare(strict_types=1);
/* Translated legal pages. The English files in the site root (privacy.html, terms.html, delete-account.html) stay the official text and are
   what the Play Store links to. A translation is a courtesy copy made with the Claude API: it is kept in the private data folder (legal/),
   is checked to have exactly the same HTML structure as the English, and is shown with a notice that the English version governs.
   The English text is cut into pieces at its <h2> headings so each request is short. A translation is marked stale when the English changes.
   Copyright (c) JSW Software Designs */

const LEGAL_PIECE_CHARS = 2600;

function legal_docs(): array { return ['privacy' => 'privacy.html', 'terms' => 'terms.html', 'delete-account' => 'delete-account.html']; }
function legal_root(): string { return dirname(__DIR__, 2); }
function legal_dir(): string { $d = data_dir() . '/legal'; if (!is_dir($d)) @mkdir($d, 0700, true); return $d; }

/** The English page cut into head (before <main>), main (the text), and tail (after </main>). */
function legal_parts(string $doc): ?array {
    $docs = legal_docs(); if (!isset($docs[$doc])) return null;
    $f = legal_root() . '/' . $docs[$doc]; if (!is_file($f)) return null;
    $h = (string)file_get_contents($f);
    if (!preg_match('/^(.*?<main[^>]*>)(.*?)(<\/main>.*)$/s', $h, $m)) return null;
    return ['head' => $m[1], 'main' => trim($m[2]), 'tail' => $m[3]];
}
function legal_hash(string $doc): string { $p = legal_parts($doc); return $p ? substr(sha1($p['main']), 0, 12) : ''; }

/** English main text cut into pieces: each starts at an <h2> (the first piece holds the title and intro) and stays under about LEGAL_PIECE_CHARS. */
function legal_pieces(string $doc): array {
    $p = legal_parts($doc); if (!$p) return [];
    $secs = preg_split('/(?=<h2[ >])/', $p['main'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = []; $cur = '';
    foreach ($secs as $s) {
        if ($cur !== '' && strlen($cur) + strlen($s) > LEGAL_PIECE_CHARS) { $out[] = trim($cur); $cur = ''; }
        $cur .= ($cur === '' ? '' : "\n") . trim($s);
    }
    if ($cur !== '') $out[] = trim($cur);
    return $out;
}

function legal_file(string $doc, string $code): string { return legal_dir() . '/' . $doc . '-' . $code . '.html'; }
function legal_part_file(string $doc, string $code, int $i): string { return legal_dir() . '/' . $doc . '-' . $code . '.p' . $i; }

/** Reads a finished translation, or null when there is none or it no longer matches the English. */
function legal_load(string $doc, string $code): ?string {
    $f = legal_file($doc, $code); if (!is_file($f)) return null;
    $s = (string)file_get_contents($f);
    if (!preg_match('/^<!--src:([a-f0-9]+)-->\n/', $s, $m) || $m[1] !== legal_hash($doc)) return null;
    return substr($s, strlen($m[0]));
}
/** Languages that have a current translation of the page. */
function legal_available(string $doc): array {
    $out = ['en' => (langs_available()['en'] ?? 'English')];
    foreach (langs_available() as $c => $n) if ($c !== 'en' && legal_load($doc, $c) !== null) $out[$c] = $n;
    return $out;
}

/** Same HTML tags, entities and links in the same amount as the English: a translation that breaks the markup is thrown away. */
function legal_same_markup(string $en, string $tr): bool {
    return tr_placeholders($en) === tr_placeholders($tr)
        && preg_match_all('/href="[^"]*"/', $en, $a) === preg_match_all('/href="[^"]*"/', $tr, $b) && ($a[0] ?? []) === ($b[0] ?? []);
}

function legal_request(string $code, string $html): string {
    $key = (string)cfg('anthropic_api_key', ''); if ($key === '') throw new RuntimeException('No anthropic_api_key in src/config.php.');
    $name = lang_english_name($code) ?: $code;
    $extra = $code === 'zh-HK' ? ' Use Traditional Chinese characters and Hong Kong usage.' : ($code === 'zh' ? ' Use Simplified Chinese.' : '');
    $prompt = "Translate this part of the privacy policy / terms of Moriwella, a family journal app, from English into $name.$extra\n"
        . "Rules: translate all visible text faithfully and completely, in clear plain everyday language a native speaker would use, keeping the exact legal meaning; do not add, drop, soften or reorder anything. "
        . "Keep every HTML tag, attribute, link address (href) and &entity; exactly as written and in the same places. Keep the names Moriwella, JSW Software Designs, Stripe, ShockHosting, GDPR, HTTPS, Google Play and email addresses unchanged. "
        . "Translate dates into the language's usual form. Reply with ONLY the translated HTML, no commentary, no code fences.\n\n" . $html;
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
    $text = trim(preg_replace('/^```[a-z]*\n|\n```$/', '', trim($text)));
    if (($j['stop_reason'] ?? '') === 'max_tokens') throw new RuntimeException('The reply was cut off.');
    return $text;
}

/** Translates one piece ($i) of one page into one language and, after the last piece, assembles the page. Returns [done pieces, total pieces, finished]. */
function legal_run(string $doc, string $code, int $i): array {
    $pieces = legal_pieces($doc); $n = count($pieces);
    if (!$n || $i < 0 || $i >= $n) throw new RuntimeException('Unknown page or piece.');
    $hash = legal_hash($doc); $pf = legal_part_file($doc, $code, $i);
    $tr = legal_request($code, $pieces[$i]);
    if (!legal_same_markup($pieces[$i], $tr)) { $tr = legal_request($code, $pieces[$i]); }       /* one retry */
    if (!legal_same_markup($pieces[$i], $tr)) throw new RuntimeException("Piece " . ($i + 1) . " of $doc ($code) did not keep the page structure. Try again.");
    file_put_contents($pf, $hash . "\n" . $tr, LOCK_EX);
    $all = [];
    for ($k = 0; $k < $n; $k++) {
        $f = legal_part_file($doc, $code, $k); if (!is_file($f)) return ['done' => $i + 1, 'total' => $n, 'finished' => false];
        $s = (string)file_get_contents($f); [$h, $body] = explode("\n", $s, 2) + ['', ''];
        if ($h !== $hash) return ['done' => $i + 1, 'total' => $n, 'finished' => false];
        $all[] = $body;
    }
    $tmp = legal_file($doc, $code) . '.tmp'; file_put_contents($tmp, '<!--src:' . $hash . "-->\n" . implode("\n", $all), LOCK_EX); rename($tmp, legal_file($doc, $code));
    for ($k = 0; $k < $n; $k++) @unlink(legal_part_file($doc, $code, $k));
    return ['done' => $n, 'total' => $n, 'finished' => true];
}

/** For the admin page: per page, per language, whether the translation is current. */
function legal_overview(): array {
    $rows = []; $todo = 0;
    foreach (array_keys(legal_docs()) as $d) foreach (tr_codes() as $c) { $ok = legal_load($d, $c) !== null; $rows[$d][$c] = $ok; if (!$ok) $todo++; }
    return ['rows' => $rows, 'todo' => $todo, 'pieces' => array_map(fn($d) => count(legal_pieces($d)), array_keys(legal_docs()))];
}

/* ---------- public pages: /legal/<page>?lang=xx ---------- */
function legal_choose_lang(string $doc): string {
    $have = legal_available($doc);
    foreach ([(string)($_GET['lang'] ?? ''), (string)($_COOKIE['mw_legal_lang'] ?? '')] as $w) if ($w !== '' && isset($have[$w])) return $w;
    return 'en';
}
function legal_routes(string $path): void {
    if ($path === '/legal/langs') {
        header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: public, max-age=300');
        $doc = (string)($_GET['doc'] ?? 'privacy'); if (!isset(legal_docs()[$doc])) $doc = 'privacy';
        echo json_encode(['doc' => $doc, 'langs' => legal_available($doc), 'rtl' => ['ar', 'ur', 'he', 'fa']], JSON_UNESCAPED_UNICODE); exit;
    }
    if (!preg_match('#^/legal/(privacy|terms|delete-account)$#', $path, $m)) return;
    $doc = $m[1]; $p = legal_parts($doc); if (!$p) { http_response_code(404); exit; }
    $code = legal_choose_lang($doc);
    if (isset($_GET['lang']) && $_GET['lang'] === $code) setcookie('mw_legal_lang', $code, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
    lang_force($code);
    $main = $code === 'en' ? $p['main'] : (legal_load($doc, $code) ?? $p['main']);
    $h = $p['head']; $tail = $p['tail'];
    $dir = text_dir();
    $h = preg_replace('/<html lang="[^"]*"/', '<html lang="' . e($code) . '" dir="' . $dir . '"', $h, 1);
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $main, $t)) $h = preg_replace('/<title>.*?<\/title>/s', '<title>' . e(trim(strip_tags($t[1]))) . ' | Moriwella</title>', $h, 1);
    $link = function (string $d) use ($code) { return url('/legal/' . $d) . ($code === 'en' ? '' : '?lang=' . rawurlencode($code)); };
    $map = ['/delete-account.html' => $link('delete-account'), '/privacy.html' => $link('privacy'), '/terms.html' => $link('terms')];
    $main = strtr($main, array_combine(array_map(fn($k) => 'href="' . $k . '"', array_keys($map)), array_map(fn($v) => 'href="' . $v . '"', array_values($map))));
    $opts = ''; foreach (legal_available($doc) as $c => $n) $opts .= '<option value="' . e($c) . '"' . ($c === $code ? ' selected' : '') . '>' . e($n) . '</option>';
    $bar = '<div class="lglang" style="text-align:end;margin:0 0 1rem"><label>' . e(t('legal.language')) . ' <select onchange="location.href=\'' . e(url('/legal/' . $doc)) . '?lang=\'+encodeURIComponent(this.value)">' . $opts . '</select></label></div>';
    $note = $code === 'en' ? '' : '<p class="lgnote" style="background:#f3efe2;border-radius:8px;padding:.7rem 1rem">' . e(t('legal.notice')) . ' <a href="' . e(url('/legal/' . $doc) . '?lang=en') . '">' . e(t('legal.read_english')) . '</a></p>';
    $foot = '<footer class="foot"><div class="wrap"><img src="/assets/logo_horizontal_inverse.svg" alt="Moriwella"><p><a href="' . e($link('privacy')) . '">' . e(t('legal.privacy')) . '</a> &middot; <a href="' . e($link('terms')) . '">' . e(t('legal.terms')) . '</a> &middot; <a href="' . e($link('delete-account')) . '">' . e(t('legal.delete')) . '</a></p><p>&copy; 2026 JSW Software Designs</p></div></footer></body></html>';
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: public, max-age=300');
    echo $h . $bar . $note . $main . '</main>' . $foot; exit;
}
