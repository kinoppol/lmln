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
