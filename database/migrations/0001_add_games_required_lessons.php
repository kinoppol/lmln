<?php
declare(strict_types=1);

/**
 * เพิ่มเงื่อนไขปลดล็อกเกม: games.required_lessons
 *
 * ฐานข้อมูลที่ติดตั้งก่อนหน้านี้ยังไม่มีคอลัมน์นี้ ทำให้หน้าโซนเกมพัง
 * migration นี้เติมคอลัมน์แล้วใส่ค่าตามคำสั่งที่แต่ละเกมใช้จริง
 * โดยไม่แตะข้อมูลผู้เรียน (ค่าเดียวกับที่ database/seed.php ใส่ให้เครื่องติดตั้งใหม่)
 */
return function (PDO $db): void {
    if (!Migrator::hasColumn($db, 'games', 'required_lessons')) {
        $db->exec("ALTER TABLE games ADD COLUMN required_lessons JSON NOT NULL AFTER time_limit_sec");
    }

    $required = [
        'virus' => [1, 2],                       // pwd/ls, cd
        'drill' => [1, 2, 3, 4, 5, 6, 7, 8, 9],  // โจทย์ครอบคลุมคำสั่งทุกบท
        'escape' => [1, 2, 6, 8, 9],             // ls -a, cd, cat, chmod, grep/find
        'repair' => [1, 2, 4, 8],                // ls -l, cd, mv, chmod
    ];

    $stmt = $db->prepare('UPDATE games SET required_lessons = ? WHERE code = ?');
    foreach ($required as $code => $positions) {
        $stmt->execute([json_encode($positions), $code]);
    }

    // เกมที่เพิ่มเองภายหลังและยังไม่ได้กำหนดเงื่อนไข ให้ถือว่าไม่ล็อกด้วยบทเรียน
    $db->exec("UPDATE games SET required_lessons = '[]' WHERE required_lessons = '' OR JSON_VALID(required_lessons) = 0");
};
