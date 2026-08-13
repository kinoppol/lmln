<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

$user = Auth::requireTeacher();
$db = Database::get();

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid($_POST['csrf_token'] ?? null);

    if (($_POST['action'] ?? '') === 'run_pending') {
        $result = Migrator::runPending($db);
    } elseif (($_POST['action'] ?? '') === 'run_one') {
        $version = (string)($_POST['version'] ?? '');
        $all = Migrator::available();
        $applied = Migrator::applied($db);
        if (!isset($all[$version])) {
            $result = ['ok' => false, 'done' => [], 'error' => 'ไม่พบ migration หมายเลข ' . $version];
        } elseif (isset($applied[$version])) {
            $result = ['ok' => false, 'done' => [], 'error' => 'migration หมายเลข ' . $version . ' ถูกใช้ไปแล้ว'];
        } else {
            try {
                Migrator::runOne($db, $all[$version]);
                $result = ['ok' => true, 'done' => [$version . ' · ' . $all[$version]['name']], 'error' => null];
            } catch (Throwable $e) {
                $result = ['ok' => false, 'done' => [], 'error' => $all[$version]['file'] . ' — ' . $e->getMessage()];
            }
        }
    }
}

$available = Migrator::available();
$applied = Migrator::applied($db);
$pending = array_diff_key($available, $applied);

Layout::start('ปรับปรุงโครงสร้างฐานข้อมูล', $user, 'migrations');
?>
<div class="page" style="max-width:960px">
  <div class="eyebrow" style="color:var(--cyan)">DATABASE MIGRATIONS · <?= COURSE_CODE ?></div>
  <h1 style="font-size:27px">ปรับปรุงโครงสร้างฐานข้อมูล</h1>
  <p class="lead" style="max-width:680px">
    ใช้อัปเดตโครงสร้างตารางให้ตรงกับเวอร์ชันล่าสุดของระบบ โดยไม่ต้องติดตั้งใหม่และไม่ลบข้อมูลผู้เรียน ·
    ฐานข้อมูลปัจจุบัน <code style="color:var(--green)"><?= htmlspecialchars(DB_NAME) ?></code>
  </p>

  <?php if ($result): ?>
    <?php if ($result['ok']): ?>
      <div class="alert alert-ok">
        ✓ ปรับปรุงสำเร็จ <?= count($result['done']) ?> รายการ<?= $result['done'] ? ': ' . htmlspecialchars(implode(' · ', $result['done'])) : '' ?>
      </div>
    <?php else: ?>
      <div class="alert alert-error">
        ✕ หยุดกลางคัน: <?= htmlspecialchars((string)$result['error']) ?>
        <?php if ($result['done']): ?>
          <div style="margin-top:6px;font-size:12px">รายการที่สำเร็จก่อนหน้า: <?= htmlspecialchars(implode(' · ', $result['done'])) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="stats-grid" style="margin-bottom:20px">
    <div class="card">
      <div style="font-size:11.5px;color:#7d8f89">ใช้ไปแล้ว</div>
      <div class="stat-value" style="color:var(--green)"><?= count($applied) ?></div>
    </div>
    <div class="card">
      <div style="font-size:11.5px;color:#7d8f89">รอปรับปรุง</div>
      <div class="stat-value" style="color:<?= $pending ? 'var(--amber)' : '#e8f5ee' ?>"><?= count($pending) ?></div>
    </div>
    <div class="card">
      <div style="font-size:11.5px;color:#7d8f89">ทั้งหมดในระบบ</div>
      <div class="stat-value" style="color:#e8f5ee"><?= count($available) ?></div>
    </div>
  </div>

  <?php if ($pending): ?>
    <div class="gate-panel" style="align-items:center">
      <div class="gate-icon">⚠</div>
      <div style="flex:1;min-width:0">
        <div class="gate-title">มี <?= count($pending) ?> รายการรอปรับปรุง</div>
        <p class="gate-body" style="margin-bottom:0">
          ควรสำรองฐานข้อมูลก่อนทุกครั้ง (phpMyAdmin → Export หรือ <code>mysqldump</code>) เพราะคำสั่งแก้โครงสร้างของ MariaDB
          ย้อนกลับอัตโนมัติไม่ได้ · ระบบจะรันเรียงตามลำดับและหยุดทันทีถ้ามีรายการใดผิดพลาด
        </p>
      </div>
      <form method="post" style="flex:none">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="run_pending">
        <button type="submit" class="btn btn-primary">ปรับปรุงทั้งหมด →</button>
      </form>
    </div>
  <?php else: ?>
    <div class="alert alert-ok" style="display:flex;align-items:center;gap:10px">
      ✓ โครงสร้างฐานข้อมูลเป็นเวอร์ชันล่าสุดแล้ว ไม่มีรายการค้าง
    </div>
  <?php endif; ?>

  <div class="card" style="padding:20px 22px;margin-top:20px">
    <div style="font-size:13px;font-weight:600;color:#e3efe9;margin-bottom:3px">รายการทั้งหมด</div>
    <div style="font-size:11.5px;color:#5f736c;margin-bottom:16px">เรียงตามลำดับการใช้งาน · กดที่ชื่อเพื่อดูคำสั่งที่จะรัน</div>

    <?php if (!$available): ?>
      <p style="font-size:12.5px;color:#5f736c">ยังไม่มีไฟล์ใน <code>database/migrations/</code></p>
    <?php endif; ?>

    <?php foreach ($available as $version => $m):
        $isApplied = isset($applied[$version]);
    ?>
      <div class="mig-row">
        <span class="mig-badge <?= $isApplied ? 'ok' : 'wait' ?>"><?= $isApplied ? '✓' : '●' ?></span>
        <div style="flex:1;min-width:0">
          <details>
            <summary class="mig-name"><?= htmlspecialchars($version) ?> · <?= htmlspecialchars($m['name']) ?></summary>
            <div class="mig-file"><?= htmlspecialchars('database/migrations/' . $m['file']) ?></div>
            <pre class="mig-code"><?= htmlspecialchars((string)file_get_contents($m['path'])) ?></pre>
          </details>
        </div>
        <div class="mig-status">
          <?php if ($isApplied): ?>
            <span style="color:var(--green);font-size:11.5px;font-family:var(--mono)">ใช้แล้ว</span>
            <span style="color:#5f736c;font-size:10.5px"><?= htmlspecialchars(date('j M Y H:i', strtotime((string)$applied[$version]['applied_at']))) ?></span>
          <?php else: ?>
            <form method="post">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="run_one">
              <input type="hidden" name="version" value="<?= htmlspecialchars($version) ?>">
              <button type="submit" class="btn btn-ghost btn-sm">ปรับปรุงเฉพาะรายการนี้</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card" style="padding:18px 22px;margin-top:16px">
    <div style="font-size:12.5px;font-weight:600;color:#b9cbc4;margin-bottom:8px">วิธีเพิ่มรายการใหม่</div>
    <div style="font-size:12.5px;line-height:1.9;color:#8ea59d">
      สร้างไฟล์ใน <code>database/migrations/</code> ตั้งชื่อ <code>NNNN_ชื่อรายการ.sql</code> หรือ <code>.php</code>
      (ไฟล์ <code>.php</code> ต้อง <code>return function (PDO $db) { ... };</code> ใช้เมื่อต้องเช็กเงื่อนไขก่อนแก้)
      แล้วแก้ <code>database/schema.sql</code> ให้ตรงกันด้วย เพื่อให้เครื่องที่ติดตั้งใหม่ได้โครงสร้างเดียวกัน ·
      เขียนให้รันซ้ำแล้วไม่พังเสมอ เช่นเช็ก <code>Migrator::hasColumn()</code> ก่อนเพิ่มคอลัมน์
    </div>
  </div>
</div>
<?php
Layout::end();
