<?php

namespace App\Services;

class QrisService
{
    /**
     * Parse raw EMVCo QRIS string into tag-value pairs.
     */
    public static function parseEMVCo(string $qris): array
    {
        $tags = [];
        $i = 0;
        $len = strlen($qris);
        while ($i < $len) {
            if ($i + 4 > $len) {
                break;
            }
            $id = substr($qris, $i, 2);
            $lengthVal = substr($qris, $i + 2, 2);
            if (!ctype_digit($lengthVal)) {
                break;
            }
            $length = intval($lengthVal);
            $val = substr($qris, $i + 4, $length);
            $tags[$id] = $val;
            $i += 4 + $length;
        }
        return $tags;
    }

    /**
     * Rebuild EMVCo string from tag-value pairs, sorting them and omitting the CRC tag.
     */
    public static function rebuildEMVCo(array $tags): string
    {
        unset($tags['63']); // Remove CRC tag
        ksort($tags); // Sort keys alphabetically/numerically
        $rebuilt = "";
        foreach ($tags as $id => $val) {
            $len = str_pad(strlen($val), 2, '0', STR_PAD_LEFT);
            $rebuilt .= $id . $len . $val;
        }
        return $rebuilt;
    }

    /**
     * Calculate CRC16 CCITT checksum (Poly 0x1021, Init 0xFFFF).
     */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return sprintf('%04X', $crc);
    }

    /**
     * Generate dynamic QRIS by injecting transaction amount and recalculating CRC.
     */
    public static function generateDynamicQris(string $staticQris, int $amount): string
    {
        if (empty($staticQris)) {
            return '';
        }

        $tags = self::parseEMVCo($staticQris);
        
        // Inject transaction currency IDR (360) if not present
        if (!isset($tags['53'])) {
            $tags['53'] = '360';
        }

        // Inject amount (Tag 54)
        $tags['54'] = (string) $amount;

        // Rebuild without CRC
        $rebuilt = self::rebuildEMVCo($tags);

        // Append CRC Tag ID and Length prefix "6304"
        $payload = $rebuilt . '6304';

        // Calculate and append CRC checksum
        $crc = self::crc16($payload);
        
        return $payload . $crc;
    }
}
