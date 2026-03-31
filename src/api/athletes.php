<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controller/TableController.php';

setCorsHeaders();

$pdo = connectDatabase();
$controller = new TableController($pdo);

$controller->getTableData();