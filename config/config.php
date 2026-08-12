<?php
declare(strict_types=1);

// ---- database ----
define('DB_HOST', getenv('LQ_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('LQ_DB_PORT') ?: '3306');
define('DB_NAME', getenv('LQ_DB_NAME') ?: 'linuxquest_lms');
define('DB_USER', getenv('LQ_DB_USER') ?: 'root');
define('DB_PASS', getenv('LQ_DB_PASS') ?: '');

// ---- app ----
define('APP_NAME', 'LinuxQuest LMS');
define('COURSE_CODE', 'LNX-101');
define('LESSON_COUNT', 9);
define('PASS_PCT_LESSON_QUIZ', 70); // 2/3
define('PASS_PCT_POSTTEST', 70);    // 7/10

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Bangkok');
