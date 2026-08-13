<?php
declare(strict_types=1);

// ---- database ----
// db.local.php is written by install.php (untracked). Precedence: env > db.local.php > default.
$localDb = is_file(__DIR__ . '/db.local.php') ? (require __DIR__ . '/db.local.php') : [];
if (!is_array($localDb)) {
    $localDb = [];
}

define('DB_HOST', getenv('LQ_DB_HOST') ?: ($localDb['host'] ?? '127.0.0.1'));
define('DB_PORT', getenv('LQ_DB_PORT') ?: ($localDb['port'] ?? '3306'));
define('DB_NAME', getenv('LQ_DB_NAME') ?: ($localDb['name'] ?? 'linuxquest_lms'));
define('DB_USER', getenv('LQ_DB_USER') ?: ($localDb['user'] ?? 'root'));
define('DB_PASS', getenv('LQ_DB_PASS') ?: ($localDb['pass'] ?? ''));

// ---- base url ----
// ทุกลิงก์ในระบบสร้างจาก BASE_URL จึงย้ายไปวางในโฟลเดอร์ชื่ออะไรก็ได้ (/lmln, /web, / ฯลฯ)
// หาค่าเองจากตำแหน่งไฟล์ที่ถูกเรียก: ตัดส่วนที่อยู่ใต้รากโปรเจ็ค (เช่น /teacher/x.php)
// ออกจาก SCRIPT_NAME (เช่น /web/teacher/x.php) ที่เหลือคือ base (/web)
// ตั้งทับได้ด้วย env LQ_BASE_URL หรือคีย์ base ใน config/db.local.php
$base = getenv('LQ_BASE_URL');
if ($base === false || $base === '') {
    $base = $localDb['base'] ?? null;
}
if ($base === null) {
    $base = '';
    $appRoot = realpath(__DIR__ . '/..');
    $scriptFile = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($appRoot && $scriptFile && strpos($scriptFile, $appRoot) === 0) {
        $relative = str_replace('\\', '/', substr($scriptFile, strlen($appRoot)));
        if ($relative !== '' && substr($scriptName, -strlen($relative)) === $relative) {
            $base = substr($scriptName, 0, strlen($scriptName) - strlen($relative));
        }
    }
}
define('BASE_URL', rtrim(str_replace('\\', '/', $base), '/'));
unset($localDb, $base, $appRoot, $scriptFile, $scriptName, $relative);

/** ลิงก์ภายในระบบ: url('/dashboard.php') => /web/dashboard.php */
function url(string $path): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

// ---- app ----
define('APP_NAME', 'LinuxQuest LMS');
define('COURSE_CODE', 'LNX-101');
define('LESSON_COUNT', 9);
define('PASS_PCT_LESSON_QUIZ', 70); // 2/3
define('PASS_PCT_POSTTEST', 70);    // 7/10

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/', // แยก session ตามโฟลเดอร์ที่ติดตั้ง
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Bangkok');
