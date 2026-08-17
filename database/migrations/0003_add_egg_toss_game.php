<?php
declare(strict_types=1);

/**
 * เพิ่มเกมคลายเครียด "โยนไข่" (Egg Toss) ให้ฐานข้อมูลที่ติดตั้งไปแล้ว
 *
 * required_lessons เป็น array ว่าง = ไม่ผูกกับบทเรียนใด เล่นได้เลยโดยไม่ต้อง
 * รอเปิดโซนเกม (ดู games.php / game.php ที่ถือว่าเกมไม่มีเงื่อนไข = เปิดอิสระ)
 */
return function (PDO $db): void {
    $stmt = $db->prepare('SELECT id FROM games WHERE code = ?');
    $stmt->execute(['egg']);
    if ($stmt->fetchColumn()) {
        return; // มีอยู่แล้ว (migration ต้องรันซ้ำได้)
    }

    $db->prepare(
        'INSERT INTO games (code, title_th, title_en, difficulty, description, brief, time_limit_sec, required_lessons)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        'egg',
        'โยนไข่',
        'Egg Toss',
        'คลายเครียด',
        'พักสมองจากคำสั่ง Linux สักครู่ — ขยับตะกร้ารับไข่ที่ร่วงลงมาให้ทัน รับติดกันได้โบนัสคอมโบ ไข่ทองได้คะแนนพิเศษ พลาดได้ 3 ครั้ง',
        null,
        0,
        '[]',
    ]);
};
