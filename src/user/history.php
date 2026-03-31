<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/AuthMiddleware.php';

setCorsHeaders();

$auth   = requireAuth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metóda nie je povolená'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo  = connectDatabase();
$stmt = $pdo->prepare("
    SELECT id, login_type, created_at
    FROM logins
    WHERE user_id = :user_id
    ORDER BY created_at DESC
");
$stmt->execute([':user_id' => $userId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['history' => $history], JSON_UNESCAPED_UNICODE);
exit;