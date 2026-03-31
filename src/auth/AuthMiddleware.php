<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

function requireAuth(): array
{
    $headers = getallheaders();

    // headers to lowercase
    $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
    $authHeader = $normalizedHeaders['authorization'] ?? '';

    if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['error' => 'Chýba autorizačný token'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $token = substr($authHeader, 7);

    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        return (array) $decoded;

    } catch (ExpiredException $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token expiroval'], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Neplatný token'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
