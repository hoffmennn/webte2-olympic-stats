<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controller/DetailController.php';

setCorsHeaders();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);


if (!$id || $id <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => 'Chýba alebo neplatné ID']);
    exit;
}

$pdo        = connectDatabase();
$controller = new DetailController($pdo);
$controller->getAthleteDetail($id);