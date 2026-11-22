<?php
declare(strict_types=1);
/* Passkeys: sign in with the fingerprint reader, face unlock or PIN of a laptop or phone (Windows Hello, Touch ID, Android, iPhone)
   through the browser's WebAuthn. Only each device's public key is stored here; nothing secret leaves the device. Copyright (c) JSW Software Designs */

const PASSKEY_MAX = 10;

function pk_b64e(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
function pk_b64d(string $s): string { $r = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true); return $r === false ? '' : $r; }
function pk_rp_id(): string { return strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''))); }
function pk_origin_ok(string $origin): bool {
    $host = pk_rp_id(); $h = (string)($_SERVER['HTTP_HOST'] ?? '');
    return $origin === 'https://' . $h || (in_array($host, ['localhost', '127.0.0.1'], true) && $origin === 'http://' . $h);
}

/** Minimal CBOR reader (maps, arrays, ints, byte/text strings), enough for the registration data. */
function pk_cbor(string $d, int &$i) {
    $b = ord($d[$i++]); $mt = $b >> 5; $ai = $b & 31;
    if ($ai < 24) $n = $ai;
    elseif ($ai === 24) $n = ord($d[$i++]);
    elseif ($ai === 25) { $n = unpack('n', substr($d, $i, 2))[1]; $i += 2; }
    elseif ($ai === 26) { $n = unpack('N', substr($d, $i, 4))[1]; $i += 4; }
    else throw new RuntimeException('cbor');
    switch ($mt) {
        case 0: return $n;
        case 1: return -1 - $n;
        case 2: case 3: $s = substr($d, $i, $n); if (strlen($s) !== $n) throw new RuntimeException('cbor'); $i += $n; return $s;
        case 4: $a = []; for ($k = 0; $k < $n; $k++) $a[] = pk_cbor($d, $i); return $a;
        case 5: $m = []; for ($k = 0; $k < $n; $k++) { $key = pk_cbor($d, $i); $m[$key] = pk_cbor($d, $i); } return $m;
    }
    throw new RuntimeException('cbor');
}
function pk_der_len(int $n): string { return $n < 128 ? chr($n) : ($n < 256 ? "\x81" . chr($n) : "\x82" . pack('n', $n)); }
function pk_der(int $tag, string $c): string { return chr($tag) . pk_der_len(strlen($c)) . $c; }
function pk_pem(string $der): string { return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n"; }
/** COSE public key -> PEM, for ES256 (-7) and RS256 (-257). */
function pk_cose_pem(array $k): string {
    $kty = $k[1] ?? 0; $alg = $k[3] ?? 0;
    if ($kty === 2 && $alg === -7 && ($k[-1] ?? 0) === 1 && strlen((string)($k[-2] ?? '')) === 32 && strlen((string)($k[-3] ?? '')) === 32) {
        return pk_pem(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $k[-2] . $k[-3]);
    }
    if ($kty === 3 && $alg === -257 && isset($k[-1], $k[-2])) {
        $n = ltrim((string)$k[-1], "\0"); if ($n === '' || ord($n[0]) > 127) $n = "\0" . $n;
        $e = ltrim((string)$k[-2], "\0"); if ($e === '' || ord($e[0]) > 127) $e = "\0" . $e;
        $rsa = pk_der(0x30, pk_der(0x02, $n) . pk_der(0x02, $e));
        $alg = hex2bin('300d06092a864886f70d0101010500');
        return pk_pem(pk_der(0x30, $alg . pk_der(0x03, "\0" . $rsa)));
    }
    throw new RuntimeException('key');
}

function pk_challenge(string $kind): string {
    $c = random_bytes(32); $_SESSION['pk'] = ['k' => $kind, 'c' => pk_b64e($c), 't' => time()];
    return pk_b64e($c);
}
/** Checks clientDataJSON against the challenge saved for this session; returns the raw client data hash. */
function pk_client_data(string $kind, string $json): string {
    $st = $_SESSION['pk'] ?? null; unset($_SESSION['pk']);
    if (!$st || $st['k'] !== $kind || $st['t'] < time() - 300) throw new RuntimeException('challenge');
    $cd = json_decode($json, true);
    if (!is_array($cd) || ($cd['type'] ?? '') !== ($kind === 'create' ? 'webauthn.create' : 'webauthn.get')) throw new RuntimeException('type');
    if (!hash_equals($st['c'], (string)($cd['challenge'] ?? ''))) throw new RuntimeException('challenge');
    if (!pk_origin_ok((string)($cd['origin'] ?? ''))) throw new RuntimeException('origin');
    return hash('sha256', $json, true);
}

function passkey_list(int $uid): array {
    $s = db()->prepare('SELECT id, label, created_at, last_used_at FROM passkeys WHERE user_id = ? ORDER BY id DESC'); $s->execute([$uid]); return $s->fetchAll();
}
function passkey_routes(string $path, bool $post): void {
    if (strncmp($path, '/passkey/', 9) !== 0 || !$post) return;
    try {
        if ($path === '/passkey/reg-options') {
            $u = current_user(); if (!$u) device_json(['ok' => false, 'error' => 'login'], 401);
            $ex = []; foreach (db()->query('SELECT cred_id FROM passkeys WHERE user_id = ' . (int)$u['id'])->fetchAll() as $r) $ex[] = ['type' => 'public-key', 'id' => $r['cred_id']];
            device_json(['ok' => true, 'publicKey' => [
                'challenge' => pk_challenge('create'),
                'rp' => ['name' => 'Moriwella', 'id' => pk_rp_id()],
                'user' => ['id' => pk_b64e(hash('sha256', 'mw' . $u['id'], true)), 'name' => (string)$u['email'], 'displayName' => (string)($u['name'] ?? $u['email'])],
                'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
                'authenticatorSelection' => ['residentKey' => 'preferred', 'userVerification' => 'required'],
                'excludeCredentials' => $ex, 'timeout' => 120000, 'attestation' => 'none']]);
        }
        if ($path === '/passkey/register') {
            $u = current_user(); if (!$u) device_json(['ok' => false, 'error' => 'login'], 401);
            pk_client_data('create', pk_b64d((string)($_POST['clientData'] ?? '')));
            $att = pk_b64d((string)($_POST['attestation'] ?? '')); $i = 0; $o = pk_cbor($att, $i);
            $ad = (string)($o['authData'] ?? '');
            if (strlen($ad) < 55 || !hash_equals(hash('sha256', pk_rp_id(), true), substr($ad, 0, 32))) throw new RuntimeException('rp');
            $flags = ord($ad[32]); if (!($flags & 1) || !($flags & 4) || !($flags & 64)) throw new RuntimeException('flags');
            $len = unpack('n', substr($ad, 53, 2))[1]; $cred = substr($ad, 55, $len); $i = 55 + $len;
            $pem = pk_cose_pem(pk_cbor($ad, $i));
            $label = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 80);
            $p = db();
            $p->prepare('INSERT INTO passkeys (user_id, cred_id, public_key, sign_count, label, created_at, last_used_at) VALUES (?,?,?,?,?,?,?)')
              ->execute([$u['id'], pk_b64e($cred), $pem, unpack('N', substr($ad, 33, 4))[1], $label, time(), time()]);
            $p->prepare('DELETE FROM passkeys WHERE user_id = ? AND id NOT IN (SELECT id FROM passkeys WHERE user_id = ? ORDER BY id DESC LIMIT ' . PASSKEY_MAX . ')')->execute([$u['id'], $u['id']]);
            device_json(['ok' => true]);
        }
        if ($path === '/passkey/login-options') {
            if (too_many_attempts()) device_json(['ok' => false, 'error' => 'slow'], 429);
            device_json(['ok' => true, 'publicKey' => ['challenge' => pk_challenge('get'), 'rpId' => pk_rp_id(), 'userVerification' => 'required', 'timeout' => 120000]]);
        }
        if ($path === '/passkey/login') {
            if (too_many_attempts()) device_json(['ok' => false, 'error' => 'slow'], 429);
            $hash = pk_client_data('get', pk_b64d((string)($_POST['clientData'] ?? '')));
            $s = db()->prepare("SELECT p.*, u.id AS uid FROM passkeys p JOIN users u ON u.id = p.user_id WHERE p.cred_id = ? AND u.status = 'active'");
            $s->execute([(string)($_POST['id'] ?? '')]); $pk = $s->fetch();
            if (!$pk) throw new RuntimeException('unknown');
            $ad = pk_b64d((string)($_POST['authData'] ?? ''));
            if (strlen($ad) < 37 || !hash_equals(hash('sha256', pk_rp_id(), true), substr($ad, 0, 32))) throw new RuntimeException('rp');
            $flags = ord($ad[32]); if (!($flags & 1) || !($flags & 4)) throw new RuntimeException('flags');
            $ok = openssl_verify($ad . $hash, pk_b64d((string)($_POST['signature'] ?? '')), $pk['public_key'], OPENSSL_ALGO_SHA256);
            if ($ok !== 1) throw new RuntimeException('sig');
            $cnt = unpack('N', substr($ad, 33, 4))[1];
            if ($cnt !== 0 && $cnt <= (int)$pk['sign_count']) throw new RuntimeException('clone');
            db()->prepare('UPDATE passkeys SET sign_count = ?, last_used_at = ? WHERE id = ?')->execute([$cnt, time(), $pk['id']]);
            $us = db()->prepare('SELECT * FROM users WHERE id = ?'); $us->execute([$pk['uid']]);
            login_user($us->fetch());
            device_json(['ok' => true, 'next' => url('/')]);
        }
        if ($path === '/passkey/remove') {
            $u = current_user(); if (!$u) device_json(['ok' => false, 'error' => 'login'], 401);
            db()->prepare('DELETE FROM passkeys WHERE user_id = ? AND id = ?')->execute([$u['id'], (int)($_POST['id'] ?? 0)]);
            device_json(['ok' => true]);
        }
    } catch (Throwable $e) {
        if ($path === '/passkey/login') record_failed_attempt();
        device_json(['ok' => false, 'error' => 'invalid'], 400);
    }
    device_json(['ok' => false, 'error' => 'invalid'], 404);
}
