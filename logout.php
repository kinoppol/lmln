<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// ออกจากระบบต้องมาจากการยืนยันใน modal เท่านั้น (POST + CSRF)
// เข้าตรงด้วย GET จะถูกส่งกลับหน้าเดิม เพื่อกันการถูกลิงก์หลอกให้หลุดออกจากระบบ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (Auth::currentUser() ? url('/dashboard.php') : url('/login.php')));
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

Auth::logout();
header('Location: ' . url('/login.php'));
exit;
