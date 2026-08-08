<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

final class UtilisateurTest extends TestCase
{
    public function testUnJetonDActivationNestJamaisStockeEnClair(): void
    {
        $utilisateur = new Utilisateur();
        $expiration = new \DateTimeImmutable('+1 day');

        $token = $utilisateur->preparerActivation($expiration);

        self::assertSame(64, strlen($token));
        self::assertTrue($utilisateur->isChangementMotDePasseRequis());
        self::assertTrue($utilisateur->activationEstValideA(new \DateTimeImmutable()));

        $propriete = new \ReflectionProperty($utilisateur, 'jetonActivation');
        self::assertSame(hash('sha256', $token), $propriete->getValue($utilisateur));
        self::assertNotSame($token, $propriete->getValue($utilisateur));
    }

    public function testTerminerActivationInvalideLeJeton(): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->preparerActivation(new \DateTimeImmutable('+1 day'));
        $utilisateur->terminerActivation();

        self::assertFalse($utilisateur->isChangementMotDePasseRequis());
        self::assertFalse($utilisateur->activationEstValideA(new \DateTimeImmutable()));
    }
}
