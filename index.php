<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// ล็อกอินอยู่แล้วให้เข้าหน้าเรียนต่อได้เลย ไม่ต้องอ่านหน้าแนะนำซ้ำ
if (Auth::currentUser()) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

// หน้านี้ต้องเปิดได้แม้ยังไม่ได้ติดตั้งฐานข้อมูล จึงอ่านตัวเลขแบบยอมพลาดได้
$stats = ['lessons' => LESSON_COUNT, 'games' => 4, 'quizzes' => 0, 'installed' => true];
try {
    $db = Database::get();
    $stats['lessons'] = (int)$db->query('SELECT COUNT(*) FROM lessons')->fetchColumn();
    $stats['games'] = (int)$db->query('SELECT COUNT(*) FROM games')->fetchColumn();
    $stats['quizzes'] = (int)$db->query('SELECT COUNT(*) FROM quiz_questions')->fetchColumn();
} catch (Throwable $e) {
    $stats['installed'] = false;
}

$features = [
    ['glyph' => '>_', 'title' => 'Terminal จำลองในเบราว์เซอร์', 'body' => 'พิมพ์คำสั่งจริงได้ทันทีโดยไม่ต้องลง Linux ไม่ต้องติดตั้งอะไรเพิ่ม มีระบบไฟล์จำลองของตัวเองในทุกบทเรียน พร้อม Tab เติมชื่อไฟล์และ ↑ เรียกคำสั่งเดิม'],
    ['glyph' => '≡', 'title' => 'บทเรียนเรียงลำดับ ' . $stats['lessons'] . ' บท', 'body' => 'เริ่มจาก pwd/ls ไปจนถึง grep/find แต่ละบทมีคำอธิบายภาษาไทย ตัวอย่างคำสั่ง และข้อควรระวัง ต้องผ่านบทก่อนหน้าจึงจะปลดล็อกบทถัดไป'],
    ['glyph' => '✓', 'title' => 'ภารกิจฝึกปฏิบัติที่ตรวจให้อัตโนมัติ', 'body' => 'ทุกบทมีภารกิจที่ต้องลงมือพิมพ์คำสั่งจริง ระบบตรวจจากคำสั่งที่ใช้และผลลัพธ์ในระบบไฟล์ให้ทันทีที่กด Enter ไม่ต้องรอครูตรวจ'],
    ['glyph' => '◷', 'title' => 'แบบทดสอบครบทั้งก่อน ระหว่าง และหลังเรียน', 'body' => 'แบบทดสอบก่อนเรียนวัดพื้นฐาน แบบทดสอบท้ายบทต้องผ่านจึงจะไปบทถัดไป และแบบทดสอบหลังเรียนวัดผลรวม ทุกข้อมีเฉลยพร้อมคำอธิบาย'],
    ['glyph' => '▶', 'title' => 'โซนเกมฝึกทักษะ ' . $stats['games'] . ' เกม', 'body' => 'ล่าไวรัส ห้องหนีตาย ซ่อมระบบพัง และแข่งพิมพ์คำสั่ง — ทุกเกมใช้ Terminal ตัวเดียวกับบทเรียน และจะปลดล็อกเมื่อเรียนบทที่เกมนั้นใช้ครบแล้ว'],
    ['glyph' => '#', 'title' => 'XP เลเวล และกระดานผู้นำ', 'body' => 'ได้ XP จากการผ่านบทเรียน ทำแบบทดสอบ และเล่นเกม สะสมเป็นเลเวล พร้อมกระดานผู้นำให้เทียบกับเพื่อนร่วมชั้น'],
    ['glyph' => '✦', 'title' => 'ใบประกาศนียบัตรเมื่อเรียนจบ', 'body' => 'เรียนครบทุกบทและทำแบบทดสอบหลังเรียนผ่านเกณฑ์ ' . PASS_PCT_POSTTEST . '% ระบบจะออกใบประกาศพร้อมรหัสอ้างอิงให้อัตโนมัติ'],
    ['glyph' => '▤', 'title' => 'แดชบอร์ดสำหรับผู้สอน', 'body' => 'ดูความก้าวหน้ารายบุคคล คะแนนก่อน-หลังเรียน อัตราการผ่านเกณฑ์ และข้อที่ผู้เรียนตอบผิดบ่อย เพื่อวางแผนสอนซ่อมเสริม'],
];

$steps = [
    ['n' => '01', 'th' => 'สมัครสมาชิก', 'sub' => 'นักเรียนนักศึกษาหรือบุคคลทั่วไป'],
    ['n' => '02', 'th' => 'ทำแบบทดสอบก่อนเรียน', 'sub' => 'วัดพื้นฐานก่อนเริ่ม'],
    ['n' => '03', 'th' => 'เรียน ' . $stats['lessons'] . ' บท + ทำภารกิจ', 'sub' => 'ผ่านบทเรียนทีละบท'],
    ['n' => '04', 'th' => 'ฝึกต่อในโซนเกม', 'sub' => 'ปลดล็อกตามบทที่เรียนจบ'],
    ['n' => '05', 'th' => 'ทดสอบหลังเรียน', 'sub' => 'ผ่าน ' . PASS_PCT_POSTTEST . '% รับใบประกาศ'],
];

Layout::start('เรียน Linux ผ่าน Terminal จำลอง', null, '', 'landing-main');
?>
<div class="landing">

  <header class="landing-nav">
    <div class="brand">
      <div class="brand-mark">&gt;_</div>
      <div class="brand-text">
        <span class="brand-name">LinuxQuest</span>
        <span class="brand-sub">ระบบจัดการเรียนรู้ · LMS</span>
      </div>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>/login.php">เข้าสู่ระบบ</a>
    <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/register.php">สมัครสมาชิก</a>
  </header>

  <?php if (!$stats['installed']): ?>
    <div class="gate-note" style="margin-top:24px">
      ยังไม่ได้ติดตั้งฐานข้อมูล — เปิด <a href="<?= BASE_URL ?>/install.php" style="color:var(--amber);text-decoration:underline">install.php</a> เพื่อติดตั้งระบบก่อนใช้งาน
    </div>
  <?php endif; ?>

  <section class="landing-hero">
    <div class="landing-hero-text">
      <div class="eyebrow"><?= COURSE_CODE ?> · BASIC LINUX COMMANDS</div>
      <h1 class="landing-h1">เรียนคำสั่ง Linux ด้วยการลงมือพิมพ์จริง<br>ผ่าน Terminal จำลองในเบราว์เซอร์</h1>
      <p class="landing-sub">
        ไม่ต้องลง VM ไม่ต้องกลัวพิมพ์ผิดจนเครื่องพัง — เรียนทีละบทพร้อมภารกิจที่ระบบตรวจให้อัตโนมัติ
        ทดสอบความเข้าใจท้ายบท แล้วเอาคำสั่งที่เรียนไปใช้จริงในเกมกู้ระบบ เนื้อหาภาษาไทยทั้งหมด
      </p>
      <div class="landing-cta">
        <a class="btn btn-primary" href="<?= BASE_URL ?>/register.php">สมัครสมาชิกเพื่อเริ่มเรียน →</a>
        <a class="btn btn-outline" href="<?= BASE_URL ?>/login.php">มีบัญชีแล้ว · เข้าสู่ระบบ</a>
      </div>
      <div class="landing-stats">
        <div><span class="num"><?= $stats['lessons'] ?></span><span class="lbl">บทเรียน</span></div>
        <div><span class="num"><?= $stats['games'] ?></span><span class="lbl">เกมฝึกทักษะ</span></div>
        <div><span class="num"><?= $stats['quizzes'] ?: '—' ?></span><span class="lbl">ข้อสอบพร้อมเฉลย</span></div>
        <div><span class="num">20+</span><span class="lbl">คำสั่งที่ใช้ได้จริง</span></div>
      </div>
    </div>

    <div class="landing-term" aria-hidden="true">
      <div class="term-head">
        <span class="dot dot-red"></span><span class="dot dot-amber"></span><span class="dot dot-green"></span>
        <span class="cwd">student@linuxquest: ~</span>
      </div>
      <div class="landing-term-body">
        <div><span style="color:var(--green)">~ $</span> pwd</div>
        <div style="color:#d7e4de">/home/student</div>
        <div><span style="color:var(--green)">~ $</span> ls -l</div>
        <div style="color:#9bb0a8">drwxr-xr-x  documents</div>
        <div style="color:#9bb0a8">drwxr-xr-x  downloads</div>
        <div style="color:#9bb0a8">-rw-r--r--  notes.txt</div>
        <div><span style="color:var(--green)">~ $</span> grep -n "TODO" notes.txt</div>
        <div style="color:#38bdf8">1:TODO: อ่านบทที่ 6</div>
        <div style="color:#38bdf8">2:TODO: ส่งงาน Linux</div>
        <div style="color:var(--amber)">✓ ภารกิจสำเร็จ +40 XP</div>
        <div><span style="color:var(--green)">~ $</span> <span class="landing-caret">▌</span></div>
      </div>
    </div>
  </section>

  <section class="landing-section">
    <div class="eyebrow">FEATURES</div>
    <h2 class="landing-h2">ในระบบมีอะไรให้ใช้บ้าง</h2>
    <div class="landing-grid">
      <?php foreach ($features as $f): ?>
        <div class="landing-feat">
          <div class="landing-feat-glyph"><?= $f['glyph'] ?></div>
          <div class="landing-feat-title"><?= htmlspecialchars($f['title']) ?></div>
          <div class="landing-feat-body"><?= htmlspecialchars($f['body']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="landing-section">
    <div class="eyebrow">HOW IT WORKS</div>
    <h2 class="landing-h2">เส้นทางการเรียน</h2>
    <div class="landing-steps">
      <?php foreach ($steps as $s): ?>
        <div class="landing-step">
          <div class="landing-step-n"><?= $s['n'] ?></div>
          <div class="landing-step-th"><?= htmlspecialchars($s['th']) ?></div>
          <div class="landing-step-sub"><?= htmlspecialchars($s['sub']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="landing-final">
    <h2 class="landing-h2" style="margin-bottom:8px">พร้อมเริ่มบทแรกแล้วหรือยัง</h2>
    <p class="landing-sub" style="margin-bottom:22px">สมัครฟรี ใช้เวลาไม่ถึงหนึ่งนาที แล้วเริ่มพิมพ์คำสั่งแรกได้ทันที</p>
    <div class="landing-cta" style="justify-content:center">
      <a class="btn btn-primary" href="<?= BASE_URL ?>/register.php">สมัครสมาชิก →</a>
      <a class="btn btn-ghost" href="<?= BASE_URL ?>/login.php">เข้าสู่ระบบ</a>
    </div>
  </section>

  <footer class="landing-foot">
    LinuxQuest LMS · <?= COURSE_CODE ?> คำสั่งพื้นฐาน Linux · ระบบเรียนรู้พร้อมเกมฝึกทักษะ
  </footer>
</div>
<?php
Layout::end();
