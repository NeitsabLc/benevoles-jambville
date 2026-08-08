<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Thematique;
use PHPUnit\Framework\TestCase;

final class ThematiqueTest extends TestCase
{
    public function testUnePeriodeExclusiveDetecteAussiUnChevauchementPartiel(): void
    {
        $thematique = new Thematique('Grand événement');
        $thematique->modifier('Grand événement', 0, new \DateTimeImmutable('2026-08-10'), new \DateTimeImmutable('2026-08-15'), true);

        self::assertTrue($thematique->chevauche(new \DateTimeImmutable('2026-08-08'), new \DateTimeImmutable('2026-08-11')));
        self::assertFalse($thematique->estCompatibleAvec(new \DateTimeImmutable('2026-08-08'), new \DateTimeImmutable('2026-08-11')));
        self::assertTrue($thematique->estCompatibleAvec(new \DateTimeImmutable('2026-08-10'), new \DateTimeImmutable('2026-08-15')));
        self::assertFalse($thematique->chevauche(new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-08-09')));
    }
}
