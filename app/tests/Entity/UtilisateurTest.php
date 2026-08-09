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

    public function testLaDesactivationSupprimeLesAllergiesEtLeCommentaireAlimentaire(): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->modifierProfil(
            '0612345678',
            true,
            true,
            true,
            'Allergie au lait',
            'Lit en rez-de-chaussée',
        );

        $dateDesactivation = new \DateTimeImmutable('2026-07-01 12:00:00');
        $utilisateur->basculerActivation($dateDesactivation);

        self::assertFalse($utilisateur->isActif());
        self::assertSame($dateDesactivation, $utilisateur->getDesactiveLe());
        self::assertFalse($utilisateur->hasAllergieOeuf());
        self::assertFalse($utilisateur->hasAllergieArachide());
        self::assertNull($utilisateur->getRegimeAutre());
        self::assertTrue($utilisateur->isVegetarien());
        self::assertSame('Lit en rez-de-chaussée', $utilisateur->getBesoinCouchage());

        $utilisateur->basculerActivation();

        self::assertTrue($utilisateur->isActif());
        self::assertNull($utilisateur->getDesactiveLe());
        self::assertFalse($utilisateur->hasAllergieOeuf());
        self::assertFalse($utilisateur->hasAllergieArachide());
        self::assertNull($utilisateur->getRegimeAutre());
    }
}
