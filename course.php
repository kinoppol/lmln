<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::get();

$lessons = $db->prepare('SELECT * FROM lessons WHERE course_id = 1 ORDER BY position');
$lessons->execute();
$lessons = $lessons->fetchAll();

$progress = Progress::all($userId);

$preAttempt = $db->prepare("SELECT a.score, a.total FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id WHERE a.user_id=? AND q.kind='pretest' AND a.completed_at IS NOT NULL ORDER BY a.id DESC LIMIT 1");
$preAttempt->execute([$userId]);
$pre = $preAttempt->fetch();
$post = Progress::bestPosttestScore($userId, 1);

Layout::start('บทเรียนทั้งหมด', $user, 'course');
?>
<div class="page" style="max-width:1080px">
  <div class="eyebrow"><?= COURSE_CODE ?> · SYLLABUS</div>
  <h1 style="font-size:27px">บทเรียนทั้งหมด</h1>
  <p class="lead">แต่ละบทมีเนื้อหา ตัวอย่างคำสั่ง และภารกิจใน Terminal จำลอง พร้อมแบบทดสอบท้ายบทที่ต้องผ่านจึงจะปลดล็อกบทถัดไป</p>

  <div class="test-cards">
    <a class="test-card" style="border-color:rgba(56,189,248,.28);background:rgba(56,189,248,.05)" href="<?= BASE_URL ?>/quiz.php?kind=pretest">
      <div class="glyph" style="color:var(--cyan)">◷</div>
      <div style="flex:1"><div class="th">แบบทดสอบก่อนเรียน</div><div class="en">Pre-test · 10 ข้อ ปรนัย</div></div>
      <div class="status" style="color:var(--cyan)"><?= $pre ? (int)$pre['score'] . '/' . (int)$pre['total'] : 'เริ่มทำ →' ?></div>
    </a>
    <a class="test-card" style="border-color:rgba(74,222,128,.28);background:rgba(74,222,128,.05)" href="<?= BASE_URL ?>/quiz.php?kind=posttest">
      <div class="glyph" style="color:var(--green)">✓</div>
      <div style="flex:1"><div class="th">แบบทดสอบหลังเรียน</div><div class="en">Post-test · 10 ข้อ ปรนัย</div></div>
      <div class="status" style="color:var(--green)"><?= $post ? (int)$post['score'] . '/' . (int)$post['total'] : 'เริ่มทำ →' ?></div>
    </a>
  </div>

  <div class="lesson-list">
    <?php foreach ($lessons as $l):
        $p = $progress[$l['id']] ?? null;
        $status = $p['status'] ?? 'locked';
        $ok = $status === 'completed';
        $locked = $status === 'locked';
        $href = $locked ? '#' : url('/lesson.php?id=') . $l['id'];
    ?>
      <a class="lesson-card <?= $ok ? 'done' : '' ?> <?= $locked ? 'locked' : '' ?>" href="<?= $href ?>" <?= $locked ? 'onclick="return false"' : '' ?>>
        <div class="lesson-num"><?= $ok ? '✓' : ($locked ? '🔒' : str_pad((string)$l['position'], 2, '0', STR_PAD_LEFT)) ?></div>
        <div style="flex:1;min-width:0">
          <div class="lesson-title"><span class="th"><?= htmlspecialchars($l['title_th']) ?></span><span class="cmds"><?= htmlspecialchars($l['commands_summary']) ?></span></div>
          <div class="lesson-sub"><?= htmlspecialchars($l['title_en']) ?> · <?= htmlspecialchars($l['blurb']) ?></div>
        </div>
        <div class="lesson-status">
          <div class="st" style="color:<?= $ok ? 'var(--green)' : ($locked ? '#5f736c' : '#93a8a1') ?>"><?= $ok ? 'ผ่านแล้ว' : ($locked ? 'ล็อกอยู่' : 'ยังไม่ผ่าน') ?></div>
          <div class="xp">+<?= (int)$l['xp_reward'] ?> XP</div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php
Layout::end();
