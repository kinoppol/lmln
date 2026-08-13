<?php
declare(strict_types=1);

/**
 * แก้เนื้อหาข้อสอบให้สอดคล้องกับบทเรียน (ผลจากการตรวจทานทั้ง 9 บท)
 *
 *   1. แบบทดสอบท้ายบทที่ 7 มีข้อซ้ำ — สองในสามข้อถามเรื่องเดียวกันว่า
 *      "ไม่รู้ option ต้องทำอย่างไร" และเฉลยเหมือนกัน เปลี่ยนข้อที่ซ้ำเป็น
 *      ความต่างระหว่าง man กับ --help ซึ่งบทเรียนสอนไว้ตรง ๆ
 *   2. แบบทดสอบก่อนเรียนไม่มีข้อวัดบทที่ 7 เลย ขณะที่บทที่ 1 มีถึง 3 ข้อ
 *      จึงเปลี่ยนข้อ ls -l (บทที่ 1) ในชุดก่อนเรียนเป็นข้อ man ของบทที่ 7
 *      (ข้อ ls -l ยังอยู่ในแบบทดสอบท้ายบทที่ 1 ตามเดิม)
 *
 * แก้ข้อความในแถวเดิมโดยไม่ลบ/เพิ่มแถว เพื่อไม่ให้ quiz_answers ของการทำ
 * ครั้งก่อน ๆ อ้างถึง option ที่หายไป
 */
return function (PDO $db): void {
    /** เปลี่ยนคำถาม เฉลย และตัวเลือกทั้งชุดของข้อที่ระบุ โดยคงแถวเดิมไว้ */
    $rewrite = function (PDO $db, ?int $questionId, string $text, string $explanation, array $options, int $correct): void {
        if (!$questionId) {
            return; // ไม่พบข้อเดิม (อาจเคยแก้ไปแล้ว) ข้ามไปเงียบ ๆ
        }
        $db->prepare('UPDATE quiz_questions SET question_text = ?, explanation = ? WHERE id = ?')
            ->execute([$text, $explanation, $questionId]);

        $ids = $db->prepare('SELECT id FROM quiz_options WHERE question_id = ? ORDER BY position');
        $ids->execute([$questionId]);
        $ids = $ids->fetchAll(PDO::FETCH_COLUMN);

        $upd = $db->prepare('UPDATE quiz_options SET option_text = ?, is_correct = ? WHERE id = ?');
        foreach ($ids as $i => $optionId) {
            if (!isset($options[$i])) {
                break;
            }
            $upd->execute([$options[$i], $i === $correct ? 1 : 0, (int)$optionId]);
        }
    };

    $findByText = function (PDO $db, string $kind, string $like): ?int {
        $stmt = $db->prepare(
            "SELECT qq.id FROM quiz_questions qq JOIN quizzes q ON q.id = qq.quiz_id
             WHERE q.kind = ? AND qq.question_text LIKE ? LIMIT 1"
        );
        $stmt->execute([$kind, $like]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    };

    // 1) ข้อซ้ำในแบบทดสอบท้ายบทที่ 7
    $rewrite(
        $db,
        $findByText($db, 'lesson', 'หากไม่แน่ใจว่าคำสั่งหนึ่งมี option อะไรบ้าง%'),
        'man กับ --help ต่างกันอย่างไร',
        'man เปิดคู่มือฉบับเต็ม (กด q เพื่อออก) ส่วน <คำสั่ง> --help พิมพ์สรุป option ออกมาสั้น ๆ อ่านเร็วกว่า ทั้งคู่ใช้ได้แม้ออฟไลน์',
        [
            'man สรุป option สั้น ๆ ส่วน --help เป็นคู่มือฉบับเต็ม',
            'man เป็นคู่มือฉบับเต็ม ส่วน --help พิมพ์สรุป option สั้น ๆ อ่านเร็วกว่า',
            'ทั้งสองให้ผลลัพธ์เหมือนกันทุกประการ',
            'ทั้งสองต้องต่ออินเทอร์เน็ตจึงจะใช้ได้',
        ],
        1
    );

    // 2) แบบทดสอบก่อนเรียนไม่มีข้อของบทที่ 7
    $rewrite(
        $db,
        $findByText($db, 'pretest', 'ผลลัพธ์จาก ls -l บรรทัดหนึ่งขึ้นต้นด้วยตัวอักษร d%'),
        'ต้องการอ่านคู่มือฉบับเต็มของคำสั่ง ls ใช้คำสั่งใด',
        'man <คำสั่ง> เปิดคู่มือฉบับเต็มที่ติดมากับระบบ อ่านได้แม้ไม่มีอินเทอร์เน็ต กด q เพื่อออกจากหน้าคู่มือ',
        ['help ls', 'man ls', 'ls --manual', 'guide ls'],
        1
    );

    // 3) แบบทดสอบหลังเรียนมีข้อบทที่ 9 ถึงสองข้อ แต่ไม่มีข้อวัดบทที่ 1 โดยตรง
    //    (ข้อความปลอดภัยยังอยู่ในแบบทดสอบท้ายบทที่ 9 ตามเดิม)
    $securityId = $findByText($db, 'posttest', 'พบไฟล์ update.sh มีเนื้อหาตามด้านล่าง%');
    $rewrite(
        $db,
        $securityId,
        'ต้องการดูไฟล์ซ่อน (ชื่อขึ้นต้นด้วยจุด) ในโฟลเดอร์ปัจจุบันด้วย ใช้คำสั่งใด',
        'ไฟล์ที่ชื่อขึ้นต้นด้วยจุดเป็นไฟล์ซ่อน ls ธรรมดาจะไม่แสดง ต้องใช้ -a (all) เช่น .bashrc',
        ['ls -l', 'ls -a', 'ls -h', 'ls --hidden'],
        1
    );
    if ($securityId) {
        // ข้อเดิมมีตัวอย่างโค้ดแนบมาด้วย ต้องเอาออกเพราะคำถามใหม่ไม่ได้ใช้
        $db->prepare('UPDATE quiz_questions SET code_snippet = NULL WHERE id = ?')->execute([$securityId]);
    }

    $db->prepare("UPDATE quiz_questions SET is_mono = 1 WHERE question_text IN (?, ?)")
        ->execute([
            'ต้องการอ่านคู่มือฉบับเต็มของคำสั่ง ls ใช้คำสั่งใด',
            'ต้องการดูไฟล์ซ่อน (ชื่อขึ้นต้นด้วยจุด) ในโฟลเดอร์ปัจจุบันด้วย ใช้คำสั่งใด',
        ]);
};
