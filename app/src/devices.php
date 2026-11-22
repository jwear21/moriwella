<?php
declare(strict_types=1);
/* Fingerprint / face sign-in for the Android app. The phone keeps a random device token locked behind the phone's biometrics;
   unlocking it lets the app sign in without typing a password. The token is stored here only as a hash, can be switched off at any
   time, and stops working when the password changes. Copyright (c) JSW Software Designs */

const DEVICE_MAX = 5;               /* phones per person */
const DEVICE_IDLE_DAYS = 90;        /* a token not used for this long stops working */

function device_register(int $uid, string $label): array {
    $token = bin2hex(random_bytes(32));
    $p = db();
    $p->prepare('INSERT INTO device_tokens (user_id, token_hash, label, created_at, last_used_at) VALUES (?, ?, ?, ?, ?)')
      ->execute([$uid, hash('sha256', $token), mb_substr($label, 0, 80), time(), time()]);
    $id = (int)$p->lastInsertId();
    /* keep only the newest few */
    $p->prepare('DELETE FROM device_tokens WHERE user_id = ? AND id NOT IN (SELECT id FROM device_tokens WHERE user_id = ? ORDER BY id DESC LIMIT ' . DEVICE_MAX . ')')->execute([$uid, $uid]);
    return ['id' => $id, 'token' => $token];
}
/** Finds the active person this token belongs to, or null. Marks the token used. */
function device_login(string $email, string $token): ?array {
    if ($email === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $s = db()->prepare("SELECT u.*, d.id AS device_id, d.last_used_at AS device_used FROM device_tokens d JOIN users u ON u.id = d.user_id WHERE u.email = ? AND d.token_hash = ? AND u.status = 'active'");
    $s->execute([$email, hash('sha256', $token)]);
    $u = $s->fetch();
    if (!$u) return null;
    if ((int)$u['device_used'] < time() - DEVICE_IDLE_DAYS * 86400) { device_revoke_id((int)$u['id'], (int)$u['device_id']); return null; }
    db()->prepare('UPDATE device_tokens SET last_used_at = ? WHERE id = ?')->execute([time(), $u['device_id']]);
    return $u;
}
function device_revoke_token(int $uid, string $token): void {
    db()->prepare('DELETE FROM device_tokens WHERE user_id = ? AND token_hash = ?')->execute([$uid, hash('sha256', $token)]);
}
function device_revoke_id(int $uid, int $id): void { db()->prepare('DELETE FROM device_tokens WHERE user_id = ? AND id = ?')->execute([$uid, $id]); }
function device_revoke_all(int $uid): void { db()->prepare('DELETE FROM device_tokens WHERE user_id = ?')->execute([$uid]); }
function device_list(int $uid): array {
    $s = db()->prepare('SELECT id, label, created_at, last_used_at FROM device_tokens WHERE user_id = ? ORDER BY id DESC'); $s->execute([$uid]); return $s->fetchAll();
}

/** JSON replies for the app's script. */
function device_json(array $d, int $code = 200): void {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($d); exit;
}
function device_routes(string $path, bool $post): void {
    if ($path === '/device/login' && $post) {
        if (too_many_attempts()) device_json(['ok' => false, 'error' => 'slow'], 429);
        $u = device_login(strtolower(trim((string)($_POST['email'] ?? ''))), (string)($_POST['token'] ?? ''));
        if (!$u) { record_failed_attempt(); device_json(['ok' => false, 'error' => 'invalid'], 401); }
        login_user($u);
        device_json(['ok' => true, 'next' => url('/')]);
    }
    if ($path === '/device/register' && $post) {
        $u = current_user(); if (!$u) device_json(['ok' => false, 'error' => 'login'], 401);
        $label = 'Android app';
        device_json(['ok' => true, 'email' => (string)$u['email']] + device_register((int)$u['id'], $label));
    }
    if ($path === '/device/revoke' && $post) {
        $u = current_user(); if (!$u) device_json(['ok' => false, 'error' => 'login'], 401);
        if (!empty($_POST['token'])) device_revoke_token((int)$u['id'], (string)$_POST['token']);
        if (!empty($_POST['id'])) device_revoke_id((int)$u['id'], (int)$_POST['id']);
        if (!empty($_POST['all'])) device_revoke_all((int)$u['id']);
        device_json(['ok' => true]);
    }
}
