<?php
// api/athlete.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../controller/DetailController.php';

// Nacitame ID z GET parametra a validujeme
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Ak ID chyba alebo nie je platne cislo, ukonci skript
// DetailController->sendError() je private, preto riesime tu
if (!$id || $id <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => 'Chýba alebo neplatné ID']);
    exit;
}

$pdo        = connectDatabase($hostname, $database, $username, $password);
$controller = new DetailController($pdo);
$controller->getAthleteDetail($id);