<?php
/* Google Play Billing: the Android app sells the same plans through Google, and this file turns a Google purchase into the same household subscription that Stripe makes.
   Copyright (c) JSW Software Designs

   Setup (outside the website, never in the repo):
     data_dir()/play_service_account.json   the service account key from Google Cloud, with "View financial data" and "Manage orders and subscriptions" in Play Console
     data_dir()/play.php  (optional)        return ['package' => 'com.moriwella.app'];
   Subscriptions in Play Console: one product per plan, product id "moriwella_<plan key>" (moriwella_individual, moriwella_family, moriwella_family_pro),
   one monthly base plan named "monthly", and a 14-day free-trial offer. */

const PLAY_BASE_PLAN = 'monthly';

function play_cfg(): array {
    $f = data_dir() . '/play.php'; $c = is_file($f) ? (array)(require $f) : [];
    return $c + ['package' => 'com.moriwella.app'];
}
function play_ready(): bool { return is_file(data_dir() . '/play_service_account.json'); }

/** Product id in Play Console for a plan key, and back. */
function play_product_for(string $planKey): string { return 'moriwella_' . $planKey; }
function play_plan_for_product(string $productId): ?string {
    foreach (array_keys(plans()) as $k) if (play_product_for($k) === $productId) return $k;
    return null;
}

/** A private tag for a household, sent to Google with the purchase so the purchase can be tied back to the right account. */
function play_account_tag(int $hid): string {
    $f = data_dir() . '/play_secret.txt';
    if (!is_file($f)) { @file_put_contents($f, bin2hex(random_bytes(24))); @chmod($f, 0600); }
    return substr(hash_hmac('sha256', 'household:' . $hid, (string)@file_get_contents($f)), 0, 40);
}

/** One HTTPS call to Google. Tests replace the transport with $GLOBALS['mw_play_http']. Returns [status, decoded body]. */
function play_http(string $method, string $url, array $headers = [], string $body = ''): array {
    if (!empty($GLOBALS['mw_play_http'])) return $GLOBALS['mw_play_http']($method, $url, $headers, $body);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
    if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = curl_error($ch); curl_close($ch);
    if ($raw === false) throw new RuntimeException('Could not reach Google: ' . $err);
    return [$code, json_decode((string)$raw, true) ?: []];
}
function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

/** An access token for the Play Developer API, kept until shortly before it expires. */
function play_access_token(): string {
    $cache = data_dir() . '/play_token.json';
    if (is_file($cache)) { $c = json_decode((string)file_get_contents($cache), true); if (is_array($c) && ($c['exp'] ?? 0) > time() + 120) return (string)$c['token']; }
    $sa = json_decode((string)@file_get_contents(data_dir() . '/play_service_account.json'), true);
    if (!is_array($sa) || empty($sa['private_key']) || empty($sa['client_email'])) throw new RuntimeException('Google Play is not set up.');
    $now = time();
    $head = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = b64url(json_encode(['iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/androidpublisher', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3000]));
    $sig = ''; if (!openssl_sign("$head.$claim", $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) throw new RuntimeException('Could not sign the Google request.');
    [$st, $j] = play_http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$head.$claim." . b64url($sig)]));
    if ($st >= 400 || empty($j['access_token'])) { billing_log('play token error ' . $st . ' ' . substr(json_encode($j), 0, 200)); throw new RuntimeException('Google sign-in failed.'); }
    @file_put_contents($cache, json_encode(['token' => $j['access_token'], 'exp' => $now + (int)($j['expires_in'] ?? 3000)])); @chmod($cache, 0600);
    return (string)$j['access_token'];
}
function play_api(string $method, string $path, string $body = ''): array {
    [$st, $j] = play_http($method, 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/' . rawurlencode(play_cfg()['package']) . $path,
        ['Authorization: Bearer ' . play_access_token(), 'Content-Type: application/json'], $body);
    if ($st >= 400) { billing_log('play api ' . $method . ' ' . $path . ' -> ' . $st . ' ' . substr(json_encode($j), 0, 300)); throw new RuntimeException('Google said no (' . $st . ').'); }
    return $j;
}
/** Google's record of one subscription purchase. */
function play_get_subscription(string $token): array { return play_api('GET', '/purchases/subscriptionsv2/tokens/' . rawurlencode($token)); }

/** What we store for each Google state. Access follows our 'active' meaning: paid, trialing, in a grace period, or cancelled but not yet expired. */
function play_status(array $g): array {
    $state = (string)($g['subscriptionState'] ?? ''); $line = $g['lineItems'][0] ?? [];
    $exp = !empty($line['expiryTime']) ? (int)strtotime((string)$line['expiryTime']) : 0;
    $cancelling = 0;
    switch ($state) {
        case 'SUBSCRIPTION_STATE_ACTIVE': $status = 'active'; $cancelling = !empty($line['autoRenewingPlan']) && empty($line['autoRenewingPlan']['autoRenewEnabled']) ? 1 : 0; break;
        case 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD': $status = 'past_due'; break;
        case 'SUBSCRIPTION_STATE_CANCELED': $status = $exp > time() ? 'active' : 'canceled'; $cancelling = 1; break;
        case 'SUBSCRIPTION_STATE_ON_HOLD': case 'SUBSCRIPTION_STATE_PAUSED': case 'SUBSCRIPTION_STATE_EXPIRED': $status = 'canceled'; break;
        default: $status = ''; // pending purchase: nothing to grant yet
    }
    return [$status, $exp, $cancelling];
}

/** Write a Google subscription onto a household. Returns the stored status, or '' when there is nothing to store yet. */
function play_apply(array $g, int $hid, string $token): string {
    [$status, $exp, $cancelling] = play_status($g); if ($status === '') return '';
    $productId = (string)($g['lineItems'][0]['productId'] ?? ''); $planKey = play_plan_for_product($productId); if (!$planKey) throw new RuntimeException('Unknown Google plan ' . $productId);
    $iv = ((string)($g['lineItems'][0]['offerDetails']['basePlanId'] ?? 'monthly')) === 'yearly' ? 'year' : 'month';   // base plan ids in Play: "monthly" and "yearly"
    $ps = $exp > 0 ? (int)(new DateTime('@' . $exp))->modify($iv === 'year' ? '-1 year' : '-1 month')->getTimestamp() : time();
    $limit = (int)(plan_for($planKey)['seats'] ?? 1); $cur = subscription_of($hid);
    billing_tx(function () use ($hid, $token, $planKey, $status, $ps, $exp, $cancelling, $limit, $cur, $productId, $iv) {
        $applyPending = ($cur['pending_plan_key'] !== '' && $cur['pending_plan_key'] === $planKey);
        if ($applyPending) household_apply_keep($hid, $limit, (array)json_decode((string)$cur['pending_keep'], true));
        db()->prepare("INSERT INTO subscriptions (household_id, stripe_subscription_id, plan_key, status, period_start, period_end, cancel_at_period_end, seat_limit, interval_key, trial_start, trial_end, source, play_token, play_product, updated_at)
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'play', ?, ?, ?)
            ON CONFLICT(household_id) DO UPDATE SET stripe_subscription_id = NULL, plan_key = excluded.plan_key, status = excluded.status, period_start = excluded.period_start, period_end = excluded.period_end,
              cancel_at_period_end = excluded.cancel_at_period_end, seat_limit = excluded.seat_limit, interval_key = excluded.interval_key, source = 'play', play_token = excluded.play_token, play_product = excluded.play_product, updated_at = excluded.updated_at,
              pending_plan_key = CASE WHEN ? THEN '' ELSE pending_plan_key END, pending_keep = CASE WHEN ? THEN '' ELSE pending_keep END")
            ->execute([$hid, $planKey, $status, $ps, $exp, $cancelling, $limit, $iv, (int)$cur['trial_start'], (int)$cur['trial_end'], $token, $productId, time(), $applyPending ? 1 : 0, $applyPending ? 1 : 0]);
        if (in_array($status, ['active', 'past_due'], true)) db()->prepare('UPDATE subscriptions SET comped = 0 WHERE household_id = ?')->execute([$hid]);
    });
    if (in_array($status, ['active', 'past_due'], true) && $exp > time()) credits_grant_period($hid, 'p' . $ps, (int)(plan_for($planKey)['credits_per_member'] ?? 2));   // each period's movies, once (keyed by the period)
    return $status;
}

/** The person bought a plan in the Android app: check it with Google, tie it to their household, store it. Returns null on success, otherwise a language key. */
function play_verify_purchase(int $hid, string $token): ?string {
    if (!play_ready()) return 'billing.err_not_ready';
    if ($token === '' || strlen($token) > 600) return 'billing.err_play';
    $sub = subscription_of($hid);
    if (!empty($sub['stripe_subscription_id']) && in_array($sub['status'], ['active', 'trialing', 'past_due'], true)) return 'billing.err_has_web_plan';
    $used = db()->prepare('SELECT household_id FROM subscriptions WHERE play_token = ? AND household_id <> ?'); $used->execute([$token, $hid]);
    if ($used->fetchColumn()) { billing_log("play token already used by another household (tried $hid)"); return 'billing.err_play'; }
    try {
        $g = play_get_subscription($token);
        $tag = (string)($g['externalAccountIdentifiers']['obfuscatedExternalAccountId'] ?? '');
        if ($tag === '' || !hash_equals(play_account_tag($hid), $tag)) { billing_log("play purchase for household $hid has the wrong account tag"); return 'billing.err_play'; }
        $status = play_apply($g, $hid, $token);
        if ($status === '') return 'billing.err_play_pending';
        if (($g['acknowledgementState'] ?? '') === 'ACKNOWLEDGEMENT_STATE_PENDING') {
            try { play_api('POST', '/purchases/subscriptions/' . rawurlencode((string)$g['lineItems'][0]['productId']) . '/tokens/' . rawurlencode($token) . ':acknowledge', '{}'); } catch (Throwable $t) { /* the app acknowledges too */ }
        }
        billing_log("play subscription for household $hid: $status");
        return null;
    } catch (Throwable $t) { billing_log('play verify error: ' . $t->getMessage()); return 'billing.err_play'; }
}

/** Renewals and cancellations are not pushed to us yet, so ask Google again when a Google-billed household looks stale: hourly, and right after a period should have renewed. */
function play_refresh_if_due(int $hid, array $sub): void {
    if (($sub['source'] ?? 'stripe') !== 'play' || ($sub['play_token'] ?? '') === '' || !play_ready()) return;
    $age = time() - (int)$sub['updated_at'];
    $due = $age > 3600 || ((int)$sub['period_end'] < time() + 60 && $age > 300);
    if (!$due) return;
    try { play_apply(play_get_subscription((string)$sub['play_token']), $hid, (string)$sub['play_token']); }
    catch (Throwable $t) { db()->prepare('UPDATE subscriptions SET updated_at = ? WHERE household_id = ?')->execute([time(), $hid]); billing_log('play refresh error: ' . $t->getMessage()); }
}
