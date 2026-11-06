<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Une API ne redirige pas vers une page de connexion : elle répond 401 en JSON,
 * et le front décide d'afficher l'écran de connexion.
 */
final class ApiEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'error' => 'unauthenticated',
            'message' => 'Connecte-toi pour accéder à tes données.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
