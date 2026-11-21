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
    if ($v < 8) {
        $p->exec("ALTER TABLE user_settings ADD COLUMN welcomed INTEGER NOT NULL DEFAULT 0;
          UPDATE user_settings SET welcomed = 1;
          CREATE TABLE feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            message TEXT NOT NULL,
            page TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL
          );
          PRAGMA user_version = 8;");
    }
    if ($v < 9) {
        $p->exec("CREATE TABLE tracker_on (
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            tkey TEXT NOT NULL,
            PRIMARY KEY (user_id, tkey)
          );
          CREATE TABLE tracker_log (
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            tkey TEXT NOT NULL,
            day TEXT NOT NULL,
            value REAL NOT NULL,
            PRIMARY KEY (user_id, tkey, day)
          );
          CREATE TABLE tracker_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            tkey TEXT NOT NULL,
            day TEXT NOT NULL,
            text TEXT NOT NULL,
            created_at INTEGER NOT NULL
          );
          CREATE INDEX tracker_notes_day ON tracker_notes (user_id, day);
          PRAGMA user_version = 9;");
    }
    if ($v < 10) {
        $p->exec("ALTER TABLE entries ADD COLUMN import_batch TEXT;
          ALTER TABLE entries ADD COLUMN source_key TEXT;
          CREATE INDEX entries_source_key ON entries (user_id, source_key);
          CREATE TABLE imports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            batch TEXT NOT NULL UNIQUE,
            source TEXT NOT NULL,
            entries INTEGER NOT NULL DEFAULT 0,
            photos INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL
          );
          PRAGMA user_version = 10;");
    }
    if ($v < 11) {
        $p->exec("ALTER TABLE tracker_log ADD COLUMN batch TEXT;
          ALTER TABLE imports ADD COLUMN moods INTEGER NOT NULL DEFAULT 0;
          PRAGMA user_version = 11;");
    }
    if ($v < 12) {
        $p->exec("CREATE TABLE day_feelings (
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            day TEXT NOT NULL,
            feeling TEXT NOT NULL,
            batch TEXT,
            PRIMARY KEY (user_id, day, feeling)
          );
          PRAGMA user_version = 12;");
    }
    if ($v < 13) {
        $p->exec("CREATE TABLE mood_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            day TEXT NOT NULL,
            ts INTEGER NOT NULL DEFAULT 0,
            value INTEGER NOT NULL,
            batch TEXT
          );
          CREATE INDEX mood_log_day ON mood_log (user_id, day);
          INSERT INTO mood_log (user_id, day, ts, value, batch) SELECT user_id, day, 0, CAST(ROUND(value) AS INTEGER), batch FROM tracker_log WHERE tkey = 'mood';
          DELETE FROM tracker_log WHERE tkey = 'mood';
          DELETE FROM tracker_on WHERE tkey = 'mood';
          PRAGMA user_version = 13;");
    }
    if ($v < 14) {
        $p->exec("CREATE TABLE movies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
            status TEXT NOT NULL DEFAULT 'queued',
            progress INTEGER NOT NULL DEFAULT 0,
            theme TEXT NOT NULL DEFAULT 'evergreen',
            length TEXT NOT NULL DEFAULT 'full',
            subtitle TEXT NOT NULL DEFAULT '',
            closing TEXT NOT NULL DEFAULT '',
            file TEXT NOT NULL DEFAULT '',
            bytes INTEGER NOT NULL DEFAULT 0,
            seconds REAL NOT NULL DEFAULT 0,
            error TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL,
            started_at INTEGER NOT NULL DEFAULT 0,
            finished_at INTEGER NOT NULL DEFAULT 0
          );
          CREATE INDEX movies_event ON movies (event_id, id);
          PRAGMA user_version = 14;");
    }
    if ($v < 15) {
        $p->exec("ALTER TABLE users ADD COLUMN lang TEXT NOT NULL DEFAULT ''; PRAGMA user_version = 15;");
    }
    if ($v < 16) {
        /* Billing: households, subscriptions, the credit ledger and Stripe bookkeeping. Stripe collects money; this database is the record of credits. */
        $p->exec("
          CREATE TABLE households (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            owner_user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL DEFAULT '',
            stripe_customer_id TEXT UNIQUE,
            created_at INTEGER NOT NULL
          );
          CREATE TABLE household_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            household_id INTEGER NOT NULL REFERENCES households(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            role TEXT NOT NULL DEFAULT 'member',
            status TEXT NOT NULL DEFAULT 'active',
            joined_at INTEGER NOT NULL,
            removed_at INTEGER NOT NULL DEFAULT 0
          );
          CREATE INDEX hm_household ON household_members (household_id, status);
          CREATE TABLE subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            household_id INTEGER NOT NULL UNIQUE REFERENCES households(id) ON DELETE CASCADE,
            stripe_subscription_id TEXT UNIQUE,
            plan_key TEXT NOT NULL DEFAULT 'individual',
            status TEXT NOT NULL DEFAULT 'none',
            comped INTEGER NOT NULL DEFAULT 0,
            trial_start INTEGER NOT NULL DEFAULT 0,
            trial_end INTEGER NOT NULL DEFAULT 0,
            period_start INTEGER NOT NULL DEFAULT 0,
            period_end INTEGER NOT NULL DEFAULT 0,
            cancel_at_period_end INTEGER NOT NULL DEFAULT 0,
            pending_plan_key TEXT NOT NULL DEFAULT '',
            pending_keep TEXT NOT NULL DEFAULT '',
            seat_limit INTEGER NOT NULL DEFAULT 1,
            interval_key TEXT NOT NULL DEFAULT 'month',
            updated_at INTEGER NOT NULL DEFAULT 0
          );
          CREATE TABLE trial_claims (
            email_norm TEXT PRIMARY KEY,
            household_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            claimed_at INTEGER NOT NULL
          );
          CREATE TABLE credit_ledger (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            household_id INTEGER NOT NULL DEFAULT 0,
            kind TEXT NOT NULL CHECK (kind IN ('trial','included','purchased')),
            delta INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'posted' CHECK (status IN ('posted','reserved','void')),
            period_key TEXT NOT NULL DEFAULT '',
            stripe_ref TEXT NOT NULL DEFAULT '',
            movie_id INTEGER NOT NULL DEFAULT 0,
            idem_key TEXT NOT NULL UNIQUE,
            note TEXT NOT NULL DEFAULT '',
            balance_after INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL
          );
          CREATE INDEX ledger_user ON credit_ledger (user_id, kind, status);
          CREATE INDEX ledger_movie ON credit_ledger (movie_id);
          CREATE TABLE credit_purchases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            household_id INTEGER NOT NULL DEFAULT 0,
            pack_key TEXT NOT NULL,
            credits INTEGER NOT NULL,
            amount_cents INTEGER NOT NULL,
            currency TEXT NOT NULL DEFAULT 'usd',
            stripe_session_id TEXT UNIQUE,
            stripe_payment_intent TEXT,
            status TEXT NOT NULL DEFAULT 'paid',
            clawed_credits INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL
          );
          CREATE INDEX purchases_pi ON credit_purchases (stripe_payment_intent);
          CREATE TABLE stripe_events (
            event_id TEXT PRIMARY KEY,
            type TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'processing',
            error TEXT NOT NULL DEFAULT '',
            received_at INTEGER NOT NULL,
            processed_at INTEGER NOT NULL DEFAULT 0
          );
          ALTER TABLE movies ADD COLUMN cost INTEGER NOT NULL DEFAULT 1;
          PRAGMA user_version = 16;");
    }
    if ($v < 17) {
        $p->exec("ALTER TABLE subscriptions ADD COLUMN comp_credits INTEGER NOT NULL DEFAULT 0; PRAGMA user_version = 17;");
    }
    if ($v < 18) {
        /* Keepsakes: shared albums (a private link to chosen photos) and printable scrapbooks (PDF). */
        $p->exec("
          CREATE TABLE albums (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            event_id INTEGER REFERENCES events(id) ON DELETE CASCADE,
            title TEXT NOT NULL DEFAULT '',
            note TEXT NOT NULL DEFAULT '',
            token TEXT NOT NULL UNIQUE,
            expires_at INTEGER NOT NULL DEFAULT 0,
            revoked_at INTEGER NOT NULL DEFAULT 0,
            allow_download INTEGER NOT NULL DEFAULT 1,
            views INTEGER NOT NULL DEFAULT 0,
            last_view INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL
          );
          CREATE INDEX albums_user ON albums (user_id, id);
          CREATE INDEX albums_event ON albums (event_id);
          CREATE TABLE album_photos (
            album_id INTEGER NOT NULL REFERENCES albums(id) ON DELETE CASCADE,
            photo_id INTEGER NOT NULL REFERENCES entry_photos(id) ON DELETE CASCADE,
            pos INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (album_id, photo_id)
          );
          CREATE TABLE scrapbooks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
            status TEXT NOT NULL DEFAULT 'queued',
            progress INTEGER NOT NULL DEFAULT 0,
            style TEXT NOT NULL DEFAULT 'classic',
            size TEXT NOT NULL DEFAULT 'letter',
            text_mode TEXT NOT NULL DEFAULT 'captions',
            subtitle TEXT NOT NULL DEFAULT '',
            closing TEXT NOT NULL DEFAULT '',
            file TEXT NOT NULL DEFAULT '',
            bytes INTEGER NOT NULL DEFAULT 0,
            pages INTEGER NOT NULL DEFAULT 0,
            soft INTEGER NOT NULL DEFAULT 0,
            error TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL,
            started_at INTEGER NOT NULL DEFAULT 0,
            finished_at INTEGER NOT NULL DEFAULT 0
          );
          CREATE INDEX scrapbooks_event ON scrapbooks (event_id, id);
          PRAGMA user_version = 18;");
    }
    if ($v < 19) {
        $p->exec("ALTER TABLE users ADD COLUMN referral_code TEXT;
          ALTER TABLE users ADD COLUMN referred_by INTEGER NOT NULL DEFAULT 0;
          CREATE UNIQUE INDEX users_referral_code ON users (referral_code) WHERE referral_code IS NOT NULL;
          CREATE TABLE referrals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            referrer_id INTEGER NOT NULL,
            friend_id INTEGER NOT NULL UNIQUE,
            status TEXT NOT NULL DEFAULT 'signed_up',   -- signed_up, started (30 days running), qualified (first payment in), rewarded, reversed
            created_at INTEGER NOT NULL,
            started_at INTEGER NOT NULL DEFAULT 0,
            qualified_at INTEGER NOT NULL DEFAULT 0,
            first_amount INTEGER NOT NULL DEFAULT 0,
            reward_kind TEXT NOT NULL DEFAULT '',      -- month (Stripe account credit) or credits (movie credits)
            reward_ref TEXT NOT NULL DEFAULT '',
            reward_amount INTEGER NOT NULL DEFAULT 0,
            rewarded_at INTEGER NOT NULL DEFAULT 0,
            reversed_at INTEGER NOT NULL DEFAULT 0
          );
          CREATE INDEX referrals_referrer ON referrals (referrer_id, id);
          PRAGMA user_version = 19;");
    }
    if ($v < 20) {
        $p->exec("ALTER TABLE users ADD COLUMN referral_terms_at INTEGER NOT NULL DEFAULT 0; PRAGMA user_version = 20;");
    }
    if ($v < 21) {
        /* One row per save sent from the journal book, so a retry after a bad connection never writes the same words twice. */
        $p->exec("CREATE TABLE entry_saves (user_id INTEGER NOT NULL, cid TEXT NOT NULL, entry_id INTEGER NOT NULL, created_at INTEGER NOT NULL, PRIMARY KEY (user_id, cid)); PRAGMA user_version = 21;");
    }
}

/** File's last-change time, so browsers fetch a fresh copy after every deploy. */
function asset_v(string $rel): string { $f = __DIR__ . '/../' . $rel; return is_file($f) ? (string)filemtime($f) : '0'; }

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
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self' https://checkout.stripe.com https://billing.stripe.com; frame-ancestors 'none'; base-uri 'self'");
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
    if (!$ok) { http_response_code(400); render('message', ['heading' => tt('msg.that_page_expired'), 'text' => tt('msg.please_go_back_refresh')], 'Expired', true); exit; }
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
    if ($u['role'] !== 'admin') { http_response_code(403); render('message', ['heading' => tt('msg.admins_only'), 'text' => tt('msg.this_area_is_for')], 'Not allowed'); exit; }
    return $u;
}
function hash_pw(string $p): string { return password_hash($p, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT); }
function check_password_rules(string $p, string $p2): ?string {
    if (strlen($p) < 10) return tt('msg.use_at_least_10');
    if ($p !== $p2) return tt('msg.the_two_passwords_don');
    return null;
}
function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    db()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([time(), $u['id']]);
    /* a language chosen on the sign-in screen becomes this person's setting the first time */
    if (($u['lang'] ?? '') === '' && !empty($_SESSION['lang']) && isset(langs_available()[$_SESSION['lang']])) {
        db()->prepare('UPDATE users SET lang = ? WHERE id = ?')->execute([$_SESSION['lang'], $u['id']]);
    }
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
/** Hand one message to an SMTP server (the server's own port, or a mailbox login from moriwella_data/mail.php).
 *  Returns [accepted, short log of the conversation]. */
function smtp_deliver(string $host, int $port, string $secure, string $user, string $pass, string $from, string $to, string $data): array {
    $log = [];
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 8, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]));
    if (!$fp) return [false, "$host:$port $errno $errstr"];
    stream_set_timeout($fp, 12);
    $read = function () use ($fp) { $out = ''; while (($l = fgets($fp, 1024)) !== false) { $out .= $l; if (strlen($l) < 4 || $l[3] !== '-') break; } return trim($out); };
    $short = fn($t) => mb_substr(preg_replace('/\s+/', ' ', $t), 0, 110);
    $send = function (string $c, string $label = '') use ($fp, $read, &$log, $short) { fwrite($fp, $c . "\r\n"); $r = $read(); $log[] = ($label ?: preg_replace('/<[^>]*>/', '<..>', $c)) . ' -> ' . $short($r); return $r; };
    $ok2 = fn($r) => $r !== '' && $r[0] === '2';
    $bye = function (array $res) use ($fp) { @fwrite($fp, "QUIT\r\n"); @fclose($fp); return $res; };
    $log[] = 'greet ' . $short($read());
    $r = $send('EHLO moriwella.com');
    if ($secure === 'tls') {
        $r = $send('STARTTLS');
        if (!$ok2($r) || !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return $bye([false, implode(' | ', $log) . ' | tls failed']);
        $send('EHLO moriwella.com');
    }
    if ($user !== '') {
        $r = $send('AUTH LOGIN');
        if ($r === '' || $r[0] !== '3') return $bye([false, implode(' | ', $log)]);
        $r = $send(base64_encode($user), 'user');
        if ($r === '' || $r[0] !== '3') return $bye([false, implode(' | ', $log)]);
        $r = $send(base64_encode($pass), 'pass');
        if (!$ok2($r)) return $bye([false, implode(' | ', $log)]);
    }
    if (!$ok2($send('MAIL FROM:<' . $from . '>'))) return $bye([false, implode(' | ', $log)]);
    if (!$ok2($send('RCPT TO:<' . $to . '>'))) return $bye([false, implode(' | ', $log)]);
    $r = $send('DATA'); if ($r === '' || $r[0] !== '3') return $bye([false, implode(' | ', $log)]);
    fwrite($fp, $data . "\r\n"); $r = $send('.', 'end');
    return $bye([$ok2($r), implode(' | ', $log)]);
}
function smtp_local_send(string $from, string $to, string $data): array {
    $c = @include data_dir() . '/mail.php';
    if (is_array($c) && !empty($c['host'])) {
        return smtp_deliver((string)$c['host'], (int)($c['port'] ?? 465), (string)($c['secure'] ?? 'ssl'), (string)($c['user'] ?? ''), (string)($c['pass'] ?? ''), (string)($c['from'] ?? $from), $to, $data);
    }
    return smtp_deliver('127.0.0.1', 25, 'none', '', '', $from, $to, $data);
}
/** Plain-text email through the server's mail system. Returns true when the server accepted it for delivery (not proof it arrived). */
function send_mail(string $to, string $subject, string $body, string $replyTo = ''): bool {
    $clean = fn(string $v) => trim(preg_replace('/[\r\n]+/', ' ', $v));
    $to = $clean($to); if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $from = $clean((string)cfg('mail_from', 'noreply@moriwella.com'));
    $mc = @include data_dir() . '/mail.php';
    $useSmtp = is_array($mc) && !empty($mc['host']);
    if ($useSmtp && !empty($mc['from'])) $from = $clean((string)$mc['from']);
    $h = ['From: Moriwella <' . $from . '>', 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'X-Mailer: Moriwella'];
    if ($replyTo !== '' && filter_var($clean($replyTo), FILTER_VALIDATE_EMAIL)) $h[] = 'Reply-To: ' . $clean($replyTo);
    $subj = '=?UTF-8?B?' . base64_encode($clean($subject)) . '?=';
    $ok = false; $why = '';
    if ($useSmtp) {
        $m0 = 'To: ' . $to . "\r\n" . 'Subject: ' . $subj . "\r\n" . 'Date: ' . date('r') . "\r\n" . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@moriwella.com>' . "\r\n" . implode("\r\n", $h) . "\r\n\r\n" . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body)));
        [$ok, $slog] = smtp_local_send($from, $to, $m0);
        $why = ' via=mailbox smtp=' . ($ok ? 'ok' : 'failed ' . $slog);
        if ($ok) { @file_put_contents(data_dir() . '/mail.log', date('c') . ' to=' . $to . ' subject=' . $clean($subject) . ' accepted=yes' . $why . "\n", FILE_APPEND); return true; }
    } else {
        $ok = @mail($to, $subj, str_replace("\n", "\r\n", $body), implode("\r\n", $h));
        $why = $ok ? '' : ' mail()=false';
    }
    if (!$ok && !$useSmtp && function_exists('proc_open') && !in_array('proc_open', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
        /* talk to the server's mail program directly so its own error message is visible */
        $msg = 'To: ' . $to . "\n" . 'Subject: ' . $subj . "\n" . 'Date: ' . date('r') . "\n" . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@moriwella.com>' . "\n" . implode("\n", $h) . "\n\n" . $body;
        $pr = @proc_open('/usr/sbin/sendmail -t -i', [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($pr)) {
            fwrite($pipes[0], $msg); fclose($pipes[0]);
            $out = trim(stream_get_contents($pipes[1]) . ' ' . stream_get_contents($pipes[2])); fclose($pipes[1]); fclose($pipes[2]);
            $code = proc_close($pr);
            $ok = ($code === 0);
            $why .= ' direct=' . ($ok ? 'ok' : 'exit' . $code . ' ' . preg_replace('/\s+/', ' ', mb_substr($out, 0, 300)));
        } else { $why .= ' direct=could-not-start'; }
    }
    if (!$ok && !$useSmtp) {
        /* last route: the server's own mail port, the way any mail program would talk to it */
        $msg2 = 'To: ' . $to . "\r\n" . 'Subject: ' . $subj . "\r\n" . 'Date: ' . date('r') . "\r\n" . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@moriwella.com>' . "\r\n" . implode("\r\n", $h) . "\r\n\r\n" . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body)));
        [$sok, $slog] = smtp_local_send($from, $to, $msg2);
        $ok = $sok; $why .= ' smtp=' . ($sok ? 'ok' : 'failed') . ' ' . $slog;
    }
    @file_put_contents(data_dir() . '/mail.log', date('c') . ' to=' . $to . ' subject=' . $clean($subject) . ' accepted=' . ($ok ? 'yes' : 'no') . $why . "\n", FILE_APPEND);
    return $ok;
}
/** The invitation email a household owner's guest receives, with the link to choose a password. */
function invite_email(array $guest, string $link): bool {
    $me = current_user(); $who = $me ? trim((string)$me['name']) : 'Someone';
    return send_mail((string)$guest['email'], $who . ' invited you to Moriwella',
        "Hi " . $guest['name'] . ",\n\n$who invited you to share their Moriwella household, a private place to remember your life, organize your days, and live well.\n\nChoose your password and get started here (the link works for 7 days):\n$link\n\nIf you weren't expecting this, you can ignore this email.\n\nMoriwella\n", $me ? (string)$me['email'] : '');
}
function find_by_invite(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
    $s = db()->prepare('SELECT * FROM users WHERE invite_hash = ? AND invite_expires > ?');
    $s->execute([hash('sha256', $token), time()]);
    return $s->fetch() ?: null;
}
/** Sign-up and password-reset throttle: a few tries per address per 15 minutes, kept apart from sign-in attempts. */
function signup_throttled(): bool {
    $s = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ts > ?');
    $s->execute(['s:' . client_ip(), time() - 900]);
    return (int)$s->fetchColumn() >= 6;
}
function signup_record(): void { db()->prepare('INSERT INTO login_attempts (ip, ts) VALUES (?, ?)')->execute(['s:' . client_ip(), time()]); }
/** Welcome email for a new sign-up: the link lets them choose a password, which also confirms the address. */
function signup_email(string $name, string $email, string $link): bool {
    $ref = false; try { $q = db()->prepare('SELECT 1 FROM referrals r JOIN users u ON u.id = r.friend_id WHERE lower(u.email) = ?'); $q->execute([strtolower($email)]); $ref = (bool)$q->fetchColumn(); } catch (Throwable $e) {}
    $go = $ref ? "Choose your password and begin your first 30 days here (the link works for 7 days):" : "Choose your password and start your 14-day free trial here (the link works for 7 days):";
    return send_mail($email, 'Welcome to Moriwella: choose your password',
        "Hi " . $name . ",\n\nWelcome to Moriwella, a private place to remember your life, organize your days, and live well.\n\n" . $go . "\n$link\n\nIf you did not sign up, you can ignore this email and nothing will happen.\n\nMoriwella\n", 'support@moriwella.com');
}
/** Password-reset email. */
function reset_email(string $name, string $email, string $link, bool $existing = false): bool {
    $lead = $existing ? "Someone (we hope you) tried to sign up with this address, and you already have a Moriwella account. You can sign in at https://moriwella.com/app/login, or choose a new password here (the link works for 7 days):\n" : "Here is your link to choose a new password for Moriwella (it works for 7 days):\n";
    return send_mail($email, 'Your Moriwella password link', "Hi " . $name . ",\n\n" . $lead . $link . "\n\nIf you did not ask for this, you can ignore this email. Your password has not changed.\n\nMoriwella\n", 'support@moriwella.com');
}
/** True inside the Android app (its web view adds MoriwellaApp to the user agent). The Play Store does not allow selling subscriptions or credits from inside the app, so purchases are only offered on the website. */
function in_app(): bool { return stripos((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 'MoriwellaApp') !== false; }
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
function is_welcomed(int $uid): bool {
    $s = db()->prepare('SELECT welcomed FROM user_settings WHERE user_id = ?'); $s->execute([$uid]);
    return (int)$s->fetchColumn() === 1;
}
function mark_welcomed(int $uid): void {
    db()->prepare('INSERT INTO user_settings (user_id, welcomed) VALUES (?, 1) ON CONFLICT(user_id) DO UPDATE SET welcomed = 1')->execute([$uid]);
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

/* ----- per-paragraph time stamps: a paragraph may start with a marker line "⟦t:EPOCH⟧" ----- */
function body_parts(string $body, int $fallbackTs = 0): array {
    $out = [];
    foreach (preg_split('/\n{2,}/', trim(str_replace(["\r\n", "\r"], "\n", $body))) as $i => $p) {
        $ts = 0;
        if (preg_match('/^⟦t:(\d{9,11})⟧\n?/u', $p, $m)) { $ts = (int)$m[1]; $p = substr($p, strlen($m[0])); }
        elseif ($i === 0) $ts = $fallbackTs;
        $p = trim($p);
        if ($p !== '' || $ts) $out[] = ['ts' => $ts, 'text' => $p];
    }
    return $out;
}
function body_plain(string $body): string { return implode("\n\n", array_column(body_parts($body), 'text')); }
function stamp_text(int $ts, string $text): string { return $ts ? '⟦t:' . $ts . "⟧\n" . $text : $text; }
function stamp_time(int $ts): string { return date('g:i a', $ts); }
