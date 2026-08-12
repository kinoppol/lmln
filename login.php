<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (Auth::currentUser()) {
    header('Location: /lmln/dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid($_POST['csrf_token'] ?? null);
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } elseif (Auth::attempt($email, $password)) {
        header('Location: /lmln/dashboard.php');
        exit;
    } else {
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    }
}

Layout::start('เข้าสู่ระบบ', null);
?>
<div class="auth-card">
  <div class="eyebrow">LINUXQUEST · LMS</div>
  <h1>เข้าสู่ระบบ</h1>
  <p class="lead">ยังไม่มีบัญชี? <a href="/lmln/register.php">สมัครสมาชิก</a></p>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/lmln/login.php">
    <?= Csrf::field() ?>
    <div class="field">
      <label for="email">อีเมล</label>
      <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password">รหัสผ่าน</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%">เข้าสู่ระบบ →</button>
  </form>
  <div class="auth-switch">บัญชีผู้สอนตัวอย่าง: teacher@linuxquest.local</div>
</div>
<?php
Layout::end();
