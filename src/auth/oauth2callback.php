<?php
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client;
use Firebase\JWT\JWT;

// Nastavenie Google klienta zo suboru client_secret.json
// Subor stiahni z Google Cloud Console a uloz do root adresara projektu
$client = new Client();
$client->setAuthConfig('/var/www/node41.webte.fei.stuba.sk/client_secret.json');
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope(['email', 'profile']);
$client->setIncludeGrantedScopes(true);
$client->setAccessType('offline');

// presmerovanie na Google

if (!isset($_GET['code']) && !isset($_GET['error'])) {
    $state = bin2hex(random_bytes(16));
    $client->setState($state);
    $_SESSION['oauth_state'] = $state;

    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
}


// Google presmeroval spat s chybou

if (isset($_GET['error'])) {
    header('Location: ' . FRONTEND_URL . '/login?error=google_failed');
    exit;
}

// google nas presmeroval spat s code
if (isset($_GET['code'])) {

    // Overenie state - ochrana pred CSRF utokom
    if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        header('Location: ' . FRONTEND_URL . '/login?error=state_mismatch');
        exit;
    }

    // Vymena authorization code za access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        header('Location: ' . FRONTEND_URL . '/login?error=token_failed');
        exit;
    }

    $client->setAccessToken($token);

    // Ziskanie profilu pouzivatela od Google
    $oauth2   = new Google\Service\Oauth2($client);
    $userInfo = $oauth2->userinfo->get();

    $googleId  = $userInfo->getId();
    $email     = $userInfo->getEmail();
    $firstName = $userInfo->getGivenName();
    $lastName  = $userInfo->getFamilyName();

    // db - najdi alebo vytvor pouzivatela
    $pdo = connectDatabase();
    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE google_id = :google_id OR email = :email
        LIMIT 1
    ");
    $stmt->execute([':google_id' => $googleId, ':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (empty($user['google_id'])) {
            $pdo->prepare("UPDATE users SET google_id = :google_id WHERE id = :id")
                ->execute([':google_id' => $googleId, ':id' => $user['id']]);
        }
    } else {
        $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, google_id)
            VALUES (:first_name, :last_name, :email, :google_id)
        ")->execute([
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':email'      => $email,
            ':google_id'  => $googleId,
        ]);


        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ulozenie zaznamu o prihlaseni do historie
    $pdo->prepare("
        INSERT INTO logins (user_id, login_type)
        VALUES (:user_id, 'google')
    ")->execute([':user_id' => $user['id']]);


    // VYDANIE JWT TOKENU
    $payload = [
        'iat'        => time(),
        'exp'        => time() + (60 * 60 * 24),
        'user_id'    => $user['id'],
        'email'      => $email,
        'first_name' => $firstName ?? $user['first_name'],
        'last_name'  => $lastName  ?? $user['last_name'],
    ];

    $jwtToken = JWT::encode($payload, JWT_SECRET, 'HS256');


    header('Location: ' . FRONTEND_URL . '/auth/callback?token=' . urlencode($jwtToken));
    exit;
}
