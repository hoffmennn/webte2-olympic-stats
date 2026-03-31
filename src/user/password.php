<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/AuthMiddleware.php';

setCorsHeaders();

$auth   = requireAuth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Metóda nie je povolená'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$pdo  = connectDatabase();

// Načítame aktuálne heslo z DB
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Validácia
$errors = [];

$currentPassword = $body['current_password'] ?? '';
$newPassword     = $body['new_password'] ?? '';
$repeatPassword  = $body['repeat_password'] ?? '';

if (empty($currentPassword)) {
    $errors[] = 'Aktuálne heslo je povinné';
}

// Overenie aktuálneho hesla
if (!empty($currentPassword) && !password_verify($currentPassword, $user['password_hash'])) {
    $errors[] = 'Aktuálne heslo nie je správne';
}

if (empty($newPassword)) {
    $errors[] = 'Nové heslo je povinné';
} elseif (mb_strlen($newPassword) < 8) {
    $errors[] = 'Nové heslo musí mať aspoň 8 znakov';
}

if ($newPassword !== $repeatPassword) {
    $errors[] = 'Heslá sa nezhodujú';
}

// Kontrola že nové heslo nie je rovnaké ako staré
if (!empty($newPassword) && password_verify($newPassword, $user['password_hash'])) {
    $errors[] = 'Nové heslo musí byť iné ako aktuálne';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

$newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
$pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")
    ->execute([':hash' => $newHash, ':id' => $userId]);

echo json_encode(['message' => 'Heslo bolo zmenené'], JSON_UNESCAPED_UNICODE);
exit;