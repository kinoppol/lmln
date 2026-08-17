<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::get();

$games = $db->query('SELECT * FROM games ORDER BY id')->fetchAll();
$bestStmt = $db->prepare('SELECT MAX(score) FROM game_scores WHERE user_id = ? AND game_id = ?');

$arcade = Progress::arcadeGate($userId);

// ถูกเด้งกลับมาจาก game.php เพราะเข้าเกมที่ยังล็อกอยู่
$blockedCode = (string)($_GET['locked'] ?? '');

$diffClass = fn($d) => $d === 'ระดับ 3' ? 'd3' : ($d === 'ระดับ 2' ? 'd2' : 'd1');

Layout::start('โซนเกมฝึกทักษะ', $user, 'games');
?>
<div class="page">
  <div class="eyebrow">ARCADE · SKILL LAB</div>
  <h1 style="font-size:27px">โซนเกมฝึกทักษะ</h1>
  <p class="lead" style="max-width:640px">ทุกเกมใช้ Terminal จำลองตัวเดียวกับบทเรียน — คำสั่งที่เรียนมาใช้ได้จริงทั้งหมด มีคะแนน เวลา และเลเวลความยาก</p>

  <?php if ($blockedCode): ?>
    <div class="gate-note">
      ยังเข้าเล่นเกมนั้นไม่ได้ เพราะเรียนบทเรียนที่เกมใช้ยังไม่ครบ — ดูรายละเอียดที่การ์ดเกมด้านล่าง
    </div>
  <?php endif; ?>

  <?php if (!$arcade['unlocked']): ?>
    <div class="gate-panel">
      <div class="gate-icon">🔒</div>
      <div style="flex:1;min-width:0">
        <div class="gate-title">โซนเกมยังไม่เปิด</div>
        <p class="gate-body">
          โซนเกมเป็นด่านฝึกทักษะจากคำสั่งที่เรียนมาแล้ว จึงต้องเริ่มเรียนในบทเรียนก่อน ตอนนี้ยังขาด:
        </p>
        <ul class="gate-list">
          <?php foreach ($arcade['missing'] as $m): ?>
            <li><?= htmlspecialchars($m['text']) ?></li>
          <?php endforeach; ?>
        </ul>
        <div style="display:flex;gap:11px;flex-wrap:wrap;margin-top:16px">
          <?php foreach ($arcade['missing'] as $i => $m): ?>
            <a class="btn <?= $i === 0 ? 'btn-primary' : 'btn-ghost' ?>" href="<?= $m['href'] ?>"><?= htmlspecialchars($m['cta']) ?> →</a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="game-grid">
    <?php foreach ($games as $g):
        $bestStmt->execute([$userId, $g['id']]);
        $best = $bestStmt->fetchColumn();
        $glyph = ['virus' => '☣', 'drill' => '⚡', 'escape' => '🔑', 'repair' => '🛠', 'egg' => '🥚'][$g['code']] ?? '▶';

        $gate = Progress::gameGate($userId, $g);
        // เกมคลายเครียด (ไม่ผูกบทเรียน) ข้ามประตูโซนเกมไปเลย
        $open = $gate['freeplay'] || ($arcade['unlocked'] && $gate['unlocked']);
        $justBlocked = $blockedCode !== '' && $blockedCode === $g['code'];
    ?>
      <?php if ($open): ?>
        <a class="game-card" href="<?= BASE_URL ?>/game.php?code=<?= urlencode($g['code']) ?>">
      <?php else: ?>
        <div class="game-card locked <?= $justBlocked ? 'blocked' : '' ?>">
      <?php endif; ?>

        <div class="game-ghost"><?= $open ? $glyph : '🔒' ?></div>
        <div style="position:relative;display:flex;align-items:center;gap:8px">
          <span class="game-tag <?= $diffClass($g['difficulty']) ?>"><?= htmlspecialchars($g['difficulty']) ?></span>
          <?php if ($gate['freeplay']): ?><span class="game-tag free">เล่นได้เลย</span><?php endif; ?>
          <?php if (!$open): ?><span class="game-tag lock">ล็อกอยู่ · <?= htmlspecialchars($gate['doneOf']) ?> บท</span><?php endif; ?>
        </div>
        <div class="game-th"><?= htmlspecialchars($g['title_th']) ?></div>
        <div class="game-en"><?= htmlspecialchars($g['title_en']) ?></div>
        <div class="game-desc"><?= htmlspecialchars($g['description']) ?></div>

        <?php if (!$open): ?>
          <div class="game-req">
            <div class="game-req-head">ต้องผ่านบทเรียนเหล่านี้ก่อน</div>
            <?php if (!$arcade['unlocked']): ?>
              <?php foreach ($arcade['missing'] as $m): ?>
                <div class="game-req-row"><span class="n">!</span><span><?= htmlspecialchars($m['text']) ?></span></div>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php foreach ($gate['missing'] as $m): ?>
              <div class="game-req-row">
                <span class="n">บทที่ <?= (int)$m['position'] ?></span>
                <span><?= htmlspecialchars($m['title_th']) ?> <span style="color:#4c5f59">· <?= htmlspecialchars($m['commands_summary']) ?></span></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="game-footer">
            <span style="font-size:11.5px;color:#5f736c;font-family:var(--mono)">ยังเหลืออีก <?= count($gate['missing']) ?> บท</span>
            <a style="font-size:12.5px;font-weight:700;color:var(--amber)" href="<?= $gate['missing'] ? url('/lesson.php?id=') . (int)$gate['missing'][0]['id'] : url('/course.php') ?>">ไปเรียนต่อ →</a>
          </div>
        <?php else: ?>
          <div class="game-footer">
            <span style="font-size:11.5px;color:<?= $best !== false && $best !== null ? 'var(--amber)' : '#5f736c' ?>;font-family:var(--mono)"><?= $best !== false && $best !== null ? 'สถิติสูงสุด ' . (int)$best . ' คะแนน' : 'ยังไม่เคยเล่น' ?></span>
            <span style="font-size:12.5px;font-weight:700;color:var(--green)">เล่นเลย →</span>
          </div>
        <?php endif; ?>

      <?= $open ? '</a>' : '</div>' ?>
    <?php endforeach; ?>
  </div>
</div>
<?php
Layout::end();
