<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$db = Database::get();

$code = (string)($_GET['code'] ?? '');
$stmt = $db->prepare('SELECT * FROM games WHERE code = ?');
$stmt->execute([$code]);
$game = $stmt->fetch();
if (!$game) {
    header('Location: ' . url('/games.php'));
    exit;
}

// เกมจะเล่นได้ต่อเมื่อเริ่มเรียนแล้ว และผ่านบทเรียนที่เกมนี้ใช้ครบทุกบท
// (games.php เป็นที่เดียวที่อธิบายว่ายังขาดอะไร จึงเด้งกลับไปที่นั่น)
$userId = (int)$user['id'];
$gate = Progress::gameGate($userId, $game);
if (!$gate['freeplay'] && (!Progress::arcadeGate($userId)['unlocked'] || !$gate['unlocked'])) {
    header('Location: ' . url('/games.php?locked=') . urlencode($code));
    exit;
}

$drills = [];
if ($code === 'drill') {
    $drills = $db->query('SELECT prompt_th, hint, accepted_answers FROM drills')->fetchAll();
    $drills = array_map(fn($d) => ['p' => $d['prompt_th'], 'h' => $d['hint'], 'a' => json_decode($d['accepted_answers'], true)], $drills);
}

Layout::start($game['title_th'], $user, 'games');
?>
<div style="display:flex;flex-direction:column;height:calc(100vh - 62px);min-height:0">
  <div class="game-header">
    <a href="<?= BASE_URL ?>/games.php" style="font-size:11.5px;color:#6f837c">← ออกจากเกม</a>
    <div style="font-size:14.5px;font-weight:700;color:#eaf6f0"><?= htmlspecialchars($game['title_th']) ?></div>
    <div style="font-family:var(--mono);font-size:11.5px;color:#5f736c"><?= htmlspecialchars($game['title_en']) ?></div>
    <div class="spacer"></div>
    <div id="gameHud" style="display:flex"></div>
  </div>

  <?php if ($code === 'egg'): ?>
    <?php // เกมคลายเครียด วาดทั้งหมดบน canvas ไม่ใช้ Terminal จำลอง ?>
    <div class="egg-wrap">
      <div class="egg-stage">
        <canvas id="eggCanvas" width="720" height="520" aria-label="เกมโยนไข่"></canvas>

        <div class="egg-overlay" id="eggStart">
          <div class="egg-overlay-card">
            <div style="font-size:44px;line-height:1;margin-bottom:10px">🥚</div>
            <div style="font-size:20px;font-weight:700;color:#eaf6f0;margin-bottom:8px">โยนไข่ · Egg Toss</div>
            <p style="font-size:12.5px;line-height:1.9;color:#9bb0a8;margin:0 0 18px">
              ขยับตะกร้าไปรับไข่ที่แม่ไก่โยนลงมาให้ทัน · รับติดกันได้โบนัสคอมโบ<br>
              🥚 ไข่ธรรมดา +1 · 🥇 ไข่ทอง +5 · พลาดได้ 3 ครั้ง<br>
              <span style="color:#6f837c">เลื่อนด้วยเมาส์ นิ้ว หรือปุ่มลูกศร ← → · เว้นวรรค = หยุดชั่วคราว</span>
            </p>
            <button type="button" class="btn btn-primary" id="eggStartBtn">เริ่มเล่น →</button>
          </div>
        </div>

        <div class="egg-overlay" id="eggPause" hidden>
          <div class="egg-overlay-card">
            <div style="font-size:19px;font-weight:700;color:#eaf6f0;margin-bottom:14px">พักก่อน ⏸</div>
            <button type="button" class="btn btn-primary" id="eggResumeBtn">เล่นต่อ</button>
          </div>
        </div>

        <div class="egg-overlay" id="eggOver" hidden>
          <div class="egg-overlay-card">
            <div style="font-size:12px;color:#6f837c;letter-spacing:.08em;margin-bottom:6px">จบเกม · GAME OVER</div>
            <div style="font-family:var(--mono);font-size:52px;font-weight:700;color:var(--green);line-height:1" id="eggFinalScore">0</div>
            <div style="font-size:12.5px;color:#8ea59d;margin:6px 0 4px" id="eggFinalDetail"></div>
            <div style="font-size:13px;font-weight:600;color:#eaf6f0;margin-bottom:18px" id="eggFinalMsg"></div>
            <div style="display:flex;gap:11px;justify-content:center">
              <button type="button" class="btn btn-primary" id="eggAgainBtn">เล่นอีกครั้ง</button>
              <a class="btn btn-ghost" href="<?= BASE_URL ?>/games.php">กลับโซนเกม</a>
            </div>
          </div>
        </div>
      </div>
      <div class="egg-note">
        เกมนี้เล่นได้ทุกเมื่อ ไม่ต้องผ่านบทเรียน — พักสมองแล้วค่อยกลับไปเรียนต่อ · คะแนนสูงสุดถูกบันทึกในกระดานผู้นำเหมือนเกมอื่น
      </div>
    </div>

  <?php elseif ($code === 'drill'): ?>
    <div class="drill-wrap" id="drillWrap">
      <div class="drill-box" id="drillLive">
        <div class="bar-track" style="margin-bottom:34px"><div class="bar-fill" id="drillBar" style="width:100%"></div></div>
        <div style="font-size:12px;color:#6f837c;letter-spacing:.08em;margin-bottom:14px" id="drillNum">โจทย์ที่ 1 · พิมพ์คำสั่งให้ถูกต้อง</div>
        <div class="drill-prompt" id="drillPromptText"></div>
        <div style="font-family:var(--mono);font-size:13px;color:#7d8f89;margin-bottom:28px" id="drillHintText"></div>
        <div class="drill-inputrow">
          <span style="font-family:var(--mono);font-size:16px;color:var(--green)">$</span>
          <input class="drill-input" id="drillInput" autocomplete="off" spellcheck="false" placeholder="พิมพ์ที่นี่ แล้วกด Enter">
        </div>
        <div style="margin-top:16px;font-size:13px;min-height:20px;font-family:var(--mono)" id="drillFeedback"></div>
        <div class="drill-dots" id="drillDots"></div>
      </div>
      <div id="drillOver" style="display:none;text-align:center">
        <div style="font-family:var(--mono);font-size:60px;font-weight:700" id="drillFinalScore"></div>
        <div style="font-size:13px;color:#6f837c;margin-bottom:10px" id="drillFinalCorrect"></div>
        <div style="font-size:19px;font-weight:700;color:#eaf6f0;margin-bottom:22px" id="drillFinalMsg"></div>
        <div style="display:flex;gap:11px;justify-content:center">
          <a class="btn btn-primary" href="<?= BASE_URL ?>/game.php?code=drill">เล่นอีกครั้ง</a>
          <a class="btn btn-ghost" href="<?= BASE_URL ?>/games.php">กลับโซนเกม</a>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="game-shell">
      <div class="game-side">
        <div style="font-size:11.5px;font-weight:700;color:#8ea59d;letter-spacing:.08em;margin-bottom:10px">ภารกิจ · MISSION</div>
        <div style="font-size:13px;line-height:1.75;color:#a9bcb5;margin-bottom:20px"><?= htmlspecialchars($game['brief']) ?></div>
        <div id="gameObjectives" style="display:flex;flex-direction:column;gap:8px;margin-bottom:22px"></div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:9px">
          <span style="font-size:11.5px;font-weight:700;color:#8ea59d;letter-spacing:.08em">แผนผังตำแหน่ง · MAP</span>
          <span class="spacer"></span>
          <span style="font-family:var(--mono);font-size:10.5px;color:var(--cyan)" id="treePath">~</span>
        </div>
        <div class="tree-box" id="treeBox" style="margin-bottom:22px"></div>
        <div style="font-size:11.5px;font-weight:700;color:#8ea59d;letter-spacing:.08em;margin-bottom:9px">คำสั่งพิเศษ · TOOLS</div>
        <div id="gameTools"></div>
        <div id="winBox" class="win-box" style="display:none">
          <div style="font-size:14px;font-weight:700;color:#86efac;margin-bottom:5px">🏆 ภารกิจสำเร็จ!</div>
          <div style="font-size:12.5px;line-height:1.6;color:#a9d9bb" id="winMsg"></div>
          <a href="<?= BASE_URL ?>/games.php" style="margin-top:11px;display:block;text-align:center;padding:9px;border-radius:8px;background:var(--green);color:#04150b;font-size:12.5px;font-weight:700">กลับโซนเกม</a>
        </div>
      </div>
      <div class="game-term" id="gameTermWrap">
        <div class="term-lines" id="termLines" style="padding:18px 22px;font-size:13px"></div>
        <div class="term-inputrow" style="padding:0 22px 12px">
          <span class="term-prompt" id="termPrompt">~ $</span>
          <input class="term-input" id="termInput" autocomplete="off" spellcheck="false" style="font-size:13px">
        </div>
        <div class="term-hints" id="termHints"><span style="font-size:10.5px;color:#4c5f59">ทางลัด:</span></div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if ($code !== 'egg'): ?><script src="<?= BASE_URL ?>/public/js/terminal-engine.js"></script><?php endif; ?>
<script>
window.LQ_GAME = {
  base: <?= json_encode(BASE_URL) ?>,
  code: <?= json_encode($code) ?>,
  timeLimitSec: <?= (int)$game['time_limit_sec'] ?>,
  drills: <?= json_encode($drills, JSON_UNESCAPED_UNICODE) ?>,
  csrf: <?= json_encode(Csrf::token()) ?>
};
</script>
<?php // เกมโยนไข่ไม่ใช้ Terminal จำลอง จึงแยกสคริปต์ออกจาก game.js ?>
<script src="<?= BASE_URL ?>/public/js/<?= $code === 'egg' ? 'egg.js' : 'game.js' ?>"></script>
<?php
Layout::end();
