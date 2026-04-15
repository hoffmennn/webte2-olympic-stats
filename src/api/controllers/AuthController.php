<?php

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use Firebase\JWT\JWT;
use Google\Client as GoogleClient;
use Google\Service\Oauth2 as GoogleOauth2;

class AuthController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = connectDatabase();
    }

    // POST /auth/register
    public function register()
    {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            Response::json(['error' => 'Neplatné JSON dáta'], 400);
        }

        $errors = [];
        $firstName = trim($body['first_name'] ?? '');
        $lastName  = trim($body['last_name'] ?? '');
        $email     = trim($body['email'] ?? '');
        $password  = $body['password'] ?? '';
        $passwordRepeat = $body['password_repeat'] ?? '';

        // Validácia
        if (empty($firstName)) $errors[] = 'Meno je povinné';
        elseif (mb_strlen($firstName) > 50) $errors[] = 'Meno môže mať max. 50 znakov';

        if (empty($lastName)) $errors[] = 'Priezvisko je povinné';
        elseif (mb_strlen($lastName) > 50) $errors[] = 'Priezvisko môže mať max. 50 znakov';

        if (empty($email)) $errors[] = 'Email je povinný';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email nie je v správnom formáte';
        elseif (mb_strlen($email) > 100) $errors[] = 'Email môže mať max. 100 znakov';

        if (empty($password)) $errors[] = 'Heslo je povinné';
        elseif (mb_strlen($password) < 8) $errors[] = 'Heslo musí mať aspoň 8 znakov';

        if ($password !== $passwordRepeat) $errors[] = 'Heslá sa nezhodujú';

        if (!empty($errors)) {
            Response::json(['errors' => $errors], 422);
        }

        // Kontrola emailu
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetchColumn()) {
            Response::json(['error' => 'Používateľ s týmto emailom už existuje'], 409);
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

        // Generovanie 2FA
        $tfa = new TwoFactorAuth(new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'));
        $tfaSecret = $tfa->createSecret();
        $qrCode = $tfa->getQRCodeImageAsDataUri('Výsledky olympijských športovcov', $tfaSecret);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (first_name, last_name, email, password_hash, tfa_secret)
                VALUES (:first_name, :last_name, :email, :password_hash, :tfa_secret)
            ");
            $stmt->execute([
                ':first_name'    => $firstName,
                ':last_name'     => $lastName,
                ':email'         => $email,
                ':password_hash' => $passwordHash,
                ':tfa_secret'    => $tfaSecret,
            ]);

            Response::json([
                'message' => 'Registrácia prebehla úspešne',
                'qr_code' => $qrCode,
                'secret'  => $tfaSecret,
            ], 201);

        } catch (PDOException $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // POST /auth/login
    public function login()
    {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            Response::json(['error' => 'Neplatné JSON dáta'], 400);
        }

        $errors = [];
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $totp     = trim($body['totp'] ?? '');

        if (empty($email)) $errors[] = 'Email je povinný';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email nie je v správnom formáte';

        if (empty($password)) $errors[] = 'Heslo je povinné';
        if (empty($totp)) $errors[] = 'Kód 2FA je povinný';

        if (!empty($errors)) {
            Response::json(['errors' => $errors], 422);
        }

        $stmt = $this->pdo->prepare("
            SELECT id, first_name, last_name, email, password_hash, tfa_secret
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::json(['error' => 'Nesprávne prihlasovacie údaje'], 401);
        }

        // 2FA
        $tfa = new TwoFactorAuth(new BaconQrCodeProvider());
        if (!$tfa->verifyCode($user['tfa_secret'], $totp, 2)) {
            Response::json(['error' => 'Nesprávny 2FA kód'], 401);
        }

        // JWT Token
        $payload = [
            'iat'        => time(),
            'exp'        => time() + (60 * 60 * 24),
            'user_id'    => $user['id'],
            'email'      => $user['email'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
        ];
        $token = JWT::encode($payload, JWT_SECRET, 'HS256');

        // História
        $this->pdo->prepare("INSERT INTO logins (user_id, login_type) VALUES (:user_id, 'local')")
            ->execute([':user_id' => $user['id']]);

        Response::json([
            'token' => $token,
            'user'  => [
                'id'         => $user['id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
            ],
        ], 200);
    }

    // GET /auth/google/callback
    public function googleCallback()
    {
        // Google OAuth vyžaduje session pre uchovanie 'state'
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $client = new GoogleClient();
        $client->setAuthConfig('/var/www/node41.webte.fei.stuba.sk/client_secret.json');
        $client->setRedirectUri(GOOGLE_REDIRECT_URI);
        $client->addScope(['email', 'profile']);
        $client->setIncludeGrantedScopes(true);
        $client->setAccessType('offline');

        // Presmerovanie na Google (Iniciácia)
        if (!isset($_GET['code']) && !isset($_GET['error'])) {
            $state = bin2hex(random_bytes(16));
            $client->setState($state);
            $_SESSION['oauth_state'] = $state;

            $authUrl = $client->createAuthUrl();
            header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
            exit;
        }

        // Google vrátil chybu
        if (isset($_GET['error'])) {
            header('Location: ' . FRONTEND_URL . '/login?error=google_failed');
            exit;
        }

        // Google vrátil autorizačný kód
        if (isset($_GET['code'])) {
            if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
                header('Location: ' . FRONTEND_URL . '/login?error=state_mismatch');
                exit;
            }

            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
            if (isset($token['error'])) {
                header('Location: ' . FRONTEND_URL . '/login?error=token_failed');
                exit;
            }

            $client->setAccessToken($token);
            $oauth2 = new GoogleOauth2($client);
            $userInfo = $oauth2->userinfo->get();

            $googleId  = $userInfo->getId();
            $email     = $userInfo->getEmail();
            $firstName = $userInfo->getGivenName();
            $lastName  = $userInfo->getFamilyName();

            // Vyhľadanie alebo vytvorenie používateľa
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE google_id = :google_id OR email = :email LIMIT 1");
            $stmt->execute([':google_id' => $googleId, ':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (empty($user['google_id'])) {
                    $this->pdo->prepare("UPDATE users SET google_id = :google_id WHERE id = :id")
                        ->execute([':google_id' => $googleId, ':id' => $user['id']]);
                }
            } else {
                $this->pdo->prepare("
                    INSERT INTO users (first_name, last_name, email, google_id)
                    VALUES (:first_name, :last_name, :email, :google_id)
                ")->execute([
                    ':first_name' => $firstName,
                    ':last_name'  => $lastName,
                    ':email'      => $email,
                    ':google_id'  => $googleId,
                ]);

                $user = ['id' => $this->pdo->lastInsertId(), 'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email];
            }

            $this->pdo->prepare("INSERT INTO logins (user_id, login_type) VALUES (:user_id, 'google')")
                ->execute([':user_id' => $user['id']]);

            // Vydanie JWT tokenu a presmerovanie na Frontend
            $payload = [
                'iat'        => time(),
                'exp'        => time() + (60 * 60 * 24),
                'user_id'    => $user['id'],
                'email'      => $email,
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
            ];

            $jwtToken = JWT::encode($payload, JWT_SECRET, 'HS256');

            // Presmerujeme vue aplikáciu a v URL pošleme token
            header('Location: ' . FRONTEND_URL . '/auth/callback?token=' . urlencode($jwtToken));
            exit;
        }
    }
}