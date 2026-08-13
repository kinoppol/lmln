<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::get();

$attemptId = (int)($_GET['attempt'] ?? 0);
$attempt = QuizGrader::attempt($attemptId, $userId);
if (!$attempt || !$attempt['completed_at']) {
    header('Location: /lmln/dashboard.php');
    exit;
}
$quiz = QuizGrader::quiz((int)$attempt['quiz_id']);
$kind = $quiz['kind'];
$score = (int)$attempt['score'];
$total = (int)$attempt['total'];
$pct = $total > 0 ? ($score / $total) * 100 : 0;
$passed = $pct >= (int)$quiz['pass_threshold_pct'];
$review = QuizGrader::reviewRows($attemptId);

$scoreColor = $pct >= 80 ? 'var(--green)' : ($pct >= 50 ? 'var(--amber)' : 'var(--red)');

$headline = 'บันทึกคะแนนแล้ว';
$advice = '';
$primaryLabel = 'กลับหน้าแรก';
$primaryHref = '/lmln/dashboard.php';

if ($kind === 'pretest') {
    $headline = 'บันทึกคะแนนก่อนเรียนแล้ว';
    $advice = 'คะแนนนี้ใช้เปรียบเทียบกับแบบทดสอบหลังเรียนเพื่อวัดพัฒนาการ ไม่มีผลต่อเกรด เริ่มเรียนบทที่ 1 ได้เลย';
    $primaryLabel = 'เริ่มเรียนบทที่ 1 →';
    $stmt = $db->prepare('SELECT id FROM lessons WHERE course_id=1 AND position=1');
    $stmt->execute();
    $primaryHref = '/lmln/lesson.php?id=' . $stmt->fetchColumn();
} elseif ($kind === 'posttest') {
    $headline = $passed ? ($score >= 8 ? 'ยอดเยี่ยม! ผ่านเกณฑ์สบาย ๆ' : 'ผ่านเกณฑ์แล้ว') : ('ยังไม่ผ่านเกณฑ์ ' . (int)$quiz['pass_threshold_pct'] . '%');
    $advice = $passed ? 'คุณผ่านเกณฑ์แล้ว หากเรียนครบทั้ง 9 บทจะปลดล็อกใบประกาศนียบัตรทันที' : 'ลองกลับไปทบทวนบทที่ทำผิด แล้วฝึกในเกมอีกสักรอบก่อนทำใหม่';
    $primaryLabel = 'ดูใบประกาศนียบัตร';
    $primaryHref = '/lmln/certificate.php';
} elseif ($kind === 'lesson') {
    $lessonId = (int)$quiz['lesson_id'];
    if ($passed) {
        $completed = Progress::forLesson($userId, $lessonId)['status'] === 'completed';
        $headline = $completed ? 'ผ่านบทเรียนนี้แล้ว! ปลดล็อกบทถัดไปแล้ว' : 'ผ่านแบบทดสอบแล้ว';
        $advice = $completed ? 'เก็บ XP บทเรียนแล้ว ไปต่อกันเลย' : 'กลับไปทำภารกิจใน Terminal ให้ครบก่อน ระบบจะปลดล็อกบทถัดไปให้อัตโนมัติ';
        $primaryLabel = 'ไปหน้าบทเรียนทั้งหมด →';
        $primaryHref = '/lmln/course.php';
    } else {
        $headline = 'ยังไม่ผ่านเกณฑ์ ลองใหม่อีกครั้ง';
        $advice = 'ทบทวนเนื้อหาบทนี้อีกรอบแล้วกลับมาทำแบบทดสอบใหม่';
        $primaryLabel = 'กลับไปทบทวนบทเรียน';
        $primaryHref = '/lmln/lesson.php?id=' . $lessonId;
    }
}

$retakeHref = $kind === 'lesson' ? '/lmln/quiz.php?kind=lesson&lesson_id=' . (int)$quiz['lesson_id'] : '/lmln/quiz.php?kind=' . $kind;

Layout::start('ผลคะแนน', $user);
?>
<div class="page-narrow" style="text-align:center">
  <div class="eyebrow"><?= htmlspecialchars($quiz['title_th']) ?> · RESULT</div>
  <div class="score-ring" style="border-color:<?= $scoreColor ?>">
    <span class="score-num" style="color:<?= $scoreColor ?>"><?= $score ?></span>
    <span style="font-size:12px;color:#6f837c">จาก <?= $total ?> คะแนน</span>
  </div>
  <h1 style="font-size:23px"><?= htmlspecialchars($headline) ?></h1>
  <p style="margin:0 auto 26px;font-size:13.5px;line-height:1.75;color:#8ea59d;max-width:520px"><?= htmlspecialchars($advice) ?></p>
  <div style="display:flex;gap:11px;justify-content:center;flex-wrap:wrap">
    <a class="btn btn-primary" href="<?= $primaryHref ?>"><?= htmlspecialchars($primaryLabel) ?></a>
    <a class="btn btn-ghost" href="<?= $retakeHref ?>">ทำใหม่อีกครั้ง</a>
  </div>

  <?php if ($kind === 'lesson'): ?>
    <div style="margin-top:34px;text-align:left;padding:20px 22px;border-radius:13px;border:1px solid rgba(255,255,255,.06);background:rgba(255,255,255,.02)">
      <div style="font-size:12px;font-weight:600;color:#8ea59d;letter-spacing:.06em;margin-bottom:12px">สรุปรายข้อ · ITEM REVIEW</div>
      <?php foreach ($review as $r): $ok = (int)$r['is_correct'] === 1; ?>
        <div class="review-row">
          <span class="review-n" style="background:<?= $ok ? 'rgba(74,222,128,.14)' : 'rgba(248,113,113,.14)' ?>;color:<?= $ok ? 'var(--green)' : 'var(--red)' ?>"><?= (int)$r['position'] ?></span>
          <span style="flex:1;font-size:12.5px;color:#a9bcb5"><?= htmlspecialchars($r['question_text']) ?></span>
          <span style="font-family:var(--mono);font-size:11.5px;color:<?= $ok ? 'var(--green)' : 'var(--red)' ?>"><?= $ok ? 'ถูก' : 'ผิด · เฉลย ' . htmlspecialchars((string)$r['correct_text']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <?php // ก่อน/หลังเรียนไม่เฉลยระหว่างทำ จึงเฉลยเต็มรูปแบบตรงนี้ทีเดียว ?>
    <div style="margin-top:34px;text-align:left">
      <div class="answer-key-head">
        <div>
          <div style="font-size:14px;font-weight:700;color:#e3efe9">เฉลยทั้งชุด · ANSWER KEY</div>
          <div style="font-size:11.5px;color:#6f837c;margin-top:3px">ทบทวนได้ทุกข้อ พร้อมคำอธิบายว่าทำไมคำตอบนั้นถูก</div>
        </div>
        <div style="font-family:var(--mono);font-size:12px;color:<?= $scoreColor ?>">ถูก <?= $score ?> · ผิด <?= $total - $score ?></div>
      </div>

      <?php foreach ($review as $r):
          $ok = (int)$r['is_correct'] === 1;
          $noAnswer = $r['selected_option_id'] === null;
          $mono = (int)$r['is_mono'] === 1 ? 'font-family:var(--mono);' : '';
      ?>
        <div class="answer-key-item <?= $ok ? 'ok' : 'bad' ?>">
          <div class="answer-key-q">
            <span class="review-n" style="background:<?= $ok ? 'rgba(74,222,128,.14)' : 'rgba(248,113,113,.14)' ?>;color:<?= $ok ? 'var(--green)' : 'var(--red)' ?>"><?= (int)$r['position'] ?></span>
            <span style="flex:1;font-size:13px;line-height:1.7;color:#d6e4de"><?= htmlspecialchars($r['question_text']) ?></span>
            <span class="answer-key-verdict" style="color:<?= $ok ? 'var(--green)' : 'var(--red)' ?>"><?= $ok ? '✓ ถูก' : '✕ ผิด' ?></span>
          </div>

          <?php if (!empty($r['code_snippet'])): ?>
            <div class="q-code" style="margin:10px 0 0"><?= htmlspecialchars($r['code_snippet']) ?></div>
          <?php endif; ?>

          <?php if (!$ok): ?>
            <div class="answer-key-row">
              <span class="lbl">คำตอบของคุณ</span>
              <span class="val" style="color:#fca5a5;<?= $mono ?>"><?= $noAnswer ? 'ไม่ได้ตอบ' : htmlspecialchars((string)$r['selected_text']) ?></span>
            </div>
          <?php endif; ?>
          <div class="answer-key-row">
            <span class="lbl">คำตอบที่ถูก</span>
            <span class="val" style="color:var(--green);<?= $mono ?>"><?= htmlspecialchars((string)$r['correct_text']) ?></span>
          </div>

          <?php if (!empty($r['explanation'])): ?>
            <div class="answer-key-exp"><?= htmlspecialchars($r['explanation']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
Layout::end();
