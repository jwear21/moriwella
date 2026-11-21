<?php $cur = $b['sub']['plan_key']; $paid = $b['state'] === 'active'; $ref = referral_pending_start((int)$b['household']['id']); $ct = card_trial_pending((int)$b['household']['id']); ?>
<h1 class="page-title"><?= t('billing.plans_title') ?></h1>
<p class="subtle"><?= $ref ? t('referral.friend_intro') : ($ct ? t('billing.card_intro', ['days' => $ct['days']]) : t('billing.plans_intro')) ?></p>
<?php if ($ref): ?><p class="notice"><?= t('referral.friend_note', ['name' => $ref['referrer_name']]) ?></p><?php endif; ?>
<?php if ($b['state'] === 'trial'): ?><p class="notice"><?= t('billing.trial_note', ['date' => ldate('F j', (int)$b['sub']['trial_end'])]) ?></p><?php endif; ?>
<div class="plans">
<?php foreach (plans() as $k => $p): ?>
  <section class="card plan<?= $paid && $cur === $k ? ' plan--current' : '' ?>">
    <h2><?= e($p['name']) ?></h2>
    <p class="plan-price"><b><?= money($p['monthly_cents']) ?></b> <?= t('billing.per_month') ?></p>
    <ul>
      <li><?= $p['seats'] === 1 ? t('billing.one_person') : t('billing.up_to_people', ['n' => $p['seats']]) ?></li>
      <li><?= t('billing.two_each', ['n' => $p['credits_per_member']]) ?></li>
      <li><?= t('billing.not_per_family') ?></li>
    </ul>
    <?php if ($paid && $cur === $k): ?><p class="hint"><?= t('billing.your_plan') ?></p>
    <?php elseif (!$b['is_owner']): ?><p class="hint"><?= t('billing.owner_manages') ?></p>
    <?php else: ?>
    <form method="post" action="<?= url($paid ? '/billing/change' : '/billing/subscribe') ?>"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($k) ?>">
      <button class="btn" type="submit"<?= $ready ? '' : ' disabled' ?>><?= $ref ? t('referral.start_plan', ['plan' => $p['name']]) : ($ct ? t('billing.start_plan', ['days' => $ct['days'], 'plan' => $p['name']]) : ($paid ? t('billing.switch_to', ['plan' => $p['name']]) : ($b['state'] === 'trial' ? t('billing.choose_plan', ['plan' => $p['name']]) : t('billing.subscribe')))) ?></button>
    </form>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
<p class="hint"><?= $ref ? t('referral.friend_terms') : ($ct ? t('billing.card_terms', ['days' => $ct['days']]) : t('billing.trial_terms')) ?></p>
<p class="hint"><a class="link" href="<?= url('/billing') ?>"><?= t('billing.go_to_billing') ?></a></p>
