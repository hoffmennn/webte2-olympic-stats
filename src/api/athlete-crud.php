<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/AuthMiddleware.php';
require_once __DIR__ . '/../controller/AthleteController.php';

setCorsHeaders();
//requireAuth();

$pdo = connectDatabase();
$controller = new AthleteController($pdo);
$method = $_SERVER['REQUEST_METHOD'];

$body = json_decode(file_get_contents('php://input'), true);


if($method == 'POST') {
    if (isset($body[0]) && is_array($body[0])) {
        $result = $controller->createBulk($body);
    } else {
        $result = $controller->create($body);
    }

    http_response_code($result['status']);
    echo json_encode($result['body'] ?? $result, JSON_UNESCAPED_UNICODE);
    exit;
}

if($method == 'PUT') {
    $result = $controller->update($body);
    http_response_code($result['status']);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if($method == 'DELETE') {
    $result = $controller->delete($body['id']);
    http_response_code($result['status']);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}

http_response_code(405);
echo json_encode(['error' => 'Metóda nie je povolená'], JSON_UNESCAPED_UNICODE);