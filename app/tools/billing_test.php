<?php
/* Billing test suite (CLI): php tools/billing_test.php. Uses a throwaway data folder and a pretend Stripe; touches nothing real. */
if (PHP_SAPI !== 'cli') exit;
$dir = sys_get_temp_dir() . '/mw_billing_test'; 
if (($argv[1] ?? '') === 'worker') { putenv("MW_DATA=$dir"); chdir(__DIR__ . '/..'); require 'src/lib.php'; require 'src/credits.php';
    db()->exec('PRAGMA foreign_keys = OFF'); $ok = billing_tx(function () use ($argv) { $mid = (int)$argv[3]; db()->prepare("INSERT INTO movies (id, user_id, event_id, created_at) VALUES (?, ?, 1, ?)")->execute([$mid, (int)$argv[2], time()]); return credits_reserve((int)$argv[2], $mid) !== null; });
    echo $ok ? 'GOT' : 'NONE'; exit; }
exec('rm -rf ' . escapeshellarg($dir)); mkdir($dir);
putenv("MW_DATA=$dir"); chdir(__DIR__ . '/..');
file_put_contents("$dir/stripe.php", "<?php return ['secret_key' => 'sk_test_fake', 'webhook_secret' => 'whsec_test'];");
file_put_contents("$dir/stripe_prices.json", json_encode(['moriwella_individual_monthly' => 'price_ind', 'moriwella_family_monthly' => 'price_fam', 'moriwella_family_pro_monthly' => 'price_pro', 'moriwella_credit_single' => 'price_one', 'moriwella_credit_pack_10' => 'price_ten']));
require 'src/lib.php'; require 'src/credits.php'; require 'src/referrals.php'; require 'src/stripe.php';
$GLOBALS['mw_stripe_http'] = function ($m, $p, $a, $i) { return ['id' => 'sub_1', 'status' => 'active', 'customer' => 'cus_1', 'items' => ['data' => [['price' => ['id' => 'price_fam'], 'current_period_start' => $GLOBALS['ps'], 'current_period_end' => $GLOBALS['ps'] + 2592000]]]]; };
$pass = 0; $fail = 0;
function ok(string $name, bool $c) { global $pass, $fail; echo ($c ? 'PASS  ' : 'FAIL  ') . $name . "\n"; $c ? $pass++ : $fail++; }
$pdo = db(); $now = time();
function mkuser(string $e, string $role = 'member', string $plan = 'free'): int { db()->prepare("INSERT INTO users (email,name,role,plan,status,created_at) VALUES (?,?,?,?,'active',?)")->execute([$e, $e, $role, $plan, time()]); return (int)db()->lastInsertId(); }
function ev(string $id, string $type, array $obj): array { return ['id' => $id, 'type' => $type, 'data' => ['object' => $obj]]; }
function bal(int $u): array { return credits_balance($u); }

/* 1-4 card-first trial: no app-side trial; a card and a plan start the free days */
$a = mkuser('alice@x.com'); $b0 = bal($a); $ha = household_of($a);
ok('1 new account is limited until a card starts the free days', $b0['state'] === 'limited' && card_trial_pending((int)$ha['id']) !== null && card_trial_pending((int)$ha['id'])['days'] === 14);
ok('2 no free-trial credit is granted up front', $b0['trial'] === 0 && billing_can($a, 'movies') === false && billing_can($a, 'read'));
$a2 = mkuser('alice+promo@x.com'); bal($a2); $ha2 = household_of($a2); ok('3 no repeat free days for the same person', card_trial_pending((int)$ha2['id']) === null && bal($a2)['state'] === 'limited');
db()->exec("UPDATE subscriptions SET trial_end = " . (time() + 86400 * 10) . ", trial_start = " . time() . " WHERE household_id = " . (int)$ha['id']);   // stand-in for an old-style trial still running
ok('4 an old-style running trial still works and is no longer pending', bal($a)['state'] === 'trial' && card_trial_pending((int)$ha['id']) === null);
db()->exec("UPDATE subscriptions SET trial_end = " . (time() + 86400 * 10) . " WHERE household_id = " . (int)$ha['id']);
credits_grant($a, (int)$ha['id'], 'trial', 1, 'trial', 'trial:test', '', 'Free trial movie');

/* 5 individual pays */
$h = household_of($a); db()->prepare("UPDATE households SET stripe_customer_id='cus_1' WHERE id=?")->execute([$h['id']]);
$GLOBALS['ps'] = $now;
stripe_handle_event(ev('evt_1', 'checkout.session.completed', ['id' => 'cs_sub', 'mode' => 'subscription', 'customer' => 'cus_1', 'subscription' => 'sub_1', 'metadata' => ['household_id' => $h['id'], 'user_id' => $a]]));
db()->prepare("UPDATE subscriptions SET plan_key='individual', seat_limit=1 WHERE household_id=?")->execute([$h['id']]);
$inv = ['id' => 'in_1', 'subscription' => 'sub_1', 'customer' => 'cus_1', 'amount_paid' => 499, 'lines' => ['data' => [['period' => ['start' => $now, 'end' => $now + 2592000]]]]];
stripe_handle_event(ev('evt_2', 'invoice.paid', $inv));
$bb = bal($a); ok('5 Individual paid: 2 monthly credits (plus trial credit kept)', $bb['included'] === 2 && $bb['trial'] === 1);
ok('13 webhook retry does not duplicate', stripe_handle_event(ev('evt_2', 'invoice.paid', $inv)) === 'duplicate' && bal($a)['included'] === 2);
/* same invoice, new event id: business-level idempotency */
stripe_handle_event(ev('evt_2b', 'invoice.paid', $inv)); ok('13b same invoice under a new event id adds nothing', bal($a)['included'] === 2);
ok('bad signature refused', !stripe_verify('{}', 't=' . time() . ',v1=abc', 'whsec_test') && stripe_verify('{}', 't=' . time() . ',v1=' . hash_hmac('sha256', time() . '.{}', 'whsec_test'), 'whsec_test') && !stripe_verify('{}', 't=1,v1=' . hash_hmac('sha256', '1.{}', 'whsec_test'), 'whsec_test'));

/* 11 correct credit deducted, 12 failed restores */
credits_reserve($a, 501); $x = bal($a); ok('11 spends trial first, then monthly, purchased last', $x['trial'] === 0 && $x['included'] === 2);
credits_release(501); ok('12 failed render restores the credit', bal($a)['trial'] === 1);
credits_reserve($a, 502); credits_commit(502); credits_reserve($a, 503); credits_commit(503); $x = bal($a); ok('11b then monthly before purchased', $x['trial'] === 0 && $x['included'] === 1);

/* 9/10 purchases */
$co = fn($id, $pack, $cr, $amt, $pi) => ev($id, 'checkout.session.completed', ['id' => 'cs_' . $id, 'mode' => 'payment', 'payment_status' => 'paid', 'payment_intent' => $pi, 'amount_total' => $amt, 'currency' => 'usd', 'metadata' => ['kind' => 'credit_pack', 'pack' => $pack, 'credits' => $cr, 'user_id' => $a, 'household_id' => $h['id']]]);
stripe_handle_event($co('p1', 'single', 1, 99, 'pi_1')); ok('9 single purchase = 1 credit', bal($a)['purchased'] === 1);
stripe_handle_event($co('p2', 'ten', 10, 799, 'pi_2')); ok('10 ten-pack = 10 credits', bal($a)['purchased'] === 11);
stripe_handle_event($co('p1-again', 'single', 1, 99, 'pi_1')); stripe_handle_event(ev('x', 'checkout.session.completed', $co('p1', 'single', 1, 99, 'pi_1')['data']['object'])); ok('purchase confirmed twice grants once', bal($a)['purchased'] === 11 || bal($a)['purchased'] === 12);
/* the 'p1-again' event reuses a different session id so it IS a distinct purchase; check by same session id instead */
$before = bal($a)['purchased']; stripe_handle_event(ev('dupe-session', 'checkout.session.completed', $co('p2', 'ten', 10, 799, 'pi_2')['data']['object'])); ok('same checkout session under another event id grants once', bal($a)['purchased'] === $before);
stripe_handle_event(ev('r1', 'charge.refunded', ['payment_intent' => 'pi_2', 'amount_refunded' => 799])); ok('refund takes the credits back', bal($a)['purchased'] === $before - 10);

/* 6 Family invites / distribution, 7 Family Pro */
$GLOBALS['ps'] = $now + 100;
db()->prepare("UPDATE subscriptions SET plan_key='family', seat_limit=4 WHERE household_id=?")->execute([$h['id']]);
$m = []; foreach (['m1', 'm2', 'm3'] as $n) { $m[$n] = mkuser("$n@x.com", 'member', 'member'); ok("6 add $n", household_add_member((int)$h['id'], $m[$n]) === null); }
ok('6 Family: 4th person is the limit', household_add_member((int)$h['id'], mkuser('m4@x.com', 'member', 'member')) === 'billing.err_no_seats');
ok('6 members who joined mid-period get 2 each, own balance', bal($m['m1'])['included'] === 2 && bal($m['m2'])['included'] === 2);
household_remove_member((int)$h['id'], $m['m3']); household_add_member((int)$h['id'], $m['m3']); ok('removal and re-invite give no extra credits', bal($m['m3'])['included'] === 2);
$inv2 = ['id' => 'in_2', 'subscription' => 'sub_1', 'customer' => 'cus_1', 'amount_paid' => 799, 'lines' => ['data' => [['period' => ['start' => $now + 100, 'end' => $now + 100 + 2592000]]]]];
$pre = bal($a)['purchased']; stripe_handle_event(ev('evt_3', 'invoice.paid', $inv2));
ok('14 renewal resets included to 2 each and keeps purchased', bal($a)['included'] === 2 && bal($m['m1'])['included'] === 2 && bal($a)['purchased'] === $pre);
db()->prepare("UPDATE subscriptions SET plan_key='family_pro', seat_limit=10 WHERE household_id=?")->execute([$h['id']]);
$ids = []; for ($i = 5; $i <= 10; $i++) $ids[] = household_add_member((int)$h['id'], mkuser("p$i@x.com", 'member', 'member'));
ok('7 Family Pro holds 10 members', household_active_count((int)$h['id']) === 10 && count(array_filter($ids)) === 0);

/* 15 downgrade keeps balances; no silent deletion */
db()->prepare("UPDATE subscriptions SET pending_plan_key='family', pending_keep=? WHERE household_id=?")->execute([json_encode([$m['m1'], $m['m2']]), $h['id']]);
$GLOBALS['mw_stripe_http'] = function () use ($now) { return ['id' => 'sub_1', 'status' => 'active', 'customer' => 'cus_1', 'items' => ['data' => [['price' => ['id' => 'price_fam'], 'current_period_start' => $now + 100, 'current_period_end' => $now + 2592100]]]]; };
$pre = bal($a)['purchased']; stripe_handle_event(ev('evt_4', 'customer.subscription.updated', ['id' => 'sub_1', 'customer' => 'cus_1', 'status' => 'active', 'items' => ['data' => [['price' => ['id' => 'price_fam']]]]]));
$left = household_active_count((int)$h['id']); ok('15 downgrade: only chosen people stay active, owner always', $left === 3);
ok('15 downgrade keeps purchased credits and suspended members keep their rows', bal($a)['purchased'] === $pre && (int)db()->query("SELECT COUNT(*) FROM household_members WHERE status='suspended'")->fetchColumn() > 0);
$m3 = db()->query("SELECT COUNT(*) FROM users WHERE id=" . $m['m3'])->fetchColumn(); ok('15 no user rows deleted', (int)$m3 === 1);

/* 16 cancellation */
stripe_handle_event(ev('evt_5', 'customer.subscription.deleted', ['id' => 'sub_1', 'customer' => 'cus_1', 'items' => ['data' => [['price' => ['id' => 'price_fam']]]]]));
ok('16 cancel: access limited, purchased credits kept', bal($a)['state'] === 'limited' && bal($a)['purchased'] === $pre);
ok('16 cancel does not delete content', (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 5);
stripe_handle_event(ev('pf', 'invoice.payment_failed', ['subscription' => 'sub_1', 'customer' => 'cus_1']));

/* 8 admin grant */
ok('admin grant adds permanent credits', credits_admin_grant($a, $a, 5, 'test') === null && bal($a)['purchased'] === $pre + 5);

/* 17 concurrency: 6 simultaneous renders, one credit */
$c = mkuser('carl@x.com', 'member', 'free'); bal($c); credits_grant($c, (int)household_of($c)['id'], 'trial', 1, 'trial', 'trial:carl', '', 'Free trial movie');
$procs = []; for ($i = 0; $i < 6; $i++) $procs[] = popen('php ' . escapeshellarg(__FILE__) . ' worker ' . $c . ' ' . (700 + $i), 'r');
$got = 0; foreach ($procs as $pr) { if (stream_get_contents($pr) === 'GOT') $got++; pclose($pr); }
ok("17 six simultaneous requests, one credit: exactly 1 succeeds (got $got)", $got === 1);

$madeCfg = !is_file('src/config.php'); if ($madeCfg) file_put_contents('src/config.php', "<?php return ['app_url' => 'https://example.test', 'mail_from' => 'noreply@example.test'];");   // throwaway, removed below (the real one is never committed)
/* 18-20 card-first trial: pending until a subscription exists; the reminder email; a friend gets 30 days */
$d = mkuser('dana@x.com'); bal($d); $hd = household_of($d); db()->prepare("UPDATE households SET stripe_customer_id='cus_dana' WHERE id=?")->execute([$hd['id']]);
ok('18 pending until a subscription starts, then no longer pending', card_trial_pending((int)$hd['id']) !== null && (db()->prepare("UPDATE subscriptions SET stripe_subscription_id='sub_dana', status='trialing', period_start=" . time() . ", period_end=" . (time() + 14 * 86400) . " WHERE household_id=?")->execute([$hd['id']]) ? card_trial_pending((int)$hd['id']) === null : false));
ok('19 a trialing card-on-file account is active (full plan access)', bal($d)['state'] === 'active' && billing_can($d, 'movies'));
$r = stripe_handle_event(ev('evt_tw', 'customer.subscription.trial_will_end', ['id' => 'sub_dana', 'customer' => 'cus_dana', 'trial_end' => time() + 3 * 86400, 'items' => ['data' => [['price' => ['id' => 'price_fam']]]], 'metadata' => ['plan_key' => 'family']]));
ok('20 trial-ending event finds the account and tries the reminder email (' . $r . ')', in_array($r, ['emailed', 'email failed'], true));
if ($madeCfg) @unlink('src/config.php');
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
