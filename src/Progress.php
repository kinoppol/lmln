<?php
declare(strict_types=1);

require_once __DIR__ . '/TerminalRules.php';

final class Progress
{
    public static function level(int $xp): int
    {
        return max(1, intdiv($xp, 200) + 1);
    }

    public static function addXp(int $userId, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }
        $stmt = Database::get()->prepare('UPDATE users SET xp = xp + ? WHERE id = ?');
        $stmt->execute([$amount, $userId]);
    }

    /** @return array<int,array> lesson_id => progress row */
    public static function all(int $userId): array
    {
        $stmt = Database::get()->prepare(
            'SELECT p.*, l.position FROM user_lesson_progress p
             JOIN lessons l ON l.id = p.lesson_id
             WHERE p.user_id = ? ORDER BY l.position'
        );
        $stmt->execute([$userId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['lesson_id']] = $row;
        }
        return $out;
    }

    public static function forLesson(int $userId, int $lessonId): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM user_lesson_progress WHERE user_id = ? AND lesson_id = ?');
        $stmt->execute([$userId, $lessonId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function doneCount(int $userId): int
    {
        $stmt = Database::get()->prepare("SELECT COUNT(*) FROM user_lesson_progress WHERE user_id = ? AND status = 'completed'");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function tasksAllDone(int $userId, int $lessonId): bool
    {
        $row = self::forLesson($userId, $lessonId);
        if (!$row) {
            return false;
        }
        $done = json_decode($row['tasks_done'], true) ?: [];
        $stmt = Database::get()->prepare('SELECT COUNT(*) FROM lesson_tasks WHERE lesson_id = ?');
        $stmt->execute([$lessonId]);
        $total = (int)$stmt->fetchColumn();
        return $total > 0 && count($done) === $total;
    }

    public static function canAccessLesson(int $userId, int $lessonId): bool
    {
        $row = self::forLesson($userId, $lessonId);
        return $row !== null && $row['status'] !== 'locked';
    }

    /**
     * Re-check every task rule for a lesson against the submitted command
     * history + VFS snapshot, persist which ones now pass, return the set.
     *
     * @return array{doneTaskIds:int[], allDone:bool, totalTasks:int}
     */
    public static function evaluateTasks(int $lessonId, array $history, ?array $vfs): array
    {
        $stmt = Database::get()->prepare('SELECT id, rule_type, rule_params FROM lesson_tasks WHERE lesson_id = ? ORDER BY position');
        $stmt->execute([$lessonId]);
        $tasks = $stmt->fetchAll();

        $done = [];
        foreach ($tasks as $t) {
            $params = json_decode($t['rule_params'], true) ?: [];
            if (TerminalRules::evaluate($t['rule_type'], $params, $history, $vfs)) {
                $done[] = (int)$t['id'];
            }
        }
        return ['doneTaskIds' => $done, 'allDone' => count($done) === count($tasks) && count($tasks) > 0, 'totalTasks' => count($tasks)];
    }

    public static function saveTaskProgress(int $userId, int $lessonId, array $doneTaskIds): void
    {
        $stmt = Database::get()->prepare('UPDATE user_lesson_progress SET tasks_done = ? WHERE user_id = ? AND lesson_id = ?');
        $stmt->execute([json_encode(array_values($doneTaskIds)), $userId, $lessonId]);
    }

    /**
     * Mark a lesson completed if its tasks are all done AND its lesson-quiz
     * attempt passed. Unlocks the next lesson, awards XP. Returns true if the
     * lesson newly transitioned to completed.
     */
    public static function tryCompleteLesson(int $userId, int $lessonId, bool $tasksAllDone, ?int $passedAttemptId): bool
    {
        $db = Database::get();
        $row = self::forLesson($userId, $lessonId);
        if (!$row || $row['status'] === 'completed') {
            return false;
        }
        if (!$tasksAllDone || !$passedAttemptId) {
            return false;
        }

        $lessonStmt = $db->prepare('SELECT position, xp_reward, course_id FROM lessons WHERE id = ?');
        $lessonStmt->execute([$lessonId]);
        $lesson = $lessonStmt->fetch();
        if (!$lesson) {
            return false;
        }

        $db->beginTransaction();
        try {
            $upd = $db->prepare("UPDATE user_lesson_progress SET status = 'completed', lesson_quiz_attempt_id = ?, completed_at = NOW() WHERE user_id = ? AND lesson_id = ?");
            $upd->execute([$passedAttemptId, $userId, $lessonId]);

            $next = $db->prepare('SELECT id FROM lessons WHERE course_id = ? AND position = ?');
            $next->execute([$lesson['course_id'], (int)$lesson['position'] + 1]);
            $nextLesson = $next->fetch();
            if ($nextLesson) {
                $unlock = $db->prepare("UPDATE user_lesson_progress SET status = 'unlocked' WHERE user_id = ? AND lesson_id = ? AND status = 'locked'");
                $unlock->execute([$userId, $nextLesson['id']]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            return false;
        }

        self::addXp($userId, (int)$lesson['xp_reward']);
        return true;
    }

    public static function pretestDone(int $userId, int $courseId): bool
    {
        $stmt = Database::get()->prepare(
            "SELECT COUNT(*) FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id
             WHERE a.user_id = ? AND q.course_id = ? AND q.kind = 'pretest' AND a.completed_at IS NOT NULL"
        );
        $stmt->execute([$userId, $courseId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * ประตูชั้นแรกของโซนเกม: ต้อง "เริ่มเรียน" ก่อน คือทำแบบทดสอบก่อนเรียน
     * และผ่านบทเรียนอย่างน้อย 1 บท
     *
     * @return array{unlocked:bool, missing:array<int,array{text:string,href:string,cta:string}>}
     */
    public static function arcadeGate(int $userId, int $courseId = 1): array
    {
        $missing = [];
        if (!self::pretestDone($userId, $courseId)) {
            $missing[] = [
                'text' => 'ทำแบบทดสอบก่อนเรียนให้เสร็จก่อน',
                'href' => url('/quiz.php?kind=pretest'),
                'cta' => 'ไปทำแบบทดสอบก่อนเรียน',
            ];
        }
        if (self::doneCount($userId) < 1) {
            $missing[] = [
                'text' => 'เรียนและผ่านบทเรียนอย่างน้อย 1 บท',
                'href' => url('/course.php'),
                'cta' => 'ไปหน้าบทเรียน',
            ];
        }
        return ['unlocked' => $missing === [], 'missing' => $missing];
    }

    /**
     * ประตูของเกมแต่ละเกม: ต้องผ่านบทเรียนที่เกมนั้นใช้คำสั่งครบทุกบท
     * (games.required_lessons เก็บเป็น JSON ของ lessons.position)
     *
     * @return array{unlocked:bool, required:int[], missing:array<int,array>, doneOf:string}
     */
    public static function gameGate(int $userId, array $game, int $courseId = 1): array
    {
        $required = array_values(array_filter(array_map(
            'intval',
            json_decode((string)($game['required_lessons'] ?? '[]'), true) ?: []
        )));

        if (!$required) {
            return ['unlocked' => true, 'required' => [], 'missing' => [], 'doneOf' => '0/0'];
        }

        $in = implode(',', array_map('strval', $required));
        $stmt = Database::get()->prepare(
            "SELECT l.id, l.position, l.title_th, l.commands_summary, p.status
             FROM lessons l
             LEFT JOIN user_lesson_progress p ON p.lesson_id = l.id AND p.user_id = ?
             WHERE l.course_id = ? AND l.position IN ($in)
             ORDER BY l.position"
        );
        $stmt->execute([$userId, $courseId]);
        $rows = $stmt->fetchAll();

        $missing = [];
        foreach ($rows as $r) {
            if (($r['status'] ?? null) !== 'completed') {
                $missing[] = $r;
            }
        }
        return [
            'unlocked' => $missing === [],
            'required' => $required,
            'missing' => $missing,
            'doneOf' => (count($rows) - count($missing)) . '/' . count($rows),
        ];
    }

    public static function courseCompleted(int $userId, int $courseId): bool
    {
        $stmt = Database::get()->prepare(
            "SELECT COUNT(*) FROM lessons l
             LEFT JOIN user_lesson_progress p ON p.lesson_id = l.id AND p.user_id = ?
             WHERE l.course_id = ? AND (p.status IS NULL OR p.status <> 'completed')"
        );
        $stmt->execute([$userId, $courseId]);
        return (int)$stmt->fetchColumn() === 0;
    }

    public static function bestPosttestScore(int $userId, int $courseId): ?array
    {
        $stmt = Database::get()->prepare(
            "SELECT a.score, a.total FROM quiz_attempts a
             JOIN quizzes q ON q.id = a.quiz_id
             WHERE a.user_id = ? AND q.course_id = ? AND q.kind = 'posttest' AND a.completed_at IS NOT NULL
             ORDER BY a.score DESC LIMIT 1"
        );
        $stmt->execute([$userId, $courseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function certificateEligible(int $userId, int $courseId): bool
    {
        if (!self::courseCompleted($userId, $courseId)) {
            return false;
        }
        $best = self::bestPosttestScore($userId, $courseId);
        if (!$best || (int)$best['total'] === 0) {
            return false;
        }
        $pct = ($best['score'] / $best['total']) * 100;
        return $pct >= PASS_PCT_POSTTEST;
    }

    public static function issueCertificateIfEligible(int $userId, int $courseId): ?string
    {
        if (!self::certificateEligible($userId, $courseId)) {
            return null;
        }
        $db = Database::get();
        $stmt = $db->prepare('SELECT cert_code FROM certificates WHERE user_id = ? AND course_id = ?');
        $stmt->execute([$userId, $courseId]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (string)$existing;
        }
        $code = 'LNX101-' . date('Y') . '-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(bin2hex(random_bytes(2)));
        $ins = $db->prepare('INSERT INTO certificates (user_id, course_id, cert_code) VALUES (?,?,?)');
        $ins->execute([$userId, $courseId, $code]);
        return $code;
    }
}
