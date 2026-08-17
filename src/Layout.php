<?php
declare(strict_types=1);

final class Layout
{
    /** เก็บไว้ให้ end() วาด modal ยืนยันออกจากระบบได้โดยไม่ต้องรับพารามิเตอร์ซ้ำ */
    private static ?array $currentUser = null;

    /** $mainClass = คลาสเสริมของ <main> สำหรับหน้าที่ไม่ใช้การจัดวางแบบกึ่งกลาง เช่น หน้า Landing */
    public static function start(string $title, ?array $user, string $active = '', string $mainClass = ''): void
    {
        self::$currentUser = $user;
        $doneCount = $user ? Progress::doneCount((int)$user['id']) : 0;
        $level = $user ? Progress::level((int)$user['xp']) : 1;

        // ป้ายเตือนจำนวน migration ที่ค้าง แสดงเฉพาะผู้สอน และห้ามทำให้หน้าอื่นล่มถ้าเช็กไม่ได้
        $pendingMigrations = 0;
        if ($user && $user['role'] === 'teacher') {
            try {
                $pendingMigrations = count(Migrator::pending(Database::get()));
            } catch (Throwable $e) {
                $pendingMigrations = 0;
            }
        }
        ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> · <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('/public/css/style.css') ?>">
</head>
<body>
<div class="app-shell">
<?php if ($user && Auth::isImpersonating()): $actor = Auth::impersonator(); ?>
  <?php // แถบเตือนว่ากำลังดูระบบในฐานะผู้เรียนคนนี้ ไม่ใช่บัญชีตัวเอง ?>
  <div class="impersonate-bar">
    <span class="impersonate-dot"></span>
    <span>กำลังสวมสิทธิ์ <strong><?= htmlspecialchars($user['full_name']) ?></strong> — ทุกอย่างที่เห็นและ XP ที่ได้จะเป็นของบัญชีนี้</span>
    <span class="spacer"></span>
    <?php if ($actor): ?><span class="impersonate-who">ผู้ดูแล: <?= htmlspecialchars($actor['full_name']) ?></span><?php endif; ?>
    <form method="post" action="<?= BASE_URL ?>/logout.php" style="display:inline">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-sm btn-danger">คืนสิทธิ์ผู้ดูแล ↩</button>
    </form>
  </div>
<?php endif; ?>
<?php if ($user): ?>
  <header class="app-header">
    <a class="brand" href="<?= BASE_URL ?>/dashboard.php">
      <div class="brand-mark">&gt;_</div>
      <div class="brand-text">
        <span class="brand-name">LinuxQuest</span>
        <span class="brand-sub">ระบบจัดการเรียนรู้ · LMS</span>
      </div>
    </a>
    <div class="spacer"></div>
    <div class="xp-pill">
      <span class="lv-badge">LV <?= $level ?></span>
      <span class="xp-amount"><?= (int)$user['xp'] ?> XP</span>
    </div>
    <div class="user-chip">
      <div class="user-avatar"><?= htmlspecialchars(mb_substr($user['full_name'], 0, 1)) ?></div>
      <div class="user-text">
        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
        <span class="user-sub"><?= self::roleLabel($user) ?></span>
      </div>
    </div>
  </header>
  <div class="app-body">
    <nav class="app-nav">
      <div class="nav-group-label">เมนูผู้เรียน · LEARNER</div>
      <?php foreach (self::mainNav($doneCount) as $item): ?>
        <a class="nav-item <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= $item['href'] ?>">
          <span class="nav-glyph"><?= $item['glyph'] ?></span>
          <span class="nav-label"><?= $item['label'] ?></span>
          <?php if (!empty($item['badge'])): ?><span class="nav-badge"><?= $item['badge'] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <?php if ($user['role'] === 'teacher'): ?>
        <div class="nav-group-label" style="margin-top:18px">ผู้สอน · INSTRUCTOR</div>
        <a class="nav-item <?= $active === 'teacher' ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/dashboard.php">
          <span class="nav-glyph">▤</span><span class="nav-label">แดชบอร์ดผลเรียน</span>
        </a>
        <a class="nav-item <?= $active === 'students' ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/students.php">
          <span class="nav-glyph">☰</span><span class="nav-label">จัดการผู้เรียน</span>
        </a>
        <a class="nav-item <?= $active === 'migrations' ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/migrations.php">
          <span class="nav-glyph">⇪</span><span class="nav-label">ปรับปรุงฐานข้อมูล</span>
          <?php if ($pendingMigrations > 0): ?><span class="nav-badge warn"><?= $pendingMigrations ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
      <div class="spacer"></div>
      <div class="nav-progress">
        <div class="nav-progress-row"><span>ความคืบหน้ารวม</span><span class="pct"><?= round($doneCount / LESSON_COUNT * 100) ?>%</span></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= round($doneCount / LESSON_COUNT * 100) ?>%"></div></div>
        <div class="nav-progress-sub"><?= $doneCount ?> จาก <?= LESSON_COUNT ?> บทเรียน</div>
      </div>
      <button type="button" class="nav-logout" id="logoutBtn"><?= Auth::isImpersonating() ? 'คืนสิทธิ์ผู้ดูแล ↩' : 'ออกจากระบบ ↺' ?></button>
    </nav>
    <main class="app-main">
<?php else: ?>
  <main class="app-main auth-main <?= htmlspecialchars($mainClass) ?>">
<?php endif; ?>
        <?php
    }

    public static function end(): void
    {
        $user = self::$currentUser;
        ?>
    </main>
  </div>
<?php if ($user): ?>
  <?php $impersonating = Auth::isImpersonating(); ?>
  <div class="modal-backdrop" id="logoutModal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
      <div class="modal-icon"><?= $impersonating ? '↩' : '↺' ?></div>
      <div class="modal-title" id="logoutModalTitle"><?= $impersonating ? 'คืนสิทธิ์ผู้ดูแล?' : 'ออกจากระบบ?' ?></div>
      <p class="modal-body">
        <?php if ($impersonating): ?>
          เลิกสวมสิทธิ์ <strong><?= htmlspecialchars($user['full_name']) ?></strong> แล้วกลับไปใช้บัญชีผู้ดูแลของคุณ<br>
          สิ่งที่ทำไว้ในบัญชีผู้เรียนนี้ถูกบันทึกตามปกติ
        <?php else: ?>
          กำลังออกจากระบบในชื่อ <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
          ความคืบหน้าและคะแนนที่ทำไว้ถูกบันทึกไว้แล้ว เข้าสู่ระบบใหม่เมื่อไหร่ก็เรียนต่อจากเดิมได้
        <?php endif; ?>
      </p>
      <form method="post" action="<?= BASE_URL ?>/logout.php" class="modal-actions">
        <?= Csrf::field() ?>
        <button type="button" class="btn btn-ghost" id="logoutCancel">ยกเลิก</button>
        <button type="submit" class="btn btn-danger"><?= $impersonating ? 'คืนสิทธิ์ผู้ดูแล' : 'ออกจากระบบ' ?></button>
      </form>
    </div>
  </div>
  <script>
  (function () {
    var modal = document.getElementById('logoutModal');
    var openBtn = document.getElementById('logoutBtn');
    var cancelBtn = document.getElementById('logoutCancel');
    if (!modal || !openBtn) return;
    var lastFocused = null;

    function open() {
      lastFocused = document.activeElement;
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      cancelBtn.focus();
    }
    function close() {
      modal.hidden = true;
      document.body.style.overflow = '';
      if (lastFocused) lastFocused.focus();
    }

    openBtn.addEventListener('click', open);
    cancelBtn.addEventListener('click', close);
    modal.addEventListener('mousedown', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
  })();
  </script>
<?php endif; ?>
</div>
</body>
</html>
        <?php
    }

    private static function roleLabel(array $user): string
    {
        if ($user['role'] === 'teacher') {
            return 'ผู้สอน';
        }
        if ($user['role'] === 'student') {
            $bits = array_filter([$user['education_level'], $user['major']]);
            return $bits ? implode(' · ', $bits) : 'นักเรียนนักศึกษา';
        }
        return 'บุคคลทั่วไป';
    }

    private static function mainNav(int $doneCount): array
    {
        return [
            ['key' => 'home', 'glyph' => '~', 'label' => 'หน้าแรก', 'href' => url('/dashboard.php')],
            ['key' => 'course', 'glyph' => '≡', 'label' => 'บทเรียน', 'href' => url('/course.php'), 'badge' => $doneCount . '/' . LESSON_COUNT],
            ['key' => 'games', 'glyph' => '▶', 'label' => 'โซนเกม', 'href' => url('/games.php')],
            ['key' => 'board', 'glyph' => '#', 'label' => 'กระดานผู้นำ', 'href' => url('/leaderboard.php')],
            ['key' => 'cert', 'glyph' => '✦', 'label' => 'ใบประกาศ', 'href' => url('/certificate.php')],
        ];
    }
}
