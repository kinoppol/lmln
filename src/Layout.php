<?php
declare(strict_types=1);

final class Layout
{
    public static function start(string $title, ?array $user, string $active = ''): void
    {
        $doneCount = $user ? Progress::doneCount((int)$user['id']) : 0;
        $level = $user ? Progress::level((int)$user['xp']) : 1;
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
<link rel="stylesheet" href="/lmln/public/css/style.css">
</head>
<body>
<div class="app-shell">
<?php if ($user): ?>
  <header class="app-header">
    <a class="brand" href="/lmln/dashboard.php">
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
        <a class="nav-item <?= $active === 'teacher' ? 'active' : '' ?>" href="/lmln/teacher/dashboard.php">
          <span class="nav-glyph">▤</span><span class="nav-label">แดชบอร์ดผลเรียน</span>
        </a>
      <?php endif; ?>
      <div class="spacer"></div>
      <div class="nav-progress">
        <div class="nav-progress-row"><span>ความคืบหน้ารวม</span><span class="pct"><?= round($doneCount / LESSON_COUNT * 100) ?>%</span></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= round($doneCount / LESSON_COUNT * 100) ?>%"></div></div>
        <div class="nav-progress-sub"><?= $doneCount ?> จาก <?= LESSON_COUNT ?> บทเรียน</div>
      </div>
      <a class="nav-logout" href="/lmln/logout.php">ออกจากระบบ ↺</a>
    </nav>
    <main class="app-main">
<?php else: ?>
  <main class="app-main auth-main">
<?php endif; ?>
        <?php
    }

    public static function end(): void
    {
        ?>
    </main>
  </div>
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
            ['key' => 'home', 'glyph' => '~', 'label' => 'หน้าแรก', 'href' => '/lmln/dashboard.php'],
            ['key' => 'course', 'glyph' => '≡', 'label' => 'บทเรียน', 'href' => '/lmln/course.php', 'badge' => $doneCount . '/' . LESSON_COUNT],
            ['key' => 'games', 'glyph' => '▶', 'label' => 'โซนเกม', 'href' => '/lmln/games.php'],
            ['key' => 'board', 'glyph' => '#', 'label' => 'กระดานผู้นำ', 'href' => '/lmln/leaderboard.php'],
            ['key' => 'cert', 'glyph' => '✦', 'label' => 'ใบประกาศ', 'href' => '/lmln/certificate.php'],
        ];
    }
}
