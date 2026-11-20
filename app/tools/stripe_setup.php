<?php
/* One-time (and safe to repeat) Stripe setup: creates the Moriwella products and prices and records their IDs.
   Run from the command line only:  php tools/stripe_setup.php
   Needs data_dir()/stripe.php with the TEST secret key. Finds existing prices by lookup_key, so nothing is created twice.
   Never touches anything that is not named Moriwella. Copyright (c) JSW Software Designs */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/lib.php';
require __DIR__ . '/../src/stripe.php';

if (stripe_mode() === 'live' && !in_array('--live', $argv, true)) { fwrite(STDERR, "A live key is configured. Live setup needs approval and the --live flag.\n"); exit(1); }
$img = 'https://moriwella.com/app/static/brand/product.png';
$out = [];

$items = [];
foreach (plans() as $k => $p) {
    $items[] = ['key' => $k . '_month', 'name' => 'Moriwella ' . $p['name'], 'lookup' => $p['lookup_month'], 'cents' => $p['monthly_cents'], 'recurring' => true,
        'desc' => ['individual' => 'Moriwella for one person. 2 movie credits each month.', 'family' => 'Moriwella for a family of up to 4. 2 movie credits each month for every member.', 'family_pro' => 'Moriwella for an extended family of up to 10. 2 movie credits each month for every member.'][$k],
        'meta' => ['plan_key' => $k, 'seats' => (string)$p['seats'], 'credits_per_member' => (string)$p['credits_per_member']]];
}
foreach (credit_packs() as $k => $c) {
    $items[] = ['key' => 'credit_' . $k, 'name' => $c['name'], 'lookup' => $c['lookup'], 'cents' => $c['cents'], 'recurring' => false,
        'desc' => $c['credits'] === 1 ? 'One Moriwella movie credit. Never expires.' : 'Ten Moriwella movie credits. Never expire.',
        'meta' => ['pack_key' => $k, 'credits' => (string)$c['credits']]];
}

foreach ($items as $it) {
    $found = stripe_api('GET', '/v1/prices', ['lookup_keys' => [$it['lookup']], 'expand' => ['data.product']]);
    if (!empty($found['data'][0])) {
        $price = $found['data'][0]; $prod = $price['product'];
        echo "exists  {$it['name']}  {$price['id']}\n";
    } else {
        $prod = stripe_api('POST', '/v1/products', ['name' => $it['name'], 'description' => $it['desc'], 'images' => [$img], 'metadata' => $it['meta'] + ['app' => 'moriwella']], 'mw-prod-' . $it['lookup']);
        $price = stripe_api('POST', '/v1/prices', ['product' => $prod['id'], 'unit_amount' => $it['cents'], 'currency' => 'usd', 'lookup_key' => $it['lookup'],
            'recurring' => $it['recurring'] ? ['interval' => 'month'] : null, 'metadata' => $it['meta'] + ['app' => 'moriwella']], 'mw-price-' . $it['lookup']);
        echo "created {$it['name']}  {$price['id']}\n";
    }
    /* make sure the artwork is on the product */
    if (empty($prod['images'])) stripe_api('POST', '/v1/products/' . $prod['id'], ['images' => [$img]]);
    $out[$it['lookup']] = $price['id'];
}
file_put_contents(data_dir() . '/stripe_prices.json', json_encode($out, JSON_PRETTY_PRINT));
echo "\nSaved " . count($out) . " price IDs to " . data_dir() . "/stripe_prices.json\n";
