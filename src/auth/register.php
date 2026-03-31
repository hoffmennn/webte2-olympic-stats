<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

setCorsHeaders();


function sendError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Metóda nie je povolená', 405);
}

// Načítanie JSON body
$body = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    sendError('Neplatné JSON dáta');
}


// validacia
$errors = [];

$firstName = trim($body['first_name'] ?? '');
if (empty($firstName)) {
    $errors[] = 'Meno je povinné';
} elseif (mb_strlen($firstName) > 50) {
    $errors[] = 'Meno môže mať max. 50 znakov';
}

$lastName = trim($body['last_name'] ?? '');
if (empty($lastName)) {
    $errors[] = 'Priezvisko je povinné';
} elseif (mb_strlen($lastName) > 50) {
    $errors[] = 'Priezvisko môže mať max. 50 znakov';
}

$email = trim($body['email'] ?? '');
if (empty($email)) {
    $errors[] = 'Email je povinný';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email nie je v správnom formáte';
} elseif (mb_strlen($email) > 100) {
    $errors[] = 'Email môže mať max. 100 znakov';
}

$password = $body['password'] ?? '';
if (empty($password)) {
    $errors[] = 'Heslo je povinné';
} elseif (mb_strlen($password) < 8) {
    $errors[] = 'Heslo musí mať aspoň 8 znakov';
}

$passwordRepeat = $body['password_repeat'] ?? '';
if ($password !== $passwordRepeat) {
    $errors[] = 'Heslá sa nezhodujú';
}


if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

// db
$pdo = connectDatabase();

// Kontrola či email už existuje
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
if ($stmt->fetchColumn()) {
    sendError('Používateľ s týmto emailom už existuje', 409);
}

// Hash hesla - ARGON2ID
$passwordHash = password_hash($password, PASSWORD_ARGON2ID);

// Generovanie 2FA
$tfa        = new TwoFactorAuth(new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'));
$tfaSecret  = $tfa->createSecret();
$qrCode     = $tfa->getQRCodeImageAsDataUri('Výsledky olympijských športovcov', $tfaSecret);

try {
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, password_hash, `tfa_secret`)
        VALUES (:first_name, :last_name, :email, :password_hash, :tfa_secret)
    ");
    $stmt->execute([
            ':first_name'    => $firstName,
            ':last_name'     => $lastName,
            ':email'         => $email,
            ':password_hash' => $passwordHash,
            ':tfa_secret'    => $tfaSecret,
    ]);
} catch (PDOException $e) {
    sendError($e->getMessage(), 500);
}

// Vue zobrazí QR kód používateľovi na nascanovanie
http_response_code(201);
echo json_encode([
        'message' => 'Registrácia prebehla úspešne',
        'qr_code' => $qrCode,
        'secret'  => $tfaSecret,
], JSON_UNESCAPED_UNICODE);
exit;