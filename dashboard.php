<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$doneCount = Progress::doneCount($userId);
$level = Progress::level((int)$user['xp']);

$db = Database::get();
$preAttempt = $db->prepare(
    "SELECT a.score, a.total FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id
     WHERE a.user_id=? AND q.kind='pretest' AND a.completed_at IS NOT NULL ORDER BY a.id DESC LIMIT 1"
);
$preAttempt->execute([$userId]);
$pre = $preAttempt->fetch();

$post = Progress::bestPosttestScore($userId, $courseId = 1);
$certReady = Progress::certificateEligible($userId, 1);

$stmt = $db->prepare('SELECT COUNT(DISTINCT game_id) c FROM game_scores WHERE user_id=?');
$stmt->execute([$userId]);
$gamesPlayed = (int)$stmt->fetchColumn();

Layout::start('หน้าแรก', $user, 'home');
?>
<div class="page">
  <div class="hero">
    <div class="hero-inner">
      <div style="flex:1;min-width:280px">
        <div class="hero-tag"><span class="hero-dot"></span>COURSE · <?= COURSE_CODE ?></div>
        <h1>คำสั่งพื้นฐาน Linux<br><span style="font-family:var(--mono);font-size:22px;color:var(--green);font-weight:500">Basic Linux Commands</span></h1>
        <p>เรียนคำสั่งที่ใช้จริงในการทำงานผ่าน Terminal จำลองในเบราว์เซอร์ — พิมพ์เอง เห็นผลทันที ไม่ต้องติดตั้งอะไร แล้วปิดท้ายด้วยเกมล่าไวรัสและภารกิจกู้ระบบ</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= $doneCount ? '/lmln/course.php' : '/lmln/quiz.php?kind=pretest' ?>"><?= $doneCount ? 'เรียนต่อ' : 'เริ่มด้วยแบบทดสอบก่อนเรียน' ?> →</a>
          <a class="btn btn-outline" href="/lmln/games.php">เข้าโซนเกม</a>
        </div>
      </div>
      <div class="term-preview">
        <div class="term-titlebar"><span class="dot dot-red"></span><span class="dot dot-amber"></span><span class="dot dot-green"></span><span style="margin-left:6px;font-family:var(--mono);font-size:10.5px;color:#5f736c">student@linuxquest:~</span></div>
        <div class="term-body">
          <div><span style="color:var(--green)">$</span> pwd</div>
          <div>/home/student</div>
          <div><span style="color:var(--green)">$</span> ls -l</div>
          <div>drwxr-xr-x  documents</div>
          <div>drwxr-xr-x  downloads</div>
          <div>-rw-r--r--  notes.txt</div>
          <div><span style="color:var(--green)">$</span> av --scan .</div>
          <div style="color:var(--red)">!! 2 infected files found</div>
          <div><span style="color:var(--green)">$</span> <span style="display:inline-block;width:8px;height:14px;background:var(--green);vertical-align:-2px;animation:lqblink 1.1s infinite"></span></div>
        </div>
      </div>
    </div>
  </div>

  <div class="stats-grid">
    <div class="card"><div class="stat-value" style="color:var(--green)">9</div><div class="stat-label">บทเรียน</div><div class="stat-sub">LESSONS</div></div>
    <div class="card"><div class="stat-value" style="color:var(--cyan)">20</div><div class="stat-label">ข้อสอบก่อน+หลังเรียน</div><div class="stat-sub">QUIZ ITEMS</div></div>
    <div class="card"><div class="stat-value" style="color:var(--amber)">4</div><div class="stat-label">เกมฝึกทักษะ</div><div class="stat-sub">MINI GAMES</div></div>
    <div class="card"><div class="stat-value" style="color:#e8f5ee">∞</div><div class="stat-label">Terminal จำลองให้ลองผิดลองถูก</div><div class="stat-sub">SANDBOX</div></div>
  </div>

  <h2 style="margin:34px 0 4px;font-size:17px;font-weight:600;color:#e3efe9">เส้นทางการเรียน · Learning path</h2>
  <p style="margin:0 0 18px;font-size:12.5px;color:#6f837c">ทำแบบทดสอบก่อนเรียน → เรียน 9 บท (แต่ละบทมี Terminal ให้ฝึก + แบบทดสอบท้ายบท) → เกมฝึกทักษะ → แบบทดสอบหลังเรียน → รับใบประกาศ</p>
  <div class="path-row">
    <a class="path-step" style="border-color:rgba(56,189,248,.2);background:rgba(56,189,248,.05)" href="/lmln/quiz.php?kind=pretest">
      <div class="n" style="color:var(--cyan)">STEP 1</div>
      <div class="th">แบบทดสอบก่อนเรียน</div><div class="en">Pre-test</div>
      <div class="state" style="color:var(--cyan)"><?= $pre ? 'ทำแล้ว ' . (int)$pre['score'] . '/' . (int)$pre['total'] : 'ยังไม่ได้ทำ' ?></div>
    </a>
    <a class="path-step" style="border-color:rgba(74,222,128,.2);background:rgba(74,222,128,.05)" href="/lmln/course.php">
      <div class="n" style="color:var(--green)">STEP 2</div>
      <div class="th">เรียน 9 บท + ฝึกใน Terminal</div><div class="en">Lessons & sandbox</div>
      <div class="state" style="color:var(--green)"><?= $doneCount ?> / <?= LESSON_COUNT ?> บท</div>
    </a>
    <a class="path-step" style="border-color:rgba(251,191,36,.2);background:rgba(251,191,36,.05)" href="/lmln/games.php">
      <div class="n" style="color:var(--amber)">STEP 3</div>
      <div class="th">เกมฝึกทักษะ 4 เกม</div><div class="en">Skill games</div>
      <div class="state" style="color:var(--amber)"><?= $gamesPlayed ?> / 4 เกมที่เคยเล่น</div>
    </a>
    <a class="path-step" style="border-color:rgba(56,189,248,.2);background:rgba(56,189,248,.05)" href="/lmln/quiz.php?kind=posttest">
      <div class="n" style="color:var(--cyan)">STEP 4</div>
      <div class="th">แบบทดสอบหลังเรียน</div><div class="en">Post-test</div>
      <div class="state" style="color:var(--cyan)"><?= $post ? 'ทำแล้ว ' . (int)$post['score'] . '/' . (int)$post['total'] : 'ยังไม่ได้ทำ' ?></div>
    </a>
    <a class="path-step" style="border-color:rgba(255,255,255,.08)" href="/lmln/certificate.php">
      <div class="n" style="color:<?= $certReady ? 'var(--green)' : '#6f837c' ?>">STEP 5</div>
      <div class="th">รับใบประกาศนียบัตร</div><div class="en">Certificate</div>
      <div class="state" style="color:<?= $certReady ? 'var(--green)' : '#6f837c' ?>"><?= $certReady ? 'พร้อมรับแล้ว' : 'ยังไม่ปลดล็อก' ?></div>
    </a>
  </div>
</div>
<?php
Layout::end();
