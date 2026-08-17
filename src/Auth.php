<?php
declare(strict_types=1);

final class Auth
{
    public static function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $stmt = Database::get()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }

        // กันบัญชีที่ไม่มีแถวความคืบหน้า (สร้างจาก install.php/seed หรือมีบทเรียนเพิ่มใหม่ภายหลัง)
        Progress::ensureRows((int)$user['id']);
        return $user;
    }

    public static function requireLogin(): array
    {
        $user = self::currentUser();
        if (!$user) {
            header('Location: ' . url('/login.php'));
            exit;
        }
        return $user;
    }

    public static function requireTeacher(): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            die('เฉพาะผู้สอนเท่านั้นที่เข้าหน้านี้ได้');
        }
        return $user;
    }

    /**
     * สวมสิทธิ์ผู้เรียน (ผู้สอนเท่านั้น)
     *
     * เก็บ id ของผู้สอนไว้ใน impersonator_id แล้วสลับ user_id เป็นของผู้เรียน
     * ทุกหน้าจึงเห็นสภาพเดียวกับที่ผู้เรียนคนนั้นเห็นจริง ๆ รวมถึงบทที่ยังล็อกอยู่
     * และเพราะบทบาทที่อ่านได้กลายเป็นผู้เรียน หน้าของผู้สอนจึงถูกปิดไปด้วยเอง
     * เลิกสวมสิทธิ์เมื่อกดออกจากระบบ (ดู logout.php) แล้วได้สิทธิ์ผู้สอนคืน
     */
    public static function impersonate(int $targetId): array
    {
        $actor = self::currentUser();
        if (!$actor || $actor['role'] !== 'teacher') {
            return [false, 'เฉพาะผู้สอนเท่านั้นที่สวมสิทธิ์ผู้เรียนได้'];
        }
        if (self::isImpersonating()) {
            return [false, 'กำลังสวมสิทธิ์อยู่แล้ว กรุณาคืนสิทธิ์ก่อน'];
        }
        if ($targetId === (int)$actor['id']) {
            return [false, 'สวมสิทธิ์บัญชีตัวเองไม่ได้'];
        }

        $stmt = Database::get()->prepare('SELECT id, role FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            return [false, 'ไม่พบบัญชีผู้เรียนนี้'];
        }
        if ($target['role'] === 'teacher') {
            return [false, 'สวมสิทธิ์บัญชีผู้สอนคนอื่นไม่ได้'];
        }

        $_SESSION['impersonator_id'] = (int)$actor['id'];
        $_SESSION['user_id'] = (int)$target['id'];
        return [true, null];
    }

    public static function isImpersonating(): bool
    {
        return !empty($_SESSION['impersonator_id']);
    }

    /** ผู้สอนที่กำลังสวมสิทธิ์อยู่ (ใช้แสดงบนแถบเตือน) */
    public static function impersonator(): ?array
    {
        if (!self::isImpersonating()) {
            return null;
        }
        $stmt = Database::get()->prepare('SELECT id, full_name, role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['impersonator_id']]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** คืนสิทธิ์ให้ผู้สอน — คืน true ถ้ามีการสวมสิทธิ์อยู่จริง */
    public static function stopImpersonating(): bool
    {
        $teacher = self::impersonator();
        unset($_SESSION['impersonator_id']);
        if (!$teacher || $teacher['role'] !== 'teacher') {
            return false; // บัญชีผู้สอนถูกลบ/เปลี่ยนบทบาทระหว่างสวมสิทธิ์ ให้ออกจากระบบไปเลย
        }
        $_SESSION['user_id'] = (int)$teacher['id'];
        return true;
    }

    public static function register(string $email, string $password, string $fullName, string $role, ?string $eduLevel, ?string $major, ?string $school): array
    {
        $db = Database::get();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return [false, 'อีเมลนี้ถูกใช้สมัครแล้ว'];
        }

        if (!in_array($role, ['student', 'general'], true)) {
            return [false, 'ประเภทผู้ใช้งานไม่ถูกต้อง'];
        }
        if ($role === 'student') {
            if ($eduLevel === '' || $major === '' || $school === '') {
                return [false, 'กรุณากรอกระดับการศึกษา สาขาที่เรียน และสถานศึกษาให้ครบ'];
            }
        } else {
            $eduLevel = $major = $school = null;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO users (email, password_hash, full_name, role, education_level, major, school_name) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$email, $hash, $fullName, $role, $eduLevel, $major, $school]);
            $userId = (int)$db->lastInsertId();

            // seed lesson progress: lesson 1 unlocked, rest locked
            Progress::ensureRows($userId);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            return [false, 'สมัครสมาชิกไม่สำเร็จ กรุณาลองใหม่'];
        }

        return [true, $userId];
    }

    public static function attempt(string $email, string $password): bool
    {
        $stmt = Database::get()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
