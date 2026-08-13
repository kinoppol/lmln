<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::get();

$lessonId = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM lessons WHERE id = ? AND course_id = 1');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: ' . url('/course.php'));
    exit;
}
if (!Progress::canAccessLesson($userId, $lessonId)) {
    header('Location: ' . url('/course.php?locked=1'));
    exit;
}

$progressRow = Progress::forLesson($userId, $lessonId);
$isCompleted = $progressRow['status'] === 'completed';

$points = $db->prepare('SELECT cmd, description FROM lesson_points WHERE lesson_id = ? ORDER BY position');
$points->execute([$lessonId]); $points = $points->fetchAll();

$examples = $db->prepare('SELECT text_line, color_tag FROM lesson_examples WHERE lesson_id = ? ORDER BY position');
$examples->execute([$lessonId]); $examples = $examples->fetchAll();

$hints = $db->prepare('SELECT text_hint FROM lesson_hints WHERE lesson_id = ? ORDER BY position');
$hints->execute([$lessonId]); $hints = array_column($hints->fetchAll(), 'text_hint');

$tasksStmt = $db->prepare('SELECT id, label FROM lesson_tasks WHERE lesson_id = ? ORDER BY position');
$tasksStmt->execute([$lessonId]); $tasks = $tasksStmt->fetchAll();

$doneTaskIds = json_decode($progressRow['tasks_done'] ?? '[]', true) ?: [];

$prevStmt = $db->prepare('SELECT id FROM lessons WHERE course_id = 1 AND position = ?');
$prevStmt->execute([(int)$lesson['position'] - 1]);
$prevId = $prevStmt->fetchColumn();

$colorMap = ['green' => '#4ade80', 'txt' => '#d7e4de', 'dim' => '#9bb0a8', 'red' => '#f87171', 'amber' => '#fbbf24', 'cyan' => '#38bdf8'];

Layout::start($lesson['title_th'], $user, 'course');
?>
<div class="lesson-layout">
  <div class="lesson-left">
    <a class="back-link" href="<?= BASE_URL ?>/course.php">← กลับไปหน้าบทเรียนทั้งหมด</a>
    <div class="lesson-tag">LESSON <?= (int)$lesson['position'] ?> / <?= LESSON_COUNT ?></div>
    <h1><?= htmlspecialchars($lesson['title_th']) ?></h1>
    <div class="lesson-en"><?= htmlspecialchars($lesson['title_en']) ?></div>
    <p class="lesson-intro"><?= nl2br(htmlspecialchars($lesson['intro'])) ?></p>

    <div class="section-label">คำสั่งในบทนี้ · COMMANDS</div>
    <?php foreach ($points as $p): ?>
      <div class="point-row"><code><?= htmlspecialchars($p['cmd']) ?></code><div class="desc"><?= htmlspecialchars($p['description']) ?></div></div>
    <?php endforeach; ?>

    <div class="section-label">ตัวอย่าง · EXAMPLE</div>
    <div class="example-box">
      <?php foreach ($examples as $e): ?><div style="color:<?= $colorMap[$e['color_tag']] ?? '#d7e4de' ?>"><?= htmlspecialchars($e['text_line']) ?></div><?php endforeach; ?>
    </div>

    <div class="warn-box">
      <div class="title">⚠ ข้อควรระวัง · Watch out</div>
      <div class="body"><?= htmlspecialchars($lesson['warn_text']) ?></div>
    </div>

    <div class="lesson-actions">
      <?php if ($prevId): ?><a class="btn btn-ghost" href="<?= BASE_URL ?>/lesson.php?id=<?= (int)$prevId ?>">← บทก่อนหน้า</a><?php endif; ?>
      <?php if ($isCompleted): ?>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/course.php">ผ่านบทนี้แล้ว — กลับไปหน้าบทเรียน →</a>
      <?php else: ?>
        <a class="btn btn-primary" id="quizLink" href="<?= BASE_URL ?>/quiz.php?kind=lesson&lesson_id=<?= $lessonId ?>" style="<?= count($doneTaskIds) >= count($tasks) ? '' : 'pointer-events:none;opacity:.4' ?>">ทำแบบทดสอบท้ายบทเพื่อปลดล็อกบทถัดไป →</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="lesson-right">
    <div class="task-panel">
      <div class="task-head"><span>ภารกิจฝึกปฏิบัติ · TASKS</span><span class="progress" id="taskProgress"><?= count($doneTaskIds) ?> / <?= count($tasks) ?></span></div>
      <div id="taskList">
        <?php foreach ($tasks as $t): $done = in_array((int)$t['id'], $doneTaskIds, true); ?>
          <div class="task-row" data-task-id="<?= (int)$t['id'] ?>">
            <span class="task-check <?= $done ? 'done' : '' ?>"><?= $done ? '✓' : '' ?></span>
            <span class="task-label <?= $done ? 'done' : '' ?>"><?= htmlspecialchars($t['label']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div id="taskDoneBanner" class="task-done-banner" style="<?= count($doneTaskIds) >= count($tasks) ? '' : 'display:none' ?>">✓ ทำภารกิจครบแล้ว! ไปทำแบบทดสอบท้ายบทเพื่อปลดล็อกบทถัดไปได้เลย</div>
    </div>

    <div class="tree-panel">
      <div class="tree-head" id="treeToggle"><span style="font-family:var(--mono);font-size:10px;color:var(--cyan)" id="treeCaret">▾</span><span>แผนผังตำแหน่ง · WHERE AM I</span><span class="spacer"></span><span class="path" id="treePath">~</span></div>
      <div class="tree-box" id="treeBox"></div>
      <div class="tree-note">แผนผังนี้อัปเดตทุกครั้งที่ใช้ cd — ลองเทียบกับผลของ pwd ดู</div>
    </div>

    <div class="term-wrap" id="termWrap">
      <div class="term-head"><span class="dot dot-red"></span><span class="dot dot-amber"></span><span class="dot dot-green"></span><span class="cwd" id="termCwdLabel">student@linuxquest: ~</span><span class="term-clear" id="termClear">clear</span></div>
      <div class="term-lines" id="termLines"></div>
      <div class="term-inputrow">
        <span class="term-prompt" id="termPrompt">~ $</span>
        <input class="term-input" id="termInput" autocomplete="off" spellcheck="false" placeholder="พิมพ์คำสั่ง · Enter=รัน · Tab=เติมชื่อ · ↑=คำสั่งเดิม">
      </div>
      <div class="term-hints" id="termHints"><span style="font-size:10.5px;color:#4c5f59">ลองพิมพ์:</span></div>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/public/js/terminal-engine.js"></script>
<script>
window.LQ_LESSON = {
  base: <?= json_encode(BASE_URL) ?>,
  lessonId: <?= json_encode($lessonId) ?>,
  vfsSeed: <?= $lesson['vfs_seed'] ?>,
  hints: <?= json_encode($hints, JSON_UNESCAPED_UNICODE) ?>,
  csrf: <?= json_encode(Csrf::token()) ?>,
  tasksTotal: <?= count($tasks) ?>
};
</script>
<script src="<?= BASE_URL ?>/public/js/lesson.js"></script>
<?php
Layout::end();
