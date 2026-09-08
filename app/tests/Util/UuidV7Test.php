<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\UuidV7;
use PHPUnit\Framework\TestCase;

final class UuidV7Test extends TestCase
{
    public function testGenereUnUuidV7Horodate(): void
    {
        $avant = (int) floor(microtime(true) * 1000);
        $uuid = UuidV7::generate();
        $apres = (int) floor(microtime(true) * 1000);

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid);
        $horodatage = hexdec(str_replace('-', '', substr($uuid, 0, 13)));
        self::assertGreaterThanOrEqual($avant, $horodatage);
        self::assertLessThanOrEqual($apres, $horodatage);
    }
}
