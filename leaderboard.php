<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::get();

$rows = $db->query(
    "SELECT u.id, u.full_name, u.xp,
            (SELECT COUNT(*) FROM user_lesson_progress p WHERE p.user_id = u.id AND p.status = 'completed') AS lessons_done,
            (SELECT a.score FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id WHERE a.user_id = u.id AND q.kind = 'posttest' AND a.completed_at IS NOT NULL ORDER BY a.score DESC LIMIT 1) AS post_score
     FROM users u WHERE u.role IN ('student','general')
     ORDER BY u.xp DESC, u.id ASC"
)->fetchAll();

// best score per (user, game), summed per user — computed in PHP since MariaDB
// 10.4 doesn't support correlated subqueries inside a derived table (FROM subquery).
$gameTotals = [];
foreach ($db->query('SELECT user_id, game_id, MAX(score) best FROM game_scores GROUP BY user_id, game_id') as $g) {
    $gameTotals[(int)$g['user_id']] = ($gameTotals[(int)$g['user_id']] ?? 0) + (int)$g['best'];
}
foreach ($rows as &$r) $r['game_total'] = $gameTotals[(int)$r['id']] ?? 0;
unset($r);

$maxXp = 100;
foreach ($rows as $r) $maxXp = max($maxXp, (int)$r['xp']);

Layout::start('กระดานผู้นำ', $user, 'board');
?>
<div class="page" style="max-width:880px">
  <div class="eyebrow">CLASSROOM</div>
  <h1 style="font-size:27px">กระดานผู้นำ</h1>
  <p class="lead">จัดอันดับจาก XP รวม (บทเรียน + แบบทดสอบ + เกม)</p>
  <?php foreach ($rows as $i => $r):
      $me = (int)$r['id'] === $userId;
      $rank = $i + 1;
      $rankLabel = $rank === 1 ? '①' : ($rank === 2 ? '②' : ($rank === 3 ? '③' : (string)$rank));
      $barW = round(((int)$r['xp']) / $maxXp * 100);
  ?>
    <div class="board-row <?= $me ? 'me' : '' ?>">
      <div class="board-rank" style="color:<?= $rank <= 3 ? 'var(--amber)' : '#5f736c' ?>"><?= $rankLabel ?></div>
      <div class="board-av"><?= htmlspecialchars(mb_substr($r['full_name'], 0, 1)) ?></div>
      <div style="flex:1;min-width:0">
        <div class="board-name" style="<?= $me ? 'color:#eaf6f0' : '' ?>"><?= htmlspecialchars($r['full_name']) ?><?= $me ? ' (คุณ)' : '' ?></div>
        <div class="board-detail"><?= (int)$r['lessons_done'] ?> บท · หลังเรียน <?= $r['post_score'] !== null ? (int)$r['post_score'] . '/10' : '—' ?> · เกม <?= (int)$r['game_total'] ?></div>
      </div>
      <div class="board-bar"><div class="bar-track"><div class="bar-fill" style="width:<?= $barW ?>%;<?= $me ? '' : 'background:rgba(255,255,255,.22)' ?>"></div></div></div>
      <div class="board-xp" style="<?= $me ? 'color:var(--green)' : '' ?>"><?= (int)$r['xp'] ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php
Layout::end();
