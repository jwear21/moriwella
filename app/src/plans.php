<?php
/* Plans and what each one includes. Prices live in Stripe; everything about what a plan GIVES lives here, so a feature can be added to
   one tier without touching payments. Copyright (c) JSW Software Designs */

const TRIAL_DAYS = 14;
const MV_CREDIT_COST = 1;   // credits for one movie today; longer or premium movies can cost more later

/** Credit packs sold once. Credits are granted by our ledger after Stripe confirms payment. */
function credit_packs(): array {
    return [
        'single' => ['credits' => 1,  'cents' => 99,  'name' => 'Moriwella Single Movie Credit', 'lookup' => 'moriwella_credit_single'],
        'ten'    => ['credits' => 10, 'cents' => 799, 'name' => 'Moriwella 10-Movie Credit Pack', 'lookup' => 'moriwella_credit_pack_10'],
    ];
}

/** Subscription plans. 'annual' prices are defined for later but are not created or sold until approved. */
function plans(): array {
    $core = ['journal', 'lists', 'experiences', 'movies', 'scrapbook'];
    return [
        'individual' => ['name' => 'Individual', 'seats' => 1, 'credits_per_member' => 2, 'rank' => 1,
            'monthly_cents' => 499, 'annual_cents' => null, 'lookup_month' => 'moriwella_individual_monthly', 'lookup_year' => 'moriwella_individual_annual',
            'features' => $core],
        'family' => ['name' => 'Family', 'seats' => 4, 'credits_per_member' => 2, 'rank' => 2,
            'monthly_cents' => 799, 'annual_cents' => null, 'lookup_month' => 'moriwella_family_monthly', 'lookup_year' => 'moriwella_family_annual',
            'features' => array_merge($core, ['family_sharing'])],
        'family_pro' => ['name' => 'Family Pro', 'seats' => 10, 'credits_per_member' => 2, 'rank' => 3,
            'monthly_cents' => 1499, 'annual_cents' => null, 'lookup_month' => 'moriwella_family_pro_monthly', 'lookup_year' => 'moriwella_family_pro_annual',
            'features' => array_merge($core, ['family_sharing', 'extended_family'])],
    ];
}
/** Comped accounts (beta testers, demo accounts, the administrator): individual benefits, no payment. */
function plan_for(string $key): ?array { return plans()[$key] ?? null; }

/** What a household may do in each state. 'limited' keeps people's own memories readable and exportable and never deletes anything. */
function state_features(string $state, ?string $planKey): array {
    if ($state === 'limited') return ['journal', 'read', 'export'];
    $p = plan_for($planKey ?: 'individual') ?? plan_for('individual');
    return array_merge(['read', 'export'], $p['features']);
}
