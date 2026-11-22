<?php $yrOk = in_app() || ($ready && stripe_price(plans()['individual']['lookup_year']) && stripe_price(plans()['family']['lookup_year']) && stripe_price(plans()['family_pro']['lookup_year'])); $yr = $yrOk && ($_GET['iv'] ?? '') === 'year'; $cur = $b['sub']['plan_key']; $paid = $b['state'] === 'active'; $ref = referral_pending_start((int)$b['household']['id']); $ct = card_trial_pending((int)$b['household']['id']); ?>
<h1 class="page-title"><?= t('billing.plans_title') ?></h1>
<p class="subtle"><?= $ref ? t('referral.friend_intro') : ($ct ? t('billing.card_intro', ['days' => $ct['days']]) : t('billing.plans_intro')) ?></p>
<?php if ($ref): ?><p class="notice"><?= t('referral.friend_note', ['name' => $ref['referrer_name']]) ?></p><?php endif; ?>
<?php if ($b['state'] === 'trial'): ?><p class="notice"><?= t('billing.trial_note', ['date' => ldate('F j', (int)$b['sub']['trial_end'])]) ?></p><?php endif; ?>
<?php if ($yrOk): ?><p class="actions iv-tabs" role="tablist"><a class="btn<?= $yr ? ' btn--ghost' : '' ?>" role="tab" aria-selected="<?= $yr ? 'false' : 'true' ?>" href="<?= url('/pricing') ?>"><?= t('billing.tab_month') ?></a> <a class="btn<?= $yr ? '' : ' btn--ghost' ?>" role="tab" aria-selected="<?= $yr ? 'true' : 'false' ?>" href="<?= url('/pricing?iv=year') ?>"><?= t('billing.tab_year') ?></a></p><?php endif; ?>
<div class="plans">
<?php foreach (plans() as $k => $p): ?>
  <section class="card plan<?= $paid && $cur === $k ? ' plan--current' : '' ?>">
    <h2><?= e($p['name']) ?></h2>
    <?php if ($yr): ?><p class="plan-price"><b><?= money($p['annual_cents']) ?></b> <?= t('billing.per_year') ?></p>
    <p class="hint"><?= t('billing.year_save') ?></p>
    <?php else: ?><p class="plan-price"><b><?= money($p['monthly_cents']) ?></b> <?= t('billing.per_month') ?></p>
    <?php if ($yrOk): ?><p class="hint"><?= t('billing.or_year', ['price' => money($p['annual_cents'])]) ?></p><?php endif; ?><?php endif; ?>
    <ul>
      <li><?= $p['seats'] === 1 ? t('billing.one_person') : t('billing.up_to_people', ['n' => $p['seats']]) ?></li>
      <li><?= t('billing.two_each', ['n' => $p['credits_per_member']]) ?></li>
      <li><?= t('billing.not_per_family') ?></li>
    </ul>
    <?php if ($paid && $cur === $k): ?><p class="hint"><?= t('billing.your_plan') ?></p>
    <?php elseif (!$b['is_owner']): ?><p class="hint"><?= t('billing.owner_manages') ?></p>
    <?php else: ?>
    <?php if (in_app()): ?>
    <?php if (($b['sub']['source'] ?? 'stripe') === 'play' && $paid): ?><p class="hint"><?= t('billing.play_change_hint') ?></p>
    <?php else: ?><button class="btn" type="button" data-play-plan="<?= e($k) ?>" data-base="<?= $yr ? 'yearly' : 'monthly' ?>" data-product="<?= e(play_product_for($k)) ?>"><?= $ct ? t('billing.start_plan', ['days' => $ct['days'], 'plan' => $p['name']]) : t('billing.subscribe') ?></button><?php endif; ?>
    <?php else: ?>
    <form method="post" action="<?= url($paid ? '/billing/change' : '/billing/subscribe') ?>"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($k) ?>"><input type="hidden" name="interval" value="<?= $yr ? 'year' : 'month' ?>">
      <button class="btn" type="submit"<?= $ready ? '' : ' disabled' ?>><?= $ref ? t('referral.start_plan', ['plan' => $p['name']]) : ($ct ? t('billing.start_plan', ['days' => $ct['days'], 'plan' => $p['name']]) : ($paid ? t('billing.switch_to', ['plan' => $p['name']]) : ($b['state'] === 'trial' ? t('billing.choose_plan', ['plan' => $p['name']]) : t('billing.subscribe')))) ?></button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
<p class="hint"><?= $ref ? t('referral.friend_terms') : ($ct ? t('billing.card_terms', ['days' => $ct['days']]) : t('billing.trial_terms')) ?></p>
<?php if ($yr): ?><p class="hint"><?= t('billing.year_terms') ?></p><?php endif; ?>
<p class="hint"><a class="link" href="<?= url('/billing') ?>"><?= t('billing.go_to_billing') ?></a></p>
<?php if (in_app()): ?>
<p class="note note--err" id="play-msg" role="alert" hidden></p>
<script>window.MW_PLAY = <?= json_encode(['tag' => play_account_tag((int)$b['household']['id']), 'base' => PLAY_BASE_PLAN, 'csrf' => csrf_token(), 'verify' => url('/billing/play/verify'), 'next' => url('/billing?subscribed=1'), 'wait' => tt('billing.play_wait'), 'fail' => tt('billing.err_play')]) ?>;</script>
<script src="<?= url('/static/play-billing.js') ?>?v=<?= e(asset_v('static/play-billing.js')) ?>"></script>
<?php endif; ?>
