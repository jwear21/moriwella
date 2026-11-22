<?php
declare(strict_types=1);
/* Translations. Every on-screen phrase lives in lang/<code>.php as key => text.
   English (lang/en.php) is the source and the fallback: a language file only needs the keys it has translated.
   Strings are trusted HTML-safe text written by us; {name} placeholders are filled with escaped values. */

/** Marks a value that is already safe HTML (for example a name wrapped in <b>) so t() does not escape it. */
final class Raw { public function __construct(public string $html) {} }

function lang_dir(): string { return __DIR__ . '/../lang'; }

/** Languages that have a file, as code => native name (read from each file's '_name' key). */
function langs_available(): array {
    static $l = null;
    if ($l !== null) return $l;
    $l = [];
    foreach (glob(lang_dir() . '/*.php') ?: [] as $f) {
        $code = basename($f, '.php');
        if (!preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $code)) continue;
        $a = lang_load($code);
        $l[$code] = (string)($a['_name'] ?? $code);
    }
    ksort($l);
    if (isset($l['en'])) { $l = ['en' => $l['en']] + $l; }
    return $l;
}

function lang_load(string $code): array {
    static $cache = [];
    if (!isset($cache[$code])) {
        $f = lang_dir() . '/' . $code . '.php';
        $cache[$code] = is_file($f) ? (array)(require $f) : [];
        /* feature areas add their phrases in lang/<code>.d/*.php so the main file stays stable */
        foreach (glob(lang_dir() . '/' . $code . '.d/*.php') ?: [] as $part) $cache[$code] = array_merge($cache[$code], (array)(require $part));
    }
    return $cache[$code];
}

/** Picks the language: the signed-in person's choice, else this browser session's choice, else the phone's language, else English. */
function lang_force(?string $code): void { $GLOBALS['mw_lang_force'] = $code; }

/** Languages written right to left. */
function text_dir(): string { return in_array(current_lang(), ['ar','ur','he','fa'], true) ? 'rtl' : 'ltr'; }

function current_lang(): string {
    static $c = null;
    if (!empty($GLOBALS['mw_lang_force'])) return (string)$GLOBALS['mw_lang_force'];
    if ($c !== null) return $c;
    $have = langs_available();
    $u = function_exists('current_user') ? current_user() : null;
    foreach ([$u['lang'] ?? '', $_SESSION['lang'] ?? ''] as $want) {
        if ($want !== '' && isset($have[$want])) return $c = $want;
    }
    foreach (lang_accepted() as $code) {
        if (isset($have[$code])) return $c = $code;
        $base = substr($code, 0, 2);
        if (isset($have[$base])) return $c = $base;
    }
    return $c = 'en';
}

/** Codes from the Accept-Language header, best first. Chinese variants map to zh-HK/zh-TW/zh as files exist. */
function lang_accepted(): array {
    $out = [];
    foreach (explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
        $bits = explode(';', trim($part));
        $code = strtolower(trim($bits[0]));
        if ($code === '' || $code === '*') continue;
        $q = 1.0;
        if (isset($bits[1]) && preg_match('/q=([0-9.]+)/', $bits[1], $m)) $q = (float)$m[1];
        $parts = explode('-', $code);
        $norm = $parts[0] . (isset($parts[1]) ? '-' . strtoupper($parts[1]) : '');
        // Chinese: Traditional (Hong Kong, Taiwan, Macau, Hant) and Cantonese use the zh-HK file; the rest use zh.
        if ($parts[0] === 'yue') $norm = 'zh-HK';
        elseif ($parts[0] === 'zh') $norm = (in_array('hant', $parts, true) || array_intersect(['hk', 'tw', 'mo'], $parts)) ? 'zh-HK' : 'zh';
        $out[] = [$norm, $q];
    }
    usort($out, fn($a, $b) => $b[1] <=> $a[1]);
    return array_column($out, 0);
}

function set_lang(string $code): bool {
    if (!isset(langs_available()[$code])) return false;
    $_SESSION['lang'] = $code;
    if (function_exists('current_user') && ($u = current_user())) {
        db()->prepare('UPDATE users SET lang = ? WHERE id = ?')->execute([$code, $u['id']]);
    }
    return true;
}

/** Translate a key. Falls back to English, then to the key itself so a missing key is visible, not blank. */
function t(string $key, array $vars = []): string {
    $s = lang_load(current_lang())[$key] ?? lang_load('en')[$key] ?? $key;
    if ($vars) {
        foreach ($vars as $k => $v) $s = str_replace('{' . $k . '}', $v instanceof Raw ? $v->html : e((string)$v), $s);
    }
    return $s;
}

/** Same as t() but for plain-text spots (titles, attributes already escaped by the caller). */
function tt(string $key, array $vars = []): string { return html_entity_decode(t($key, $vars), ENT_QUOTES, 'UTF-8'); }

/** Language chooser (a small form). Shows nothing until a second language exists. */
function lang_picker(string $class = 'lang-pick'): string {
    $have = langs_available();
    if (count($have) < 2) return '';
    $cur = current_lang();
    $back = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $o = '';
    uksort($have, fn($a, $b) => $a === 'en' ? -1 : ($b === 'en' ? 1 : strcasecmp(lang_english_name($a) ?: $a, lang_english_name($b) ?: $b)));
    foreach ($have as $code => $name) $o .= '<option value="' . e($code) . '"' . ($code === $cur ? ' selected' : '') . ' lang="' . e($code) . '">' . e($name) . (($en = lang_english_name($code)) !== '' && $en !== $name ? ' (' . e($en) . ')' : '') . '</option>';
    return '<form method="post" action="' . e(url('/lang')) . '" class="' . e($class) . '">' . csrf_field()
        . '<input type="hidden" name="back" value="' . e($back) . '">'
        . '<label>' . t('lang.label') . '<select name="lang">' . $o . '</select></label>'
        . '<button class="btn btn--quiet" type="submit">' . t('lang.change') . '</button></form>';
}

/** Compact picker for the account menu: one select that submits as soon as a language is chosen. */
/** English names, shown beside each language's own name so people can recognise a script they cannot read. */
function lang_english_name(string $code): string {
    static $n = ['en'=>'English','es'=>'Spanish','fr'=>'French','it'=>'Italian','de'=>'German','nl'=>'Dutch','ja'=>'Japanese','ko'=>'Korean',
        'zh'=>'Chinese, Mandarin','zh-HK'=>'Chinese, Cantonese','pt'=>'Portuguese','ru'=>'Russian','uk'=>'Ukrainian','tr'=>'Turkish',
        'id'=>'Indonesian','vi'=>'Vietnamese','pl'=>'Polish','th'=>'Thai','hi'=>'Hindi','mr'=>'Marathi','bn'=>'Bengali','te'=>'Telugu','ta'=>'Tamil',
        'ar'=>'Arabic','ur'=>'Urdu','he'=>'Hebrew','fa'=>'Persian, Farsi'];
    return $n[$code] ?? '';
}

function lang_menu_picker(): string {
    $have = langs_available();
    if (count($have) < 2) return '';
    $cur = current_lang();
    $back = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $o = '';
    uksort($have, fn($a, $b) => $a === 'en' ? -1 : ($b === 'en' ? 1 : strcasecmp(lang_english_name($a) ?: $a, lang_english_name($b) ?: $b)));
    foreach ($have as $code => $name) $o .= '<option value="' . e($code) . '"' . ($code === $cur ? ' selected' : '') . ' lang="' . e($code) . '">' . e($name) . (($en = lang_english_name($code)) !== '' && $en !== $name ? ' (' . e($en) . ')' : '') . '</option>';
    return '<form method="post" action="' . e(url('/lang')) . '" class="acct-lang">' . csrf_field()
        . '<input type="hidden" name="back" value="' . e($back) . '">'
        . '<label><span>' . t('lang.label') . '</span><select name="lang" data-autosubmit>' . $o . '</select></label>'
        . '<noscript><button class="btn btn--quiet" type="submit">' . t('lang.change') . '</button></noscript></form>';
}

/** Plural form for a count: 'one', 'two', 'few', 'many' or 'other'. Missing forms fall back to 'other' in tn(). French counts 0 as singular; Japanese, Chinese and Korean have no plural. */
function plural_form(string $lang, int $n): string {
    $base = substr($lang, 0, 2);
    if (in_array($base, ['ja', 'zh', 'ko'], true)) return 'other';
    if ($base === 'fr') return ($n === 0 || $n === 1) ? 'one' : 'other';
    if ($base === 'ar') {
        $m = $n % 100;
        if ($n === 1) return 'one';
        if ($n === 2) return 'two';
        if ($m >= 3 && $m <= 10) return 'other';
        return $n === 0 ? 'other' : 'many';
    }
    if ($base === 'he' && $n === 2) return 'two';
    return $n === 1 ? 'one' : 'other';
}

/** Translate a counted phrase: looks up "<key>.one" or "<key>.other"; {n} is filled in. Returns plain text (escape it where it is printed). */
function tn(string $key, int $n, array $vars = []): string {
    $form = plural_form(current_lang(), $n);
    $own = lang_load(current_lang()); $en = lang_load('en');
    $s = $own[$key . '.' . $form] ?? $own[$key . '.other'] ?? $en[$key . '.' . plural_form('en', $n)] ?? $en[$key . '.other'] ?? $key;
    $vars = ['n' => $n] + $vars;
    foreach ($vars as $k => $v) $s = str_replace('{' . $k . '}', (string)$v, $s);
    return html_entity_decode($s, ENT_QUOTES, 'UTF-8');
}

/** Date for people: month/weekday names ('date.months', 'date.days') and reordered patterns ('date.fmt.<pattern>') come from the language file; English uses PHP's own. */
function ldate(string $fmt, ?int $ts = null): string {
    $ts ??= time();
    $own = lang_load(current_lang());
    $fmt = $own['date.fmt.' . $fmt] ?? $fmt;   // a language can reorder, e.g. 'M j' -> 'j \d\e M'
    $months = $own['date.months'] ?? null; $days = $own['date.days'] ?? null;
    // Some languages (ru, uk, pl) inflect the month when a day number goes with it; alone it is the plain form.
    if ($months && !empty($own['date.months_nom']) && strpbrk($fmt, 'jd') === false) $months = $own['date.months_nom'];
    if (!$months && !$days) return date($fmt, $ts);   // English: PHP's own names
    $out = '';
    $len = strlen($fmt);
    for ($i = 0; $i < $len; $i++) {
        $c = $fmt[$i];
        if ($c === '\\' && $i + 1 < $len) { $out .= $fmt[++$i]; continue; }
        $n = (int)date('n', $ts) - 1; $w = (int)date('w', $ts);
        if ($c === 'F' && $months) $out .= $months[$n];
        elseif ($c === 'M' && $months) $out .= ($own['date.months_short'] ?? $months)[$n];
        elseif ($c === 'l' && $days) $out .= $days[$w];
        elseif ($c === 'D' && $days) $out .= ($own['date.days_short'] ?? $days)[$w];
        else $out .= date($c, $ts);
    }
    return $out;
}

/** Browser-tab title: translated when we have 'title.<words>' for it, otherwise as given (experience names, dates and so on). */
function page_title(string $title): string {
    $k = 'title.' . trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($title)), '_');
    $own = lang_load(current_lang()); $en = lang_load('en');
    $s = $own[$k] ?? $en[$k] ?? null;
    return $s === null ? $title : html_entity_decode($s, ENT_QUOTES, 'UTF-8');
}

/** Key for built-in content (tracker names, template sections, feelings...) so it can be translated without changing what is stored. */
function data_key(string $english): string {
    $w = preg_replace('/[^A-Za-z0-9]+/', '_', html_entity_decode($english, ENT_QUOTES, 'UTF-8'));
    return 'data.' . trim(substr($w, 0, 60), '_');
}

/** Translate built-in content by its English text; anything without a translation (including names people typed) is returned unchanged. */
function tl(string $english): string {
    if ($english === '') return '';
    return lang_load(current_lang())[data_key($english)] ?? $english;
}

/** Translate the text fields (name, blurb, prompt) found anywhere inside a nested built-in list. */
function tl_walk(array $a): array {
    foreach ($a as $k => $v) {
        if (is_array($v)) $a[$k] = tl_walk($v);
        elseif (in_array($k, ['name', 'blurb', 'prompt'], true) && is_string($v)) $a[$k] = tl($v);
    }
    return $a;
}

/** Phrases for the scripts ('js.*' keys), current language over English, written into the page for static/i18n.js. */
function js_strings(): array {
    $out = [];
    foreach (lang_load('en') as $k => $v) if (strncmp($k, 'js.', 3) === 0) $out[$k] = $v;
    foreach (lang_load(current_lang()) as $k => $v) if (strncmp($k, 'js.', 3) === 0) $out[$k] = $v;
    return $out;
}
