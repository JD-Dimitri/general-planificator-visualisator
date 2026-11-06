<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SubDomain;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubDomain>
 */
class SubDomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubDomain::class);
    }

    /**
     * Toutes les lignes de ce compte, et d'aucun autre.
     *
     * @return list<SubDomain>
     */
    public function findForOwner(User $owner): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('e.position', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Un sous-domaine et toute sa descendance : Parents rapporte aussi Maman et Papa.
     * Chargé en une requête, puis parcouru en mémoire — l'arbre d'un compte tient
     * dans quelques dizaines de lignes.
     *
     * @return list<SubDomain>
     */
    public function findBranch(SubDomain $root): array
    {
        $all = $this->findForOwner($root->getOwner());
        $byParent = [];
        foreach ($all as $node) {
            $byParent[$node->getParentId() ?? ''][] = $node;
        }

        $branch = [$root];
        for ($i = 0; $i < \count($branch); ++$i) {
            foreach ($byParent[(string) $branch[$i]->getId()] ?? [] as $child) {
                $branch[] = $child;
            }
        }

        return $branch;
    }
}
