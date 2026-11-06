<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\AccessLinkRepository;
use App\Repository\DomainRepository;
use App\Repository\InboxEntryRepository;
use App\Repository\ItemRepository;
use App\Repository\ShiftRepository;
use App\Repository\SubDomainRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tout l'écran en une requête.
 *
 * GPV reste ouvert en permanence et affiche d'un coup les domaines, leurs
 * sous-domaines, les éléments, les accès et le planning. Six requêtes séparées
 * arriveraient en ordre dispersé et feraient clignoter l'écran à chaque retour ;
 * une seule donne un état cohérent.
 */
final class SnapshotController extends AbstractApiController
{
    #[Route('/api/snapshot', name: 'api_snapshot', methods: ['GET'])]
    public function __invoke(
        DomainRepository $domains,
        SubDomainRepository $subDomains,
        ItemRepository $items,
        AccessLinkRepository $links,
        ShiftRepository $shifts,
        InboxEntryRepository $inbox,
    ): JsonResponse {
        $user = $this->currentUser();

        return $this->ok([
            'user' => $user,
            'domains' => $domains->findForOwner($user),
            'subDomains' => $subDomains->findForOwner($user),
            'items' => $items->findForOwner($user),
            'accessLinks' => $links->findForOwner($user),
            'shifts' => $shifts->findForOwner($user),
            'inbox' => $inbox->findForOwner($user),
            'fetchedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
