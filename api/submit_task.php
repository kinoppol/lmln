<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$user = Auth::currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !Csrf::verify($body['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf']);
    exit;
}

$lessonId = (int)($body['lesson_id'] ?? 0);
$history = is_array($body['history'] ?? null) ? array_slice($body['history'], -500) : [];
$vfs = is_array($body['vfs'] ?? null) ? $body['vfs'] : null;

$userId = (int)$user['id'];
if (!$lessonId || !Progress::canAccessLesson($userId, $lessonId)) {
    http_response_code(403);
    echo json_encode(['error' => 'lesson_locked']);
    exit;
}

$result = Progress::evaluateTasks($lessonId, $history, $vfs);
Progress::saveTaskProgress($userId, $lessonId, $result['doneTaskIds']);

$stmt = Database::get()->prepare('SELECT id, position, label FROM lesson_tasks WHERE lesson_id = ? ORDER BY position');
$stmt->execute([$lessonId]);
$tasks = array_map(function ($t) use ($result) {
    return ['id' => (int)$t['id'], 'label' => $t['label'], 'done' => in_array((int)$t['id'], $result['doneTaskIds'], true)];
}, $stmt->fetchAll());

echo json_encode(['tasks' => $tasks, 'allDone' => $result['allDone']]);
