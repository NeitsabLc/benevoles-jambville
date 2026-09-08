<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\PurgerCampagnePrecedenteCommand;
use PHPUnit\Framework\TestCase;

final class PurgerCampagnePrecedenteCommandTest extends TestCase
{
    public function testLaCampagneEchueEstRattrapeeAvantLeDixOctobreSuivant(): void
    {
        [$debut, $fin] = PurgerCampagnePrecedenteCommand::calculerCampagneEligible(
            new \DateTimeImmutable('2027-03-15'),
        );

        self::assertSame('2025-09-01', $debut->format('Y-m-d'));
        self::assertSame('2026-08-31', $fin->format('Y-m-d'));
    }

    public function testLaNouvelleCampagneDevientEligibleLeDixOctobre(): void
    {
        [$debut, $fin] = PurgerCampagnePrecedenteCommand::calculerCampagneEligible(
            new \DateTimeImmutable('2027-10-10'),
        );

        self::assertSame('2026-09-01', $debut->format('Y-m-d'));
        self::assertSame('2027-08-31', $fin->format('Y-m-d'));
    }
}
