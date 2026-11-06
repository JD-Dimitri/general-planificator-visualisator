<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Émission et rotation des jetons de rafraîchissement.
 *
 * Le jeton en clair n'existe qu'une fois, dans la réponse HTTP ; la base n'en garde
 * que l'empreinte. À chaque rafraîchissement l'ancien jeton est révoqué et un
 * nouveau émis : un jeton rejoué une seconde fois est donc refusé.
 */
final class RefreshTokenManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenRepository $repository,
        private readonly int $refreshTtl,
    ) {
    }

    /** @return string le jeton en clair, à renvoyer au client et à ne jamais réafficher */
    public function issue(User $user, string $userAgent = ''): string
    {
        $plain = bin2hex(random_bytes(32));

        $this->em->persist(new RefreshToken(
            $user,
            $this->hash($plain),
            new \DateTimeImmutable(\sprintf('+%d seconds', $this->refreshTtl)),
            $userAgent,
        ));
        $this->em->flush();

        return $plain;
    }

    /**
     * Vérifie un jeton présenté, le révoque et en émet un nouveau.
     *
     * @return array{0: User, 1: string}|null le compte et le nouveau jeton, ou null si refusé
     */
    public function rotate(string $plain, string $userAgent = ''): ?array
    {
        $token = $this->repository->findOneByHash($this->hash($plain));

        if (null === $token || !$token->isUsable()) {
            return null;
        }

        $token->revoke();
        $user = $token->getOwner();
        $this->em->flush();

        return [$user, $this->issue($user, $userAgent)];
    }

    public function revoke(string $plain): void
    {
        $this->repository->findOneByHash($this->hash($plain))?->revoke();
        $this->em->flush();
    }

    public function revokeAll(User $user): void
    {
        $this->repository->revokeAllFor($user);
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
