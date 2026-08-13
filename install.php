<?php
declare(strict_types=1);

/**
 * LinuxQuest LMS — ตัวติดตั้งผ่านเว็บ (รองรับการติดตั้งซ้ำ)
 *
 * ทำงานทั้งหมดในไฟล์เดียว โดยไม่ require bootstrap.php เพราะต้องเขียน
 * config/db.local.php ให้เสร็จ "ก่อน" ที่ค่าคงที่ DB_* จะถูก define
 * (ค่าคงที่ใน PHP เปลี่ยนทีหลังไม่ได้)
 *
 * ขั้นตอน:
 *   1. ตรวจสภาพแวดล้อม (PHP, pdo_mysql, สิทธิ์เขียนไฟล์)
 *   2. ต่อ MySQL/MariaDB ด้วยค่าที่กรอก แล้วสร้างฐานข้อมูลถ้ายังไม่มี
 *   3. ถ้ามีตารางเดิมอยู่ = ติดตั้งซ้ำ ต้องติ๊กยืนยัน แล้วลบตารางเดิมทิ้งทั้งหมด
 *   4. รัน database/schema.sql
 *   5. เขียน config/db.local.php
 *   6. รัน database/seed.php (ใส่เนื้อหาบทเรียน/ข้อสอบ/เกม + บัญชีผู้สอน)
 *   7. เขียน config/installed.lock + config/install_key.php
 *
 * สิทธิ์การเข้าถึง (ดูบล็อก guard ด้านล่าง): เปิดจาก localhost ได้เสมอ,
 * เปิดจากที่ไหนก็ได้ถ้ายังไม่เคยติดตั้ง, หลังติดตั้งแล้วต้องล็อกอินเป็นผู้สอน
 * หรือมีกุญแจติดตั้ง · ใช้งานจริงควรลบไฟล์นี้ทิ้งหรือตั้ง LQ_DISABLE_INSTALLER=1
 */

// ตัวติดตั้งไม่ได้โหลด config.php ตอนแสดงฟอร์ม จึงหา base path ของตัวเองแยก
// (ไฟล์นี้อยู่ที่รากโปรเจ็ค base จึงเป็นโฟลเดอร์ของ SCRIPT_NAME ตรง ๆ)
$BASE = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
if ($BASE === '.' || $BASE === '/') {
    $BASE = '';
}

const ROOT = __DIR__;
const LOCK_FILE = ROOT . '/config/installed.lock';
const DB_CONFIG_FILE = ROOT . '/config/db.local.php';
const KEY_FILE = ROOT . '/config/install_key.php';
const SCHEMA_FILE = ROOT . '/database/schema.sql';
const SEED_FILE = ROOT . '/database/seed.php';

$lockInfo = is_file(LOCK_FILE) ? json_decode((string)file_get_contents(LOCK_FILE), true) : null;
$isReinstall = is_array($lockInfo);

// ---------------------------------------------------------------- helpers

/** ค่าเริ่มต้นในฟอร์ม: ใช้ค่าที่เคยติดตั้งไว้ ถ้ามี */
function savedDbConfig(): array
{
    if (!is_file(DB_CONFIG_FILE)) {
        return [];
    }
    $cfg = require DB_CONFIG_FILE;
    return is_array($cfg) ? $cfg : [];
}

/** กุญแจติดตั้งที่ใช้ยืนยันตัวตนจากเครื่องอื่น (env มาก่อนไฟล์) */
function installKey(): ?string
{
    $env = getenv('LQ_INSTALL_KEY');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    if (is_file(KEY_FILE)) {
        $key = require KEY_FILE;
        return is_string($key) && $key !== '' ? $key : null;
    }
    return null;
}

/** ระบบติดตั้งไปแล้วหรือยัง — ดูทั้งไฟล์ล็อกและตารางจริงในฐานข้อมูล */
function alreadyInstalled(array $saved): bool
{
    if (is_file(LOCK_FILE)) {
        return true;
    }
    $pdo = connectSaved($saved);
    if (!$pdo) {
        return false;
    }
    try {
        return (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** ต่อฐานข้อมูลด้วยค่าที่เคยบันทึกไว้ (ไม่แตะค่าคงที่ DB_* เพราะฟอร์มอาจเปลี่ยนค่าใหม่) */
function connectSaved(array $saved): ?PDO
{
    if (!$saved) {
        return null;
    }
    try {
        return new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $saved['host'] ?? '', $saved['port'] ?? '3306', $saved['name'] ?? ''),
            (string)($saved['user'] ?? ''),
            (string)($saved['pass'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        return null;
    }
}

/** ผู้ที่ล็อกอินอยู่เป็นผู้สอนหรือไม่ — ใช้ session เดียวกับตัวเว็บ (cookie path = BASE) */
function loggedInTeacher(array $saved, string $base): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime' => 0, 'path' => $base . '/', 'httponly' => true, 'samesite' => 'Lax']);
        @session_start();
    }
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    $pdo = connectSaved($saved);
    if (!$pdo) {
        return false;
    }
    try {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchColumn() === 'teacher';
    } catch (Throwable $e) {
        return false;
    }
}

function writeInstallKey(): string
{
    $key = bin2hex(random_bytes(16));
    @file_put_contents(
        KEY_FILE,
        "<?php\n// กุญแจสำหรับเรียก install.php จากเครื่องอื่น สร้างเมื่อ " . date('Y-m-d H:i:s') . "\n"
        . "// ลบไฟล์นี้ทิ้งได้ถ้าไม่ต้องการให้ติดตั้งซ้ำจากระยะไกล\ndeclare(strict_types=1);\n\nreturn '" . $key . "';\n"
    );
    return $key;
}

/** ตัดคอมเมนต์ -- ออก แล้วแยก statement ตาม ; (schema.sql ไม่มี DELIMITER/procedure) */
function splitSql(string $sql): array
{
    $lines = [];
    foreach (preg_split('/\R/', $sql) ?: [] as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0) {
            continue;
        }
        // ข้าม CREATE DATABASE / USE ในไฟล์ เพราะตัวติดตั้งจัดการชื่อฐานข้อมูลเอง
        if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $trimmed)) {
            continue;
        }
        $lines[] = $line;
    }
    $out = [];
    foreach (explode(';', implode("\n", $lines)) as $stmt) {
        if (trim($stmt) !== '') {
            $out[] = $stmt;
        }
    }
    return $out;
}

function dropAllTables(PDO $pdo): int
{
    $tables = $pdo->query(
        'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()'
    )->fetchAll(PDO::FETCH_COLUMN);

    if (!$tables) {
        return 0;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $t) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string)$t) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    return count($tables);
}

function writeDbConfig(array $cfg): void
{
    $php = "<?php\n"
        . "// สร้างโดย install.php เมื่อ " . date('Y-m-d H:i:s') . " — แก้ได้ หรือใช้ env LQ_DB_* แทน\n"
        . "declare(strict_types=1);\n\n"
        . "return " . var_export([
            'host' => $cfg['host'],
            'port' => $cfg['port'],
            'name' => $cfg['name'],
            'user' => $cfg['user'],
            'pass' => $cfg['pass'],
        ], true) . ";\n";

    if (file_put_contents(DB_CONFIG_FILE, $php) === false) {
        throw new RuntimeException('เขียนไฟล์ config/db.local.php ไม่สำเร็จ');
    }
}

// ---------------------------------------------------------------- guard
// ตัวติดตั้งลบฐานข้อมูลทั้งชุดได้ จึงต้องผ่านอย่างน้อยหนึ่งเงื่อนไขก่อน:
//   1. เรียกจากเครื่องเซิร์ฟเวอร์เอง (localhost)
//   2. ยังไม่เคยติดตั้ง — เปิดให้ติดตั้งครั้งแรกจากที่ไหนก็ได้ (ติดตั้งเสร็จจะปิดทันที)
//   3. ล็อกอินเป็นผู้สอนอยู่แล้ว
//   4. กรอกกุญแจติดตั้งตรงกับ config/install_key.php หรือ env LQ_INSTALL_KEY
// ปิดตายทั้งหมดได้ด้วย env LQ_DISABLE_INSTALLER=1
$savedForGuard = savedDbConfig();
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remote, ['127.0.0.1', '::1', 'localhost', ''], true);

if (getenv('LQ_DISABLE_INSTALLER') === '1') {
    http_response_code(403);
    exit('ตัวติดตั้งถูกปิดไว้ (LQ_DISABLE_INSTALLER=1)');
}

$installedBefore = alreadyInstalled($savedForGuard);
$key = installKey();
$givenKey = (string)($_POST['install_key'] ?? $_GET['key'] ?? '');
$keyOk = $key !== null && $givenKey !== '' && hash_equals($key, $givenKey);
$teacherOk = $installedBefore && loggedInTeacher($savedForGuard, $BASE);

$authorized = $isLocal || !$installedBefore || $keyOk || $teacherOk;
$badKey = !$authorized && $givenKey !== '';

if (!$authorized) {
    // ยังไม่ผ่าน — แสดงหน้าขอกุญแจ ไม่แตะฐานข้อมูลใด ๆ
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ยืนยันสิทธิ์ติดตั้ง · LinuxQuest LMS</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/css/style.css">
</head>
<body>
<div class="app-shell"><main class="app-main auth-main">
  <div class="auth-card">
    <div class="eyebrow">LINUXQUEST · INSTALLER</div>
    <h1>ยืนยันสิทธิ์ก่อนติดตั้ง</h1>
    <p class="lead">ระบบนี้ติดตั้งไว้แล้ว การเรียกตัวติดตั้งจากเครื่องอื่นจึงต้องยืนยันตัวตนก่อน เพราะการติดตั้งซ้ำจะลบข้อมูลทั้งหมด</p>
    <?php if ($badKey): ?><div class="alert alert-error">กุญแจติดตั้งไม่ถูกต้อง</div><?php endif; ?>
    <?php if ($key === null): ?>
      <div class="alert alert-error" style="line-height:1.9">
        ยังไม่มีกุญแจติดตั้งในระบบ · เลือกทำอย่างใดอย่างหนึ่ง:<br>
        · <a href="<?= $BASE ?>/login.php" style="text-decoration:underline">เข้าสู่ระบบด้วยบัญชีผู้สอน</a> แล้วเปิดหน้านี้ใหม่<br>
        · สร้างไฟล์ <code>config/install_key.php</code> ที่มีเนื้อหา <code>&lt;?php return 'รหัสลับของคุณ';</code><br>
        · หรือตั้ง environment variable <code>LQ_INSTALL_KEY</code>
      </div>
    <?php else: ?>
      <form method="get" action="<?= $BASE ?>/install.php">
        <div class="field">
          <label for="key">กุญแจติดตั้ง</label>
          <input type="password" id="key" name="key" required autocomplete="off" placeholder="ค่าจาก config/install_key.php">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">ยืนยัน →</button>
      </form>
    <?php endif; ?>
    <div class="auth-switch"><a href="<?= $BASE ?>/">← กลับหน้าแรก</a></div>
  </div>
</main></div>
</body>
</html>
    <?php
    exit;
}

// ---------------------------------------------------------------- env checks
$checks = [
    ['PHP 8.0 ขึ้นไป', PHP_VERSION_ID >= 80000, 'พบ PHP ' . PHP_VERSION],
    ['ส่วนขยาย pdo_mysql', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'พร้อมใช้งาน' : 'เปิดใน php.ini ก่อน'],
    ['ส่วนขยาย mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'พร้อมใช้งาน' : 'เปิดใน php.ini ก่อน'],
    ['เขียนโฟลเดอร์ config/ ได้', is_writable(ROOT . '/config'), is_writable(ROOT . '/config') ? 'เขียนได้' : 'ต้องให้สิทธิ์เขียน'],
    ['พบ database/schema.sql', is_readable(SCHEMA_FILE), is_readable(SCHEMA_FILE) ? 'อ่านได้' : 'ไฟล์หาย'],
    ['พบ database/seed.php', is_readable(SEED_FILE), is_readable(SEED_FILE) ? 'อ่านได้' : 'ไฟล์หาย'],
];
$envOk = true;
foreach ($checks as $c) {
    $envOk = $envOk && $c[1];
}

// ---------------------------------------------------------------- install
$errors = [];
$done = null;
$saved = savedDbConfig();

$form = [
    'host' => (string)($_POST['db_host'] ?? $saved['host'] ?? '127.0.0.1'),
    'port' => (string)($_POST['db_port'] ?? $saved['port'] ?? '3306'),
    'name' => (string)($_POST['db_name'] ?? $saved['name'] ?? 'linuxquest_lms'),
    'user' => (string)($_POST['db_user'] ?? $saved['user'] ?? 'root'),
    'pass' => (string)($_POST['db_pass'] ?? $saved['pass'] ?? ''),
];
$confirmWipe = !empty($_POST['confirm_wipe']);

// บัญชีผู้ดูแลระบบ (role=teacher — เข้าแดชบอร์ดผู้สอนได้)
$admin = [
    'name' => trim((string)($_POST['admin_name'] ?? $lockInfo['admin_name'] ?? '')),
    'email' => trim((string)($_POST['admin_email'] ?? $lockInfo['admin_email'] ?? '')),
    'pass' => (string)($_POST['admin_pass'] ?? ''),
    'pass2' => (string)($_POST['admin_pass2'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log = [];
    try {
        if (!$envOk) {
            throw new RuntimeException('สภาพแวดล้อมยังไม่พร้อม กรุณาแก้รายการที่ขึ้นสีแดงก่อน');
        }
        if ($form['name'] === '' || !preg_match('/^[A-Za-z0-9_]+$/', $form['name'])) {
            throw new RuntimeException('ชื่อฐานข้อมูลต้องเป็นตัวอักษร ตัวเลข หรือ _ เท่านั้น');
        }
        if ($form['user'] === '') {
            throw new RuntimeException('กรุณากรอกชื่อผู้ใช้ฐานข้อมูล');
        }

        // ตรวจข้อมูลผู้ดูแลระบบให้ครบก่อนแตะฐานข้อมูล จะได้ไม่ลบของเดิมทิ้งแล้วไปพังตอนท้าย
        if ($admin['name'] === '') {
            throw new RuntimeException('กรุณากรอกชื่อ-นามสกุลของผู้ดูแลระบบ');
        }
        if (!filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('อีเมลผู้ดูแลระบบไม่ถูกต้อง');
        }
        if (mb_strlen($admin['pass']) < 8) {
            throw new RuntimeException('รหัสผ่านผู้ดูแลระบบต้องยาวอย่างน้อย 8 ตัวอักษร');
        }
        if ($admin['pass'] !== $admin['pass2']) {
            throw new RuntimeException('รหัสผ่านทั้งสองช่องไม่ตรงกัน');
        }

        // 1) ต่อเซิร์ฟเวอร์ (ยังไม่เลือกฐานข้อมูล)
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $form['host'], $form['port']);
        $pdo = new PDO($dsn, $form['user'], $form['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $log[] = 'เชื่อมต่อเซิร์ฟเวอร์ฐานข้อมูลสำเร็จ (' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . ')';

        // 2) สร้างฐานข้อมูลถ้ายังไม่มี แล้วเข้าใช้งาน
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $form['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . $form['name'] . '`');

        // 3) ตรวจว่ามีของเดิมอยู่ไหม — ถ้ามี ต้องยืนยันก่อนลบ
        $existing = (int)$pdo->query(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
        )->fetchColumn();

        if ($existing > 0 && !$confirmWipe) {
            throw new RuntimeException(
                'ฐานข้อมูล "' . $form['name'] . '" มีตารางอยู่แล้ว ' . $existing . ' ตาราง — '
                . 'การติดตั้งซ้ำจะลบข้อมูลทั้งหมด รวมถึงบัญชีผู้เรียนและความก้าวหน้า '
                . 'กรุณาติ๊กยืนยันด้านล่างถ้าต้องการติดตั้งใหม่ทับของเดิม'
            );
        }
        if ($existing > 0) {
            $dropped = dropAllTables($pdo);
            $log[] = 'ลบตารางเดิมทิ้งแล้ว ' . $dropped . ' ตาราง';
        }

        // 4) สร้างโครงสร้างตารางจาก schema.sql
        $statements = splitSql((string)file_get_contents(SCHEMA_FILE));
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
        $log[] = 'สร้างตารางจาก database/schema.sql แล้ว (' . count($statements) . ' คำสั่ง)';

        // 5) บันทึกค่าเชื่อมต่อ ก่อนเรียก seed (seed ใช้ค่าคงที่จาก config.php)
        writeDbConfig($form);
        $log[] = 'บันทึก config/db.local.php แล้ว';

        // 6) ใส่เนื้อหาเริ่มต้น — seed.php จะ TRUNCATE ทุกตารางก่อนเสมอ จึงรันซ้ำได้
        ob_start();
        require SEED_FILE;
        $seedOut = trim((string)ob_get_clean());
        $log[] = 'ใส่เนื้อหาเริ่มต้นด้วย database/seed.php แล้ว';

        // 7) แทนบัญชีผู้สอนตัวอย่างของ seed ด้วยผู้ดูแลระบบที่กรอกในฟอร์ม
        //    (รหัสผ่านตัวอย่างเป็นค่าคงที่ในโค้ด จึงต้องไม่เหลือค้างไว้ในระบบจริง)
        $db = Database::get();
        $db->prepare('DELETE FROM users WHERE role = ?')->execute(['teacher']);
        $db->prepare('INSERT INTO users (email, password_hash, full_name, role) VALUES (?,?,?,?)')
            ->execute([$admin['email'], password_hash($admin['pass'], PASSWORD_DEFAULT), $admin['name'], 'teacher']);

        // ผู้ดูแลก็เข้าเรียนได้ จึงต้องมีแถวความคืบหน้าเหมือนผู้เรียนทั่วไป ไม่งั้นทุกบทจะขึ้นว่าล็อก
        $db->prepare(
            "INSERT IGNORE INTO user_lesson_progress (user_id, lesson_id, status)
             SELECT ?, l.id, IF(l.position = 1, 'unlocked', 'locked') FROM lessons l"
        )->execute([(int)$db->lastInsertId()]);
        $log[] = 'สร้างบัญชีผู้ดูแลระบบ ' . $admin['email'] . ' แล้ว';

        // 8) schema.sql เป็นโครงสร้างล่าสุดอยู่แล้ว จึงบันทึกว่า migration ทุกตัวถูกใช้แล้ว
        //    ไม่งั้นหน้า migrations จะเห็นเป็นค้างและรันซ้ำโดยไม่จำเป็น
        require_once ROOT . '/src/Migrator.php';
        $log[] = 'บันทึกสถานะ migration แล้ว ' . Migrator::markAllApplied($db) . ' รายการ';

        // 9) ล็อกไฟล์บอกสถานะการติดตั้ง (ไม่เก็บรหัสผ่าน)
        file_put_contents(LOCK_FILE, json_encode([
            'installed_at' => date('c'),
            'db_name' => $form['name'],
            'admin_name' => $admin['name'],
            'admin_email' => $admin['email'],
            'php' => PHP_VERSION,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // บรรทัดบัญชีตัวอย่างของ seed ใช้ไม่ได้แล้ว (ถูกลบไปในขั้นที่ 7) จึงไม่แสดง
        $seedOut = trim(preg_replace('/^.*teacher account.*$\R?/mi', '', $seedOut) ?? $seedOut);

        // 10) กุญแจสำหรับเรียกตัวติดตั้งจากเครื่องอื่นในครั้งถัดไป (ถ้ายังไม่มี)
        $newKey = installKey() === null ? writeInstallKey() : null;

        $done = ['log' => $log, 'seed' => $seedOut, 'admin' => $admin['email'], 'key' => $newKey];
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        if ($log) {
            $errors[] = 'ทำไปแล้ว: ' . implode(' · ', $log);
        }
    }
}

// เตือนเรื่องติดตั้งซ้ำ ใช้ผลตรวจชุดเดียวกับที่ guard ด้านบนใช้แล้ว
$hasTablesNow = $_SERVER['REQUEST_METHOD'] !== 'POST' && $installedBefore;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ติดตั้งระบบ · LinuxQuest LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $BASE ?>/public/css/style.css">
<style>
  .install-wrap{max-width:720px;margin:0 auto;padding:46px 24px 70px}
  .check-row{display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border);font-size:12.5px}
  .check-row .name{flex:1;color:#c3d4cd}
  .check-row .note{font-family:var(--mono);font-size:11px;color:var(--mut2)}
  .check-mark{width:18px;font-family:var(--mono);font-weight:700}
  .install-log{font-family:var(--mono);font-size:11.5px;color:#9bb0a8;background:var(--panel2);border:1px solid var(--border);border-radius:10px;padding:14px 16px;white-space:pre-wrap;line-height:1.7}
  .field-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<div class="install-wrap">

  <div class="eyebrow" style="color:var(--green)">LINUXQUEST · LMS · INSTALLER</div>
  <h1 style="font-size:27px;margin:6px 0 4px">ติดตั้งระบบ</h1>
  <p class="lead" style="margin-bottom:26px">
    สร้างฐานข้อมูล ตาราง และเนื้อหาเริ่มต้นทั้งหมดในขั้นตอนเดียว · รันซ้ำได้ทุกเมื่อเพื่อรีเซ็ตระบบกลับสู่ค่าเริ่มต้น
  </p>

  <?php if ($done): ?>
    <div class="alert" style="border:1px solid rgba(74,222,128,.3);background:rgba(74,222,128,.07);color:var(--green-soft);padding:14px 16px;border-radius:10px;margin-bottom:20px">
      ✓ ติดตั้งเสร็จเรียบร้อย
    </div>
    <div class="install-log"><?php
      foreach ($done['log'] as $l) {
          echo '· ' . htmlspecialchars($l) . "\n";
      }
      if ($done['seed'] !== '') {
          echo "\n" . htmlspecialchars($done['seed']);
      }
    ?></div>
    <div style="margin-top:18px;padding:14px 16px;border-radius:10px;border:1px solid var(--border);background:var(--panel2);font-size:12.5px;color:#b9cbc4">
      เข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบ: <code style="color:var(--green)"><?= htmlspecialchars($done['admin']) ?></code> พร้อมรหัสผ่านที่เพิ่งตั้งไว้
    </div>
    <?php if (!empty($done['key'])): ?>
      <div style="margin-top:14px;padding:14px 16px;border-radius:10px;border:1px solid rgba(251,191,36,.3);background:rgba(251,191,36,.06);font-size:12.5px;color:#fcd34d;line-height:1.9">
        กุญแจติดตั้ง (แสดงครั้งเดียว — เก็บไว้ให้ดี):
        <div style="font-family:var(--mono);font-size:14px;color:#fde68a;margin:6px 0;word-break:break-all"><?= htmlspecialchars($done['key']) ?></div>
        ต้องใช้เมื่อจะเรียก <code>install.php</code> จากเครื่องอื่นอีกครั้ง (หรือเข้าสู่ระบบด้วยบัญชีผู้สอนก็ได้) ·
        เก็บอยู่ในไฟล์ <code>config/install_key.php</code> ลบไฟล์นั้นทิ้งได้ถ้าไม่ต้องการให้ติดตั้งซ้ำจากระยะไกล
      </div>
    <?php endif; ?>
    <div class="alert alert-error" style="margin-top:14px;padding:14px 16px;border-radius:10px;border:1px solid rgba(248,113,113,.3);background:rgba(248,113,113,.07);color:#fca5a5;font-size:12.5px;line-height:1.9">
      เพื่อความปลอดภัย เมื่อใช้งานจริงบนอินเทอร์เน็ตควรลบไฟล์ <code>install.php</code> ทิ้งหลังติดตั้งเสร็จ
      หรือปิดถาวรด้วย environment variable <code>LQ_DISABLE_INSTALLER=1</code>
    </div>
    <div style="display:flex;gap:11px;margin-top:22px">
      <a class="btn btn-primary" href="<?= $BASE ?>/login.php">ไปหน้าเข้าสู่ระบบ →</a>
      <a class="btn btn-ghost" href="<?= $BASE ?>/install.php">ติดตั้งซ้ำอีกครั้ง</a>
    </div>

  <?php else: ?>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error" style="padding:13px 16px;border-radius:10px;border:1px solid rgba(248,113,113,.3);background:rgba(248,113,113,.07);color:#fca5a5;font-size:12.5px;margin-bottom:14px"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if ($isReinstall || $hasTablesNow): ?>
      <div class="alert" style="padding:13px 16px;border-radius:10px;border:1px solid rgba(251,191,36,.3);background:rgba(251,191,36,.07);color:#fcd34d;font-size:12.5px;margin-bottom:20px">
        ⚠ ระบบนี้เคยติดตั้งไว้แล้ว<?= $isReinstall && !empty($lockInfo['installed_at']) ? ' เมื่อ ' . htmlspecialchars(date('j M Y H:i', strtotime((string)$lockInfo['installed_at']))) . ' น.' : '' ?>
        — การติดตั้งซ้ำจะ<strong>ลบข้อมูลเดิมทั้งหมด</strong> ทั้งบัญชีผู้เรียน ความก้าวหน้า คะแนนสอบ และใบประกาศ
      </div>
    <?php endif; ?>

    <div class="card" style="padding:20px 22px;margin-bottom:20px">
      <div style="font-size:13px;font-weight:600;color:#e3efe9;margin-bottom:12px">ตรวจสภาพแวดล้อม</div>
      <?php foreach ($checks as $c): ?>
        <div class="check-row">
          <span class="check-mark" style="color:<?= $c[1] ? 'var(--green)' : 'var(--red)' ?>"><?= $c[1] ? '✓' : '✕' ?></span>
          <span class="name"><?= htmlspecialchars($c[0]) ?></span>
          <span class="note"><?= htmlspecialchars($c[2]) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="post" action="<?= $BASE ?>/install.php" class="card" style="padding:20px 22px">
      <?php // ส่งกุญแจต่อไปกับ POST ด้วย ไม่งั้นการยืนยันสิทธิ์จะหลุดตอนกดติดตั้ง ?>
      <input type="hidden" name="install_key" value="<?= htmlspecialchars($givenKey) ?>">
      <div style="font-size:13px;font-weight:600;color:#e3efe9;margin-bottom:3px">การเชื่อมต่อฐานข้อมูล</div>
      <div style="font-size:11.5px;color:#5f736c;margin-bottom:16px">ค่าเริ่มต้นของ XAMPP คือ root โดยไม่มีรหัสผ่าน</div>

      <div class="field-grid">
        <div class="field">
          <label for="db_host">โฮสต์</label>
          <input id="db_host" name="db_host" required value="<?= htmlspecialchars($form['host']) ?>">
        </div>
        <div class="field">
          <label for="db_port">พอร์ต</label>
          <input id="db_port" name="db_port" required value="<?= htmlspecialchars($form['port']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="db_name">ชื่อฐานข้อมูล</label>
        <input id="db_name" name="db_name" required value="<?= htmlspecialchars($form['name']) ?>">
      </div>
      <div class="field-grid">
        <div class="field">
          <label for="db_user">ผู้ใช้</label>
          <input id="db_user" name="db_user" required value="<?= htmlspecialchars($form['user']) ?>">
        </div>
        <div class="field">
          <label for="db_pass">รหัสผ่าน</label>
          <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($form['pass']) ?>">
        </div>
      </div>

      <div style="height:1px;background:var(--border);margin:20px 0"></div>

      <div style="font-size:13px;font-weight:600;color:#e3efe9;margin-bottom:3px">บัญชีผู้ดูแลระบบ</div>
      <div style="font-size:11.5px;color:#5f736c;margin-bottom:16px">
        บัญชีนี้เป็นสิทธิ์ผู้สอน เข้าดูแดชบอร์ดผลการเรียนของผู้เรียนทั้งหมดได้ ·
        บัญชีผู้สอนตัวอย่างของชุดข้อมูลเริ่มต้นจะถูกลบทิ้งและแทนที่ด้วยบัญชีนี้
      </div>

      <div class="field">
        <label for="admin_name">ชื่อ-นามสกุล</label>
        <input id="admin_name" name="admin_name" required value="<?= htmlspecialchars($admin['name']) ?>" placeholder="เช่น อ. ปิยะพงษ์ ศรีวัฒนา">
      </div>
      <div class="field">
        <label for="admin_email">อีเมล (ใช้เข้าสู่ระบบ)</label>
        <input type="email" id="admin_email" name="admin_email" required value="<?= htmlspecialchars($admin['email']) ?>" placeholder="admin@example.ac.th">
      </div>
      <div class="field-grid">
        <div class="field">
          <label for="admin_pass">รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label>
          <input type="password" id="admin_pass" name="admin_pass" required minlength="8" autocomplete="new-password">
        </div>
        <div class="field">
          <label for="admin_pass2">ยืนยันรหัสผ่าน</label>
          <input type="password" id="admin_pass2" name="admin_pass2" required minlength="8" autocomplete="new-password">
        </div>
      </div>

      <div style="height:1px;background:var(--border);margin:20px 0"></div>

      <label style="display:flex;align-items:flex-start;gap:10px;margin:6px 0 18px;font-size:12.5px;color:#b9cbc4;cursor:pointer">
        <input type="checkbox" name="confirm_wipe" value="1" <?= $confirmWipe ? 'checked' : '' ?> style="margin-top:3px">
        <span>ยืนยันติดตั้งทับของเดิม — ลบทุกตารางในฐานข้อมูลนี้แล้วสร้างใหม่พร้อมเนื้อหาเริ่มต้น (จำเป็นเฉพาะกรณีที่มีข้อมูลเดิมอยู่)</span>
      </label>

      <button type="submit" class="btn btn-primary" style="width:100%" <?= $envOk ? '' : 'disabled' ?>>
        เริ่มติดตั้ง →
      </button>
    </form>

  <?php endif; ?>
</div>
</main>
</div>
</body>
</html>
