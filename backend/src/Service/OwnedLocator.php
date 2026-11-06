<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Domain;
use App\Entity\Item;
use App\Entity\SubDomain;
use App\Entity\User;
use App\Repository\DomainRepository;
use App\Repository\ItemRepository;
use App\Repository\SubDomainRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Résout un identifiant reçu du client en entité **du compte courant**.
 *
 * Tous les contrôleurs passent par ici pour lire un `domainId` ou un `subDomainId`
 * venu d'une requête : un identifiant inconnu et un identifiant appartenant à
 * quelqu'un d'autre donnent le même résultat, `null`. L'API ne révèle donc pas
 * l'existence des données des autres comptes.
 */
final class OwnedLocator
{
    public function __construct(
        private readonly DomainRepository $domains,
        private readonly SubDomainRepository $subDomains,
        private readonly ItemRepository $items,
    ) {
    }

    public function domain(?string $id, User $owner): ?Domain
    {
        return $this->owned($this->domains, $id, $owner);
    }

    public function subDomain(?string $id, User $owner): ?SubDomain
    {
        return $this->owned($this->subDomains, $id, $owner);
    }

    public function item(?string $id, User $owner): ?Item
    {
        return $this->owned($this->items, $id, $owner);
    }

    /**
     * Le domaine d'un élément ne se discute pas : rangé dans un sous-domaine,
     * il prend celui de ce sous-domaine, quoi que dise `domainId`.
     *
     * @return array{0: Domain, 1: ?SubDomain}|null
     */
    public function placement(?string $domainId, ?string $subDomainId, User $owner): ?array
    {
        $subDomain = null !== $subDomainId ? $this->subDomain($subDomainId, $owner) : null;

        if (null !== $subDomainId && null === $subDomain) {
            return null;
        }

        $domain = $subDomain?->getDomain() ?? $this->domain($domainId, $owner);

        return null !== $domain ? [$domain, $subDomain] : null;
    }

    /**
     * @template T of object
     *
     * @param \Doctrine\Persistence\ObjectRepository<T> $repository
     *
     * @return T|null
     */
    private function owned(object $repository, ?string $id, User $owner): ?object
    {
        if (null === $id || '' === $id || !Uuid::isValid($id)) {
            return null;
        }

        $entity = $repository->find(Uuid::fromString($id));

        if (null === $entity || !method_exists($entity, 'getOwner')) {
            return null;
        }

        return $entity->getOwner()->getId()->equals($owner->getId()) ? $entity : null;
    }
}
