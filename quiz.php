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

// แบบทดสอบทุกชนิดไม่เฉลยระหว่างทำ จึงย้อนกลับไปแก้ข้อที่ตอบแล้วได้จนกว่าจะส่งคำตอบ
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
    if ($attempt['completed_at']) {
        // ส่งคำตอบไปแล้วและเห็นเฉลยแล้ว ห้ามแก้ย้อนหลัง
        header('Location: ' . url('/quiz_result.php?attempt=') . $attemptId);
        exit;
    }

    // ---- กดยืนยันส่งคำตอบ: ตรวจให้คะแนนแล้วไปหน้าเฉลย ----
    if (($_POST['action'] ?? '') === 'finish') {
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
        header('Location: ' . url('/quiz_result.php?attempt=') . $attemptId);
        exit;
    }

    QuizGrader::submitAnswer($attemptId, $questionId, $optionId);

    // ตอบแล้วไปข้อถัดไปที่ยังไม่ได้ตอบ (กรณีย้อนมาแก้ จะได้ไม่ต้องกดผ่านข้อเดิมซ้ำ)
    // ถ้าตอบครบทุกข้อแล้วให้ไปหน้ายืนยันส่งคำตอบ
    $order = QuizGrader::questions($quizId, $attemptId);
    $answered = QuizGrader::answers($attemptId);
    $next = count($order) + 1;
    foreach ($order as $i => $item) {
        if (!array_key_exists((int)$item['id'], $answered)) {
            $next = $i + 1;
            break;
        }
    }

    header('Location: ' . qsBase($kind, $lessonId) . "&attempt=$attemptId&q=$next");
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
if ($attempt['completed_at']) {
    // ส่งคำตอบไปแล้ว = เห็นเฉลยแล้ว กลับมาแก้ไม่ได้
    header('Location: ' . url('/quiz_result.php?attempt=') . $attemptId);
    exit;
}

$questions = QuizGrader::questions($quizId, $attemptId);
$total = count($questions);
$answers = QuizGrader::answers($attemptId);
$answeredCount = count(array_intersect_key($answers, array_flip(array_column($questions, 'id'))));

$reviewMode = $q > $total; // ทำครบแล้ว: หน้าตรวจทานก่อนส่ง
$question = $reviewMode ? null : $questions[$q - 1];
$picked = $question ? ($answers[(int)$question['id']] ?? null) : null;

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
    เลือกคำตอบที่ถูกที่สุดเพียงข้อเดียว · ย้อนกลับไปแก้ข้อที่ตอบแล้วได้จนกว่าจะกดส่งคำตอบ · ลำดับข้อสุ่มใหม่ทุกครั้งที่ทำ
  </div>

  <?php // จุดบอกความคืบหน้า กดข้ามไปข้อที่ตอบแล้วได้ ?>
  <div class="quiz-dots">
    <?php for ($i = 0; $i < $total; $i++):
        $isAnswered = array_key_exists((int)$questions[$i]['id'], $answers);
        $isCurrent = !$reviewMode && $i === $q - 1;
        $cls = $isCurrent ? 'current' : ($isAnswered ? 'correct' : '');
    ?>
      <?php if ($isAnswered && !$isCurrent): ?>
        <a class="quiz-dot <?= $cls ?>" title="กลับไปแก้ข้อ <?= $i + 1 ?>" href="<?= qsBase($kind, $lessonId) ?>&attempt=<?= $attemptId ?>&q=<?= $i + 1 ?>"></a>
      <?php else: ?>
        <div class="quiz-dot <?= $cls ?>"></div>
      <?php endif; ?>
    <?php endfor; ?>
  </div>

  <?php if ($reviewMode): ?>
    <?php // ตอบครบแล้ว — ให้ตรวจทานและเลือกแก้ก่อนส่ง เพราะส่งแล้วเห็นเฉลยและแก้ไม่ได้อีก ?>
    <div class="quiz-card">
      <div class="q-num" style="color:<?= $accentMap[$kind] ?>">ตรวจทานก่อนส่ง · <?= $answeredCount ?> / <?= $total ?> ข้อ</div>
      <div class="q-text" style="margin-bottom:6px">ตอบครบทุกข้อแล้ว ต้องการแก้ข้อไหนอีกไหม</div>
      <div style="font-size:12.5px;color:#6f837c;line-height:1.85;margin-bottom:18px">
        กดที่ข้อเพื่อกลับไปแก้คำตอบ · เมื่อกดส่งคำตอบแล้วระบบจะเฉลยทุกข้อพร้อมคำอธิบาย และแก้ไขไม่ได้อีก
      </div>

      <?php foreach ($questions as $i => $item):
          $sel = $answers[(int)$item['id']] ?? null;
          $selText = '';
          foreach ($item['options'] as $o) {
              if ((int)$o['id'] === $sel) {
                  $selText = $o['option_text'];
              }
          }
      ?>
        <a class="quiz-recap" href="<?= qsBase($kind, $lessonId) ?>&attempt=<?= $attemptId ?>&q=<?= $i + 1 ?>">
          <span class="review-n" style="background:rgba(74,222,128,.12);color:var(--green)"><?= $i + 1 ?></span>
          <span style="flex:1;min-width:0">
            <span class="quiz-recap-q"><?= htmlspecialchars($item['question_text']) ?></span>
            <span class="quiz-recap-a"><?= $selText === '' ? 'ยังไม่ได้ตอบ' : 'ตอบ: ' . htmlspecialchars($selText) ?></span>
          </span>
          <span style="flex:none;font-size:11.5px;color:#6f837c">แก้ไข →</span>
        </a>
      <?php endforeach; ?>

      <form method="post" action="<?= qsBase($kind, $lessonId) ?>" style="margin-top:20px;display:flex;gap:11px;flex-wrap:wrap">
        <?= Csrf::field() ?>
        <input type="hidden" name="attempt" value="<?= $attemptId ?>">
        <input type="hidden" name="action" value="finish">
        <a class="btn btn-ghost" href="<?= qsBase($kind, $lessonId) ?>&attempt=<?= $attemptId ?>&q=<?= $total ?>">← กลับไปข้อสุดท้าย</a>
        <button type="submit" class="btn btn-primary">ส่งคำตอบและดูเฉลย →</button>
      </form>
    </div>

  <?php else: ?>
  <div class="quiz-card">
    <div class="q-num" style="color:<?= $accentMap[$kind] ?>">ข้อ <?= $q ?> / <?= $total ?><?= $picked !== null ? ' · ตอบไว้แล้ว แก้ได้' : '' ?></div>
    <div class="q-text"><?= htmlspecialchars($question['question_text']) ?></div>
    <?php if ($question['code_snippet']): ?><div class="q-code"><?= htmlspecialchars($question['code_snippet']) ?></div><?php endif; ?>

    <form method="post" action="<?= qsBase($kind, $lessonId) ?>" class="q-options">
      <?= Csrf::field() ?>
      <input type="hidden" name="attempt" value="<?= $attemptId ?>">
      <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
      <input type="hidden" name="q" value="<?= $q ?>">
      <?php foreach ($question['options'] as $i => $o): $isPicked = $picked !== null && $picked === (int)$o['id']; ?>
        <button type="submit" name="option_id" value="<?= (int)$o['id'] ?>" class="q-option <?= $isPicked ? 'picked' : '' ?>" style="width:100%;text-align:left;<?= $question['is_mono'] ? 'font-family:var(--mono)' : '' ?>">
          <span class="q-key"><?= chr(65 + $i) ?></span>
          <span class="q-option-text"><?= htmlspecialchars($o['option_text']) ?></span>
          <?php if ($isPicked): ?><span class="q-picked-tag">คำตอบที่เลือกไว้</span><?php endif; ?>
        </button>
      <?php endforeach; ?>
    </form>

    <div class="quiz-nav">
      <?php if ($q > 1): ?>
        <a class="btn btn-ghost btn-sm" href="<?= qsBase($kind, $lessonId) ?>&attempt=<?= $attemptId ?>&q=<?= $q - 1 ?>">← ข้อก่อนหน้า</a>
      <?php endif; ?>
      <span class="spacer"></span>
      <?php if ($picked !== null): ?>
        <?php // ตอบข้อนี้ไว้แล้ว จึงข้ามไปข้อถัดไปได้โดยไม่ต้องเลือกซ้ำ ?>
        <a class="btn btn-ghost btn-sm" href="<?= qsBase($kind, $lessonId) ?>&attempt=<?= $attemptId ?>&q=<?= $q + 1 ?>"><?= $q >= $total ? 'ไปหน้าตรวจทาน' : 'ข้อถัดไป' ?> →</a>
      <?php elseif ($answeredCount >= $total): ?>
        <a class="btn btn-ghost btn-sm" href="<?= qsBase($kind, $lessonId) ?>&attempt=<?= $attemptId ?>&q=<?= $total + 1 ?>">ไปหน้าตรวจทาน →</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php
Layout::end();
