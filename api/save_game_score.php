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

$code = (string)($body['code'] ?? '');
$score = max(0, (int)($body['score'] ?? 0));
$timeTaken = max(0, (int)($body['time_taken_sec'] ?? 0));

$db = Database::get();
$stmt = $db->prepare('SELECT id FROM games WHERE code = ?');
$stmt->execute([$code]);
$gameId = $stmt->fetchColumn();
if (!$gameId) {
    http_response_code(404);
    echo json_encode(['error' => 'unknown_game']);
    exit;
}

$ins = $db->prepare('INSERT INTO game_scores (user_id, game_id, score, time_taken_sec) VALUES (?,?,?,?)');
$ins->execute([(int)$user['id'], (int)$gameId, $score, $timeTaken]);

Progress::addXp((int)$user['id'], (int)($body['xp'] ?? 0));

$bestStmt = $db->prepare('SELECT MAX(score) FROM game_scores WHERE user_id = ? AND game_id = ?');
$bestStmt->execute([(int)$user['id'], (int)$gameId]);
echo json_encode(['ok' => true, 'best' => (int)$bestStmt->fetchColumn()]);
