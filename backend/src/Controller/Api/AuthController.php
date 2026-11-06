<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\RegisterInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AccountSeeder;
use App\Service\RefreshTokenManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Inscription, jetons et compte courant.
 *
 * Deux façons de se connecter, pour les deux usages du cahier des charges :
 * le web pose un cookie de session (/api/auth/login) ; une application mobile
 * ou de bureau demande un jeton (/api/auth/token) et le rafraîchit.
 */
#[Route('/api/auth', name: 'api_auth_')]
final class AuthController extends AbstractApiController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        AccountSeeder $seeder,
        RateLimiterFactoryInterface $loginLimiter,
    ): JsonResponse {
        if (false === $loginLimiter->create($request->getClientIp() ?? 'anon')->consume()->isAccepted()) {
            return $this->json(['error' => 'too_many_attempts', 'message' => 'Trop de tentatives. Réessaie dans quelques minutes.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $input = $this->input($request, RegisterInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        if (null !== $users->findOneByEmail($input->email)) {
            return $this->json([
                'error' => 'email_taken',
                'message' => 'Un compte existe déjà avec cette adresse.',
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($input->email)
            ->setDisplayName('' !== $input->displayName ? $input->displayName : (strstr($input->email, '@', true) ?: 'Moi'))
            ->setPassword($hasher->hashPassword($user, $input->password));

        $this->em->persist($user);
        $seeder->seed($user);
        $this->em->flush();

        return $this->ok($user, Response::HTTP_CREATED);
    }

    /**
     * Point d'entrée déclaré au pare-feu `json_login`. Symfony intercepte la requête
     * avant d'arriver ici ; la méthode n'existe que pour donner une route à la config.
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        throw new \LogicException('Interceptée par le pare-feu json_login.');
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        throw new \LogicException('Interceptée par le pare-feu logout.');
    }

    /** Jeton d'accès JWT et jeton de rafraîchissement, pour les clients sans cookie. */
    #[Route('/token', name: 'token', methods: ['POST'])]
    public function token(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwt,
        RefreshTokenManager $refreshTokens,
        RateLimiterFactoryInterface $loginLimiter,
        int $jwtTtl,
    ): JsonResponse {
        if (false === $loginLimiter->create($request->getClientIp() ?? 'anon')->consume()->isAccepted()) {
            return $this->json(['error' => 'too_many_attempts', 'message' => 'Trop de tentatives. Réessaie dans quelques minutes.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode($request->getContent() ?: '{}', true);
        $email = \is_array($payload) && \is_string($payload['email'] ?? null) ? $payload['email'] : '';
        $password = \is_array($payload) && \is_string($payload['password'] ?? null) ? $payload['password'] : '';

        $user = $users->findOneByEmail($email);

        if (null === $user || !$hasher->isPasswordValid($user, $password)) {
            return $this->json([
                'error' => 'invalid_credentials',
                'message' => 'Adresse ou mot de passe incorrect.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->tokenResponse($user, $jwt, $refreshTokens->issue($user, $request->headers->get('User-Agent', '')), $jwtTtl);
    }

    /** Rotation : le jeton présenté est révoqué et remplacé à chaque appel. */
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        JWTTokenManagerInterface $jwt,
        RefreshTokenManager $refreshTokens,
        int $jwtTtl,
    ): JsonResponse {
        $payload = json_decode($request->getContent() ?: '{}', true);
        $presented = \is_array($payload) && \is_string($payload['refreshToken'] ?? null) ? $payload['refreshToken'] : '';

        $rotated = '' !== $presented ? $refreshTokens->rotate($presented, $request->headers->get('User-Agent', '')) : null;

        if (null === $rotated) {
            return $this->json([
                'error' => 'invalid_refresh_token',
                'message' => 'Session expirée. Reconnecte-toi.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        [$user, $newToken] = $rotated;

        return $this->tokenResponse($user, $jwt, $newToken, $jwtTtl);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->ok($this->currentUser());
    }

    private function tokenResponse(User $user, JWTTokenManagerInterface $jwt, string $refreshToken, int $jwtTtl): JsonResponse
    {
        return new JsonResponse([
            'token' => $jwt->create($user),
            'refreshToken' => $refreshToken,
            'expiresIn' => $jwtTtl,
            'user' => json_decode($this->serializer->serialize($user, 'json', ['groups' => ['read']]), true),
        ]);
    }
}
