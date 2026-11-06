<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Connexion réussie par cookie de session : on renvoie le compte, jamais de jeton.
 * Le jeton JWT s'obtient sur /api/auth/token, pour les clients sans cookie.
 */
final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly SerializerInterface $serializer)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        \assert($user instanceof User);

        return new JsonResponse(
            $this->serializer->serialize($user, 'json', ['groups' => ['read']]),
            Response::HTTP_OK,
            [],
            true
        );
    }
}
