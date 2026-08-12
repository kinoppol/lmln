<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::get();

$games = $db->query('SELECT * FROM games ORDER BY id')->fetchAll();
$bestStmt = $db->prepare('SELECT MAX(score) FROM game_scores WHERE user_id = ? AND game_id = ?');

$diffClass = fn($d) => $d === 'ระดับ 3' ? 'd3' : ($d === 'ระดับ 2' ? 'd2' : 'd1');

Layout::start('โซนเกมฝึกทักษะ', $user, 'games');
?>
<div class="page">
  <div class="eyebrow">ARCADE · SKILL LAB</div>
  <h1 style="font-size:27px">โซนเกมฝึกทักษะ</h1>
  <p class="lead" style="max-width:640px">ทุกเกมใช้ Terminal จำลองตัวเดียวกับบทเรียน — คำสั่งที่เรียนมาใช้ได้จริงทั้งหมด มีคะแนน เวลา และเลเวลความยาก</p>
  <div class="game-grid">
    <?php foreach ($games as $g):
        $bestStmt->execute([$userId, $g['id']]);
        $best = $bestStmt->fetchColumn();
        $glyph = ['virus' => '☣', 'drill' => '⚡', 'escape' => '🔑', 'repair' => '🛠'][$g['code']] ?? '▶';
    ?>
      <a class="game-card" href="/lmln/game.php?code=<?= urlencode($g['code']) ?>">
        <div class="game-ghost"><?= $glyph ?></div>
        <div style="position:relative;display:flex;align-items:center;gap:8px">
          <span class="game-tag <?= $diffClass($g['difficulty']) ?>"><?= htmlspecialchars($g['difficulty']) ?></span>
        </div>
        <div class="game-th"><?= htmlspecialchars($g['title_th']) ?></div>
        <div class="game-en"><?= htmlspecialchars($g['title_en']) ?></div>
        <div class="game-desc"><?= htmlspecialchars($g['description']) ?></div>
        <div class="game-footer">
          <span style="font-size:11.5px;color:<?= $best !== false && $best !== null ? 'var(--amber)' : '#5f736c' ?>;font-family:var(--mono)"><?= $best !== false && $best !== null ? 'สถิติสูงสุด ' . (int)$best . ' คะแนน' : 'ยังไม่เคยเล่น' ?></span>
          <span style="font-size:12.5px;font-weight:700;color:var(--green)">เล่นเลย →</span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php
Layout::end();
