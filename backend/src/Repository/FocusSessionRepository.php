<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FocusSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FocusSession>
 */
class FocusSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FocusSession::class);
    }

    /**
     * Toutes les lignes de ce compte, et d'aucun autre.
     *
     * @return list<FocusSession>
     */
    public function findForOwner(User $owner): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('e.day', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
