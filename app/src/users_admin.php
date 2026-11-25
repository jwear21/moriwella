<?php
/* Administrator tools for removing people. Copyright (c) JSW Software Designs */

function admin_user_stats(int $uid): array {
    $one = function (string $sql) use ($uid): int { $s = db()->prepare($sql); $s->execute([$uid]); return (int)$s->fetchColumn(); };
    return [
        'entries' => $one('SELECT COUNT(*) FROM entries WHERE user_id = ?'),
        'photos' => $one('SELECT COUNT(*) FROM entry_photos WHERE user_id = ?'),
        'movies' => $one('SELECT COUNT(*) FROM movies WHERE user_id = ?'),
    ];
}

/* Removes one account and everything it owns, including photo, avatar, movie and scrapbook files. Returns an error message, or null when done. */
function admin_delete_user(int $adminId, int $uid): ?string {
    if ($uid === $adminId) return 'You cannot delete your own account.';
    $s = db()->prepare('SELECT * FROM users WHERE id = ?'); $s->execute([$uid]); $u = $s->fetch();
    if (!$u) return 'That account no longer exists.';
    if ($u['role'] === 'admin') return 'Remove administrator access first, then delete the account.';
    $h = household_of($uid);
    if ($h && (int)$h['owner_user_id'] === $uid) {
        $m = db()->prepare('SELECT COUNT(*) FROM household_members WHERE household_id = ? AND user_id <> ?'); $m->execute([$h['id'], $uid]);
        if ((int)$m->fetchColumn() > 0) return $u['name'] . ' owns a household that other people belong to. Move or remove them first.';
        $sub = subscription_of((int)$h['id']);
        if (!empty($sub) && empty($sub['comped']) && in_array($sub['status'] ?? '', ['active', 'trialing', 'past_due'], true)) return $u['name'] . ' has a paid subscription. Cancel it in Stripe first.';
    }
    $pq = db()->prepare('SELECT * FROM entry_photos WHERE user_id = ?'); $pq->execute([$uid]);
    foreach ($pq->fetchAll() as $p) { $p['user_id'] = $uid; delete_photo_files($p); }
    $mq = db()->prepare('SELECT file FROM movies WHERE user_id = ?'); $mq->execute([$uid]);
    foreach ($mq->fetchAll() as $m) mv_delete_files($m);
    $sq = db()->prepare('SELECT file FROM scrapbooks WHERE user_id = ?'); $sq->execute([$uid]);
    foreach ($sq->fetchAll() as $b) sb_delete_files($b);
    avatar_delete($uid);
    $pd = data_dir() . '/photos/' . $uid; if (is_dir($pd)) transfer_rmdir($pd);
    transfer_rmdir(transfer_tmp($uid));
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
    billing_log('admin ' . $adminId . ' deleted user ' . $uid . ' (' . $u['email'] . ')');
    return null;
}
