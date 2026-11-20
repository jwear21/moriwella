<p><a class="link" href="<?= url('/settings') ?>"><?= t('common.account') ?></a></p>
<h1 class="page-title"><?= t('common.achievements') ?></h1>
<p class="subtle"><?= t('achievements.small_milestones_kept_for') ?></p>
<?php $names = ['journaling' => tl('Journaling'), 'daily-living' => tl('Daily goals'), 'memories' => tl('Memories'), 'family' => tl('Family')];
foreach ($names as $cat => $label): if (empty($groups[$cat])) continue; ?>
  <h2 class="section-h"><?= e($label) ?></h2>
  <div class="badge-grid">
  <?php foreach ($groups[$cat] as $a): $got = $a['got']; ?>
    <div class="card badge<?= $got ? ' badge--on' : '' ?>">
      <svg class="badge-medal" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="44" fill="<?= $got ? '#DDA12F' : '#D9DDD3' ?>"/><circle cx="50" cy="50" r="36" fill="<?= $got ? '#F6DE9C' : '#EEF0EA' ?>"/><path d="M30 52 L45 66 L72 36" fill="none" stroke="<?= $got ? '#073B2B' : '#B9C0B4' ?>" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <b><?= e($a['title']) ?></b>
      <span class="subtle"><?= e($a['description']) ?></span>
      <?php if ($got): ?><small class="due"><?= (int)$got['n'] > 1 ? t('achievements.earned_times', ['n' => (int)$got['n'], 'date' => ldate('M j', (int)$got['last_at'])]) : t('achievements.earned_once', ['date' => ldate('M j, Y', (int)$got['first_at'])]) ?></small>
      <?php else: ?><small class="due"><?= t('achievements.not_yet') ?></small><?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
<?php endforeach; ?>
