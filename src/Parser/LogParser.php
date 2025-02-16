<?php
namespace App\Parser;

class LogParser
{
    public function parseLine(string $hexLine): ?array
    {
        $bin = @hex2bin($hexLine);
        if ($bin === false || strlen($bin) < 5) {
            return null;
        }

        $isSingleByteLength = $this->determineStart($bin);
        if ($isSingleByteLength === null) {
            return null;
        }

        $pos = 2;
        $packetLength = $this->readPacketLength($bin, $pos, $isSingleByteLength);
        if ($packetLength === null) {
            return null;
        }

        $expectedTotal = 2 + ($isSingleByteLength ? 1 : 2) + $packetLength + 2;
        if ($expectedTotal !== strlen($bin)) {
            return null;
        }

        if (strlen($bin) < $pos + 1) {
            return null;
        }
        $protocol = ord($bin[$pos]);
        $pos++;

        $infoLength = $packetLength - 1 - 2 - 2;
        if ($infoLength < 0) {
            return null;
        }
        $infoContent = substr($bin, $pos, $infoLength);
        $pos += $infoLength;

        if (($pos + 4) > strlen($bin)) {
            return null;
        }
        $pos += 4;

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
            case 0x01:
                $this->parseLogin($infoContent, $out);
                break;
            case 0x12:
                $this->parseLocation($infoContent, $out);
                break;
            case 0x13:
                $this->parseHeartbeat($infoContent, $out);
                break;
            default:
                break;
        }

        return $out;
    }

    private function determineStart(string $bin): ?bool
    {
        $start1 = ord($bin[0]);
        $start2 = ord($bin[1]);
        if ($start1 === 0x78 && $start2 === 0x78) {
            return true;
        }
        if ($start1 === 0x79 && $start2 === 0x79) {
            return false;
        }
        return null;
    }

    private function readPacketLength(string $bin, int &$pos, bool $isSingleByteLength): ?int
    {
        if ($isSingleByteLength) {
            if (strlen($bin) < $pos + 1) {
                return null;
            }
            $length = ord($bin[$pos]);
            $pos++;
            return $length;
        } else {
            if (strlen($bin) < $pos + 2) {
                return null;
            }
            $lengthHigh = ord($bin[$pos]);
            $lengthLow  = ord($bin[$pos + 1]);
            $pos += 2;
            return ($lengthHigh << 8) + $lengthLow;
        }
    }

    private function parseLogin(string $infoContent, array &$out): void
    {
        if (strlen($infoContent) >= 8) {
            $out["imei"] = strtoupper(bin2hex(substr($infoContent, 0, 8)));
        }
    }

    private function parseLocation(string $info, array &$out): void
    {
        if (strlen($info) < 18) {
            return;
        }
        $year   = ord($info[0]);
        $month  = ord($info[1]);
        $day    = ord($info[2]);
        $hour   = ord($info[3]);
        $min    = ord($info[4]);
        $sec    = ord($info[5]);
        $yearFull = 2000 + $year;
        $out["data"] = sprintf("%04d-%02d-%02d %02d:%02d:%02d", $yearFull, $month, $day, $hour, $min, $sec);

        $latRaw = $this->readUint32(substr($info, 7, 4));
        $lonRaw = $this->readUint32(substr($info, 11, 4));

        $speed = ord($info[15]);
        $out["speed"] = $speed;

        $ch = ord($info[16]);
        $cl = ord($info[17]);
        $direction = (($ch & 0x03) << 8) + $cl;
        $out["direcao"] = $direction;

        $gpsFix = ($ch & 0x10) ? true : false;
        $out["gps"] = $gpsFix ? "F" : "A";

        $bit3 = ($ch & 0x08) ? 1 : 0;
        $bit2 = ($ch & 0x04) ? 1 : 0;

        if ($bit2 === 1) {
            $out["latitudeHemisferio"] = "N";
            $out["latitude"] = sprintf("%.7f", $latRaw / 1800000.0);
        } else {
            $out["latitudeHemisferio"] = "S";
            $out["latitude"] = sprintf("%.7f", -($latRaw / 1800000.0));
        }

        if ($bit3 === 1) {
            $out["longitudeHemisferio"] = "E";
            $out["longitude"] = sprintf("%.7f", $lonRaw / 1800000.0);
        } else {
            $out["longitudeHemisferio"] = "W";
            $out["longitude"] = sprintf("%.7f", -($lonRaw / 1800000.0));
        }

        $out["alarm"] = "tracker";
    }

    private function parseHeartbeat(string $info, array &$out): void
    {
        if (strlen($info) < 5) {
            return;
        }
        $terminalInfo = ord($info[0]);
        $voltageLevel = ord($info[1]);

        $bit1 = ($terminalInfo & 0x02) ? 1 : 0;
        $out["acc"] = $bit1 ? "on" : "off";

        $map = [0 => "0", 1 => "10", 2 => "20", 3 => "40", 4 => "60", 5 => "80", 6 => "100"];
        $out["nivelBateria"] = isset($map[$voltageLevel]) ? $map[$voltageLevel] : "";
    }

    private function readUint32(string $b): int
    {
        return ((ord($b[0]) << 24) & 0xff000000)
             | ((ord($b[1]) << 16) & 0x00ff0000)
             | ((ord($b[2]) << 8)  & 0x0000ff00)
             |  (ord($b[3])       & 0x000000ff);
    }
}
