<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class AuthMiddleware
{
    public static function requireAuth(): array
    {
        $headers = getallheaders();
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        $authHeader = $normalizedHeaders['authorization'] ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            Response::json(['error' => 'Chýba autorizačný token'], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            $userPayload = (array) $decoded;
            $_REQUEST['user'] = $userPayload;

            return $userPayload;
        } catch (ExpiredException $e) {
            Response::json(['error' => 'Token expiroval'], 401);
        } catch (Exception $e) {
            Response::json(['error' => 'Neplatný token'], 401);
        }

        return [];
    }
}