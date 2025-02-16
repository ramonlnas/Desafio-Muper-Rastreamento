<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\LogController;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Apenas POST permitido.']);
    exit;
}

$rawData = file_get_contents('php://input');
if (!$rawData) {
    http_response_code(400);
    echo json_encode(['error' => 'Corpo vazio.']);
    exit;
}

$controller = new LogController();
$result = $controller->parseLogGrouped($rawData);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
