<?php
/* Stripe: a small API client, webhook signature check and event handling. Stripe is the payment processor only;
   credits are granted by src/credits.php after Stripe confirms payment. Copyright (c) JSW Software Designs */
require_once __DIR__ . '/credits.php';

/** Keys live outside the website: data_dir()/stripe.php returns ['secret_key' => 'sk_test_...', 'webhook_secret' => 'whsec_...', 'allow_live' => false].
    Environment variables STRIPE_SECRET_KEY and STRIPE_WEBHOOK_SECRET override. Price IDs come from data_dir()/stripe_prices.json (written by tools/stripe_setup.php). */
function stripe_cfg(): array {
    static $c = null; if ($c !== null) return $c;
    $f = data_dir() . '/stripe.php'; $c = is_file($f) ? (array)(require $f) : [];
    if (getenv('STRIPE_SECRET_KEY')) $c['secret_key'] = getenv('STRIPE_SECRET_KEY');
    if (getenv('STRIPE_WEBHOOK_SECRET')) $c['webhook_secret'] = getenv('STRIPE_WEBHOOK_SECRET');
    $p = data_dir() . '/stripe_prices.json'; $c['prices'] = is_file($p) ? (array)json_decode((string)file_get_contents($p), true) : [];
    return $c;
}
function stripe_ready(): bool { $c = stripe_cfg(); return !empty($c['secret_key']) && !empty($c['prices']); }
function stripe_mode(): string { $k = (string)(stripe_cfg()['secret_key'] ?? ''); return strpos($k, 'sk_live_') === 0 || strpos($k, 'rk_live_') === 0 ? 'live' : 'test'; }
/** Live billing needs explicit approval: a live key is refused unless the config says allow_live. */
function stripe_live_blocked(): bool { return stripe_mode() === 'live' && empty(stripe_cfg()['allow_live']); }
function stripe_price(string $key): ?string { return stripe_cfg()['prices'][$key] ?? null; }

/** Form-encode nested arrays the way Stripe expects: a[b][0]=c. */
function stripe_encode(array $a, string $prefix = ''): array {
    $out = [];
    foreach ($a as $k => $v) {
        $name = $prefix === '' ? (string)$k : $prefix . '[' . $k . ']';
        if (is_array($v)) $out = array_merge($out, stripe_encode($v, $name));
        elseif ($v === true) $out[$name] = 'true'; elseif ($v === false) $out[$name] = 'false';
        elseif ($v !== null) $out[$name] = (string)$v;
    }
    return $out;
}

/** Call the Stripe API. Tests replace the transport with $GLOBALS['mw_stripe_http']. Throws RuntimeException with Stripe's message on failure. */
function stripe_api(string $method, string $path, array $params = [], string $idem = ''): array {
    if (stripe_live_blocked()) throw new RuntimeException('Live Stripe keys are not approved for use yet.');
    $key = (string)(stripe_cfg()['secret_key'] ?? ''); if ($key === '') throw new RuntimeException('Stripe is not configured.');
    $body = http_build_query(stripe_encode($params));
    if (!empty($GLOBALS['mw_stripe_http'])) return $GLOBALS['mw_stripe_http']($method, $path, $params, $idem);
    $url = 'https://api.stripe.com' . $path . ($method === 'GET' && $body !== '' ? '?' . $body : '');
    $ch = curl_init($url);
    $h = ['Authorization: Bearer ' . $key, 'Stripe-Version: ' . (stripe_cfg()['api_version'] ?? '2025-09-30.clover')]; if ($idem !== '') $h[] = 'Idempotency-Key: ' . $idem;
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h]);
    if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = curl_error($ch); curl_close($ch);
    if ($raw === false) throw new RuntimeException('Could not reach Stripe: ' . $err);
    $j = json_decode((string)$raw, true);
    if ($code >= 400 || !is_array($j)) { billing_log('stripe ' . $method . ' ' . $path . ' -> ' . $code . ' ' . substr((string)($j['error']['message'] ?? $raw), 0, 300)); throw new RuntimeException((string)($j['error']['message'] ?? 'Stripe error ' . $code)); }
    return $j;
}

/** Verify the Stripe-Signature header: HMAC-SHA256 of "timestamp.payload" with the endpoint secret, within five minutes. */
function stripe_verify(string $payload, string $header, string $secret, int $tolerance = 300, ?int $now = null): bool {
    $t = 0; $sigs = [];
    foreach (explode(',', $header) as $part) { [$k, $v] = array_pad(explode('=', trim($part), 2), 2, ''); if ($k === 't') $t = (int)$v; elseif ($k === 'v1') $sigs[] = $v; }
    if ($t <= 0 || !$sigs || $secret === '') return false;
    if (abs(($now ?? time()) - $t) > $tolerance) return false;
    $want = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($sigs as $s) if (hash_equals($want, $s)) return true;
    return false;
}

/* ---------- reading Stripe objects (the shape differs a little between API versions, so be tolerant) ---------- */

function stripe_plan_for_price(string $priceId): array {
    foreach (plans() as $key => $p) {
        foreach (['month' => 'lookup_month', 'year' => 'lookup_year'] as $iv => $lk) if ($priceId !== '' && $priceId === stripe_price($p[$lk])) return [$key, $iv];
    }
    return ['', ''];
}
function stripe_sub_price(array $sub): string { return (string)($sub['items']['data'][0]['price']['id'] ?? $sub['plan']['id'] ?? ''); }
function stripe_sub_period(array $sub): array {
    $i = $sub['items']['data'][0] ?? [];
    return [(int)($sub['current_period_start'] ?? $i['current_period_start'] ?? 0), (int)($sub['current_period_end'] ?? $i['current_period_end'] ?? 0)];
}
function stripe_invoice_sub_id(array $inv): string { return (string)($inv['subscription'] ?? $inv['parent']['subscription_details']['subscription'] ?? ''); }
function stripe_invoice_period(array $inv): array {
    $l = $inv['lines']['data'][0]['period'] ?? [];
    return [(int)($l['start'] ?? $inv['period_start'] ?? 0), (int)($l['end'] ?? $inv['period_end'] ?? 0)];
}
function billing_household_by_customer(string $cus): ?array { if ($cus === '') return null; $q = db()->prepare('SELECT * FROM households WHERE stripe_customer_id = ?'); $q->execute([$cus]); return $q->fetch() ?: null; }
function billing_household_by_sub(string $sid): ?array {
    if ($sid === '') return null;
    $q = db()->prepare('SELECT h.* FROM households h JOIN subscriptions s ON s.household_id = h.id WHERE s.stripe_subscription_id = ?'); $q->execute([$sid]); return $q->fetch() ?: null;
}

/** Copy a Stripe subscription onto our row. Called by several events; each is safe to repeat. A plan change that takes effect now applies any pending seat selection. */
/** True when the subscription will end at the close of the paid period. Newer Stripe versions (and the customer portal) express this as a cancel_at date rather than the older cancel_at_period_end flag. */
function stripe_sub_cancelling(array $sub): bool {
    if (!empty($sub['cancel_at_period_end'])) return true;
    $at = (int)($sub['cancel_at'] ?? 0);
    if ($at <= 0) return false;
    [, $pe] = stripe_sub_period($sub);
    return $pe <= 0 || $at <= $pe + 86400;
}
function billing_sync_subscription(array $sub, ?array $household = null): ?array {
    $h = $household ?? billing_household_by_sub((string)$sub['id']) ?? billing_household_by_customer((string)($sub['customer'] ?? '')) ?? (isset($sub['metadata']['household_id']) ? (function ($id) { $q = db()->prepare('SELECT * FROM households WHERE id = ?'); $q->execute([(int)$id]); return $q->fetch() ?: null; })($sub['metadata']['household_id']) : null);
    if (!$h) { billing_log('subscription ' . ($sub['id'] ?? '?') . ' has no matching household'); return null; }
    $hid = (int)$h['id']; $cur = subscription_of($hid);
    [$planKey, $iv] = stripe_plan_for_price(stripe_sub_price($sub));
    if ($planKey === '') $planKey = $sub['metadata']['plan_key'] ?? $cur['plan_key'];
    [$ps, $pe] = stripe_sub_period($sub);
    $limit = (int)(plan_for($planKey)['seats'] ?? 1);
    $applyPending = ($cur['pending_plan_key'] !== '' && $cur['pending_plan_key'] === $planKey);
    billing_tx(function () use ($hid, $sub, $planKey, $iv, $ps, $pe, $limit, $cur, $applyPending) {
        if ($applyPending) household_apply_keep($hid, $limit, (array)json_decode((string)$cur['pending_keep'], true));
        db()->prepare("INSERT INTO subscriptions (household_id, stripe_subscription_id, plan_key, status, period_start, period_end, cancel_at_period_end, seat_limit, interval_key, trial_start, trial_end, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(household_id) DO UPDATE SET stripe_subscription_id = excluded.stripe_subscription_id, plan_key = excluded.plan_key, status = excluded.status, period_start = excluded.period_start,
              period_end = excluded.period_end, cancel_at_period_end = excluded.cancel_at_period_end, seat_limit = excluded.seat_limit, interval_key = excluded.interval_key, updated_at = excluded.updated_at,
              pending_plan_key = CASE WHEN ? THEN '' ELSE pending_plan_key END, pending_keep = CASE WHEN ? THEN '' ELSE pending_keep END")
            ->execute([$hid, $sub['id'], $planKey, (string)$sub['status'], $ps, $pe, stripe_sub_cancelling($sub) ? 1 : 0, $limit, $iv ?: 'month', (int)$cur['trial_start'], (int)$cur['trial_end'], time(), $applyPending ? 1 : 0, $applyPending ? 1 : 0]);
        // A real paid plan replaces any free (comped) status; admins can re-comp the account later.
        if (in_array((string)$sub['status'], ['active', 'trialing', 'past_due'], true)) db()->prepare('UPDATE subscriptions SET comped = 0 WHERE household_id = ?')->execute([$hid]);
    });
    return $h;
}
/** After a downgrade takes effect: only the chosen people stay active (the owner always does). Nobody's content is touched. */
function household_apply_keep(int $hid, int $limit, array $keep): void {
    $q = db()->prepare("SELECT user_id, role FROM household_members WHERE household_id = ? AND status = 'active' ORDER BY (role = 'owner') DESC, id"); $q->execute([$hid]);
    $kept = 0;
    foreach ($q->fetchAll() as $m) {
        $ok = $m['role'] === 'owner' || in_array((int)$m['user_id'], array_map('intval', $keep), true);
        if ($ok && $kept < $limit) { $kept++; continue; }
        db()->prepare("UPDATE household_members SET status = 'suspended', removed_at = ? WHERE household_id = ? AND user_id = ?")->execute([time(), $hid, $m['user_id']]);
    }
}

/* ---------- webhook events ---------- */

/** Handle one verified event once. Duplicate deliveries are recognised by event id and do nothing. Returns a short outcome word. */
function stripe_handle_event(array $ev): string {
    $id = (string)($ev['id'] ?? ''); $type = (string)($ev['type'] ?? ''); if ($id === '' || $type === '') return 'ignored';
    $ins = db()->prepare("INSERT OR IGNORE INTO stripe_events (event_id, type, status, received_at) VALUES (?, ?, 'processing', ?)");
    $ins->execute([$id, $type, time()]);
    if ($ins->rowCount() === 0) {
        $st = db()->prepare('SELECT status FROM stripe_events WHERE event_id = ?'); $st->execute([$id]); $s = (string)$st->fetchColumn();
        if ($s !== 'failed') return 'duplicate';   // a failed one is retried by Stripe and may run again
        db()->prepare("UPDATE stripe_events SET status = 'processing', error = '' WHERE event_id = ?")->execute([$id]);
    }
    try {
        $out = billing_tx(fn() => stripe_dispatch($type, (array)($ev['data']['object'] ?? []), $ev));
        db()->prepare("UPDATE stripe_events SET status = 'done', processed_at = ? WHERE event_id = ?")->execute([time(), $id]);
        return $out;
    } catch (Throwable $t) {
        db()->prepare("UPDATE stripe_events SET status = 'failed', error = ? WHERE event_id = ?")->execute([substr($t->getMessage(), 0, 300), $id]);
        billing_log("event $id $type failed: " . $t->getMessage());
        throw $t;
    }
}

function stripe_dispatch(string $type, array $o, array $ev): string {
    switch ($type) {
        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded': return stripe_on_checkout($o);
        case 'customer.subscription.created':
        case 'customer.subscription.updated': return billing_sync_subscription($o) ? 'synced' : 'no household';
        case 'customer.subscription.deleted': { $o['status'] = 'canceled'; return billing_sync_subscription($o) ? 'canceled' : 'no household'; }
        case 'invoice.paid': return stripe_on_invoice_paid($o);
        case 'invoice.payment_failed': {
            $h = billing_household_by_sub(stripe_invoice_sub_id($o)) ?? billing_household_by_customer((string)($o['customer'] ?? ''));
            if ($h) { db()->prepare("UPDATE subscriptions SET status = 'past_due', updated_at = ? WHERE household_id = ? AND stripe_subscription_id IS NOT NULL")->execute([time(), $h['id']]); billing_log('payment failed for household ' . $h['id']); }
            return 'payment failed';
        }
        case 'customer.subscription.trial_will_end': billing_log('trial ending soon: ' . ($o['id'] ?? '?')); return 'noted';
        case 'charge.refunded': return stripe_on_refund($o);
        case 'charge.dispute.created': return stripe_on_dispute($o);
        case 'payment_intent.succeeded': return 'noted';   // credits and plans are granted from the checkout session, which carries our metadata
        default: return 'ignored';
    }
}

function stripe_on_checkout(array $s): string {
    if (($s['payment_status'] ?? '') !== 'paid' && ($s['mode'] ?? '') === 'payment') return 'unpaid';
    $uid = (int)($s['metadata']['user_id'] ?? 0); $hid = (int)($s['metadata']['household_id'] ?? $s['client_reference_id'] ?? 0);
    if (($s['mode'] ?? '') === 'payment' && ($s['metadata']['kind'] ?? '') === 'credit_pack') {
        $pack = credit_packs()[$s['metadata']['pack'] ?? ''] ?? null; if (!$pack || $uid < 1) { billing_log('checkout ' . ($s['id'] ?? '?') . ' has no valid credit pack'); return 'bad metadata'; }
        $cr = (int)($s['metadata']['credits'] ?? $pack['credits']);
        if ($cr !== $pack['credits']) $cr = $pack['credits'];   // never trust the quantity from the client
        $own = db()->prepare('SELECT COUNT(*) FROM household_members WHERE user_id = ? AND household_id = ?'); $own->execute([$uid, $hid]);
        if ((int)$own->fetchColumn() < 1) { $h = household_of($uid); $hid = $h ? (int)$h['id'] : 0; }
        $pi = (string)($s['payment_intent'] ?? '');
        $ok = credits_grant($uid, $hid, 'purchased', $cr, '', 'purchase:' . ($s['id'] ?? ''), (string)($s['id'] ?? ''), $pack['name']);
        if ($ok) db()->prepare("INSERT OR IGNORE INTO credit_purchases (user_id, household_id, pack_key, credits, amount_cents, currency, stripe_session_id, stripe_payment_intent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$uid, $hid, $s['metadata']['pack'], $cr, (int)($s['amount_total'] ?? $pack['cents']), (string)($s['currency'] ?? 'usd'), $s['id'], $pi, time()]);
        return $ok ? 'credits granted' : 'already granted';
    }
    if (($s['mode'] ?? '') === 'subscription') {
        $h = $hid ? (function ($id) { $q = db()->prepare('SELECT * FROM households WHERE id = ?'); $q->execute([$id]); return $q->fetch() ?: null; })($hid) : billing_household_by_customer((string)($s['customer'] ?? ''));
        if (!$h) return 'no household';
        if (!empty($s['customer'])) db()->prepare('UPDATE households SET stripe_customer_id = ? WHERE id = ? AND (stripe_customer_id IS NULL OR stripe_customer_id = ?)')->execute([$s['customer'], $h['id'], $s['customer']]);
        if (!empty($s['subscription'])) { try { $sub = stripe_api('GET', '/v1/subscriptions/' . $s['subscription']); billing_sync_subscription($sub, $h); } catch (Throwable $t) { billing_log('could not read subscription after checkout: ' . $t->getMessage()); } }
        return 'subscribed';
    }
    return 'ignored';
}

/** A paid invoice for a subscription. Only a real payment (amount above zero) opens a new credit period; a free trial invoice grants nothing. */
function stripe_on_invoice_paid(array $inv): string {
    $sid = stripe_invoice_sub_id($inv); $h = billing_household_by_sub($sid) ?? billing_household_by_customer((string)($inv['customer'] ?? ''));
    if (!$h) return 'no household';
    [$ps, $pe] = stripe_invoice_period($inv);
    $sub = subscription_of((int)$h['id']);
    if ($ps > 0 && (int)($inv['amount_paid'] ?? 0) > 0) {
        db()->prepare("UPDATE subscriptions SET status = 'active', period_start = ?, period_end = ?, updated_at = ? WHERE household_id = ?")->execute([$ps, $pe ?: $ps + 30 * 86400, time(), $h['id']]);
        $sub = subscription_of((int)$h['id']);
        $n = credits_grant_period((int)$h['id'], 'p' . $ps, (int)(plan_for($sub['plan_key'])['credits_per_member'] ?? 2));
        return "renewed, $n members credited";
    }
    db()->prepare("UPDATE subscriptions SET status = CASE WHEN status = 'past_due' THEN 'active' ELSE status END, updated_at = ? WHERE household_id = ?")->execute([time(), $h['id']]);
    return 'zero-amount invoice, no credits';
}

/** Refunds on credit-pack purchases: take back the matching credits. If they were already spent the balance goes negative, which blocks more spending until it is cleared. */
function stripe_on_refund(array $ch): string {
    $pi = (string)($ch['payment_intent'] ?? ''); if ($pi === '') return 'not a pack';
    $q = db()->prepare('SELECT * FROM credit_purchases WHERE stripe_payment_intent = ?'); $q->execute([$pi]); $p = $q->fetch(); if (!$p) return 'not a pack';
    $refunded = (int)($ch['amount_refunded'] ?? 0); $total = max(1, (int)$p['amount_cents']);
    $should = (int)min($p['credits'], floor($p['credits'] * $refunded / $total)); $more = $should - (int)$p['clawed_credits'];
    if ($more <= 0) return 'nothing more to take back';
    credits_grant((int)$p['user_id'], (int)$p['household_id'], 'purchased', -$more, '', 'refund:' . $pi . ':' . $should, $pi, 'Refund: credits returned');
    db()->prepare("UPDATE credit_purchases SET clawed_credits = ?, status = ? WHERE id = ?")->execute([$should, $should >= $p['credits'] ? 'refunded' : 'partially_refunded', $p['id']]);
    return "took back $more";
}
function stripe_on_dispute(array $d): string {
    $pi = (string)($d['payment_intent'] ?? ''); $q = db()->prepare('SELECT * FROM credit_purchases WHERE stripe_payment_intent = ?'); $q->execute([$pi]); $p = $q->fetch(); if (!$p) return 'not a pack';
    $more = (int)$p['credits'] - (int)$p['clawed_credits']; if ($more <= 0) return 'already taken back';
    credits_grant((int)$p['user_id'], (int)$p['household_id'], 'purchased', -$more, '', 'dispute:' . ($d['id'] ?? $pi), $pi, 'Payment disputed: credits returned');
    db()->prepare("UPDATE credit_purchases SET clawed_credits = credits, status = 'disputed' WHERE id = ?")->execute([$p['id']]);
    return "took back $more";
}
