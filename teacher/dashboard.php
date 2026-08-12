<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

$user = Auth::requireTeacher();
$db = Database::get();

$totalStudents = (int)$db->query("SELECT COUNT(*) FROM users WHERE role IN ('student','general')")->fetchColumn();

$preDone = (int)$db->query(
    "SELECT COUNT(DISTINCT a.user_id) FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id
     JOIN users u ON u.id=a.user_id WHERE q.kind='pretest' AND a.completed_at IS NOT NULL AND u.role IN ('student','general')"
)->fetchColumn();

$avgPre = $db->query(
    "SELECT AVG(a.score) FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id
     JOIN users u ON u.id=a.user_id WHERE q.kind='pretest' AND a.completed_at IS NOT NULL AND u.role IN ('student','general')"
)->fetchColumn();

$avgPost = $db->query(
    "SELECT AVG(best) FROM (
        SELECT a.user_id, MAX(a.score) best FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id
        JOIN users u ON u.id=a.user_id WHERE q.kind='posttest' AND a.completed_at IS NOT NULL AND u.role IN ('student','general')
        GROUP BY a.user_id
     ) t"
)->fetchColumn();

$postAttempted = (int)$db->query(
    "SELECT COUNT(*) FROM (
        SELECT a.user_id, MAX(a.score) best FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id
        JOIN users u ON u.id=a.user_id WHERE q.kind='posttest' AND a.completed_at IS NOT NULL AND u.role IN ('student','general')
        GROUP BY a.user_id
     ) t"
)->fetchColumn();

$postPassed = (int)$db->query(
    "SELECT COUNT(*) FROM (
        SELECT a.user_id, MAX(a.score) best FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id
        JOIN users u ON u.id=a.user_id WHERE q.kind='posttest' AND a.completed_at IS NOT NULL AND u.role IN ('student','general')
        GROUP BY a.user_id HAVING best >= 7
     ) t"
)->fetchColumn();

$classRows = $db->query(
    "SELECT u.id, u.full_name,
            (SELECT COUNT(*) FROM user_lesson_progress p WHERE p.user_id=u.id AND p.status='completed') lessons_done,
            (SELECT a.score FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id WHERE a.user_id=u.id AND q.kind='pretest' AND a.completed_at IS NOT NULL ORDER BY a.id DESC LIMIT 1) pre_score,
            (SELECT MAX(a.score) FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id WHERE a.user_id=u.id AND q.kind='posttest' AND a.completed_at IS NOT NULL) post_score
     FROM users u WHERE u.role IN ('student','general')
     ORDER BY post_score DESC, u.xp DESC LIMIT 8"
)->fetchAll();

// best score per (user, game), summed per user — see leaderboard.php for why this
// isn't a single correlated-subquery SQL expression on MariaDB 10.4.
$gameTotals = [];
foreach ($db->query('SELECT user_id, game_id, MAX(score) best FROM game_scores GROUP BY user_id, game_id') as $g) {
    $gameTotals[(int)$g['user_id']] = ($gameTotals[(int)$g['user_id']] ?? 0) + (int)$g['best'];
}
foreach ($classRows as &$r) $r['game_total'] = $gameTotals[(int)$r['id']] ?? 0;
unset($r);

// wrong-answer rate per posttest question
$itemRows = $db->query(
    "SELECT qq.question_text,
            SUM(CASE WHEN qa.is_correct=0 THEN 1 ELSE 0 END) wrong,
            COUNT(*) total
     FROM quiz_answers qa
     JOIN quiz_questions qq ON qq.id = qa.question_id
     JOIN quizzes q ON q.id = qq.quiz_id
     WHERE q.kind = 'posttest'
     GROUP BY qq.id
     HAVING total > 0
     ORDER BY (SUM(CASE WHEN qa.is_correct=0 THEN 1 ELSE 0 END) / COUNT(*)) DESC
     LIMIT 4"
)->fetchAll();

Layout::start('แดชบอร์ดผลการเรียน', $user, 'teacher');
?>
<div class="page">
  <div class="eyebrow" style="color:var(--cyan)">INSTRUCTOR VIEW · <?= COURSE_CODE ?></div>
  <h1 style="font-size:27px">แดชบอร์ดผลการเรียน</h1>
  <p class="lead">ผู้เรียนทั้งหมด <?= $totalStudents ?> คน · ข้อมูล ณ <?= date('j M Y H:i') ?> น.</p>

  <div class="stats-grid">
    <div class="card"><div style="font-size:11.5px;color:#7d8f89">ทำแบบทดสอบก่อนเรียนแล้ว</div><div class="stat-value" style="color:var(--cyan)"><?= $preDone ?>/<?= $totalStudents ?></div></div>
    <div class="card"><div style="font-size:11.5px;color:#7d8f89">คะแนนเฉลี่ยก่อนเรียน</div><div class="stat-value" style="color:#e8f5ee"><?= $avgPre !== null ? number_format((float)$avgPre, 1) : '—' ?></div><div class="stat-sub">จาก 10 คะแนน</div></div>
    <div class="card"><div style="font-size:11.5px;color:#7d8f89">คะแนนเฉลี่ยหลังเรียน</div><div class="stat-value" style="color:var(--green)"><?= $avgPost !== null ? number_format((float)$avgPost, 1) : '—' ?></div><div class="stat-sub">จาก 10 คะแนน</div></div>
    <div class="card"><div style="font-size:11.5px;color:#7d8f89">ผ่านเกณฑ์ 70%</div><div class="stat-value" style="color:var(--amber)"><?= $postAttempted ? round($postPassed / $postAttempted * 100) . '%' : '—' ?></div><div class="stat-sub"><?= $postPassed ?> จาก <?= $postAttempted ?> คนที่ทำแล้ว</div></div>
  </div>

  <div class="teacher-grid" style="margin-top:22px">
    <div class="card" style="padding:20px 22px">
      <div style="font-size:13px;font-weight:600;color:#e3efe9;margin-bottom:3px">ความก้าวหน้ารายบุคคล</div>
      <div style="font-size:11.5px;color:#5f736c;margin-bottom:16px">เรียงตามคะแนนหลังเรียน · แสดง 8 คนแรก</div>
      <div style="display:flex;gap:12px;padding:0 10px 8px;font-size:10.5px;color:#4c5f59">
        <span style="flex:1">ผู้เรียน</span><span style="width:104px">บทเรียน</span><span style="width:52px;text-align:right">ก่อน</span><span style="width:52px;text-align:right">หลัง</span><span style="width:56px;text-align:right">เกม</span>
      </div>
      <?php foreach ($classRows as $r): $lessonsPct = round((int)$r['lessons_done'] / LESSON_COUNT * 100); ?>
        <div class="class-row">
          <span style="flex:1;font-size:12.5px;color:#c3d4cd"><?= htmlspecialchars($r['full_name']) ?></span>
          <span style="width:104px;display:flex;align-items:center;gap:8px">
            <span class="bar-track" style="flex:1"><span class="bar-fill" style="width:<?= $lessonsPct ?>%;display:block"></span></span>
            <span style="font-family:var(--mono);font-size:10.5px;color:#6f837c"><?= (int)$r['lessons_done'] ?>/<?= LESSON_COUNT ?></span>
          </span>
          <span style="width:52px;text-align:right;font-family:var(--mono);font-size:12px;color:#6f837c"><?= $r['pre_score'] !== null ? (int)$r['pre_score'] : '—' ?></span>
          <span style="width:52px;text-align:right;font-family:var(--mono);font-size:12px;color:<?= ((int)($r['post_score'] ?? 0)) >= 7 ? 'var(--green)' : 'var(--red)' ?>;font-weight:600"><?= $r['post_score'] !== null ? (int)$r['post_score'] : '—' ?></span>
          <span style="width:56px;text-align:right;font-family:var(--mono);font-size:12px;color:#7d8f89"><?= (int)$r['game_total'] ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$classRows): ?><p style="font-size:12.5px;color:#5f736c">ยังไม่มีนักเรียนในระบบ</p><?php endif; ?>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px">
      <div class="card" style="padding:20px 22px">
        <div style="font-size:13px;font-weight:600;color:#e3efe9;margin-bottom:3px">ข้อที่ตอบผิดบ่อย</div>
        <div style="font-size:11.5px;color:#5f736c;margin-bottom:16px">จากแบบทดสอบหลังเรียน · ควรทบทวนซ้ำ</div>
        <?php foreach ($itemRows as $i): $pct = round($i['wrong'] / $i['total'] * 100); $color = $pct >= 50 ? 'var(--red)' : ($pct >= 30 ? 'var(--amber)' : 'var(--cyan)'); ?>
          <div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
              <span style="font-size:12px;color:#b9cbc4"><?= htmlspecialchars(mb_strimwidth($i['question_text'], 0, 46, '…')) ?></span>
              <span style="font-size:11.5px;color:<?= $color ?>;font-family:var(--mono)">ผิด <?= $pct ?>%</span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$itemRows): ?><p style="font-size:12.5px;color:#5f736c">ยังไม่มีข้อมูลการทำแบบทดสอบหลังเรียน</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
Layout::end();
