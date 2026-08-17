<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::get();

// ตัวกรองสาขา: ค่าว่าง = ทุกสาขา, '-' = ผู้เรียนที่ไม่ได้ระบุสาขา (บุคคลทั่วไป)
$major = isset($_GET['major']) ? trim((string)$_GET['major']) : '';

$sql = "SELECT u.id, u.full_name, u.xp, u.major, u.education_level,
               (SELECT COUNT(*) FROM user_lesson_progress p WHERE p.user_id = u.id AND p.status = 'completed') AS lessons_done,
               (SELECT a.score FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id
                 WHERE a.user_id = u.id AND q.kind = 'posttest' AND a.completed_at IS NOT NULL ORDER BY a.score DESC LIMIT 1) AS post_score
        FROM users u WHERE u.role IN ('student','general')";
$params = [];
if ($major === '-') {
    $sql .= " AND (u.major IS NULL OR u.major = '')";
} elseif ($major !== '') {
    $sql .= ' AND u.major = ?';
    $params[] = $major;
}
$sql .= ' ORDER BY u.xp DESC, u.id ASC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// รายชื่อสาขาสำหรับปุ่มกรอง พร้อมจำนวนผู้เรียนในแต่ละสาขา
$majors = $db->query(
    "SELECT u.major, COUNT(*) c FROM users u
     WHERE u.role IN ('student','general') AND u.major IS NOT NULL AND u.major <> ''
     GROUP BY u.major ORDER BY c DESC, u.major ASC"
)->fetchAll();
$noMajorCount = (int)$db->query(
    "SELECT COUNT(*) FROM users WHERE role IN ('student','general') AND (major IS NULL OR major = '')"
)->fetchColumn();
$totalCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role IN ('student','general')")->fetchColumn();

// best score per (user, game), summed per user — computed in PHP since MariaDB
// 10.4 doesn't support correlated subqueries inside a derived table (FROM subquery).
$gameTotals = [];
foreach ($db->query('SELECT user_id, game_id, MAX(score) best FROM game_scores GROUP BY user_id, game_id') as $g) {
    $gameTotals[(int)$g['user_id']] = ($gameTotals[(int)$g['user_id']] ?? 0) + (int)$g['best'];
}
foreach ($rows as &$r) $r['game_total'] = $gameTotals[(int)$r['id']] ?? 0;
unset($r);

// ความยาวแท่งเทียบกันภายในกลุ่มที่กำลังดู ไม่ใช่เทียบทั้งระบบ
$maxXp = 100;
foreach ($rows as $r) $maxXp = max($maxXp, (int)$r['xp']);

$myMajor = trim((string)($user['major'] ?? ''));
$viewLabel = $major === '' ? 'ทุกสาขา' : ($major === '-' ? 'ไม่ได้ระบุสาขา' : $major);
$qs = fn(string $m): string => url('/leaderboard.php') . ($m === '' ? '' : '?major=' . urlencode($m));

Layout::start('กระดานผู้นำ', $user, 'board');
?>
<div class="page" style="max-width:880px">
  <div class="eyebrow">CLASSROOM</div>
  <h1 style="font-size:27px">กระดานผู้นำ</h1>
  <p class="lead">
    จัดอันดับจาก XP รวม (บทเรียน + แบบทดสอบ + เกม) · กำลังดู: <strong style="color:#b9cbc4"><?= htmlspecialchars($viewLabel) ?></strong>
    <?= count($rows) ? ' · ' . count($rows) . ' คน' : '' ?>
  </p>

  <?php // ปุ่มกรองตามสาขา จะได้เทียบกับเพื่อนในสาขาเดียวกันได้ ไม่ใช่ปนทุกสาขา ?>
  <div class="board-filter">
    <a class="board-chip <?= $major === '' ? 'active' : '' ?>" href="<?= $qs('') ?>">ทุกสาขา <span><?= $totalCount ?></span></a>
    <?php foreach ($majors as $m): ?>
      <a class="board-chip <?= $major === $m['major'] ? 'active' : '' ?>" href="<?= $qs((string)$m['major']) ?>">
        <?= htmlspecialchars($m['major']) ?> <span><?= (int)$m['c'] ?></span>
      </a>
    <?php endforeach; ?>
    <?php if ($noMajorCount > 0): ?>
      <a class="board-chip <?= $major === '-' ? 'active' : '' ?>" href="<?= $qs('-') ?>">ไม่ระบุสาขา <span><?= $noMajorCount ?></span></a>
    <?php endif; ?>
  </div>

  <?php if ($myMajor !== '' && $major === ''): ?>
    <div style="font-size:12px;color:#6f837c;margin:-4px 0 16px">
      อยากเทียบกับเพื่อนสาขาเดียวกัน? <a href="<?= $qs($myMajor) ?>" style="text-decoration:underline">ดูอันดับเฉพาะ <?= htmlspecialchars($myMajor) ?></a>
    </div>
  <?php endif; ?>

  <?php foreach ($rows as $i => $r):
      $me = (int)$r['id'] === $userId;
      $rank = $i + 1;
      $rankLabel = $rank === 1 ? '①' : ($rank === 2 ? '②' : ($rank === 3 ? '③' : (string)$rank));
      $barW = round(((int)$r['xp']) / $maxXp * 100);
      $rowMajor = trim((string)($r['major'] ?? ''));
  ?>
    <div class="board-row <?= $me ? 'me' : '' ?>">
      <div class="board-rank" style="color:<?= $rank <= 3 ? 'var(--amber)' : '#5f736c' ?>"><?= $rankLabel ?></div>
      <div class="board-av"><?= htmlspecialchars(mb_substr($r['full_name'], 0, 1)) ?></div>
      <div style="flex:1;min-width:0">
        <div class="board-name" style="<?= $me ? 'color:#eaf6f0' : '' ?>"><?= htmlspecialchars($r['full_name']) ?><?= $me ? ' (คุณ)' : '' ?></div>
        <div class="board-detail">
          <?= (int)$r['lessons_done'] ?> บท · หลังเรียน <?= $r['post_score'] !== null ? (int)$r['post_score'] . '/10' : '—' ?> · เกม <?= (int)$r['game_total'] ?>
          <?php if ($major === '' && $rowMajor !== ''): ?>
            · <a href="<?= $qs($rowMajor) ?>" style="color:#5f8f7c"><?= htmlspecialchars($rowMajor) ?></a>
          <?php endif; ?>
        </div>
      </div>
      <div class="board-bar"><div class="bar-track"><div class="bar-fill" style="width:<?= $barW ?>%;<?= $me ? '' : 'background:rgba(255,255,255,.22)' ?>"></div></div></div>
      <div class="board-xp" style="<?= $me ? 'color:var(--green)' : '' ?>"><?= (int)$r['xp'] ?></div>
    </div>
  <?php endforeach; ?>

  <?php if (!$rows): ?>
    <div style="padding:26px;text-align:center;font-size:12.5px;color:#5f736c">
      ยังไม่มีผู้เรียนในกลุ่มนี้ · <a href="<?= $qs('') ?>" style="text-decoration:underline">ดูทุกสาขา</a>
    </div>
  <?php endif; ?>
</div>
<?php
Layout::end();
