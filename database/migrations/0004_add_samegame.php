<?php
declare(strict_types=1);

/**
 * เพิ่มเกมคลายเครียด "บล็อกสีเดียวกัน" (SameGame) ให้ฐานข้อมูลที่ติดตั้งไปแล้ว
 * required_lessons ว่าง = เล่นได้เลยไม่ต้องผ่านบทเรียน (ดู Progress::gameGate)
 */
return function (PDO $db): void {
    $stmt = $db->prepare('SELECT id FROM games WHERE code = ?');
    $stmt->execute(['same']);
    if ($stmt->fetchColumn()) {
        return; // มีอยู่แล้ว — migration ต้องรันซ้ำได้
    }

    $db->prepare(
        'INSERT INTO games (code, title_th, title_en, difficulty, description, brief, time_limit_sec, required_lessons)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        'same',
        'บล็อกสีเดียวกัน',
        'SameGame',
        'คลายเครียด',
        'เกมลับสมองแบบเงียบ ๆ — กดกลุ่มบล็อกสีเดียวกันที่ติดกันตั้งแต่ 2 ช่องขึ้นไปเพื่อลบทิ้ง กลุ่มยิ่งใหญ่ยิ่งได้คะแนนทวีคูณ เก็บให้เหลือน้อยที่สุด',
        null,
        0,
        '[]',
    ]);
};
