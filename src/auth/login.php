<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;

setCorsHeaders();

use Firebase\JWT\JWT;

function sendError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Metóda nie je povolená', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    sendError('Neplatné JSON dáta');
}

// validacia vstupov
$errors = [];

$email    = trim($body['email'] ?? '');
$password = $body['password'] ?? '';
$totp     = trim($body['totp'] ?? '');

if (empty($email)) {
    $errors[] = 'Email je povinný';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email nie je v správnom formáte';
}

if (empty($password)) {
    $errors[] = 'Heslo je povinné';
}

if (empty($totp)) {
    $errors[] = 'Kód 2FA je povinný';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}


$pdo = connectDatabase();

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, password_hash, tfa_secret, created_at
    FROM users
    WHERE email = :email
    LIMIT 1
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    sendError('Nesprávne prihlasovacie údaje', 401);
}

// 2FA
$tfa = new TwoFactorAuth(new BaconQrCodeProvider());

// discrepancy=2 - kód platí 60 sekúnd
if (!$tfa->verifyCode($user['tfa_secret'], $totp, 2)) {
    sendError('Nesprávny 2FA kód', 401);
}


// JWT TOKEN
$issuedAt  = time();
$expiresAt = $issuedAt + (60 * 60 * 24); // Token - 24 hodín

$payload = [
        'iat'        => $issuedAt,
        'exp'        => $expiresAt,
        'user_id'    => $user['id'],
        'email'      => $user['email'],
        'first_name' => $user['first_name'],
        'last_name'  => $user['last_name'],
];


$token = JWT::encode($payload, JWT_SECRET, 'HS256');

// save login to history
$pdo->prepare("
    INSERT INTO logins (user_id, login_type)
    VALUES (:user_id, 'local')
")->execute([':user_id' => $user['id']]);


echo json_encode([
        'token' => $token,
        'user'  => [
                'id'         => $user['id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
        ],
], JSON_UNESCAPED_UNICODE);
exit;