<?php
namespace App\Controller;

use App\Parser\LogParser;

class LogController
{
    public function parseLogAsArray(string $rawData): array
    {
        $lines = preg_split('/\r?\n/', trim($rawData));
        $lines = array_filter($lines, fn($line) => trim($line) !== '');

        $parser = new LogParser();

        $parsedResults = [];

        foreach ($lines as $line) {
            $hexLine = trim(str_replace(' ', '', $line));
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
                    "_error" => "Falha ao decodificar a linha: $line"
                ];
            }
        }

        return $parsedResults;
    }

    public function parseLogGrouped(string $rawData): array
    {
        $lines = preg_split('/\r?\n/', trim($rawData));
        $lines = array_filter($lines, fn($line) => trim($line) !== '');

        $parser = new LogParser();

        $pacotes = [];
        $imeiFound = "";

        foreach ($lines as $line) {
            $hexLine = trim(str_replace(' ', '', $line));
            $hexLine = strtoupper($hexLine);

            $parsed = $parser->parseLine($hexLine);
            if ($parsed === null) {
                $pacotes[] = [
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
                    "_error" => "Falha ao decodificar a linha: $line"
                ];
            } else {
                if (!empty($parsed["imei"])) {
                    $imeiFound = $parsed["imei"];
                }
                $pacotes[] = $parsed;
            }
        }

        return [
            "imei" => $imeiFound,
            "pacotes" => $pacotes
        ];
    }
}
