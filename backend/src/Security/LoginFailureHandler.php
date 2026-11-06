<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Le message ne distingue jamais « adresse inconnue » de « mot de passe faux » :
 * sinon l'API dit à n'importe qui quelles adresses ont un compte.
 */
final class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse([
                'error' => 'too_many_attempts',
                'message' => 'Trop de tentatives. Réessaie dans quelques minutes.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return new JsonResponse([
            'error' => 'invalid_credentials',
            'message' => 'Adresse ou mot de passe incorrect.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
