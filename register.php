<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (Auth::currentUser()) {
    header('Location: /lmln/dashboard.php');
    exit;
}

$error = null;
$old = ['role' => 'student', 'full_name' => '', 'email' => '', 'education_level' => '', 'major' => '', 'school_name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid($_POST['csrf_token'] ?? null);
    $old['role'] = ($_POST['role'] ?? 'student') === 'general' ? 'general' : 'student';
    $old['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $old['email'] = trim((string)($_POST['email'] ?? ''));
    $old['education_level'] = trim((string)($_POST['education_level'] ?? ''));
    $old['major'] = trim((string)($_POST['major'] ?? ''));
    $old['school_name'] = trim((string)($_POST['school_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if ($old['full_name'] === '' || $old['email'] === '' || $password === '') {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบ';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif (strlen($password) < 8) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif ($password !== $password2) {
        $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    } else {
        [$ok, $result] = Auth::register(
            $old['email'], $password, $old['full_name'], $old['role'],
            $old['education_level'], $old['major'], $old['school_name']
        );
        if ($ok) {
            Auth::attempt($old['email'], $password);
            header('Location: /lmln/dashboard.php');
            exit;
        }
        $error = $result;
    }
}

Layout::start('สมัครสมาชิก', null);
?>
<div class="auth-card">
  <div class="eyebrow">LINUXQUEST · LMS</div>
  <h1>สมัครสมาชิก</h1>
  <p class="lead">มีบัญชีอยู่แล้ว? <a href="/lmln/login.php">เข้าสู่ระบบ</a></p>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/lmln/register.php" id="regForm">
    <?= Csrf::field() ?>

    <div class="field">
      <label>ประเภทผู้ใช้งาน</label>
      <div class="role-toggle">
        <label class="role-opt <?= $old['role'] === 'student' ? 'checked' : '' ?>">
          <input type="radio" name="role" value="student" <?= $old['role'] === 'student' ? 'checked' : '' ?>> นักเรียนนักศึกษา
        </label>
        <label class="role-opt <?= $old['role'] === 'general' ? 'checked' : '' ?>">
          <input type="radio" name="role" value="general" <?= $old['role'] === 'general' ? 'checked' : '' ?>> บุคคลทั่วไป
        </label>
      </div>
    </div>

    <div class="field">
      <label for="full_name">ชื่อ-นามสกุล</label>
      <input type="text" id="full_name" name="full_name" required value="<?= htmlspecialchars($old['full_name']) ?>">
    </div>

    <div class="field">
      <label for="email">อีเมล</label>
      <input type="email" id="email" name="email" required value="<?= htmlspecialchars($old['email']) ?>">
    </div>

    <div id="studentFields">
      <div class="field">
        <label for="education_level">ระดับการศึกษา</label>
        <select id="education_level" name="education_level">
          <option value="">— เลือกระดับการศึกษา —</option>
          <?php foreach (['มัธยมศึกษาตอนต้น', 'มัธยมศึกษาตอนปลาย', 'ปวช.', 'ปวส.', 'ปริญญาตรี', 'ปริญญาโท', 'ปริญญาเอก', 'อื่น ๆ'] as $lv): ?>
            <option value="<?= htmlspecialchars($lv) ?>" <?= $old['education_level'] === $lv ? 'selected' : '' ?>><?= htmlspecialchars($lv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="major">สาขาที่เรียน</label>
        <input type="text" id="major" name="major" placeholder="เช่น คอมพิวเตอร์ธุรกิจ" value="<?= htmlspecialchars($old['major']) ?>">
      </div>
      <div class="field">
        <label for="school_name">ชื่อสถานศึกษาที่สังกัด</label>
        <input type="text" id="school_name" name="school_name" value="<?= htmlspecialchars($old['school_name']) ?>">
      </div>
    </div>

    <div class="field">
      <label for="password">รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label>
      <input type="password" id="password" name="password" required minlength="8">
    </div>
    <div class="field">
      <label for="password2">ยืนยันรหัสผ่าน</label>
      <input type="password" id="password2" name="password2" required minlength="8">
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%">สมัครสมาชิก →</button>
  </form>
</div>
<script>
(function(){
  const opts = document.querySelectorAll('.role-opt');
  const studentFields = document.getElementById('studentFields');
  function sync(){
    let role = 'student';
    opts.forEach(o => { const input = o.querySelector('input'); o.classList.toggle('checked', input.checked); if (input.checked) role = input.value; });
    studentFields.style.display = role === 'student' ? '' : 'none';
  }
  opts.forEach(o => o.querySelector('input').addEventListener('change', sync));
  sync();
})();
</script>
<?php
Layout::end();
