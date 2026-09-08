<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Utilisateur;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener]
final readonly class RedirectionInformationsAccueilSubscriber
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $utilisateur = $event->getUser();
        if (!$utilisateur instanceof Utilisateur || !$utilisateur->doitCompleterInformationsAccueil()) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_informations_accueil')));
    }
}
