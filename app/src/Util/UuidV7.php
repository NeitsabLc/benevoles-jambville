<?php

declare(strict_types=1);

namespace App\Util;

final class UuidV7
{
    private function __construct()
    {
    }

    public static function generate(): string
    {
        $timestamp = str_pad(dechex((int) floor(microtime(true) * 1000)), 12, '0', STR_PAD_LEFT);
        $bytes = hex2bin($timestamp).random_bytes(10);

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
