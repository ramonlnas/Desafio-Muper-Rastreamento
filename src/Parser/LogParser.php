<?php
namespace Src\Parser;

class LogParser
{
    public function parseLine(string $hexLine): ?array
    {
        $bin = @hex2bin($hexLine);
        if (!$bin) {
            return null;
        }

        if (strlen($bin) < 5) {
            return null;
        }

        $start1 = ord($bin[0]);
        $start2 = ord($bin[1]);
        if (!(($start1 === 0x78 && $start2 === 0x78) || ($start1 === 0x79 && $start2 === 0x79))) {
            return null;
        }

        $isSingleByteLength = ($start1 === 0x78 && $start2 === 0x78);

        $pos = 2;
        $packetLength = 0;
        if ($isSingleByteLength) {
            $packetLength = ord($bin[$pos]); // 1 byte
            $pos += 1;
        } else {
            $packetLength = (ord($bin[$pos]) << 8) + ord($bin[$pos + 1]);
            $pos += 2;
        }
        $expectedTotal = 2 + ($isSingleByteLength ? 1 : 2) + $packetLength + 2;
        if ($expectedTotal !== strlen($bin)) {
            return null;
        }

        $protocol = ord($bin[$pos]);
        $pos += 1;

        $infoContentLength = $packetLength - 1 - 2 - 2;
        if ($infoContentLength < 0) {
            return null;
        }

        $infoContent = substr($bin, $pos, $infoContentLength);
        $pos += $infoContentLength;

        $serialHigh = ord($bin[$pos]);
        $serialLow  = ord($bin[$pos+1]);
        $serial = ($serialHigh << 8) + $serialLow;
        $pos += 2;

        $crcHigh = ord($bin[$pos]);
        $crcLow  = ord($bin[$pos+1]);
        $pos += 2;

        $out = [
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
            "nivelBateria" => ""
        ];

        switch ($protocol) {
            case 0x01: // Login Packet
                $this->parseLoginPacket($infoContent, $out);
                break;
            case 0x12: // GPS Location
                $this->parseLocationPacket($infoContent, $out);
                break;
            case 0x13: // Heartbeat
                $this->parseHeartbeatPacket($infoContent, $out);
                break;
            default:
                break;
        }

        return $out;
    }

    private function parseLoginPacket(string $infoContent, array &$out)
    {
        if (strlen($infoContent) >= 8) {
            $imeiBCD = bin2hex($infoContent);
            $out["imei"] = strtoupper($imeiBCD);
        }
    }

    private function parseHeartbeatPacket(string $infoContent, array &$out)
    {
        if (strlen($infoContent) < 5) {
            return;
        }

        $terminalInfo = ord($infoContent[0]);
        $voltageLevel = ord($infoContent[1]);
        $bit6 = ($terminalInfo & 0x40) ? 1 : 0;
        $bit2 = ($terminalInfo & 0x04) ? 1 : 0;
        $bit1 = ($terminalInfo & 0x02) ? 1 : 0;
        $bit0 = ($terminalInfo & 0x01) ? 1 : 0;

        $out["acc"] = $bit1 ? "on" : "off";

        $map = [0 => "0", 1 => "10", 2 => "20", 3 => "40", 4 => "60", 5 => "80", 6 => "100"];
        if (isset($map[$voltageLevel])) {
            $out["nivelBateria"] = $map[$voltageLevel];
        } else {
            $out["nivelBateria"] = "";
        }
    }

    private function parseLocationPacket(string $infoContent, array &$out)
    {
        if (strlen($infoContent) < 16) {
            return;
        }

        $year   = ord($infoContent[0]);
        $month  = ord($infoContent[1]);
        $day    = ord($infoContent[2]);
        $hour   = ord($infoContent[3]);
        $minute = ord($infoContent[4]);
        $second = ord($infoContent[5]);
        $yearFull = 2000 + $year;
        $out["data"] = sprintf("%04d-%02d-%02d %02d:%02d:%02d",
            $yearFull, $month, $day, $hour, $minute, $second
        );

        $latRaw = $this->readUint32(substr($infoContent, 7, 4));
        $lonRaw = $this->readUint32(substr($infoContent, 11, 4));

        $speed = ord($infoContent[15]);
        $out["speed"] = $speed;

        if (strlen($infoContent) < 18) {
            return;
        }
        $courseHigh = ord($infoContent[16]);
        $courseLow  = ord($infoContent[17]);

        $direction = (($courseHigh & 0x03) << 8) + $courseLow;
        $out["direcao"] = $direction;

        $gpsFixed = ($courseHigh & 0x10) ? true : false;
        $out["gps"] = $gpsFixed ? "F" : "A";

        $isWest = (($courseHigh & 0x08) == 0) ? true : false;
        $isNorth= (($courseHigh & 0x04) == 0) ? false : true;

        if ($isNorth) {
            $out["latitudeHemisferio"] = "N";
            $latitudeVal = $latRaw / 1800000.0;
        } else {
            $out["latitudeHemisferio"] = "S";
            $latitudeVal = -($latRaw / 1800000.0);
        }

        if ($isWest) {
            $out["longitudeHemisferio"] = "W";
            $longitudeVal = -($lonRaw / 1800000.0);
        } else {
            $out["longitudeHemisferio"] = "E";
            $longitudeVal = $lonRaw / 1800000.0;
        }

        $out["latitude"] = sprintf("%.7f", $latitudeVal);
        $out["longitude"] = sprintf("%.7f", $longitudeVal);

        $out["alarm"] = "tracker";
    }

    private function readUint32(string $binary4): int
    {
        $b0 = ord($binary4[0]);
        $b1 = ord($binary4[1]);
        $b2 = ord($binary4[2]);
        $b3 = ord($binary4[3]);
        return (($b0 << 24) & 0xFF000000)
             | (($b1 << 16) & 0x00FF0000)
             | (($b2 << 8)  & 0x0000FF00)
             | ( $b3        & 0x000000FF);
    }

    private function bcdToDecimal(string $bcdData): string
    {
        $result = "";
        $length = strlen($bcdData);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($bcdData[$i]);
            $highNibble = ($byte >> 4) & 0x0F;
            $lowNibble  = $byte & 0x0F;
            $result .= $highNibble;
            $result .= $lowNibble;
        }
        return $result;
    }

}
