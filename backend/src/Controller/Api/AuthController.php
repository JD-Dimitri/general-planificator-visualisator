<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CodeInput;
use App\Dto\CredentialsInput;
use App\Dto\ForgotPasswordInput;
use App\Dto\RegisterInput;
use App\Dto\ResetPasswordInput;
use App\Entity\LoginCode;
use App\Entity\User;
use App\Repository\LoginCodeRepository;
use App\Repository\UserRepository;
use App\Security\SessionLogin;
use App\Service\AccountSeeder;
use App\Service\PasswordResetService;
use App\Service\RefreshTokenManager;
use App\Service\VerificationCodeService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Création de compte, connexion et mot de passe oublié.
 *
 * Se connecter demande toujours deux étapes : le mot de passe, puis un code à
 * usage unique envoyé par e-mail ou par SMS. Un nouveau code est tiré à chaque
 * fois. Le mot de passe seul n'ouvre donc jamais de session, et c'est pour
 * cela que le pare-feu n'a plus de `json_login` : il ne doit exister aucune
 * route qui contourne la deuxième étape.
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
        VerificationCodeService $codes,
        RateLimiterFactoryInterface $loginLimiter,
    ): JsonResponse {
        if ($response = $this->throttle($request, $loginLimiter)) {
            return $response;
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
            ->setPhone($input->phone)
            ->setDisplayName('' !== $input->displayName ? $input->displayName : (strstr($input->email, '@', true) ?: 'Moi'))
            ->setPassword($hasher->hashPassword($user, $input->password));

        $this->em->persist($user);
        $seeder->seed($user);
        $this->em->flush();

        $issued = $codes->issue($user, LoginCode::PURPOSE_REGISTRATION, $input->channel);

        if (null === $issued) {
            return $this->tooManyCodes();
        }

        return new JsonResponse([
            'status' => 'code_sent',
            'message' => 'Compte créé. Saisis le code que nous venons de t\'envoyer pour le valider.',
            'challenge' => $codes->describe($issued['challenge'], $issued['code'], $issued['sent']),
        ], Response::HTTP_CREATED);
    }

    /** Valide le compte créé juste avant, puis ouvre la session. */
    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verify(
        Request $request,
        LoginCodeRepository $challenges,
        VerificationCodeService $codes,
        SessionLogin $sessionLogin,
    ): JsonResponse {
        return $this->consumeAndLogIn($request, $challenges, $codes, $sessionLogin, LoginCode::PURPOSE_REGISTRATION);
    }

    /**
     * Première étape : le mot de passe. Aucune session n'est ouverte ici ;
     * la réponse décrit seulement où le code a été envoyé.
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        VerificationCodeService $codes,
        RateLimiterFactoryInterface $loginLimiter,
    ): JsonResponse {
        return $this->startChallenge($request, $users, $hasher, $codes, $loginLimiter, LoginCode::PURPOSE_LOGIN);
    }

    /** Deuxième étape : le code. C'est seulement ici que la session s'ouvre. */
    #[Route('/login/verify', name: 'login_verify', methods: ['POST'])]
    public function loginVerify(
        Request $request,
        LoginCodeRepository $challenges,
        VerificationCodeService $codes,
        SessionLogin $sessionLogin,
    ): JsonResponse {
        return $this->consumeAndLogIn($request, $challenges, $codes, $sessionLogin, LoginCode::PURPOSE_LOGIN);
    }

    /** Même parcours en deux temps pour les clients sans cookie. */
    #[Route('/token', name: 'token', methods: ['POST'])]
    public function token(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        VerificationCodeService $codes,
        RateLimiterFactoryInterface $loginLimiter,
    ): JsonResponse {
        return $this->startChallenge($request, $users, $hasher, $codes, $loginLimiter, LoginCode::PURPOSE_TOKEN);
    }

    #[Route('/token/verify', name: 'token_verify', methods: ['POST'])]
    public function tokenVerify(
        Request $request,
        LoginCodeRepository $challenges,
        VerificationCodeService $codes,
        JWTTokenManagerInterface $jwt,
        RefreshTokenManager $refreshTokens,
        int $jwtTtl,
    ): JsonResponse {
        $input = $this->input($request, CodeInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        $challenge = $challenges->findChallenge($input->challengeId);

        if (null === $challenge) {
            return $this->unknownChallenge();
        }

        [$valid, $message] = $codes->verify($challenge, $input->code, LoginCode::PURPOSE_TOKEN);

        if (!$valid) {
            return $this->json(['error' => 'invalid_code', 'message' => $message], Response::HTTP_UNAUTHORIZED);
        }

        $user = $challenge->getUser();
        $user->markVerified();
        $this->em->flush();

        return new JsonResponse([
            'token' => $jwt->create($user),
            'refreshToken' => $refreshTokens->issue($user, $request->headers->get('User-Agent', '')),
            'expiresIn' => $jwtTtl,
            'user' => json_decode($this->serializer->serialize($user, 'json', ['groups' => ['read']]), true),
        ]);
    }

    /** Renvoie un code neuf pour une demande en cours. L'ancien cesse de marcher. */
    #[Route('/code/resend', name: 'code_resend', methods: ['POST'])]
    public function resend(
        Request $request,
        LoginCodeRepository $challenges,
        VerificationCodeService $codes,
        RateLimiterFactoryInterface $loginLimiter,
    ): JsonResponse {
        if ($response = $this->throttle($request, $loginLimiter)) {
            return $response;
        }

        $payload = json_decode($request->getContent() ?: '{}', true);
        $id = \is_array($payload) && \is_string($payload['challengeId'] ?? null) ? $payload['challengeId'] : '';
        $previous = $challenges->findChallenge($id);

        if (null === $previous) {
            return $this->unknownChallenge();
        }

        $channel = \is_array($payload) && \is_string($payload['channel'] ?? null) ? $payload['channel'] : $previous->getChannel();
        $issued = $codes->issue($previous->getUser(), $previous->getPurpose(), $channel);

        if (null === $issued) {
            return $this->tooManyCodes();
        }

        return new JsonResponse([
            'status' => 'code_sent',
            'message' => 'Nouveau code envoyé.',
            'challenge' => $codes->describe($issued['challenge'], $issued['code'], $issued['sent']),
        ]);
    }

    /**
     * Mot de passe oublié. La réponse est la même que l'adresse existe ou non :
     * ce formulaire ne doit pas servir à savoir qui a un compte.
     */
    #[Route('/password/forgot', name: 'password_forgot', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        PasswordResetService $resets,
        RateLimiterFactoryInterface $loginLimiter,
    ): JsonResponse {
        if ($response = $this->throttle($request, $loginLimiter)) {
            return $response;
        }

        $input = $this->input($request, ForgotPasswordInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        $devToken = $resets->request($input->email);

        $body = [
            'status' => 'sent',
            'message' => 'Si un compte existe avec cette adresse, un lien de réinitialisation vient de partir. Vérifie aussi tes indésirables.',
        ];

        if (null !== $devToken) {
            $body['devToken'] = $devToken;
        }

        return new JsonResponse($body, Response::HTTP_ACCEPTED);
    }

    /** Le front appelle ceci en ouvrant le lien, pour savoir s'il affiche le formulaire. */
    #[Route('/password/reset/{token}', name: 'password_reset_check', methods: ['GET'])]
    public function checkResetToken(string $token, PasswordResetService $resets): JsonResponse
    {
        $found = $resets->find($token);

        if (null === $found) {
            return $this->json([
                'error' => 'invalid_token',
                'message' => "Ce lien n'est plus valable. Demandes-en un nouveau depuis l'écran de connexion.",
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'valid' => true,
            'email' => $found->getUser()->getEmail(),
            'expiresIn' => max(0, $found->getExpiresAt()->getTimestamp() - time()),
        ]);
    }

    /**
     * Nouveau mot de passe. Pas de code à usage unique ici : recevoir le lien
     * dans sa boîte mail prouve déjà l'accès au second facteur.
     */
    #[Route('/password/reset', name: 'password_reset', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        PasswordResetService $resets,
        SessionLogin $sessionLogin,
        RateLimiterFactoryInterface $loginLimiter,
    ): JsonResponse {
        if ($response = $this->throttle($request, $loginLimiter)) {
            return $response;
        }

        $input = $this->input($request, ResetPasswordInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        $token = $resets->find($input->token);

        if (null === $token) {
            return $this->json([
                'error' => 'invalid_token',
                'message' => "Ce lien n'est plus valable. Demandes-en un nouveau depuis l'écran de connexion.",
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $resets->reset($token, $input->password);
        $sessionLogin->logIn($user);

        return $this->ok($user);
    }

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

        return new JsonResponse([
            'token' => $jwt->create($user),
            'refreshToken' => $newToken,
            'expiresIn' => $jwtTtl,
            'user' => json_decode($this->serializer->serialize($user, 'json', ['groups' => ['read']]), true),
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        throw new \LogicException('Interceptée par le pare-feu logout.');
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->ok($this->currentUser());
    }

    // ---------- rouages communs ----------

    private function startChallenge(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        VerificationCodeService $codes,
        RateLimiterFactoryInterface $loginLimiter,
        string $purpose,
    ): JsonResponse {
        if ($response = $this->throttle($request, $loginLimiter)) {
            return $response;
        }

        $input = $this->input($request, CredentialsInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        $user = $users->findOneByEmail($input->email);

        // Le même message dans les deux cas : sinon le formulaire dit qui est inscrit.
        if (null === $user || !$hasher->isPasswordValid($user, $input->password)) {
            return $this->json([
                'error' => 'invalid_credentials',
                'message' => 'Adresse ou mot de passe incorrect.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $issued = $codes->issue($user, $purpose, $input->channel);

        if (null === $issued) {
            return $this->tooManyCodes();
        }

        return new JsonResponse([
            'status' => 'code_sent',
            'message' => 'Code envoyé. Saisis-le pour terminer la connexion.',
            'challenge' => $codes->describe($issued['challenge'], $issued['code'], $issued['sent']),
        ]);
    }

    private function consumeAndLogIn(
        Request $request,
        LoginCodeRepository $challenges,
        VerificationCodeService $codes,
        SessionLogin $sessionLogin,
        string $purpose,
    ): JsonResponse {
        $input = $this->input($request, CodeInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        $challenge = $challenges->findChallenge($input->challengeId);

        if (null === $challenge) {
            return $this->unknownChallenge();
        }

        [$valid, $message] = $codes->verify($challenge, $input->code, $purpose);

        if (!$valid) {
            return $this->json(['error' => 'invalid_code', 'message' => $message], Response::HTTP_UNAUTHORIZED);
        }

        $user = $challenge->getUser();
        $user->markVerified();
        $this->em->flush();

        $sessionLogin->logIn($user);

        return $this->ok($user);
    }

    private function throttle(Request $request, RateLimiterFactoryInterface $limiter): ?JsonResponse
    {
        if ($limiter->create($request->getClientIp() ?? 'anon')->consume()->isAccepted()) {
            return null;
        }

        return $this->json([
            'error' => 'too_many_attempts',
            'message' => 'Trop de tentatives. Réessaie dans quelques minutes.',
        ], Response::HTTP_TOO_MANY_REQUESTS);
    }

    private function unknownChallenge(): JsonResponse
    {
        return $this->json([
            'error' => 'unknown_challenge',
            'message' => 'Cette demande de connexion est inconnue ou trop ancienne. Recommence.',
        ], Response::HTTP_NOT_FOUND);
    }

    private function tooManyCodes(): JsonResponse
    {
        return $this->json([
            'error' => 'too_many_codes',
            'message' => 'Trop de codes demandés pour ce compte. Attends une heure, ou utilise « Mot de passe oublié ».',
        ], Response::HTTP_TOO_MANY_REQUESTS);
    }
}
