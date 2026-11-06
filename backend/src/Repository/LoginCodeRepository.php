<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LoginCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<LoginCode>
 */
class LoginCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginCode::class);
    }

    public function findChallenge(string $id): ?LoginCode
    {
        return Uuid::isValid($id) ? $this->find(Uuid::fromString($id)) : null;
    }

    /** Un nouveau code annule les précédents : un seul doit être valable à la fois. */
    public function consumePendingFor(User $user, string $purpose): void
    {
        $this->createQueryBuilder('c')
            ->update()
            ->set('c.consumedAt', ':now')
            ->andWhere('c.user = :user')
            ->andWhere('c.purpose = :purpose')
            ->andWhere('c.consumedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->setParameter('purpose', $purpose)
            ->getQuery()
            ->execute();
    }

    /** Combien de codes ce compte a-t-il demandés depuis `$since` ? Borne les renvois. */
    public function countSince(User $user, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.user = :user')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function purgeExpired(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.expiresAt < :limit')
            ->setParameter('limit', new \DateTimeImmutable('-1 day'))
            ->getQuery()
            ->execute();
    }
}
