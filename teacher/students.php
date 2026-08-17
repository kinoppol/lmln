<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

$teacher = Auth::requireTeacher();
$teacherId = (int)$teacher['id'];
$db = Database::get();

$notice = null;
$error = null;

// ---------------------------------------------------------------- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    $targetId = (int)($_POST['user_id'] ?? 0);

    // ห้ามแก้บัญชีตัวเองผ่านหน้านี้ กันเผลอลบ/ปลดบทบาทตัวเองจนไม่มีผู้สอนเหลือ
    if ($targetId === $teacherId && in_array($action, ['delete', 'update', 'reset_progress'], true)) {
        $error = 'บัญชีของตัวเองแก้ไขหรือลบจากหน้านี้ไม่ได้';
        $action = '';
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if ($action !== '' && !$target) {
        $error = 'ไม่พบบัญชีที่เลือก';
        $action = '';
    }

    if ($action === 'impersonate') {
        [$ok, $why] = Auth::impersonate($targetId);
        if ($ok) {
            header('Location: ' . url('/dashboard.php'));
            exit;
        }
        $error = $why;

    } elseif ($action === 'update') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = (string)($_POST['role'] ?? '');
        $eduLevel = trim((string)($_POST['education_level'] ?? ''));
        $major = trim((string)($_POST['major'] ?? ''));
        $school = trim((string)($_POST['school_name'] ?? ''));
        $newPass = (string)($_POST['new_password'] ?? '');

        $dup = $db->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $dup->execute([$email, $targetId]);

        if ($fullName === '') {
            $error = 'กรุณากรอกชื่อ-นามสกุล';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'อีเมลไม่ถูกต้อง';
        } elseif ($dup->fetch()) {
            $error = 'อีเมลนี้ถูกใช้กับบัญชีอื่นแล้ว';
        } elseif (!in_array($role, ['student', 'general', 'teacher'], true)) {
            $error = 'บทบาทไม่ถูกต้อง';
        } elseif ($newPass !== '' && mb_strlen($newPass) < 8) {
            $error = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร';
        } else {
            if ($role !== 'student') {
                $eduLevel = $major = $school = null; // ข้อมูลการศึกษาใช้กับผู้เรียนในระบบการศึกษาเท่านั้น
            }
            $db->prepare(
                'UPDATE users SET full_name = ?, email = ?, role = ?, education_level = ?, major = ?, school_name = ? WHERE id = ?'
            )->execute([$fullName, $email, $role, $eduLevel ?: null, $major ?: null, $school ?: null, $targetId]);

            if ($newPass !== '') {
                $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($newPass, PASSWORD_DEFAULT), $targetId]);
            }
            $notice = 'บันทึกข้อมูลของ ' . $fullName . ' แล้ว' . ($newPass !== '' ? ' (ตั้งรหัสผ่านใหม่ให้ด้วย)' : '');
        }

    } elseif ($action === 'reset_progress') {
        // ล้างผลการเรียนทั้งหมดแต่ยังเก็บบัญชีไว้ ให้เริ่มเรียนใหม่จากบทที่ 1
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM quiz_answers WHERE attempt_id IN (SELECT id FROM quiz_attempts WHERE user_id = ?)')->execute([$targetId]);
            $db->prepare('DELETE FROM quiz_attempts WHERE user_id = ?')->execute([$targetId]);
            $db->prepare('DELETE FROM game_scores WHERE user_id = ?')->execute([$targetId]);
            $db->prepare('DELETE FROM certificates WHERE user_id = ?')->execute([$targetId]);
            $db->prepare('DELETE FROM user_lesson_progress WHERE user_id = ?')->execute([$targetId]);
            $db->prepare('UPDATE users SET xp = 0 WHERE id = ?')->execute([$targetId]);
            $db->commit();
            Progress::ensureRows($targetId);
            $notice = 'รีเซ็ตความคืบหน้าของ ' . $target['full_name'] . ' แล้ว (บัญชียังอยู่)';
        } catch (Throwable $e) {
            $db->rollBack();
            $error = 'รีเซ็ตความคืบหน้าไม่สำเร็จ';
        }

    } elseif ($action === 'delete') {
        // ตาราง progress/attempt/score/certificate ตั้ง ON DELETE CASCADE ไว้แล้ว
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
        $notice = 'ลบบัญชี ' . $target['full_name'] . ' และข้อมูลทั้งหมดแล้ว';
    }
}

// ---------------------------------------------------------------- list
$search = trim((string)($_GET['q'] ?? ''));
$roleFilter = in_array($_GET['role'] ?? '', ['student', 'general', 'teacher'], true) ? $_GET['role'] : '';
$editId = (int)($_GET['edit'] ?? 0);

$sql = 'SELECT u.*,
        (SELECT COUNT(*) FROM user_lesson_progress p WHERE p.user_id = u.id AND p.status = \'completed\') lessons_done,
        (SELECT a.score FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id
          WHERE a.user_id = u.id AND q.kind = \'pretest\' AND a.completed_at IS NOT NULL ORDER BY a.id DESC LIMIT 1) pre_score,
        (SELECT MAX(a.score) FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id
          WHERE a.user_id = u.id AND q.kind = \'posttest\' AND a.completed_at IS NOT NULL) post_score
        FROM users u WHERE 1 = 1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.school_name LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
if ($roleFilter !== '') {
    $sql .= ' AND u.role = ?';
    $params[] = $roleFilter;
}
$sql .= ' ORDER BY u.role = \'teacher\' DESC, u.created_at DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$editUser = null;
foreach ($users as $u) {
    if ((int)$u['id'] === $editId) {
        $editUser = $u;
    }
}

$counts = $db->query("SELECT role, COUNT(*) c FROM users GROUP BY role")->fetchAll();
$countBy = [];
foreach ($counts as $c) {
    $countBy[$c['role']] = (int)$c['c'];
}

$roleLabel = ['student' => 'นักเรียนนักศึกษา', 'general' => 'บุคคลทั่วไป', 'teacher' => 'ผู้สอน'];
$listQs = ($search !== '' ? '&q=' . urlencode($search) : '') . ($roleFilter !== '' ? '&role=' . $roleFilter : '');

Layout::start('จัดการผู้เรียน', $teacher, 'students');
?>
<div class="page" style="max-width:1180px">
  <div class="eyebrow" style="color:var(--cyan)">INSTRUCTOR VIEW · USER MANAGEMENT</div>
  <h1 style="font-size:27px">จัดการผู้เรียน</h1>
  <p class="lead">
    แก้ข้อมูลบัญชี ตั้งรหัสผ่านใหม่ รีเซ็ตความคืบหน้า และสวมสิทธิ์เพื่อดูระบบในมุมของผู้เรียนแต่ละคน ·
    ผู้เรียน <?= ($countBy['student'] ?? 0) + ($countBy['general'] ?? 0) ?> คน · ผู้สอน <?= $countBy['teacher'] ?? 0 ?> คน
  </p>

  <?php if (!empty($_GET['returned'])): ?>
    <div class="alert" style="border:1px solid rgba(74,222,128,.3);background:rgba(74,222,128,.07);color:var(--green-soft)">
      ✓ คืนสิทธิ์ผู้ดูแลเรียบร้อย กลับมาใช้บัญชีของคุณแล้ว
    </div>
  <?php endif; ?>
  <?php if ($notice): ?><div class="alert" style="border:1px solid rgba(74,222,128,.3);background:rgba(74,222,128,.07);color:var(--green-soft)"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="get" action="<?= BASE_URL ?>/teacher/students.php" class="student-filter">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อ อีเมล หรือสถานศึกษา">
    <select name="role">
      <option value="">ทุกบทบาท</option>
      <?php foreach ($roleLabel as $k => $v): ?>
        <option value="<?= $k ?>" <?= $roleFilter === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">ค้นหา</button>
    <?php if ($search !== '' || $roleFilter !== ''): ?>
      <a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>/teacher/students.php">ล้างตัวกรอง</a>
    <?php endif; ?>
  </form>

  <?php if ($editUser): ?>
    <div class="card" style="padding:22px 24px;margin-bottom:20px;border-color:rgba(56,189,248,.28)">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div style="font-size:14px;font-weight:700;color:#e3efe9">แก้ข้อมูล: <?= htmlspecialchars($editUser['full_name']) ?></div>
        <span class="spacer"></span>
        <a href="<?= BASE_URL ?>/teacher/students.php?<?= ltrim($listQs, '&') ?>" style="font-size:11.5px;color:#6f837c">ปิด ✕</a>
      </div>
      <form method="post" action="<?= BASE_URL ?>/teacher/students.php?<?= ltrim($listQs, '&') ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
        <div class="student-form-grid">
          <div class="field">
            <label>ชื่อ-นามสกุล</label>
            <input type="text" name="full_name" required value="<?= htmlspecialchars($editUser['full_name']) ?>">
          </div>
          <div class="field">
            <label>อีเมล</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($editUser['email']) ?>">
          </div>
          <div class="field">
            <label>บทบาท</label>
            <select name="role">
              <?php foreach ($roleLabel as $k => $v): ?>
                <option value="<?= $k ?>" <?= $editUser['role'] === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>ระดับการศึกษา</label>
            <input type="text" name="education_level" value="<?= htmlspecialchars((string)$editUser['education_level']) ?>" placeholder="เช่น ปวช. / ปริญญาตรี">
          </div>
          <div class="field">
            <label>สาขาที่เรียน</label>
            <input type="text" name="major" value="<?= htmlspecialchars((string)$editUser['major']) ?>">
          </div>
          <div class="field">
            <label>สถานศึกษา</label>
            <input type="text" name="school_name" value="<?= htmlspecialchars((string)$editUser['school_name']) ?>">
          </div>
          <div class="field">
            <label>ตั้งรหัสผ่านใหม่ (เว้นว่างไว้ถ้าไม่เปลี่ยน)</label>
            <input type="password" name="new_password" autocomplete="new-password" minlength="8" placeholder="อย่างน้อย 8 ตัวอักษร">
          </div>
        </div>
        <div style="font-size:11.5px;color:#5f736c;margin-bottom:14px">
          ข้อมูลระดับการศึกษา สาขา และสถานศึกษา ใช้กับบทบาทนักเรียนนักศึกษาเท่านั้น — ถ้าเปลี่ยนเป็นบทบาทอื่นระบบจะล้างค่าเหล่านี้
        </div>
        <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
      </form>
    </div>
  <?php endif; ?>

  <div class="card" style="padding:0;overflow:hidden">
    <div class="student-row student-head">
      <span style="flex:1;min-width:0">ผู้เรียน</span>
      <span style="width:132px">บทบาท</span>
      <span style="width:96px">บทเรียน</span>
      <span style="width:74px;text-align:right">XP</span>
      <span style="width:96px;text-align:right">ก่อน / หลัง</span>
      <span style="width:250px;text-align:right">จัดการ</span>
    </div>

    <?php foreach ($users as $u):
        $isSelf = (int)$u['id'] === $teacherId;
        $isTeacher = $u['role'] === 'teacher';
        $pct = round((int)$u['lessons_done'] / LESSON_COUNT * 100);
    ?>
      <div class="student-row">
        <span style="flex:1;min-width:0">
          <span class="student-name"><?= htmlspecialchars($u['full_name']) ?><?= $isSelf ? ' <span style="color:var(--cyan);font-size:10.5px">(บัญชีของคุณ)</span>' : '' ?></span>
          <span class="student-sub"><?= htmlspecialchars($u['email']) ?><?= $u['school_name'] ? ' · ' . htmlspecialchars($u['school_name']) : '' ?></span>
        </span>
        <span style="width:132px;font-size:11.5px;color:<?= $isTeacher ? 'var(--cyan)' : '#8ea59d' ?>">
          <?= $roleLabel[$u['role']] ?? $u['role'] ?>
          <?php if ($u['education_level']): ?><span style="display:block;color:#5f736c;font-size:10.5px"><?= htmlspecialchars($u['education_level']) ?></span><?php endif; ?>
        </span>
        <span style="width:96px;display:flex;align-items:center;gap:7px">
          <span class="bar-track" style="flex:1"><span class="bar-fill" style="width:<?= $pct ?>%;display:block"></span></span>
          <span style="font-family:var(--mono);font-size:10.5px;color:#6f837c"><?= (int)$u['lessons_done'] ?>/<?= LESSON_COUNT ?></span>
        </span>
        <span style="width:74px;text-align:right;font-family:var(--mono);font-size:11.5px;color:var(--green)">
          <?= (int)$u['xp'] ?><span style="color:#4c5f59;font-size:10px"> · LV<?= Progress::level((int)$u['xp']) ?></span>
        </span>
        <span style="width:96px;text-align:right;font-family:var(--mono);font-size:11.5px;color:#8ea59d">
          <?= $u['pre_score'] !== null ? (int)$u['pre_score'] : '—' ?> /
          <span style="color:<?= ((int)($u['post_score'] ?? 0)) >= 7 ? 'var(--green)' : '#8ea59d' ?>"><?= $u['post_score'] !== null ? (int)$u['post_score'] : '—' ?></span>
        </span>
        <span style="width:250px;display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
          <a class="btn btn-ghost btn-xs" href="<?= BASE_URL ?>/teacher/students.php?edit=<?= (int)$u['id'] ?><?= $listQs ?>">แก้ข้อมูล</a>

          <?php if (!$isTeacher): ?>
            <form method="post" action="<?= BASE_URL ?>/teacher/students.php?<?= ltrim($listQs, '&') ?>" style="display:inline">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="impersonate">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <button type="submit" class="btn btn-outline btn-xs">สวมสิทธิ์</button>
            </form>
          <?php endif; ?>

          <?php if (!$isSelf): ?>
            <button type="button" class="btn btn-ghost btn-xs js-confirm"
                    data-title="รีเซ็ตความคืบหน้า?"
                    data-body="ล้างผลการเรียนของ <?= htmlspecialchars($u['full_name']) ?> ทั้งหมด: บทเรียนที่ผ่าน คะแนนสอบ คะแนนเกม XP และใบประกาศ — บัญชียังอยู่และเริ่มเรียนใหม่จากบทที่ 1 ได้ทันที"
                    data-confirm="รีเซ็ตความคืบหน้า"
                    data-action="reset_progress" data-user="<?= (int)$u['id'] ?>">รีเซ็ต</button>
            <button type="button" class="btn btn-ghost btn-xs js-confirm" style="color:#fca5a5"
                    data-title="ลบบัญชีนี้?"
                    data-body="ลบบัญชี <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['email']) ?>) พร้อมความคืบหน้า คะแนนสอบ คะแนนเกม และใบประกาศทั้งหมด — กู้คืนไม่ได้"
                    data-confirm="ลบบัญชี"
                    data-action="delete" data-user="<?= (int)$u['id'] ?>">ลบ</button>
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>

    <?php if (!$users): ?>
      <div style="padding:26px;text-align:center;font-size:12.5px;color:#5f736c">ไม่พบบัญชีที่ตรงกับเงื่อนไข</div>
    <?php endif; ?>
  </div>

  <div style="margin-top:16px;font-size:11.5px;color:#5f736c;line-height:1.9">
    <strong style="color:#8ea59d">การสวมสิทธิ์:</strong> ระบบจะสลับไปใช้บัญชีผู้เรียนคนนั้นทั้งหมด เห็นบทเรียนที่ล็อก/ปลดล็อกและโซนเกมตามสภาพจริงของเขา
    มีแถบเตือนค้างไว้ด้านบนตลอด และกด “คืนสิทธิ์ผู้ดูแล” (หรือปุ่มออกจากระบบ) เพื่อกลับมาเป็นบัญชีของคุณ
    ระวังว่าสิ่งที่ทำระหว่างสวมสิทธิ์ เช่น ทำแบบทดสอบหรือเล่นเกม จะถูกบันทึกเป็นผลงานของผู้เรียนคนนั้นจริง
  </div>
</div>

<?php // modal ยืนยันสำหรับปุ่มที่ย้อนกลับไม่ได้ ใช้ร่วมกันทุกแถวเพื่อไม่ให้มี modal ซ้ำ 50 ชุด ?>
<div class="modal-backdrop" id="confirmModal" hidden>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="modal-icon" style="color:var(--red);background:rgba(248,113,113,.1);border-color:rgba(248,113,113,.25)">!</div>
    <div class="modal-title" id="confirmTitle"></div>
    <p class="modal-body" id="confirmBody"></p>
    <form method="post" action="<?= BASE_URL ?>/teacher/students.php?<?= ltrim($listQs, '&') ?>" class="modal-actions">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" id="confirmAction">
      <input type="hidden" name="user_id" id="confirmUser">
      <button type="button" class="btn btn-ghost" id="confirmCancel">ยกเลิก</button>
      <button type="submit" class="btn btn-danger" id="confirmOk"></button>
    </form>
  </div>
</div>
<script>
(function () {
  var modal = document.getElementById('confirmModal');
  var cancel = document.getElementById('confirmCancel');

  function close() { modal.hidden = true; document.body.style.overflow = ''; }

  document.querySelectorAll('.js-confirm').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('confirmTitle').textContent = btn.dataset.title;
      document.getElementById('confirmBody').textContent = btn.dataset.body;
      document.getElementById('confirmOk').textContent = btn.dataset.confirm;
      document.getElementById('confirmAction').value = btn.dataset.action;
      document.getElementById('confirmUser').value = btn.dataset.user;
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      cancel.focus();
    });
  });

  cancel.addEventListener('click', close);
  modal.addEventListener('mousedown', function (e) { if (e.target === modal) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
})();
</script>
<?php
Layout::end();
