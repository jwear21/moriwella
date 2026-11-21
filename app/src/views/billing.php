<?php $sub = $b['sub']; $state = $b['state']; $plan = $b['plan']; ?>
<h1 class="page-title"><?= t('billing.title') ?></h1>
<?php if (!empty($_GET['subscribed'])): ?><p class="notice"><?= t('billing.thanks_subscribed') ?></p><?php endif; ?>
<?php if (!empty($_GET['bought'])): ?><p class="notice"><?= t('billing.thanks_bought') ?></p><?php endif; ?>

<section class="card">
  <h2 class="section-h"><?= t('billing.your_plan_h') ?></h2>
  <?php if ($state === 'comped'): ?><p><?= t('billing.state_comped') ?></p>
  <?php elseif ($state === 'trial'): ?><p><?= t('billing.state_trial', ['date' => ldate('F j', (int)$sub['trial_end'])]) ?></p>
  <?php elseif ($state === 'active'): ?><p><b><?= e($plan['name']) ?></b> · <?= money($plan['monthly_cents']) ?> <?= t('billing.per_month') ?><?= $sub['status'] === 'past_due' ? ' · ' . t('billing.payment_problem') : '' ?></p>
    <p class="hint"><?= !empty($sub['cancel_at_period_end']) ? t('billing.ends_on', ['date' => ldate('F j, Y', (int)$sub['period_end'])]) : t('billing.renews_on', ['date' => ldate('F j, Y', (int)$sub['period_end'])]) ?></p>
    <?php if ($sub['pending_plan_key']): ?><p class="hint"><?= t('billing.switching_to', ['plan' => (plan_for($sub['pending_plan_key'])['name'] ?? ''), 'date' => ldate('F j', (int)$sub['period_end'])]) ?></p><?php endif; ?>
  <?php else: ?><p><?= t('billing.state_limited') ?></p><?php endif; ?>
  <?php if (in_app()): ?><p class="hint"><?= t('billing.in_app_note') ?></p><?php else: ?>
  <p class="actions"><a class="btn" href="<?= url('/pricing') ?>"><?= $state === 'active' ? t('billing.change_plan') : t('billing.see_plans') ?></a>
  <?php if ($b['is_owner'] && !empty($sub['stripe_subscription_id'])): ?>
    <form method="post" action="<?= url('/billing/portal') ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn--ghost" type="submit"><?= t('billing.manage_billing') ?></button></form>
  <?php endif; ?></p>
  <?php endif; ?>
</section>

<section class="card" id="credits">
  <h2 class="section-h"><?= t('billing.credits_h') ?></h2>
  <dl class="balances">
    <?php if ($bal['trial'] > 0): ?><div><dt><?= t('billing.trial_credit') ?></dt><dd><?= (int)$bal['trial'] ?></dd></div><?php endif; ?>
    <div><dt><?= t('billing.monthly_left') ?></dt><dd><?= max(0, (int)$bal['included']) ?></dd></div>
    <div><dt><?= t('billing.purchased_left') ?></dt><dd><?= (int)$bal['purchased'] ?></dd></div>
  </dl>
  <?php if ($bal['renews'] && in_array($state, ['active', 'comped'], true)): ?><p class="hint"><?= t('billing.monthly_resets', ['date' => ldate('F j', (int)$bal['renews'])]) ?></p><?php endif; ?>
  <?php if (in_app()): ?>
  <?php elseif ($b['is_owner'] || true): ?>
  <div class="packs">
    <?php foreach ($packs as $k => $c): ?>
    <form method="post" action="<?= url('/billing/credits') ?>"><?= csrf_field() ?><input type="hidden" name="pack" value="<?= e($k) ?>">
      <button class="btn<?= $k === 'ten' ? '' : ' btn--ghost' ?>" type="submit"<?= $ready ? '' : ' disabled' ?>><?= $c['credits'] === 1 ? t('billing.buy_one', ['price' => money($c['cents'])]) : t('billing.buy_ten', ['price' => money($c['cents'])]) ?></button>
    </form>
    <?php endforeach; ?>
  </div>
  <?php if (!$b['is_owner']): ?><p class="hint"><?= t('billing.credits_yours') ?></p><?php endif; ?>
  <?php endif; ?>
  <?php if ($activity): ?><h3 class="section-h"><?= t('billing.recent') ?></h3>
  <ul class="activity"><?php foreach ($activity as $a): ?><li><span><?= e($a['note'] ?: t('billing.movie_credit')) ?></span><b><?= ((int)$a['delta'] > 0 ? '+' : '') . (int)$a['delta'] ?></b><small><?= e(ldate('M j', (int)$a['created_at'])) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
</section>

<?php if (in_array('family_sharing', $b['features'], true) || $b['is_owner']): ?>
<section class="card" id="members">
  <h2 class="section-h"><?= t('billing.members_h') ?></h2>
  <p class="hint"><?= t('billing.seats_used', ['used' => $active, 'total' => (int)$sub['seat_limit']]) ?></p>
  <?php if ($newLink && !empty($newLink['email'])): $ok = !empty($newLink['emailed']); ?>
  <dialog class="mw-dialog" data-autoshow aria-labelledby="invdlg-h">
    <h3 id="invdlg-h"><?= t($ok ? 'billing.dlg_sent_h' : 'billing.dlg_failed_h') ?></h3>
    <p><?= t($ok ? 'billing.dlg_sent' : 'billing.dlg_failed', ['name' => $newLink['name'], 'email' => $newLink['email']]) ?></p>
    <form method="dialog"><button class="btn" type="submit" autofocus><?= t('billing.dlg_ok') ?></button></form>
  </dialog>
  <?php endif; ?>
  <?php if ($newLink): ?><p class="notice"><?= t(!empty($newLink['emailed']) ? 'billing.invite_emailed' : 'billing.invite_link', ['name' => $newLink['name'], 'email' => $newLink['email'] ?? '']) ?> <input type="text" readonly value="<?= e($newLink['link']) ?>" onclick="this.select()"></p><?php endif; ?>
  <ul class="members">
  <?php foreach ($members as $m): if ($m['status'] === 'removed' || $m['status'] === 'suspended') continue; ?>
    <li><?= avatar_html((int)$m['user_id'], $m['name'], 'avatar--sm') ?><span><b><?= e($m['name']) ?></b><small><?= e($m['email']) ?><?= $m['role'] === 'owner' ? ' · ' . t('billing.owner') : '' ?></small></span>
      <?php if ($b['is_owner'] && $m['role'] !== 'owner' && ($m['user_status'] ?? '') === 'invited'): ?><form method="post" action="<?= url('/household/' . (int)$m['user_id'] . '/resend') ?>" data-keepscroll data-loader="<?= e(t('billing.sending_invite')) ?>" data-detail="<?= e(t('billing.sending_invite_detail')) ?>"><?= csrf_field() ?><button class="linkbtn" type="submit"><?= t('billing.resend') ?></button></form><?php endif; ?>
      <?php if ($b['is_owner'] && $m['role'] !== 'owner'): ?><form method="post" action="<?= url('/household/' . (int)$m['user_id'] . '/remove') ?>" data-keepscroll><?= csrf_field() ?><button class="linkbtn" type="submit" data-confirm="<?= e(t('billing.confirm_remove')) ?>"><?= t('billing.remove') ?></button></form><?php endif; ?></li>
  <?php endforeach; ?>
  </ul>
  <?php if ($b['is_owner'] && in_array('family_sharing', $b['features'], true) && $active < (int)$sub['seat_limit']): ?>
  <form method="post" action="<?= url('/household/add') ?>" class="stack" data-keepscroll data-loader="<?= e(t('billing.sending_invite')) ?>" data-detail="<?= e(t('billing.sending_invite_detail')) ?>"><?= csrf_field() ?>
    <label><?= t('billing.member_name') ?> <input type="text" name="name" maxlength="100"></label>
    <label><?= t('billing.member_email') ?> <input type="email" name="email" required></label>
    <button class="btn" type="submit"><?= t('billing.add_member') ?></button>
  </form>
  <?php elseif ($b['is_owner'] && !in_array('family_sharing', $b['features'], true)): ?><p class="hint"><?= t('billing.family_needed') ?> <a class="link" href="<?= url('/pricing') ?>"><?= t('billing.see_plans') ?></a></p><?php endif; ?>
</section>
<?php endif; ?>
