<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Service\Notification\CodeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Mot de passe oublié : demande, vérification du lien, nouveau mot de passe.
 *
 * La demande répond toujours la même chose, que l'adresse existe ou non. Un
 * écran qui dirait « compte inconnu » transformerait le formulaire en outil
 * pour savoir qui est inscrit.
 */
final class PasswordResetService
{
    private const MAX_REQUESTS_PER_HOUR = 5;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PasswordResetTokenRepository $tokens,
        private readonly UserRepository $users,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly CodeNotifier $notifier,
        private readonly bool $exposeCodes,
    ) {
    }

    /**
     * Envoie le lien si le compte existe. Rend le jeton en clair uniquement en
     * développement, pour pouvoir essayer le parcours sans serveur de mail.
     */
    public function request(string $email): ?string
    {
        $user = $this->users->findOneByEmail($email);

        if (null === $user) {
            return null;
        }

        if ($this->tokens->countSince($user, new \DateTimeImmutable('-1 hour')) >= self::MAX_REQUESTS_PER_HOUR) {
            return null;
        }

        $this->tokens->invalidateAllFor($user);

        $plain = bin2hex(random_bytes(32));
        $this->em->persist(new PasswordResetToken(
            $user,
            $this->hash($plain),
            new \DateTimeImmutable(\sprintf('+%d seconds', PasswordResetToken::TTL_SECONDS)),
        ));
        $this->em->flush();

        $this->notifier->sendPasswordReset($user, $plain);

        return $this->exposeCodes ? $plain : null;
    }

    public function find(string $plainToken): ?PasswordResetToken
    {
        $token = $this->tokens->findOneByHash($this->hash($plainToken));

        return null !== $token && $token->isUsable() ? $token : null;
    }

    /**
     * Pose le nouveau mot de passe, brûle le lien et déconnecte tout le monde.
     * Si quelqu'un s'était introduit dans le compte, il en sort ici.
     */
    public function reset(PasswordResetToken $token, string $plainPassword): User
    {
        $user = $token->getUser();

        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        // Recevoir le lien prouve l'accès à la boîte mail : le compte est validé.
        $user->markVerified();

        $token->use();
        $this->tokens->invalidateAllFor($user);
        $this->refreshTokens->revokeAllFor($user);
        $this->em->flush();

        $this->notifier->sendPasswordChanged($user);

        return $user;
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
