<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/AuthMiddleware.php';
require_once __DIR__ . '/../controller/PlacementController.php';

setCorsHeaders();
//requireAuth();

$pdo = connectDatabase();
$controller = new PlacementController($pdo);
$method = $_SERVER['REQUEST_METHOD'];

$body = json_decode(file_get_contents('php://input'), true);

if ($method == 'POST') {
    $result = $controller->createPlacement($body);
    http_response_code($result['status']);
    json_encode($result, JSON_UNESCAPED_UNICODE);
}

if ($method == 'DELETE') {
    $result = $controller->delete($body['id']);
    http_response_code($result['status']);
    json_encode($result, JSON_UNESCAPED_UNICODE);
}

