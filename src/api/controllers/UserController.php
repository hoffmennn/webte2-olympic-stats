<?php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Users', description: 'Správa používateľského profilu')]
class UserController
{
    private PDO $pdo;
    private int $userId;

    public function __construct()
    {
        $this->pdo = connectDatabase();
        $payload = AuthMiddleware::requireAuth();
        $this->userId = $payload['user_id'];
    }

    // GET /users/me
    #[OA\Get(
        path: '/users/me',
        summary: 'Získať profil prihláseného používateľa',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil používateľa',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id',           type: 'integer', example: 1),
                                new OA\Property(property: 'first_name',   type: 'string',  example: 'Ján'),
                                new OA\Property(property: 'last_name',    type: 'string',  example: 'Novák'),
                                new OA\Property(property: 'email',        type: 'string',  example: 'jan@example.com'),
                                new OA\Property(property: 'created_at',   type: 'string',  format: 'date-time'),
                                new OA\Property(property: 'has_google',   type: 'boolean', example: false),
                                new OA\Property(property: 'has_password', type: 'boolean', example: true),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Neautorizovaný prístup'),
            new OA\Response(response: 404, description: 'Používateľ nenájdený'),
        ]
    )]
    public function showProfile()
    {
        $stmt = $this->pdo->prepare("
            SELECT id, first_name, last_name, email, google_id, password_hash, created_at
            FROM users
            WHERE id = :id
        ");
        $stmt->execute([':id' => $this->userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['error' => 'Používateľ nenájdený'], 404);
        }

        $user['has_google']   = !empty($user['google_id']);
        $user['has_password'] = !empty($user['password_hash']);

        unset($user['google_id']);
        unset($user['password_hash']);

        Response::json(['user' => $user], 200);
    }

    // PUT /users/me
    #[OA\Put(
        path: '/users/me',
        summary: 'Aktualizovať meno a priezvisko prihláseného používateľa',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', maxLength: 50, example: 'Ján'),
                    new OA\Property(property: 'last_name',  type: 'string', maxLength: 50, example: 'Novák'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil bol aktualizovaný',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Profil bol aktualizovaný')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validačné chyby',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Neautorizovaný prístup'),
        ]
    )]
    public function updateProfile()
    {
        $body = json_decode(file_get_contents('php://input'), true);
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
            Response::json(['errors' => $errors], 422);
        }

        $stmt = $this->pdo->prepare("
            UPDATE users SET first_name = :first_name, last_name = :last_name
            WHERE id = :id
        ");
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':id'         => $this->userId,
        ]);

        Response::json(['message' => 'Profil bol aktualizovaný'], 200);
    }

    // PUT /users/me/password
    #[OA\Put(
        path: '/users/me/password',
        summary: 'Zmeniť heslo prihláseného používateľa',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password', 'repeat_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'StaréHeslo123'),
                    new OA\Property(property: 'new_password',     type: 'string', format: 'password', minLength: 8, example: 'NovéHeslo123'),
                    new OA\Property(property: 'repeat_password',  type: 'string', format: 'password', example: 'NovéHeslo123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Heslo bolo zmenené',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Heslo bolo zmenené')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validačné chyby',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Neautorizovaný prístup'),
            new OA\Response(response: 404, description: 'Používateľ nenájdený'),
        ]
    )]
    public function updatePassword()
    {
        $body = json_decode(file_get_contents('php://input'), true);

        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute([':id' => $this->userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['error' => 'Používateľ nenájdený'], 404);
        }

        $errors = [];
        $currentPassword = $body['current_password'] ?? '';
        $newPassword     = $body['new_password'] ?? '';
        $repeatPassword  = $body['repeat_password'] ?? '';

        if (empty($currentPassword)) {
            $errors[] = 'Aktuálne heslo je povinné';
        } elseif (!empty($user['password_hash']) && !password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Aktuálne heslo nie je správne';
        }

        if (empty($newPassword)) {
            $errors[] = 'Nové heslo je povinné';
        } elseif (mb_strlen($newPassword) < 8) {
            $errors[] = 'Nové heslo musí mať aspoň 8 znakov';
        } elseif ($newPassword !== $repeatPassword) {
            $errors[] = 'Heslá sa nezhodujú';
        } elseif (!empty($user['password_hash']) && password_verify($newPassword, $user['password_hash'])) {
            $errors[] = 'Nové heslo musí byť iné ako aktuálne';
        }

        if (!empty($errors)) {
            Response::json(['errors' => $errors], 422);
        }

        $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        $this->pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")
            ->execute([':hash' => $newHash, ':id' => $this->userId]);

        Response::json(['message' => 'Heslo bolo zmenené'], 200);
    }

    // GET /users/me/logins
    #[OA\Get(
        path: '/users/me/logins',
        summary: 'Získať históriu prihlásení prihláseného používateľa',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'História prihlásení',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'history',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id',         type: 'integer', example: 42),
                                    new OA\Property(property: 'login_type', type: 'string',  example: 'google'),
                                    new OA\Property(property: 'created_at', type: 'string',  format: 'date-time'),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Neautorizovaný prístup'),
        ]
    )]
    public function getLoginHistory()
    {
        $stmt = $this->pdo->prepare("
            SELECT id, login_type, created_at
            FROM logins
            WHERE user_id = :user_id
            ORDER BY created_at DESC
        ");
        $stmt->execute([':user_id' => $this->userId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json(['history' => $history], 200);
    }
}