<?php


require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/AuthMiddleware.php';

setCorsHeaders();

// Každá požiadavka musí mať platný JWT token
$auth   = requireAuth();
$userId = $auth['user_id'];
$pdo    = connectDatabase();

// -------------------------
// GET - načítanie profilu
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, google_id, password_hash, created_at
        FROM users
        WHERE id = :id
    ");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Používateľ nenájdený'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Pridáme info či má Google účet prepojený
    // Vue to použije na zobrazenie správnych možností
    $user['has_google']   = !empty($user['google_id']);
    $user['has_password'] = !empty($user['password_hash'] ?? null);
    unset($user['google_id']);

    echo json_encode(['user' => $user], JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------------
// PUT - úprava mena a priezviska
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);

    // Validácia
    $errors = [];

    $firstName = trim($body['first_name'] ?? '');
    $lastName  = trim($body['last_name'] ?? '');

    if (empty($firstName)) {
        $errors[] = 'Meno je povinné';
    } elseif (mb_strlen($firstName) > 50) {
        $errors[] = 'Meno môže mať max. 50 znakov';
    }

    if (empty($lastName)) {
        $errors[] = 'Priezvisko je povinné';
    } elseif (mb_strlen($lastName) > 50) {
        $errors[] = 'Priezvisko môže mať max. 50 znakov';
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->prepare("
        UPDATE users SET first_name = :first_name, last_name = :last_name
        WHERE id = :id
    ")->execute([
        ':first_name' => $firstName,
        ':last_name'  => $lastName,
        ':id'         => $userId,
    ]);

    echo json_encode(['message' => 'Profil bol aktualizovaný'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Metóda nie je povolená'], JSON_UNESCAPED_UNICODE);
exit;