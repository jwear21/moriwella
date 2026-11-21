<?php
/* One-off check of the "free month" referral reward against live Stripe, with no card charge.
   Uses the disabled Referral Test Friend account (it has a Stripe customer) as a stand-in referrer:
   it temporarily makes that account look like an active paying referrer, runs the real
   referral_reward() and referral_on_charge_problem() code, then puts every row back.
   The Stripe customer balance goes -$4.99 then +$4.99, so it ends at zero. Nothing is charged.
   Run on the server:  php app/tools/referral_month_check.php --run
   Copyright (c) JSW Software Designs */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--run', $argv ?? [], true)) { echo "Add --run to execute.\n"; exit(1); }
require __DIR__ . '/../src/lib.php';
require_once __DIR__ . '/../src/stripe.php';
require_once __DIR__ . '/../src/referrals.php';

$friend = db()->prepare("SELECT id FROM users WHERE email = 'referral.test.friend@example.com'"); $friend->execute(); $uid = (int)$friend->fetchColumn();
if (!$uid) { echo "Test friend not found.\n"; exit(1); }
$h = household_of($uid); if (!$h || empty($h['stripe_customer_id'])) { echo "Test friend has no Stripe customer.\n"; exit(1); }
$cus = $h['stripe_customer_id']; $hid = (int)$h['id'];
$ref = db()->query('SELECT * FROM referrals WHERE friend_id = ' . $uid)->fetch(); if (!$ref) { echo "No referral row for the test friend.\n"; exit(1); }
$sub0 = db()->query('SELECT * FROM subscriptions WHERE household_id = ' . $hid)->fetch();
$bal = function () use ($cus) { $c = stripe_api('GET', '/v1/customers/' . $cus); return (int)($c['balance'] ?? 0); };
echo "Stripe balance before: " . $bal() . " cents\n";
try {
    db()->prepare("UPDATE subscriptions SET status='active', comped=0, plan_key='individual' WHERE household_id=?")->execute([$hid]);
    db()->prepare("UPDATE referrals SET referrer_id=?, status='qualified', first_amount=499, reward_kind='', reward_ref='', reward_amount=0, reversed_at=0 WHERE id=?")->execute([$uid, $ref['id']]);
    referral_reward((int)$ref['id']);
    $r = db()->query('SELECT status,reward_kind,reward_ref,reward_amount FROM referrals WHERE id=' . $ref['id'])->fetch();
    echo "After reward: " . json_encode($r) . " | Stripe balance: " . $bal() . " cents (expect -499)\n";
    referral_reward((int)$ref['id']);   // a second call must do nothing
    echo "After second reward call, balance: " . $bal() . " cents (expect -499 still)\n";
    referral_on_charge_problem(['amount' => 499, 'amount_refunded' => 499, 'customer' => $cus]);
    $r = db()->query('SELECT status FROM referrals WHERE id=' . $ref['id'])->fetchColumn();
    echo "After full refund: status=$r | Stripe balance: " . $bal() . " cents (expect 0)\n";
} catch (Throwable $e) { echo 'ERROR: ' . $e->getMessage() . "\n"; }
finally {
    db()->prepare('UPDATE subscriptions SET status=?, comped=?, plan_key=? WHERE household_id=?')->execute([$sub0['status'], $sub0['comped'], $sub0['plan_key'], $hid]);
    db()->prepare('UPDATE referrals SET referrer_id=?, status=?, first_amount=?, reward_kind=?, reward_ref=?, reward_amount=?, rewarded_at=?, reversed_at=? WHERE id=?')
        ->execute([$ref['referrer_id'], $ref['status'], $ref['first_amount'], $ref['reward_kind'], $ref['reward_ref'], $ref['reward_amount'], $ref['rewarded_at'], $ref['reversed_at'], $ref['id']]);
    echo "Rows restored. Final Stripe balance: " . $bal() . " cents\n";
}
