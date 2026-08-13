<?php
declare(strict_types=1);

final class QuizGrader
{
    public static function quiz(int $quizId): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM quizzes WHERE id = ?');
        $stmt->execute([$quizId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findQuiz(int $courseId, string $kind, ?int $lessonId = null): ?array
    {
        if ($kind === 'lesson') {
            $stmt = Database::get()->prepare("SELECT * FROM quizzes WHERE course_id = ? AND kind = 'lesson' AND lesson_id = ?");
            $stmt->execute([$courseId, $lessonId]);
        } else {
            $stmt = Database::get()->prepare('SELECT * FROM quizzes WHERE course_id = ? AND kind = ?');
            $stmt->execute([$courseId, $kind]);
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array> ordered questions with nested options (is_correct stripped) */
    public static function questions(int $quizId): array
    {
        $db = Database::get();
        $qStmt = $db->prepare('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY position');
        $qStmt->execute([$quizId]);
        $questions = $qStmt->fetchAll();

        $oStmt = $db->prepare('SELECT * FROM quiz_options WHERE question_id = ? ORDER BY position');
        foreach ($questions as &$q) {
            $oStmt->execute([$q['id']]);
            $q['options'] = $oStmt->fetchAll();
        }
        return $questions;
    }

    public static function startAttempt(int $userId, int $quizId): int
    {
        $stmt = Database::get()->prepare('INSERT INTO quiz_attempts (user_id, quiz_id, total) VALUES (?,?, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?))');
        $stmt->execute([$userId, $quizId, $quizId]);
        return (int)Database::get()->lastInsertId();
    }

    public static function attempt(int $attemptId, int $userId): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM quiz_attempts WHERE id = ? AND user_id = ?');
        $stmt->execute([$attemptId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{correct:bool, correct_option_id:int, explanation:string} */
    public static function submitAnswer(int $attemptId, int $questionId, ?int $selectedOptionId): array
    {
        $db = Database::get();

        $optStmt = $db->prepare('SELECT id, is_correct FROM quiz_options WHERE question_id = ?');
        $optStmt->execute([$questionId]);
        $options = $optStmt->fetchAll();
        $correctOptionId = null;
        foreach ($options as $o) {
            if ((int)$o['is_correct'] === 1) {
                $correctOptionId = (int)$o['id'];
            }
        }
        $isCorrect = $selectedOptionId !== null && $selectedOptionId === $correctOptionId;

        $exists = $db->prepare('SELECT id FROM quiz_answers WHERE attempt_id = ? AND question_id = ?');
        $exists->execute([$attemptId, $questionId]);
        if ($existingId = $exists->fetchColumn()) {
            $upd = $db->prepare('UPDATE quiz_answers SET selected_option_id = ?, is_correct = ? WHERE id = ?');
            $upd->execute([$selectedOptionId, $isCorrect ? 1 : 0, $existingId]);
        } else {
            $ins = $db->prepare('INSERT INTO quiz_answers (attempt_id, question_id, selected_option_id, is_correct) VALUES (?,?,?,?)');
            $ins->execute([$attemptId, $questionId, $selectedOptionId, $isCorrect ? 1 : 0]);
        }

        $expStmt = $db->prepare('SELECT explanation FROM quiz_questions WHERE id = ?');
        $expStmt->execute([$questionId]);

        return [
            'correct' => $isCorrect,
            'correct_option_id' => (int)$correctOptionId,
            'explanation' => (string)$expStmt->fetchColumn(),
        ];
    }

    /** @return array{score:int,total:int,passed:bool} */
    public static function finishAttempt(int $attemptId): array
    {
        $db = Database::get();
        $stmt = $db->prepare(
            'SELECT a.total, q.pass_threshold_pct, (SELECT COUNT(*) FROM quiz_answers WHERE attempt_id = a.id AND is_correct = 1) AS score
             FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id WHERE a.id = ?'
        );
        $stmt->execute([$attemptId]);
        $row = $stmt->fetch();
        $score = (int)$row['score'];
        $total = (int)$row['total'];

        $upd = $db->prepare('UPDATE quiz_attempts SET score = ?, completed_at = NOW() WHERE id = ?');
        $upd->execute([$score, $attemptId]);

        $pct = $total > 0 ? ($score / $total) * 100 : 0;
        return ['score' => $score, 'total' => $total, 'passed' => $pct >= (int)$row['pass_threshold_pct']];
    }

    /**
     * เฉลยรายข้อของการทำแบบทดสอบครั้งนั้น — ไล่จาก quiz_questions เป็นหลัก
     * เพื่อให้ข้อที่ไม่ได้ตอบก็ยังขึ้นในเฉลย (is_correct จะเป็น NULL)
     */
    public static function reviewRows(int $attemptId): array
    {
        $db = Database::get();
        $stmt = $db->prepare(
            'SELECT qq.position, qq.question_text, qq.code_snippet, qq.is_mono, qq.explanation,
                    qa.is_correct, qa.selected_option_id,
                    (SELECT option_text FROM quiz_options WHERE id = qa.selected_option_id) AS selected_text,
                    (SELECT option_text FROM quiz_options WHERE question_id = qq.id AND is_correct = 1 LIMIT 1) AS correct_text
             FROM quiz_questions qq
             LEFT JOIN quiz_answers qa ON qa.question_id = qq.id AND qa.attempt_id = ?
             WHERE qq.quiz_id = (SELECT quiz_id FROM quiz_attempts WHERE id = ?)
             ORDER BY qq.position'
        );
        $stmt->execute([$attemptId, $attemptId]);
        return $stmt->fetchAll();
    }
}
