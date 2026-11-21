<h1 class="page-title"><?= t('invite.heading') ?></h1>
<p class="subtle"><?= t('invite.intro') ?></p>
<section class="card">
  <h2 class="section-h"><?= t('invite.rules_title') ?></h2>
  <ul class="invite-steps">
    <li><?= t('invite.rule_1') ?></li>
    <li><?= t('invite.rule_2', ['n' => $creditsReward]) ?></li>
    <li><?= t('invite.rule_3') ?></li>
    <li><?= t('invite.rule_4') ?></li>
    <li><?= t('invite.rule_5') ?></li>
  </ul>
  <p><a class="link" href="/terms.html#referrals" target="_blank" rel="noopener"><?= t('invite.full_terms') ?></a></p>
<?php if (!$agreed): ?>
  <form method="post" action="<?= url('/invite/agree') ?>"><?= csrf_field() ?>
    <label class="check-row"><input type="checkbox" name="agree" value="1" required> <?= t('invite.agree') ?></label>
    <button class="btn" type="submit"><?= t('invite.agree_button') ?></button>
  </form>
<?php endif; ?>
</section>
<?php if ($agreed): ?>
<section class="card invite-card">
  <label for="invite-link"><?= t('invite.your_link') ?></label>
  <div class="invite-row">
    <input id="invite-link" type="text" readonly value="<?= e($link) ?>" data-invite-link>
    <button class="btn" type="button" data-invite-copy data-done="<?= e(t('invite.copied')) ?>"><?= t('invite.copy') ?></button>
    <button class="btn btn--quiet" type="button" data-invite-share hidden><?= t('invite.share') ?></button>
  </div>
</section>
<?php endif; ?>
<h2 class="section-h"><?= t('invite.how') ?></h2>
<ol class="invite-steps">
  <li><?= t('invite.how_1') ?></li>
  <li><?= t('invite.how_2') ?></li>
  <li><?= t('invite.how_3') ?></li>
</ol>
<p class="hint"><?= $b['state'] === 'active' ? t('invite.fine_print_paid') : t('invite.fine_print_free', ['n' => $creditsReward]) ?></p>
<h2 class="section-h"><?= t('invite.friends') ?></h2>
<?php if (!$agreed): ?><?php elseif (!$rows): ?><p class="subtle"><?= t('invite.none_yet') ?></p><?php else: ?>
<ul class="invite-list">
<?php foreach ($rows as $r): ?>
  <li><b><?= e($r['friend_name']) ?></b>
    <span><?php
      if ($r['status'] === 'rewarded') echo $r['reward_kind'] === 'month' ? t('invite.st_rewarded_month') : t('invite.st_rewarded_credits', ['n' => (int)$r['reward_amount']]);
      else echo t('invite.st_' . $r['status']);
    ?></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<script src="<?= url('/static/invite.js') ?>?v=<?= asset_v('static/invite.js') ?>" defer></script>
