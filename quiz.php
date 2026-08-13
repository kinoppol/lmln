<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::get();
$courseId = 1;

$kind = in_array($_GET['kind'] ?? '', ['pretest', 'posttest', 'lesson'], true) ? $_GET['kind'] : 'pretest';
$lessonId = $kind === 'lesson' ? (int)($_GET['lesson_id'] ?? 0) : null;

if ($kind === 'lesson') {
    if (!$lessonId || !Progress::canAccessLesson($userId, $lessonId)) {
        header('Location: ' . url('/course.php'));
        exit;
    }
    if (!Progress::tasksAllDone($userId, $lessonId)) {
        header('Location: ' . url('/lesson.php?id=') . $lessonId);
        exit;
    }
}

$quiz = QuizGrader::findQuiz($courseId, $kind, $lessonId);
if (!$quiz) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}
$quizId = (int)$quiz['id'];

function qsBase(string $kind, ?int $lessonId): string
{
    $s = url('/quiz.php?kind=') . urlencode($kind);
    if ($lessonId) $s .= '&lesson_id=' . $lessonId;
    return $s;
}

// แบบทดสอบทุกชนิดไม่เฉลยระหว่างทำ ตอบแล้วไปข้อถัดไปทันที
// แล้วไปแสดงเฉลยทั้งชุดพร้อมคำอธิบายในหน้าผลคะแนน (quiz_result.php)
// ลำดับข้อสุ่มต่อการทำหนึ่งครั้ง — ดู QuizGrader::questions()

// ---- handle answer POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid($_POST['csrf_token'] ?? null);
    $attemptId = (int)($_POST['attempt'] ?? 0);
    $questionId = (int)($_POST['question_id'] ?? 0);
    $optionId = isset($_POST['option_id']) ? (int)$_POST['option_id'] : null;
    $q = (int)($_POST['q'] ?? 1);

    $attempt = QuizGrader::attempt($attemptId, $userId);
    if (!$attempt || (int)$attempt['quiz_id'] !== $quizId) {
        header('Location: ' . qsBase($kind, $lessonId));
        exit;
    }
    QuizGrader::submitAnswer($attemptId, $questionId, $optionId);

    header('Location: ' . qsBase($kind, $lessonId) . "&attempt=$attemptId&q=" . ($q + 1));
    exit;
}

// ---- start a new attempt if none given ----
$attemptId = (int)($_GET['attempt'] ?? 0);
$q = max(1, (int)($_GET['q'] ?? 1));

if (!$attemptId) {
    $attemptId = QuizGrader::startAttempt($userId, $quizId);
    header('Location: ' . qsBase($kind, $lessonId) . "&attempt=$attemptId&q=1");
    exit;
}
$attempt = QuizGrader::attempt($attemptId, $userId);
if (!$attempt || (int)$attempt['quiz_id'] !== $quizId) {
    header('Location: ' . qsBase($kind, $lessonId));
    exit;
}

$questions = QuizGrader::questions($quizId, $attemptId);
$total = count($questions);

// ---- finished all questions: grade + redirect to result ----
if ($q > $total) {
    if (!$attempt['completed_at']) {
        $result = QuizGrader::finishAttempt($attemptId);
        if ($kind === 'pretest') {
            Progress::addXp($userId, 30);
        } elseif ($kind === 'posttest') {
            Progress::addXp($userId, $result['score'] * 15);
            Progress::issueCertificateIfEligible($userId, $courseId);
        } elseif ($kind === 'lesson' && $lessonId) {
            if ($result['passed'] && Progress::tasksAllDone($userId, $lessonId)) {
                Progress::tryCompleteLesson($userId, $lessonId, true, $attemptId);
            }
        }
    }
    header('Location: ' . url('/quiz_result.php?attempt=') . $attemptId);
    exit;
}

$question = $questions[$q - 1];

$titleMap = ['pretest' => 'แบบทดสอบก่อนเรียน', 'posttest' => 'แบบทดสอบหลังเรียน', 'lesson' => $quiz['title_th']];
$tagMap = ['pretest' => 'PRE-TEST · ก่อนเรียน', 'posttest' => 'POST-TEST · หลังเรียน', 'lesson' => 'แบบทดสอบท้ายบท'];
$accentMap = ['pretest' => 'var(--cyan)', 'posttest' => 'var(--green)', 'lesson' => 'var(--amber)'];

Layout::start($titleMap[$kind], $user, $kind === 'lesson' ? 'course' : '');
?>
<div class="page-narrow">
  <div class="quiz-topbar">
    <div class="eyebrow" style="color:<?= $accentMap[$kind] ?>"><?= $tagMap[$kind] ?></div>
    <a href="<?= BASE_URL ?>/dashboard.php" style="font-size:11.5px;color:#6f837c">ออกจากแบบทดสอบ ✕</a>
  </div>
  <h1 style="font-size:24px"><?= htmlspecialchars($titleMap[$kind]) ?></h1>
  <div style="font-size:12.5px;color:#6f837c;margin-bottom:22px">
    เลือกคำตอบที่ถูกที่สุดเพียงข้อเดียว · ลำดับข้อสุ่มใหม่ทุกครั้งที่ทำ · ระบบจะเฉลยทุกข้อพร้อมคำอธิบายหลังทำครบทั้งชุด
  </div>

  <div class="quiz-dots">
    <?php for ($i = 0; $i < $total; $i++): $cls = $i < ($q - 1) ? 'correct' : ($i === $q - 1 ? 'current' : ''); ?>
      <div class="quiz-dot <?= $cls ?>"></div>
    <?php endfor; ?>
  </div>

  <div class="quiz-card">
    <div class="q-num" style="color:<?= $accentMap[$kind] ?>">ข้อ <?= $q ?> / <?= $total ?></div>
    <div class="q-text"><?= htmlspecialchars($question['question_text']) ?></div>
    <?php if ($question['code_snippet']): ?><div class="q-code"><?= htmlspecialchars($question['code_snippet']) ?></div><?php endif; ?>

    <form method="post" action="<?= qsBase($kind, $lessonId) ?>" class="q-options">
      <?= Csrf::field() ?>
      <input type="hidden" name="attempt" value="<?= $attemptId ?>">
      <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
      <input type="hidden" name="q" value="<?= $q ?>">
      <?php foreach ($question['options'] as $i => $o): ?>
        <button type="submit" name="option_id" value="<?= (int)$o['id'] ?>" class="q-option" style="width:100%;text-align:left;<?= $question['is_mono'] ? 'font-family:var(--mono)' : '' ?>">
          <span class="q-key"><?= chr(65 + $i) ?></span>
          <span class="q-option-text"><?= htmlspecialchars($o['option_text']) ?></span>
        </button>
      <?php endforeach; ?>
    </form>
  </div>
</div>
<?php
Layout::end();
