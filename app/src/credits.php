<?php
/* Households, access state and the movie-credit ledger.
   This file is the authority on credits. Stripe only tells us that money arrived; every grant, reservation and refund is a ledger row here.
   No Stripe calls in this file, so the movie worker can use it too. Copyright (c) JSW Software Designs */
require_once __DIR__ . '/plans.php';

/** Run work in one write transaction (BEGIN IMMEDIATE), so two requests cannot both spend the same credit. Joins an open transaction. */
function billing_tx(callable $fn) {
    static $depth = 0;
    if ($depth > 0) return $fn();
    $pdo = db(); $pdo->exec('BEGIN IMMEDIATE'); $depth = 1;
    try { $r = $fn(); $pdo->exec('COMMIT'); return $r; }
    catch (Throwable $e) { try { $pdo->exec('ROLLBACK'); } catch (Throwable $x) {} throw $e; }
    finally { $depth = 0; }
}

function billing_log(string $msg): void { @file_put_contents(data_dir() . '/billing.log', date('c') . ' ' . $msg . "\n", FILE_APPEND); }

/** One trial per person: lower-case, and ignore Gmail dots and "+tag" so a@gmail.com, a.b+x@gmail.com count as the same address. */
function billing_norm_email(string $email): string {
    $email = strtolower(trim($email)); [$l, $d] = array_pad(explode('@', $email, 2), 2, '');
    $l = explode('+', $l, 2)[0];
    if ($d === 'googlemail.com') $d = 'gmail.com';
    if ($d === 'gmail.com') $l = str_replace('.', '', $l);
    return $l . '@' . $d;
}

/* ---------- households ---------- */

function household_of(int $uid): ?array {
    $q = db()->prepare("SELECT h.* FROM households h JOIN household_members m ON m.household_id = h.id WHERE m.user_id = ? AND m.status = 'active'");
    $q->execute([$uid]); return $q->fetch() ?: null;
}
function subscription_of(int $hid): array {
    $q = db()->prepare('SELECT * FROM subscriptions WHERE household_id = ?'); $q->execute([$hid]);
    return $q->fetch() ?: ['household_id' => $hid, 'status' => 'none', 'plan_key' => 'individual', 'comped' => 0, 'trial_end' => 0, 'period_start' => 0, 'period_end' => 0, 'seat_limit' => 1, 'cancel_at_period_end' => 0, 'pending_plan_key' => '', 'pending_keep' => ''];
}

/** The household this person belongs to; created on first need. Beta, demo and admin accounts are comped. Everyone else gets one 14-day trial. */
function household_ensure(int $uid): array {
    if ($h = household_of($uid)) return $h;
    return billing_tx(function () use ($uid) {
        if ($h = household_of($uid)) return $h;
        $u = db()->prepare('SELECT * FROM users WHERE id = ?'); $u->execute([$uid]); $u = $u->fetch();
        if (!$u) throw new RuntimeException('No such user.');
        $now = time();
        $prev = db()->prepare('SELECT id FROM households WHERE owner_user_id = ?'); $prev->execute([$uid]); $old = $prev->fetch();
        if ($old) { $hid = (int)$old['id']; db()->prepare("INSERT OR REPLACE INTO household_members (id, household_id, user_id, role, status, joined_at) VALUES ((SELECT id FROM household_members WHERE user_id = ?), ?, ?, 'owner', 'active', ?)")->execute([$uid, $hid, $uid, $now]); }
        else {
            db()->prepare('INSERT INTO households (owner_user_id, name, created_at) VALUES (?, ?, ?)')->execute([$uid, trim((string)$u['name']) . '\'s household', $now]);
            $hid = (int)db()->lastInsertId();
            /* someone who was removed from another household still has a membership row (user_id is unique): reuse it */
            $ex = db()->prepare('SELECT id FROM household_members WHERE user_id = ?'); $ex->execute([$uid]);
            if ($ex->fetch()) db()->prepare("UPDATE household_members SET household_id = ?, role = 'owner', status = 'active', joined_at = ?, removed_at = 0 WHERE user_id = ?")->execute([$hid, $now, $uid]);
            else db()->prepare("INSERT INTO household_members (household_id, user_id, role, status, joined_at) VALUES (?, ?, 'owner', 'active', ?)")->execute([$hid, $uid, $now]);
            $comped = $u['role'] === 'admin' || in_array($u['plan'], ['beta_free', 'demo', 'admin'], true);
            if ($comped) {
                db()->prepare("INSERT INTO subscriptions (household_id, plan_key, status, comped, seat_limit, updated_at) VALUES (?, 'individual', 'comped', 1, 1, ?)")->execute([$hid, $now]);
            } else {
                $norm = billing_norm_email((string)$u['email']);
                $claim = db()->prepare('INSERT OR IGNORE INTO trial_claims (email_norm, household_id, user_id, claimed_at) VALUES (?, ?, ?, ?)');
                $claim->execute([$norm, $hid, $uid, $now]);
                if ($claim->rowCount() === 1) {
                    /* trial: family-sized so people can try sharing; one free credit for the whole household, never renewed */
                    db()->prepare("INSERT INTO subscriptions (household_id, plan_key, status, trial_start, trial_end, seat_limit, updated_at) VALUES (?, 'family', 'none', ?, ?, ?, ?)")->execute([$hid, $now, $now + TRIAL_DAYS * 86400, plan_for('family')['seats'], $now]);
                    credits_grant($uid, $hid, 'trial', 1, 'trial', 'trial:' . $norm, '', 'Free trial movie');
                } else {
                    db()->prepare("INSERT INTO subscriptions (household_id, plan_key, status, seat_limit, updated_at) VALUES (?, 'individual', 'none', 1, ?)")->execute([$hid, $now]);
                    billing_log("trial already used for $norm (user $uid)");
                }
            }
        }
        return household_of($uid);
    });
}

/** 'comped', 'trial', 'active' (paid, including a payment being retried) or 'limited'. */
function billing_state(array $sub): string {
    if (!empty($sub['comped'])) return 'comped';
    if (in_array($sub['status'], ['active', 'trialing', 'past_due'], true)) return 'active';
    if ((int)$sub['trial_end'] > time() && $sub['status'] === 'none') return 'trial';
    return 'limited';
}
function billing_period_key(array $sub): string {
    if (!empty($sub['comped'])) return 'c' . date('Y-m');
    if (in_array($sub['status'], ['active', 'trialing', 'past_due', 'canceled'], true) && (int)$sub['period_start'] > 0) return 'p' . (int)$sub['period_start'];
    return '';
}
/** Everything the screens and checks need about one person's billing, in one call. */
function billing_for(int $uid): array {
    $h = household_ensure($uid); $sub = subscription_of((int)$h['id']); $state = billing_state($sub);
    $plan = plan_for($sub['plan_key']) ?? plan_for('individual');
    $isOwner = (int)$h['owner_user_id'] === $uid;
    return ['household' => $h, 'sub' => $sub, 'state' => $state, 'plan' => $plan, 'is_owner' => $isOwner,
            'features' => state_features($state, $sub['plan_key']), 'period_key' => billing_period_key($sub)];
}
function billing_can(int $uid, string $feature): bool { return in_array($feature, billing_for($uid)['features'], true); }

function household_members(int $hid): array {
    $q = db()->prepare("SELECT m.*, u.name, u.email, u.status AS user_status FROM household_members m JOIN users u ON u.id = m.user_id WHERE m.household_id = ? ORDER BY (m.role = 'owner') DESC, m.id");
    $q->execute([$hid]); return $q->fetchAll();
}
function household_active_count(int $hid): int {
    $q = db()->prepare("SELECT COUNT(*) FROM household_members WHERE household_id = ? AND status = 'active'"); $q->execute([$hid]); return (int)$q->fetchColumn();
}

/** Add an existing user. Seat limits are checked inside the transaction, so a full plan can never go over. Returns null or a message key. */
function household_add_member(int $hid, int $uid): ?string {
    return billing_tx(function () use ($hid, $uid) {
        $sub = subscription_of($hid); $state = billing_state($sub);
        if (!in_array('family_sharing', state_features($state, $sub['plan_key']), true)) return 'billing.err_no_family';
        $other = db()->prepare("SELECT household_id FROM household_members WHERE user_id = ? AND status = 'active'"); $other->execute([$uid]); $o = $other->fetch();
        if ($o && (int)$o['household_id'] !== $hid) return 'billing.err_in_other_household';
        if (!$o && household_active_count($hid) >= (int)$sub['seat_limit']) return 'billing.err_no_seats';
        if (!$o) {
            $ex = db()->prepare('SELECT id FROM household_members WHERE user_id = ?'); $ex->execute([$uid]);
            if ($ex->fetch()) db()->prepare("UPDATE household_members SET household_id = ?, status = 'active', removed_at = 0, role = 'member' WHERE user_id = ?")->execute([$hid, $uid]);
            else db()->prepare("INSERT INTO household_members (household_id, user_id, role, status, joined_at) VALUES (?, ?, 'member', 'active', ?)")->execute([$hid, $uid, time()]);
            /* joined mid-period: the monthly credits for what is left of this period. The idempotency key makes remove-and-re-add give nothing extra. */
            if (($pk = billing_period_key($sub)) !== '' && in_array($state, ['active', 'comped'], true)) credits_grant_included($hid, $uid, $pk, comp_credits_per_member($sub));
        }
        return null;
    });
}
function household_remove_member(int $hid, int $uid): ?string {
    return billing_tx(function () use ($hid, $uid) {
        $q = db()->prepare('SELECT * FROM household_members WHERE household_id = ? AND user_id = ?'); $q->execute([$hid, $uid]); $m = $q->fetch();
        if (!$m) return 'billing.err_not_member';
        if ($m['role'] === 'owner') return 'billing.err_remove_owner';
        db()->prepare("UPDATE household_members SET status = 'removed', removed_at = ? WHERE id = ?")->execute([time(), $m['id']]);
        return null;
    });
}

/* ---------- the ledger ---------- */

/** Balance of one kind for a person. Included credits count only for the current period; trial and purchased credits stay until used. */
function credits_kind_balance(int $uid, string $kind, string $periodKey = ''): int {
    if ($kind === 'included') {
        if ($periodKey === '') return 0;
        $q = db()->prepare("SELECT COALESCE(SUM(delta), 0) FROM credit_ledger WHERE user_id = ? AND kind = 'included' AND period_key = ? AND status IN ('posted','reserved')"); $q->execute([$uid, $periodKey]);
    } else {
        $q = db()->prepare("SELECT COALESCE(SUM(delta), 0) FROM credit_ledger WHERE user_id = ? AND kind = ? AND status IN ('posted','reserved')"); $q->execute([$uid, $kind]);
    }
    return (int)$q->fetchColumn();
}

/** Add credits once. The idempotency key is unique, so a repeated webhook, a retry or a re-invitation adds nothing. Returns true if a row was added. */
function credits_grant(int $uid, int $hid, string $kind, int $n, string $periodKey, string $idem, string $stripeRef = '', string $note = ''): bool {
    return billing_tx(function () use ($uid, $hid, $kind, $n, $periodKey, $idem, $stripeRef, $note) {
        $after = credits_kind_balance($uid, $kind, $periodKey) + $n;
        $q = db()->prepare("INSERT OR IGNORE INTO credit_ledger (user_id, household_id, kind, delta, status, period_key, stripe_ref, idem_key, note, balance_after, created_at) VALUES (?, ?, ?, ?, 'posted', ?, ?, ?, ?, ?, ?)");
        $q->execute([$uid, $hid, $kind, $n, $periodKey, $stripeRef, $idem, $note, $after, time()]);
        return $q->rowCount() === 1;
    });
}
function credits_grant_included(int $hid, int $uid, string $periodKey, int $n): bool {
    return credits_grant($uid, $hid, 'included', $n, $periodKey, 'included:' . $uid . ':' . $periodKey, '', 'Monthly movie credits');
}
/** A paid renewal (or the first paid invoice): every active member gets their own monthly credits for the new period. */
function credits_grant_period(int $hid, string $periodKey, int $perMember): int {
    $n = 0;
    foreach (household_members($hid) as $m) if ($m['status'] === 'active' && credits_grant_included($hid, (int)$m['user_id'], $periodKey, $perMember)) $n++;
    return $n;
}
/** Comped accounts get their monthly credits when first used each calendar month. */
function credits_ensure_comped(int $uid): void {
    $b = household_ensure($uid); $sub = subscription_of((int)$b['id']);
    if (!empty($sub['comped'])) credits_grant_included((int)$b['id'], $uid, billing_period_key($sub), comp_credits_per_member($sub));
}
/** Monthly credits per member: the administrator's number for a comped account, otherwise the plan's. */
function comp_credits_per_member(array $sub): int {
    if (!empty($sub['comped']) && (int)($sub['comp_credits'] ?? 0) > 0) return (int)$sub['comp_credits'];
    return (int)(plan_for($sub['plan_key'])['credits_per_member'] ?? 2);
}
/** Administrator control of one household's access. Mode 'comped' gives a free plan with a set number of seats; 'standard' returns it to normal (trial, then paid). Returns null or a message. */
function admin_set_access(int $adminId, int $uid, string $mode, string $planKey, int $seats, int $credits): ?string {
    $h = household_ensure($uid);
    if ((int)$h['owner_user_id'] !== $uid) return 'That person is a member of another household. Change the household owner instead.';
    $hid = (int)$h['id'];
    if ($mode === 'standard') {
        db()->prepare("UPDATE subscriptions SET comped = 0, comp_credits = 0, updated_at = ? WHERE household_id = ?")->execute([time(), $hid]);
        if (($u = db()->prepare("UPDATE users SET plan = 'free' WHERE id = ? AND plan = 'beta_free'")) && $u->execute([$uid])) {}
        billing_log("admin $adminId set household $hid to standard billing");
        return null;
    }
    if (!plan_for($planKey)) return 'Choose a plan.';
    if ($seats < 1 || $seats > 500) return 'Seats must be from 1 to 500.';
    if ($credits < 0 || $credits > 100) return 'Monthly credits must be from 0 to 100 (0 uses the plan\'s number).';
    if ($seats < household_active_count($hid)) return 'There are already ' . household_active_count($hid) . ' people in this household. Remove some first or give more seats.';
    $has = db()->prepare('SELECT 1 FROM subscriptions WHERE household_id = ?'); $has->execute([$hid]);
    if ($has->fetch()) db()->prepare("UPDATE subscriptions SET comped = 1, plan_key = ?, seat_limit = ?, comp_credits = ?, pending_plan_key = '', pending_keep = '', updated_at = ? WHERE household_id = ?")->execute([$planKey, $seats, $credits, time(), $hid]);
    else db()->prepare("INSERT INTO subscriptions (household_id, plan_key, status, comped, seat_limit, comp_credits, updated_at) VALUES (?, ?, 'comped', 1, ?, ?, ?)")->execute([$hid, $planKey, $seats, $credits, time()]);
    $sub = subscription_of($hid);
    foreach (household_members($hid) as $m) if ($m['status'] === 'active') credits_grant_included($hid, (int)$m['user_id'], billing_period_key($sub), comp_credits_per_member($sub));
    billing_log("admin $adminId comped household $hid: $planKey, $seats seats, $credits credits");
    return null;
}

function credits_balance(int $uid): array {
    credits_ensure_comped($uid);
    $b = billing_for($uid); $pk = $b['period_key']; $sub = $b['sub'];
    $inc = credits_kind_balance($uid, 'included', $pk); $pur = credits_kind_balance($uid, 'purchased'); $tri = credits_kind_balance($uid, 'trial');
    $renews = !empty($sub['comped']) ? strtotime('first day of next month 00:00') : (int)$sub['period_end'];
    return ['trial' => $tri, 'included' => $inc, 'purchased' => $pur,
            'available' => max(0, $tri) + max(0, $inc) + max(0, $pur), 'renews' => $renews, 'state' => $b['state']];
}

/** Hold credits for a movie. Call inside billing_tx(); returns the ledger rows or null when there are not enough. Order: trial, monthly, purchased. */
function credits_reserve(int $uid, int $movieId, int $cost = MV_CREDIT_COST): ?array {
    return billing_tx(function () use ($uid, $movieId, $cost) {
        credits_ensure_comped($uid);
        $b = billing_for($uid); $pk = $b['period_key'];
        $have = ['trial' => max(0, credits_kind_balance($uid, 'trial')), 'included' => max(0, credits_kind_balance($uid, 'included', $pk)), 'purchased' => max(0, credits_kind_balance($uid, 'purchased'))];
        if (array_sum($have) < $cost) return null;
        $need = $cost; $rows = [];
        foreach (['trial', 'included', 'purchased'] as $k) {
            if ($need <= 0) break; $take = min($need, $have[$k]); if ($take <= 0) continue;
            $after = credits_kind_balance($uid, $k, $k === 'included' ? $pk : '') - $take;
            $ins = db()->prepare("INSERT INTO credit_ledger (user_id, household_id, kind, delta, status, period_key, movie_id, idem_key, note, balance_after, created_at) VALUES (?, ?, ?, ?, 'reserved', ?, ?, ?, 'Movie', ?, ?)");
            $ins->execute([$uid, (int)$b['household']['id'], $k, -$take, $k === 'included' ? $pk : ($k === 'trial' ? 'trial' : ''), $movieId, 'movie:' . $movieId . ':' . $k, $after, time()]);
            $rows[] = ['kind' => $k, 'n' => $take]; $need -= $take;
        }
        return $rows;
    });
}
/** The movie finished: the held credits are spent for good. */
function credits_commit(int $movieId): void { db()->prepare("UPDATE credit_ledger SET status = 'posted' WHERE movie_id = ? AND status = 'reserved'")->execute([$movieId]); }
/** The movie failed or was interrupted: the held credits come back. Safe to call twice. */
function credits_release(int $movieId): void { db()->prepare("UPDATE credit_ledger SET status = 'void', note = 'Movie did not finish: credit returned' WHERE movie_id = ? AND status = 'reserved'")->execute([$movieId]); }
/** Safety net for the worker: holds whose movie no longer exists, or failed, are returned. */
function credits_reconcile(): int {
    $q = db()->prepare("UPDATE credit_ledger SET status = 'void', note = 'Movie did not finish: credit returned' WHERE status = 'reserved' AND movie_id > 0 AND NOT EXISTS (SELECT 1 FROM movies m WHERE m.id = credit_ledger.movie_id AND m.status IN ('queued','rendering','done'))");
    $q->execute(); return $q->rowCount();
}

function credits_activity(int $uid, int $limit = 12): array {
    $q = db()->prepare("SELECT * FROM credit_ledger WHERE user_id = ? AND status <> 'void' ORDER BY id DESC LIMIT ?"); $q->bindValue(1, $uid, PDO::PARAM_INT); $q->bindValue(2, $limit, PDO::PARAM_INT); $q->execute();
    return $q->fetchAll();
}

/** Administrator tool: give credits to anyone (including yourself). They are permanent "purchased"-type credits with the admin and reason on the row. */
function credits_admin_grant(int $adminId, int $uid, int $n, string $reason): ?string {
    if ($n < 1 || $n > 500) return 'billing.err_bad_amount';
    $h = household_ensure($uid);
    credits_grant($uid, (int)$h['id'], 'purchased', $n, '', 'admin:' . $adminId . ':' . $uid . ':' . bin2hex(random_bytes(8)), 'admin:' . $adminId, 'Added by administrator' . ($reason !== '' ? ': ' . mb_substr($reason, 0, 120) : ''));
    return null;
}

/** Stale renders are marked failed by the worker; their held credits come back right after. */
function credits_reconcile_after_stale(): void {
    db()->exec("UPDATE credit_ledger SET status = 'void', note = 'Movie did not finish: credit returned' WHERE status = 'reserved' AND movie_id IN (SELECT id FROM movies WHERE status = 'rendering' AND started_at < " . (time() - 3600) . ")");
}
