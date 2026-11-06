<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Ouvre la session une fois le code vérifié.
 *
 * Le pare-feu n'a plus de `json_login` : il ne doit exister aucune route où un
 * couple adresse + mot de passe suffise à entrer, sinon le code à usage unique
 * se contournerait. La session est donc posée ici, à la main, et seulement
 * après la deuxième étape.
 *
 * `gpv` est le `context` du pare-feu, d'où la clé de session `_security_gpv`.
 */
final class SessionLogin
{
    private const FIREWALL_CONTEXT = 'gpv';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function logIn(User $user): void
    {
        $token = new UsernamePasswordToken($user, self::FIREWALL_CONTEXT, $user->getRoles());
        $this->tokenStorage->setToken($token);

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        // Un identifiant de session neuf à chaque connexion : un identifiant
        // glissé avant la connexion ne devient jamais une session ouverte.
        $session->migrate(true);
        $session->set('_security_'.self::FIREWALL_CONTEXT, serialize($token));
    }
}
