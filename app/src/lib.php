<?php
declare(strict_types=1);

const MW_BASE = '/app';

function cfg(string $k, $d = null) {
    static $c = null;
    if ($c === null) { $c = require __DIR__ . '/config.php'; }
    return $c[$k] ?? $d;
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $p = ''): string { return MW_BASE . $p; }

/* ---------- storage (private folder outside the website) ---------- */
function data_dir(): string {
    $d = getenv('MW_DATA') ?: dirname(__DIR__, 3) . '/moriwella_data';
    if (!is_dir($d)) { @mkdir($d, 0700, true); }
    return $d;
}
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . data_dir() . '/moriwella.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    migrate($pdo);
    return $pdo;
}
function migrate(PDO $p): void {
    $v = (int)$p->query('PRAGMA user_version')->fetchColumn();
    if ($v < 1) {
        $p->exec("
        CREATE TABLE users (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          email TEXT NOT NULL UNIQUE COLLATE NOCASE,
          name TEXT NOT NULL,
          role TEXT NOT NULL DEFAULT 'member',
          plan TEXT NOT NULL DEFAULT 'beta_free',
          status TEXT NOT NULL DEFAULT 'invited',
          password_hash TEXT,
          invite_hash TEXT,
          invite_expires INTEGER,
          created_at INTEGER NOT NULL,
          last_login_at INTEGER
        );
        CREATE TABLE entries (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
          entry_date TEXT NOT NULL,
          title TEXT NOT NULL DEFAULT '',
          body TEXT NOT NULL DEFAULT '',
          created_at INTEGER NOT NULL,
          updated_at INTEGER NOT NULL
        );
        CREATE INDEX entries_user_date ON entries(user_id, entry_date);
        CREATE TABLE login_attempts (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          ip TEXT NOT NULL,
          ts INTEGER NOT NULL
        );
        CREATE INDEX attempts_ip ON login_attempts(ip, ts);
        PRAGMA user_version = 1;");
    }
    if ($v < 2) {
        $p->exec("
        CREATE TABLE entry_photos (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          entry_id INTEGER NOT NULL REFERENCES entries(id) ON DELETE CASCADE,
          user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
          orig_name TEXT NOT NULL DEFAULT '',
          ext TEXT NOT NULL,
          mime TEXT NOT NULL,
          width INTEGER NOT NULL DEFAULT 0,
          height INTEGER NOT NULL DEFAULT 0,
          bytes INTEGER NOT NULL DEFAULT 0,
          has_derived INTEGER NOT NULL DEFAULT 0,
          created_at INTEGER NOT NULL
        );
        CREATE INDEX photos_entry ON entry_photos(entry_id);
        CREATE TABLE lists (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
          name TEXT NOT NULL,
          kind TEXT NOT NULL DEFAULT 'todo',
          created_at INTEGER NOT NULL
        );
        CREATE TABLE list_members (
          list_id INTEGER NOT NULL REFERENCES lists(id) ON DELETE CASCADE,
          user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
          added_at INTEGER NOT NULL,
          PRIMARY KEY (list_id, user_id)
        );
        CREATE TABLE list_items (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          list_id INTEGER NOT NULL REFERENCES lists(id) ON DELETE CASCADE,
          text TEXT NOT NULL,
          due_date TEXT,
          done_at INTEGER,
          done_by INTEGER,
          created_by INTEGER NOT NULL,
          created_at INTEGER NOT NULL
        );
        CREATE INDEX items_list ON list_items(list_id);
        CREATE TABLE water_log (
          user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
          day TEXT NOT NULL,
          oz INTEGER NOT NULL DEFAULT 0,
          PRIMARY KEY (user_id, day)
        );
        CREATE TABLE user_settings (
          user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
          celebrations INTEGER NOT NULL DEFAULT 1
        );
        PRAGMA user_version = 2;");
    }
    if ($v < 3) {
        $p->exec("ALTER TABLE entry_photos ADD COLUMN edited_at INTEGER NOT NULL DEFAULT 0; PRAGMA user_version = 3;");
    }
    if ($v < 4) {
        $p->exec("ALTER TABLE user_settings ADD COLUMN water_on INTEGER NOT NULL DEFAULT 0;
          ALTER TABLE user_settings ADD COLUMN celebrate_size TEXT NOT NULL DEFAULT 'big';
          INSERT OR IGNORE INTO user_settings (user_id, celebrations) SELECT id, 1 FROM users;
          UPDATE user_settings SET water_on = 1;
          CREATE TABLE goals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            created_at INTEGER NOT NULL
          );
          CREATE TABLE goal_log (
            goal_id INTEGER NOT NULL REFERENCES goals(id) ON DELETE CASCADE,
            day TEXT NOT NULL,
            done_at INTEGER NOT NULL,
            PRIMARY KEY (goal_id, day)
          );
          PRAGMA user_version = 4;");
    }
    if ($v < 5) {
        $p->exec("CREATE TABLE events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            template_key TEXT NOT NULL DEFAULT 'blank',
            title TEXT NOT NULL,
            start_date TEXT NOT NULL,
            end_date TEXT NOT NULL,
            location TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL
          );
          CREATE INDEX events_user ON events(user_id, start_date);
          CREATE TABLE event_sections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            prompt TEXT NOT NULL DEFAULT '',
            position INTEGER NOT NULL DEFAULT 0
          );
          CREATE TABLE event_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            sections TEXT NOT NULL,
            created_at INTEGER NOT NULL
          );
          ALTER TABLE entries ADD COLUMN event_id INTEGER;
          ALTER TABLE entries ADD COLUMN section_id INTEGER;
          CREATE INDEX entries_event ON entries(event_id);
          PRAGMA user_version = 5;");
    }
    if ($v < 6) {
        $p->exec("ALTER TABLE list_items ADD COLUMN assigned_to INTEGER;
          ALTER TABLE list_items ADD COLUMN repeat TEXT;
          PRAGMA user_version = 6;");
    }
    if ($v < 7) {
        $p->exec("CREATE TABLE achievements_unlocked (
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            achievement_id TEXT NOT NULL,
            period TEXT NOT NULL DEFAULT '',
            unlocked_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, achievement_id, period)
          );
          PRAGMA user_version = 7;");
    }
}

/* ---------- request setup ---------- */
function boot(): void {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $days = (int)cfg('session_days', 14);
    $sess = data_dir() . '/sessions';
    if (!is_dir($sess)) { @mkdir($sess, 0700, true); }
    if (is_dir($sess) && is_writable($sess)) { session_save_path($sess); }
    ini_set('session.gc_maxlifetime', (string)(86400 * $days));
    ini_set('session.use_strict_mode', '1');
    session_name('mw_sess');
    session_set_cookie_params(['lifetime' => 86400 * $days, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    $tz = (string)($_COOKIE['mw_tz'] ?? '');
    date_default_timezone_set(in_array($tz, timezone_identifiers_list(), true) ? $tz : 'America/Chicago');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}

function redirect(string $to): never {
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') { header('Content-Type: application/json'); echo json_encode(['redirect' => $to]); exit; }
    header('Location: ' . $to); exit;
}
function flash(string $type, string $msg): void { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $ok = isset($_POST['_csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['_csrf']);
    if (!$ok) { http_response_code(400); render('message', ['heading' => 'That page expired', 'text' => 'Please go back, refresh the page and try again.'], 'Expired', true); exit; }
}

function render(string $view, array $v = [], string $title = 'Moriwella', bool $bare = false): void {
    $user = current_user();
    $flash = take_flash();
    extract($v);
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
}

/* ---------- accounts ---------- */
function current_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return $u = null;
    $s = db()->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $s->execute([$id]);
    return $u = ($s->fetch() ?: null);
}
function require_login(): array { $u = current_user(); if (!$u) redirect(url('/login')); return $u; }
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); render('message', ['heading' => 'Admins only', 'text' => 'This area is for Moriwella administrators.'], 'Not allowed'); exit; }
    return $u;
}
function hash_pw(string $p): string { return password_hash($p, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT); }
function check_password_rules(string $p, string $p2): ?string {
    if (strlen($p) < 10) return 'Use at least 10 characters.';
    if ($p !== $p2) return 'The two passwords don\'t match.';
    return null;
}
function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    db()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([time(), $u['id']]);
}
function client_ip(): string { return (string)($_SERVER['REMOTE_ADDR'] ?? '0'); }
function too_many_attempts(): bool {
    $s = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ts > ?');
    $s->execute([client_ip(), time() - 900]);
    return (int)$s->fetchColumn() >= 8;
}
function record_failed_attempt(): void {
    db()->prepare('INSERT INTO login_attempts (ip, ts) VALUES (?, ?)')->execute([client_ip(), time()]);
    db()->exec('DELETE FROM login_attempts WHERE ts < ' . (time() - 86400));
}
function make_invite(int $userId): string {
    $token = bin2hex(random_bytes(24));
    db()->prepare('UPDATE users SET invite_hash = ?, invite_expires = ? WHERE id = ?')
        ->execute([hash('sha256', $token), time() + 7 * 86400, $userId]);
    return rtrim((string)cfg('app_url'), '/') . url('/join/' . $token);
}
function find_by_invite(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
    $s = db()->prepare('SELECT * FROM users WHERE invite_hash = ? AND invite_expires > ?');
    $s->execute([hash('sha256', $token), time()]);
    return $s->fetch() ?: null;
}
function user_count(): int { return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(); }

function valid_date(string $d): bool {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}


/* ---------- settings ---------- */
function celebrations_on(int $uid): bool {
    $s = db()->prepare('SELECT celebrations FROM user_settings WHERE user_id = ?');
    $s->execute([$uid]);
    $v = $s->fetchColumn();
    return $v === false ? true : (int)$v === 1;
}
function set_celebrations(int $uid, bool $on): void {
    db()->prepare('INSERT INTO user_settings (user_id, celebrations) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET celebrations = excluded.celebrations')
        ->execute([$uid, $on ? 1 : 0]);
}
function water_on(int $uid): bool {
    $s = db()->prepare('SELECT water_on FROM user_settings WHERE user_id = ?'); $s->execute([$uid]);
    return (int)$s->fetchColumn() === 1;
}
function celebrate_size(int $uid): string {
    $s = db()->prepare('SELECT celebrate_size FROM user_settings WHERE user_id = ?'); $s->execute([$uid]);
    return $s->fetchColumn() === 'gentle' ? 'gentle' : 'big';
}
function save_prefs(int $uid, bool $celebrations, bool $water, string $size): void {
    db()->prepare('INSERT INTO user_settings (user_id, celebrations, water_on, celebrate_size) VALUES (?, ?, ?, ?)
        ON CONFLICT(user_id) DO UPDATE SET celebrations = excluded.celebrations, water_on = excluded.water_on, celebrate_size = excluded.celebrate_size')
        ->execute([$uid, $celebrations ? 1 : 0, $water ? 1 : 0, $size === 'gentle' ? 'gentle' : 'big']);
}
function json_out(array $d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($d);
    exit;
}
function wants_json(): bool { return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'; }
