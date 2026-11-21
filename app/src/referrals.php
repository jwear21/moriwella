<?php
/* Referrals. A member shares a personal link. A friend who signs up through it gets the first 30 days of the plan they choose included
   (card on file, not billed for 30 days). The member gets one month free once that friend's first payment has gone through.
   Rules and bookkeeping live here; Stripe only carries out the two money steps (a 30-day start, an account credit).
   Copyright (c) JSW Software Designs */

const REFERRAL_FRIEND_DAYS = 30;
const REFERRAL_CREDITS_REWARD = 3;   // movie credits for a member who has no bill to take a month off (free or trial accounts)

function referral_code(int $uid): string {
    $q = db()->prepare('SELECT referral_code FROM users WHERE id = ?'); $q->execute([$uid]); $c = (string)$q->fetchColumn();
    if ($c !== '') return $c;
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
    for ($try = 0; $try < 8; $try++) {
        $c = ''; for ($i = 0; $i < 8; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)];
        $u = db()->prepare('UPDATE users SET referral_code = ? WHERE id = ? AND referral_code IS NULL');
        try { $u->execute([$c, $uid]); } catch (Throwable $e) { continue; }
        $q->execute([$uid]); $now = (string)$q->fetchColumn(); if ($now !== '') return $now;
    }
    throw new RuntimeException('Could not make a referral code.');
}
function referral_link(int $uid): string { return rtrim((string)cfg('app_url'), '/') . url('/r/' . referral_code($uid)); }
function referrer_by_code(string $code): ?array {
    if (!preg_match('/^[a-z0-9]{6,12}$/', $code)) return null;
    $q = db()->prepare("SELECT id, name, email FROM users WHERE referral_code = ? AND status = 'active'"); $q->execute([$code]); return $q->fetch() ?: null;
}
/** Called when a brand-new account is created from the signup form: remembers who sent them. Existing accounts never qualify. */
function referral_record_signup(int $friendId, string $friendEmail): void {
    $code = (string)($_SESSION['ref_code'] ?? ''); if ($code === '') return;
    $r = referrer_by_code($code); if (!$r) return;
    if (billing_norm_email((string)$r['email']) === billing_norm_email($friendEmail)) return;   // no referring yourself
    db()->prepare('UPDATE users SET referred_by = ? WHERE id = ?')->execute([(int)$r['id'], $friendId]);
    db()->prepare("INSERT OR IGNORE INTO referrals (referrer_id, friend_id, status, created_at) VALUES (?, ?, 'signed_up', ?)")->execute([(int)$r['id'], $friendId, time()]);
    unset($_SESSION['ref_code']);
}
function referral_of_friend(int $friendId): ?array {
    $q = db()->prepare('SELECT r.*, u.name AS referrer_name FROM referrals r JOIN users u ON u.id = r.referrer_id WHERE r.friend_id = ?'); $q->execute([$friendId]); return $q->fetch() ?: null;
}
/** True while a referred household still has its 30 days to start (no subscription yet). */
function referral_pending_start(int $hid): ?array {
    $h = db()->prepare('SELECT owner_user_id FROM households WHERE id = ?'); $h->execute([$hid]); $o = (int)$h->fetchColumn(); if ($o < 1) return null;
    $r = referral_of_friend($o); if (!$r || $r['status'] !== 'signed_up') return null;
    $s = subscription_of($hid); if (!empty($s['stripe_subscription_id']) || !empty($s['comped'])) return null;
    return $r;
}
/** Checkout for a referred friend finished and the 30 days are running: give the first period's movie credits. */
function referral_on_started(array $h, array $stripeSub): void {
    $r = referral_of_friend((int)$h['owner_user_id']); if (!$r || $r['status'] !== 'signed_up') return;
    if (($stripeSub['status'] ?? '') !== 'trialing') return;
    db()->prepare("UPDATE referrals SET status = 'started', started_at = ? WHERE id = ?")->execute([time(), $r['id']]);
    [$ps] = stripe_sub_period($stripeSub); $sub = subscription_of((int)$h['id']);
    if ($ps > 0) credits_grant_period((int)$h['id'], 'p' . $ps, (int)(plan_for($sub['plan_key'])['credits_per_member'] ?? 2));
}
/** The friend's first real payment arrived. */
function referral_on_paid(array $h, array $inv): void {
    $paid = (int)($inv['amount_paid'] ?? 0); if ($paid < 1) return;
    $r = referral_of_friend((int)$h['owner_user_id']); if (!$r || !in_array($r['status'], ['signed_up', 'started'], true)) return;
    db()->prepare("UPDATE referrals SET status = 'qualified', qualified_at = ?, first_amount = ? WHERE id = ?")->execute([time(), $paid, $r['id']]);
    referral_reward((int)$r['id']);
}
/** Pay the referrer. Safe to call again for a reward that has not gone through yet. */
function referral_reward(int $rid): void {
    $q = db()->prepare("SELECT * FROM referrals WHERE id = ? AND status = 'qualified'"); $q->execute([$rid]); $r = $q->fetch(); if (!$r) return;
    $rh = household_ensure((int)$r['referrer_id']); if (!$rh) return;
    $sub = subscription_of((int)$rh['id']); $plan = plan_for($sub['plan_key']);
    try {
        if (billing_state($sub) === 'active' && empty($sub['comped']) && !empty($rh['stripe_customer_id']) && $plan) {
            $cents = ($sub['interval_key'] ?? 'month') === 'year' && !empty($plan['annual_cents']) ? (int)round($plan['annual_cents'] / 12) : (int)$plan['monthly_cents'];
            $t = stripe_api('POST', '/v1/customers/' . $rh['stripe_customer_id'] . '/balance_transactions', ['amount' => -$cents, 'currency' => 'usd', 'description' => 'Moriwella referral: one month free'], 'mw-ref-' . $r['id']);
            db()->prepare("UPDATE referrals SET status = 'rewarded', reward_kind = 'month', reward_ref = ?, reward_amount = ?, rewarded_at = ? WHERE id = ?")->execute([(string)($t['id'] ?? ''), $cents, time(), $r['id']]);
        } else {
            credits_grant((int)$r['referrer_id'], (int)$rh['id'], 'purchased', REFERRAL_CREDITS_REWARD, '', 'referral:' . $r['id'], '', 'Referral reward');
            db()->prepare("UPDATE referrals SET status = 'rewarded', reward_kind = 'credits', reward_ref = ?, reward_amount = ?, rewarded_at = ? WHERE id = ?")->execute(['referral:' . $r['id'], REFERRAL_CREDITS_REWARD, time(), $r['id']]);
        }
        billing_log('referral ' . $r['id'] . ' rewarded');
    } catch (Throwable $e) { billing_log('referral ' . $r['id'] . ' reward failed, will retry: ' . $e->getMessage()); }
}
/** Worker: retry rewards that could not be paid (for example Stripe was briefly unreachable). */
function referrals_worker_run(): void {
    foreach (db()->query("SELECT id FROM referrals WHERE status = 'qualified' AND qualified_at < " . (time() - 120))->fetchAll(PDO::FETCH_COLUMN) as $id) referral_reward((int)$id);
}
/** A full refund or a dispute on the friend's first payment: take the reward back. */
function referral_on_charge_problem(array $charge, bool $dispute = false): void {
    try {
        if ($dispute && !empty($charge['charge'])) $charge = stripe_api('GET', '/v1/charges/' . $charge['charge']);
        $amount = (int)($charge['amount'] ?? 0); $cus = (string)($charge['customer'] ?? '');
        if (!$dispute && (int)($charge['amount_refunded'] ?? 0) < $amount) return;   // a partial refund does not undo it
        $h = billing_household_by_customer($cus); if (!$h) return;
        $r = referral_of_friend((int)$h['owner_user_id']); if (!$r || $r['status'] !== 'rewarded' || (int)$r['first_amount'] !== $amount) return;
        if ($r['reward_kind'] === 'month') {
            $rh = household_ensure((int)$r['referrer_id']);
            if ($rh && !empty($rh['stripe_customer_id'])) stripe_api('POST', '/v1/customers/' . $rh['stripe_customer_id'] . '/balance_transactions', ['amount' => (int)$r['reward_amount'], 'currency' => 'usd', 'description' => 'Moriwella referral reversed: first payment refunded'], 'mw-refrev-' . $r['id']);
        } else {
            $rh = household_ensure((int)$r['referrer_id']);
            if ($rh) credits_grant((int)$r['referrer_id'], (int)$rh['id'], 'purchased', -(int)$r['reward_amount'], '', 'referral-reversed:' . $r['id'], '', 'Referral reversed: first payment refunded');
        }
        db()->prepare("UPDATE referrals SET status = 'reversed', reversed_at = ? WHERE id = ?")->execute([time(), $r['id']]);
        billing_log('referral ' . $r['id'] . ' reversed');
    } catch (Throwable $e) { billing_log('referral reversal error: ' . $e->getMessage()); }
}

function referral_summary(int $uid): array {
    $q = db()->prepare('SELECT r.*, u.name AS friend_name FROM referrals r JOIN users u ON u.id = r.friend_id WHERE r.referrer_id = ? ORDER BY r.id DESC'); $q->execute([$uid]);
    return $q->fetchAll();
}

/** Routes: public /r/<code>, and the member's /invite page. Returns only when the path is not ours. */
function referrals_public_route(string $path): void {
    if (preg_match('#^/r/([a-z0-9]{6,12})$#', $path, $m)) {
        if (referrer_by_code($m[1])) $_SESSION['ref_code'] = $m[1];
        redirect(url('/signup'));
    }
}
function referrals_member_routes(int $uid, array $user, string $path): void {
    if ($path !== '/invite') return;
    render('invite', ['link' => referral_link($uid), 'rows' => referral_summary($uid), 'creditsReward' => REFERRAL_CREDITS_REWARD, 'b' => billing_for($uid)], tt('invite.title'));
    exit;
}
