<h1 class="page-title"><?= t('journal.your_journal') ?></h1>
<p class="subtle"><a class="link" href="<?= url('/journal/book') ?>"><?= t('journal.open_book') ?></a> · <a class="link" href="<?= url('/calendar') ?>"><?= t('journal.open_the_calendar') ?></a> · <a class="link" href="<?= url('/track/feelings') ?>"><?= t('common.how_you_ve_been') ?></a></p>
<?php if (!$entries): ?>
  <p class="subtle"><?= t('journal.your_entries_will_appear') ?> <a class="link" href="<?= url('/') ?>"><?= t('journal.write_your_first_one') ?></a></p>
<?php else:
  $month = ''; $seenDay = [];
  foreach ($entries as $en):
    $m = ldate('F Y', strtotime($en['entry_date']));
    if ($m !== $month): $month = $m; ?><h2 class="section-h"><?= e($m) ?></h2><?php endif; ?>
  <a class="card entry-link" href="<?= url('/day/' . $en['entry_date']) ?>">
    <span class="entry-date"><?= e(ldate('D, M j', strtotime($en['entry_date']))) ?><?php if (empty($seenDay[$en['entry_date']]) && !empty($moods[$en['entry_date']])): $fc = feelings_faces()[$moods[$en['entry_date']]]; ?> <span class="entry-mood" role="img" aria-label="<?= t('common.mood_label', ['mood' => $fc['l']]) ?>"><?= $fc['e'] ?></span><?php foreach (array_slice($feel[$en['entry_date']] ?? [], 0, 3) as $ff): ?><span class="chip chip--static feel-chip"><?= e($ff) ?></span><?php endforeach; endif; $seenDay[$en['entry_date']] = true; ?></span>
    <b><?= e($en['title'] !== '' ? $en['title'] : (body_plain($en['body']) !== '' ? mb_substr(body_plain($en['body']), 0, 60) : t('common.photos'))) ?></b>
    <?php if ($en['title'] !== '' && body_plain($en['body']) !== ''): ?><span class="snippet"><?= e(mb_substr(body_plain($en['body']), 0, 120)) ?></span><?php endif; ?>
    <?php if ($en['photos']): ?><span class="thumbs thumbs--sm"><?php foreach (array_slice($en['photos'], 0, 4) as $p): ?><img src="<?= url('/photo/' . $p['id'] . '/thumb') . '?v=' . (int)$p['edited_at'] ?>" alt="" loading="lazy"><?php endforeach; ?><?php if (count($en['photos']) > 4): ?><span class="more">+<?= count($en['photos']) - 4 ?></span><?php endif; ?></span><?php endif; ?>
  </a>
<?php endforeach; endif; ?>
