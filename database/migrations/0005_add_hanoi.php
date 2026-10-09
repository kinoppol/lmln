<?php
declare(strict_types=1);

/**
 * เพิ่มเกมคลายเครียด "หอคอยฮานอย" (Tower of Hanoi) ให้ฐานข้อมูลที่ติดตั้งไปแล้ว
 * required_lessons ว่าง = เล่นได้เลยไม่ต้องผ่านบทเรียน (ดู Progress::gameGate)
 */
return function (PDO $db): void {
    $stmt = $db->prepare('SELECT id FROM games WHERE code = ?');
    $stmt->execute(['hanoi']);
    if ($stmt->fetchColumn()) {
        return; // มีอยู่แล้ว — migration ต้องรันซ้ำได้
    }

    $db->prepare(
        'INSERT INTO games (code, title_th, title_en, difficulty, description, brief, time_limit_sec, required_lessons)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        'hanoi',
        'หอคอยฮานอย',
        'Tower of Hanoi',
        'คลายเครียด',
        'ปริศนาคลาสสิก — ย้ายแผ่นดิสก์ทั้งหมดจากเสาซ้ายไปเสาขวา ทีละแผ่น และห้ามวางแผ่นใหญ่ทับแผ่นเล็ก ยิ่งใช้จำนวนครั้งใกล้ค่าน้อยที่สุดยิ่งได้คะแนนมาก',
        null,
        0,
        '[]',
    ]);
};
