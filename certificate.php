<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$courseId = 1;

$certCode = Progress::issueCertificateIfEligible($userId, $courseId);
$ready = $certCode !== null;
$doneCount = Progress::doneCount($userId);
$post = Progress::bestPosttestScore($userId, $courseId);
$level = Progress::level((int)$user['xp']);

$db = Database::get();
$certStmt = $db->prepare('SELECT * FROM certificates WHERE user_id = ? AND course_id = ?');
$certStmt->execute([$userId, $courseId]);
$cert = $certStmt->fetch();

Layout::start('ใบประกาศนียบัตร', $user, 'cert');
?>
<div class="page-narrow" style="max-width:900px">
  <div class="eyebrow">CERTIFICATE</div>
  <h1 style="font-size:25px">ใบประกาศนียบัตร</h1>
  <p class="lead"><?= $ready ? 'ยินดีด้วย! คุณเรียนครบและผ่านเกณฑ์แล้ว' : 'ใบประกาศจะปลดล็อกเมื่อเรียนครบ ' . LESSON_COUNT . ' บท และได้คะแนนหลังเรียนอย่างน้อย ' . PASS_PCT_POSTTEST . '%' ?></p>

  <div class="cert-frame <?= $ready ? 'unlocked' : 'locked' ?>">
    <div class="cert-body">
      <div class="cert-eyebrow">CERTIFICATE OF COMPLETION</div>
      <div style="margin:6px 0 26px;font-size:13px;color:#6f837c">ขอมอบใบประกาศนียบัตรฉบับนี้ให้แก่</div>
      <div class="cert-name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div style="width:230px;height:1px;background:rgba(74,222,128,.35);margin:16px auto 22px"></div>
      <div style="font-size:14px;line-height:1.9;color:#a9bcb5;max-width:520px;margin:0 auto">
        ผู้สำเร็จการอบรมรายวิชา<br>
        <span style="font-size:19px;font-weight:700;color:var(--green)">คำสั่งพื้นฐาน Linux · <?= COURSE_CODE ?></span><br>
        ครบทั้ง <?= LESSON_COUNT ?> บทเรียน พร้อมผ่านแบบทดสอบหลังเรียนและภารกิจฝึกทักษะ
      </div>
      <div class="cert-stats">
        <div><div class="n"><?= $post ? (int)$post['score'] . '/' . (int)$post['total'] : '—' ?></div><div style="font-size:11px;color:#5f736c;margin-top:3px">คะแนนหลังเรียน</div></div>
        <div><div class="n"><?= (int)$user['xp'] ?></div><div style="font-size:11px;color:#5f736c;margin-top:3px">XP สะสม</div></div>
        <div><div class="n">LV <?= $level ?></div><div style="font-size:11px;color:#5f736c;margin-top:3px">ระดับผู้เรียน</div></div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:44px;padding-top:22px;border-top:1px solid rgba(255,255,255,.08)">
        <div style="text-align:left"><div style="font-size:13px;color:#c3d4cd;font-weight:600">อ. ปิยะพงษ์ ศรีวัฒนา</div><div style="font-size:11px;color:#5f736c">ครูผู้สอน · LinuxQuest</div></div>
        <div style="font-family:var(--mono);font-size:10.5px;color:#4c5f59;text-align:right">
          ID: <?= htmlspecialchars($cert['cert_code'] ?? '—') ?><br>
          <?= $cert ? 'ออกให้เมื่อ ' . date('j M Y', strtotime($cert['issued_at'])) : '' ?>
        </div>
      </div>
    </div>
  </div>
  <?php if (!$ready): ?>
    <div class="cert-lock">
      <div class="cert-lock-box">
        <div style="font-size:14px;font-weight:700;color:var(--amber);margin-bottom:5px">🔒 ยังไม่ปลดล็อก</div>
        <div style="font-size:12.5px;color:#9bb0a8;line-height:1.6">ตอนนี้เรียนแล้ว <?= $doneCount ?>/<?= LESSON_COUNT ?> บท · คะแนนหลังเรียน <?= $post ? (int)$post['score'] . '/' . (int)$post['total'] : 'ยังไม่ได้ทำ' ?></div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
Layout::end();
