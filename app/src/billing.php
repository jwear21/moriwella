<?php
/* Billing screens and Stripe checkout. Credits themselves live in credits.php; this file only talks to people and to Stripe.
   Copyright (c) JSW Software Designs */
require_once __DIR__ . '/stripe.php';

function money(int $cents): string { return '$' . number_format($cents / 100, $cents % 100 ? 2 : 0); }
function billing_back(string $q = ''): never { redirect(url('/billing') . $q); }
function billing_abs(string $p): string { return rtrim((string)cfg('app_url'), '/') . url($p); }

/** The Stripe customer for this household, created the first time it is needed. */
function billing_customer(array $b, array $user): string {
    $h = $b['household'];
    if (!empty($h['stripe_customer_id'])) return $h['stripe_customer_id'];
    $c = stripe_api('POST', '/v1/customers', ['email' => $user['email'], 'name' => $user['name'], 'metadata' => ['household_id' => $h['id'], 'app' => 'moriwella']], 'mw-cus-' . $h['id']);
    db()->prepare('UPDATE households SET stripe_customer_id = ? WHERE id = ?')->execute([$c['id'], $h['id']]);
    return $c['id'];
}

/** POST /webhooks/stripe — called by Stripe, not by a person: no sign-in, no CSRF, but the signature must verify. */
function stripe_webhook(): never {
    $payload = (string)file_get_contents('php://input'); $sig = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
    $secret = (string)(stripe_cfg()['webhook_secret'] ?? '');
    if (!stripe_verify($payload, $sig, $secret)) { http_response_code(400); echo 'bad signature'; exit; }
    $ev = json_decode($payload, true); if (!is_array($ev)) { http_response_code(400); exit; }
    try { $out = stripe_handle_event($ev); http_response_code(200); echo $out; }
    catch (Throwable $t) { http_response_code(500); echo 'error'; }   // Stripe will retry
    exit;
}

if (strpos($path, '/billing') === 0 || $path === '/pricing' || strpos($path, '/household') === 0) {
    $uid = (int)$user['id']; $b = billing_for($uid); $isOwner = $b['is_owner'];

    if ($path === '/pricing' && in_app()) { billing_back(); }
    if ($path === '/pricing') { render('pricing', ['b' => $b, 'packs' => credit_packs(), 'ready' => stripe_ready()], 'Plans'); exit; }

    if ($path === '/billing') {
        credits_ensure_comped($uid);
        render('billing', ['b' => $b, 'bal' => credits_balance($uid), 'activity' => credits_activity($uid), 'members' => household_members((int)$b['household']['id']),
            'active' => household_active_count((int)$b['household']['id']), 'packs' => credit_packs(), 'ready' => stripe_ready(), 'plans' => plans(), 'newLink' => ($l = $_SESSION['new_link'] ?? null) ? $_SESSION['new_link'] : null], 'Plan and credits');
        unset($_SESSION['new_link']); exit;
    }

    if ($post && in_app() && in_array($path, ['/billing/subscribe', '/billing/credits', '/billing/portal', '/billing/change'], true)) { billing_back(); }
    if ($post && in_array($path, ['/billing/subscribe', '/billing/credits', '/billing/portal', '/billing/change'], true)) {
        if (!stripe_ready() || stripe_live_blocked()) { flash('err', tt('billing.err_not_ready')); billing_back(); }
        $isPack = $path === '/billing/credits';
        if (!$isOwner && !$isPack) { flash('err', tt('billing.err_owner_only')); billing_back(); }   // anyone may buy credits for themselves; the plan is the owner's
        try {
            $cus = $isOwner ? billing_customer($b, $user) : '';
            $hid = (int)$b['household']['id'];
            if ($path === '/billing/portal') {
                $s = stripe_api('POST', '/v1/billing_portal/sessions', ['customer' => $cus, 'return_url' => billing_abs('/billing')]);
                redirect($s['url']);
            }
            if ($path === '/billing/credits') {
                $pack = credit_packs()[$_POST['pack'] ?? ''] ?? null; if (!$pack) billing_back();
                $price = stripe_price($pack['lookup']); if (!$price) { flash('err', tt('billing.err_not_ready')); billing_back(); }
                $s = stripe_api('POST', '/v1/checkout/sessions', ['mode' => 'payment', 'customer' => $cus ?: null, 'customer_email' => $cus ? null : $user['email'], 'client_reference_id' => $hid,
                    'line_items' => [['price' => $price, 'quantity' => 1]],
                    'metadata' => ['kind' => 'credit_pack', 'pack' => $_POST['pack'], 'credits' => $pack['credits'], 'user_id' => $uid, 'household_id' => $hid],
                    'payment_intent_data' => ['metadata' => ['kind' => 'credit_pack', 'user_id' => $uid, 'household_id' => $hid]],
                    'success_url' => billing_abs('/billing?bought=1#credits'), 'cancel_url' => billing_abs('/billing#credits')]);
                redirect($s['url']);
            }
            $planKey = (string)($_POST['plan'] ?? ''); $plan = plan_for($planKey); if (!$plan) billing_back();
            $price = stripe_price($plan['lookup_month']); if (!$price) { flash('err', tt('billing.err_not_ready')); billing_back(); }
            $sub = $b['sub'];
            if ($path === '/billing/subscribe') {
                if (!empty($sub['stripe_subscription_id']) && in_array($sub['status'], ['active', 'trialing', 'past_due'], true)) billing_back();
                $data = ['metadata' => ['household_id' => $hid, 'plan_key' => $planKey]];
                /* the trial was ours: a card captured during it is charged only when it ends (Stripe needs the end date at least two days away) */
                $cardTrial = card_trial_pending($hid); $notice = '';
                if ($cardTrial) {
                    $data['trial_period_days'] = $cardTrial['days'];   // card on file now, first payment when the free days end (14, or a friend's 30)
                    $data['trial_settings'] = ['end_behavior' => ['missing_payment_method' => 'cancel']];
                    $notice = tt('billing.checkout_notice', ['days' => $cardTrial['days'], 'price' => money($plan['monthly_cents'])]);
                }
                elseif ($b['state'] === 'trial' && (int)$sub['trial_end'] > time() + 2 * 86400 + 600) $data['trial_end'] = (int)$sub['trial_end'];
                $s = stripe_api('POST', '/v1/checkout/sessions', ['mode' => 'subscription', 'customer' => $cus, 'client_reference_id' => $hid,
                    'line_items' => [['price' => $price, 'quantity' => 1]], 'subscription_data' => $data, 'payment_method_collection' => 'always',
                    'custom_text' => $notice !== '' ? ['submit' => ['message' => $notice]] : null,
                    'metadata' => ['kind' => 'subscription', 'plan_key' => $planKey, 'user_id' => $uid, 'household_id' => $hid],
                    'success_url' => billing_abs('/billing?subscribed=1'), 'cancel_url' => billing_abs('/pricing')]);
                redirect($s['url']);
            }
            /* change plan on an existing subscription */
            if (empty($sub['stripe_subscription_id'])) billing_back();
            $cur = plan_for($sub['plan_key']);
            if ($planKey === $sub['plan_key']) billing_back();
            $live = stripe_api('GET', '/v1/subscriptions/' . $sub['stripe_subscription_id']);
            $itemId = $live['items']['data'][0]['id'] ?? '';
            if ($plan['rank'] > $cur['rank']) {          // upgrade: now, prorated, paid before it applies
                stripe_api('POST', '/v1/subscriptions/' . $sub['stripe_subscription_id'], ['items' => [['id' => $itemId, 'price' => $price]], 'proration_behavior' => 'always_invoice', 'payment_behavior' => 'pending_if_incomplete', 'cancel_at_period_end' => 'false'], 'mw-up-' . $hid . '-' . $planKey . '-' . (int)$sub['period_start']);
                flash('ok', tt('billing.upgrade_started'));
            } else {                                      // downgrade: at the next renewal, and only after the owner picks who stays
                $keep = array_map('intval', (array)($_POST['keep'] ?? []));
                if (household_active_count($hid) > $plan['seats'] && count($keep) + 1 > $plan['seats']) { flash('err', tt('billing.err_choose_members', ['n' => $plan['seats']])); billing_back(); }
                $schedId = $live['schedule'] ?? '';
                if (!$schedId) { $sc = stripe_api('POST', '/v1/subscription_schedules', ['from_subscription' => $sub['stripe_subscription_id']]); $schedId = $sc['id']; }
                $sc = stripe_api('GET', '/v1/subscription_schedules/' . $schedId); $p0 = $sc['phases'][0];
                stripe_api('POST', '/v1/subscription_schedules/' . $schedId, ['end_behavior' => 'release', 'phases' => [
                    ['start_date' => $p0['start_date'], 'end_date' => $p0['end_date'], 'items' => [['price' => $p0['items'][0]['price'], 'quantity' => 1]]],
                    ['items' => [['price' => $price, 'quantity' => 1]]]]]);
                db()->prepare('UPDATE subscriptions SET pending_plan_key = ?, pending_keep = ? WHERE household_id = ?')->execute([$planKey, json_encode($keep), $hid]);
                flash('ok', tt('billing.downgrade_scheduled', ['date' => ldate('F j', (int)$sub['period_end'])]));
            }
            billing_back();
        } catch (Throwable $t) { billing_log('checkout error: ' . $t->getMessage()); flash('err', tt('billing.err_stripe')); billing_back(); }
    }

    if ($post && preg_match('#^/household/(\d+)/resend$#', $path, $m)) {
        if (!$isOwner) { flash('err', tt('billing.err_owner_only')); billing_back(); }
        $hid = (int)$b['household']['id'];
        $q = db()->prepare("SELECT u.* FROM users u JOIN household_members hm ON hm.user_id = u.id WHERE u.id = ? AND hm.household_id = ? AND hm.status = 'active' AND u.status = 'invited'"); $q->execute([(int)$m[1], $hid]); $t = $q->fetch();
        if (!$t) { flash('err', tt('billing.err_not_member')); billing_back(); }
        $link = make_invite((int)$t['id']); $emailed = invite_email($t, $link);
        flash('ok', tt($emailed ? 'billing.resent' : 'billing.resend_failed')); $_SESSION['new_link'] = ['name' => $t['name'], 'link' => $link, 'emailed' => $emailed, 'email' => $t['email']];
        billing_back();
    }
    if ($post && $path === '/household/add') {
        if (!$isOwner) { flash('err', tt('billing.err_owner_only')); billing_back(); }
        $email = strtolower(trim((string)($_POST['email'] ?? ''))); $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('err', tt('billing.err_email')); billing_back(); }
        $hid = (int)$b['household']['id'];
        $q = db()->prepare('SELECT * FROM users WHERE email = ?'); $q->execute([$email]); $t = $q->fetch();
        $link = null;
        if ($t) {
            $err = household_add_member($hid, (int)$t['id']);
            /* someone who was invited before but never signed in: use the name just typed and send a fresh invitation */
            if (!$err && $t['status'] === 'invited') {
                if ($name !== '') { db()->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, (int)$t['id']]); }
                else { $name = (string)$t['name']; }
                $link = make_invite((int)$t['id']);
            } elseif (!$err) { $name = $name !== '' ? $name : (string)$t['name']; }
        } else {
            if ($name === '') $name = explode('@', $email)[0];
            $err = billing_tx(function () use ($hid, $email, $name, &$link) {
                $sub = subscription_of($hid);
                if (!in_array('family_sharing', state_features(billing_state($sub), $sub['plan_key']), true)) return 'billing.err_no_family';
                if (household_active_count($hid) >= (int)$sub['seat_limit']) return 'billing.err_no_seats';
                db()->prepare("INSERT INTO users (email, name, role, plan, status, created_at) VALUES (?, ?, 'member', 'member', 'invited', ?)")->execute([$email, $name, time()]);
                $nid = (int)db()->lastInsertId();
                $r = household_add_member($hid, $nid); if ($r) return $r;
                $link = make_invite($nid); return null;
            });
        }
        $emailed = (!$err && $link) ? invite_email(['name' => $name, 'email' => $email], $link) : false;
        if ($err) flash('err', tt($err)); else { flash('ok', tt('billing.member_added')); if ($link) $_SESSION['new_link'] = ['name' => $name, 'link' => $link, 'emailed' => $emailed, 'email' => $email]; }
        billing_back();
    }
    if ($post && preg_match('#^/household/(\d+)/remove$#', $path, $m)) {
        if (!$isOwner) { flash('err', tt('billing.err_owner_only')); billing_back(); }
        $err = household_remove_member((int)$b['household']['id'], (int)$m[1]);
        flash($err ? 'err' : 'ok', tt($err ?? 'billing.member_removed')); billing_back();
    }
}
