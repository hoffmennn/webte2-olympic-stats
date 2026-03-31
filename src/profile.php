<?php
// api/user/profile.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

$auth = requireAuth();

$userId = $auth['user_id'];

$pdo  = connectDatabase();
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, created_at FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['user' => $user], JSON_UNESCAPED_UNICODE);
exit;