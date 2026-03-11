<?php

// api/athletes.php
// Tento subor je vstupny bod pre Vue frontend
// Vola controller a ten posiela JSON odpoved

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/controller/TableController.php';

$pdo = connectDatabase();
$controller = new TableController($pdo);

// Controller sam nastavi hlavicky a vypise JSON
$controller->getTableData();