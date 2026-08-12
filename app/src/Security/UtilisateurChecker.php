<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UtilisateurChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        if (!$user->isActif()) {
            throw new CustomUserMessageAccountStatusException('Ce compte est désactivé. Contactez l’équipe de Jambville.');
        }

        if (null === $user->getPassword() || $user->isChangementMotDePasseRequis()) {
            throw new CustomUserMessageAccountStatusException('Votre compte doit d’abord être activé avec le lien de première connexion reçu par e-mail.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
