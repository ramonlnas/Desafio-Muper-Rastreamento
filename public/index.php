<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Parser/LogParser.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Método não permitido. Use POST.']);
    exit;
}

$rawData = file_get_contents('php://input');
if (!$rawData) {
    http_response_code(400);
    echo json_encode(['error' => 'Corpo da requisição está vazio.']);
    exit;
}

$maxSize = 100 * 1024; // 100 KB
if (strlen($rawData) > $maxSize) {
    http_response_code(413); // Payload Too Large
    echo json_encode(['error' => 'Payload muito grande, limite de 100KB.']);
    exit;
}

$lines = preg_split('/\r?\n/', trim($rawData));
$lines = array_filter($lines, fn($l) => trim($l) !== ''); // remover linhas vazias

use Src\Parser\LogParser;

$parser = new LogParser();

$parsedResults = [];

foreach ($lines as $line) {
    $hexLine = trim($line);

    $hexLine = str_replace(' ', '', $hexLine); // remover espaços
    $hexLine = strtoupper($hexLine);

    $result = $parser->parseLine($hexLine);

    if ($result !== null) {
        $parsedResults[] = $result;
    } else {
        $parsedResults[] = [
            "gps" => "",
            "latitude" => "",
            "longitude" => "",
            "latitudeHemisferio" => "",
            "longitudeHemisferio" => "",
            "speed" => 0,
            "imei" => "",
            "data" => "",
            "alarm" => "",
            "acc" => "",
            "direcao" => 0,
            "nivelBateria" => "",
            "_error" => "Não foi possível decodificar esta linha: {$line}"
        ];
    }
}

echo json_encode($parsedResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
