<?php
declare(strict_types=1);

/**
 * ระบบปรับปรุงโครงสร้างฐานข้อมูลแบบเป็นขั้น (migrations)
 *
 * ไฟล์ migration อยู่ใน database/migrations/ ตั้งชื่อเป็น NNNN_ชื่อ.(sql|php)
 * เช่น 0001_add_games_required_lessons.php เรียงลำดับตามชื่อไฟล์
 *   - .sql : คำสั่ง SQL คั่นด้วย ; (คอมเมนต์ -- ถูกตัดทิ้ง)
 *   - .php : ต้อง return callable(PDO): void — ใช้เมื่อต้องเช็กเงื่อนไขก่อนแก้
 *
 * DDL ของ MySQL/MariaDB ไม่รองรับ transaction (แต่ละคำสั่ง commit ทันที)
 * ถ้าพังกลางคันจะย้อนกลับให้อัตโนมัติไม่ได้ migration ทุกตัวจึงต้องเขียนแบบ
 * "รันซ้ำแล้วไม่พัง" คือเช็ก information_schema ก่อนเสมอว่าทำไปแล้วหรือยัง
 */
final class Migrator
{
    public const DIR = __DIR__ . '/../database/migrations';

    public static function ensureTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(20) NOT NULL UNIQUE,
                name VARCHAR(190) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB'
        );
    }

    /** @return array<string,array{version:string,name:string,file:string,path:string,type:string}> */
    public static function available(): array
    {
        $out = [];
        foreach (glob(self::DIR . '/*.{sql,php}', GLOB_BRACE) ?: [] as $path) {
            $file = basename($path);
            if (!preg_match('/^(\d+)_(.+)\.(sql|php)$/', $file, $m)) {
                continue;
            }
            $out[$m[1]] = [
                'version' => $m[1],
                'name' => str_replace('_', ' ', $m[2]),
                'file' => $file,
                'path' => $path,
                'type' => $m[3],
            ];
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    /** @return array<string,array> version => แถวที่บันทึกไว้ */
    public static function applied(PDO $db): array
    {
        self::ensureTable($db);
        $rows = $db->query('SELECT * FROM schema_migrations ORDER BY version')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(string)$r['version']] = $r;
        }
        return $out;
    }

    /** @return array<string,array> migration ที่ยังไม่ได้รัน */
    public static function pending(PDO $db): array
    {
        return array_diff_key(self::available(), self::applied($db));
    }

    /** บันทึกว่า migration ทั้งหมดถูกใช้แล้ว — ใช้ตอนติดตั้งใหม่ ซึ่ง schema.sql ใหม่ล่าสุดอยู่แล้ว */
    public static function markAllApplied(PDO $db): int
    {
        self::ensureTable($db);
        $stmt = $db->prepare('INSERT IGNORE INTO schema_migrations (version, name) VALUES (?,?)');
        $n = 0;
        foreach (self::available() as $m) {
            $stmt->execute([$m['version'], $m['name']]);
            $n++;
        }
        return $n;
    }

    /** รัน migration ตัวเดียวแล้วบันทึกผล — โยน Throwable ถ้าพัง */
    public static function runOne(PDO $db, array $m): void
    {
        if ($m['type'] === 'php') {
            $fn = require $m['path'];
            if (!is_callable($fn)) {
                throw new RuntimeException($m['file'] . ' ต้อง return callable(PDO): void');
            }
            $fn($db);
        } else {
            foreach (self::splitSql((string)file_get_contents($m['path'])) as $stmt) {
                $db->exec($stmt);
            }
        }

        $ins = $db->prepare('INSERT INTO schema_migrations (version, name) VALUES (?,?)');
        $ins->execute([$m['version'], $m['name']]);
    }

    /**
     * รัน migration ที่ค้างทั้งหมดตามลำดับ หยุดทันทีที่ตัวใดพัง
     *
     * @return array{ok:bool, done:string[], error:?string}
     */
    public static function runPending(PDO $db): array
    {
        $done = [];
        foreach (self::pending($db) as $m) {
            try {
                self::runOne($db, $m);
                $done[] = $m['version'] . ' · ' . $m['name'];
            } catch (Throwable $e) {
                return ['ok' => false, 'done' => $done, 'error' => $m['file'] . ' — ' . $e->getMessage()];
            }
        }
        return ['ok' => true, 'done' => $done, 'error' => null];
    }

    /** ตัดคอมเมนต์ -- ออก แล้วแยกคำสั่งตาม ; (ไม่รองรับ DELIMITER/stored procedure) */
    public static function splitSql(string $sql): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || strpos($trimmed, '--') === 0) {
                continue;
            }
            $lines[] = $line;
        }
        $out = [];
        foreach (explode(';', implode("\n", $lines)) as $stmt) {
            if (trim($stmt) !== '') {
                $out[] = $stmt;
            }
        }
        return $out;
    }

    /** ตัวช่วยที่ migration เรียกใช้บ่อย เพื่อให้เขียนแบบรันซ้ำได้ง่าย */
    public static function hasColumn(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function hasTable(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
