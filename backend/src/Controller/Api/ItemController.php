<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\ItemInput;
use App\Entity\Item;
use App\Repository\ItemRepository;
use App\Repository\SubDomainRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\ItemScheduler;
use App\Service\OwnedLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Les éléments : tâches, rendez-vous, cours, examens, séances, démarches.
 *
 * Deux comportements méritent l'attention : un élément rangé dans un
 * sous-domaine reçoit le domaine de celui-ci, et un élément récurrent terminé
 * engendre son occurrence suivante.
 */
#[Route('/api/items', name: 'api_item_')]
final class ItemController extends AbstractApiController
{
    /**
     * Filtres cumulables : `domainId`, `subDomainId` (qui inclut la descendance),
     * `status`, `due=late|today|week`, `open=1`.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        Request $request,
        ItemRepository $items,
        SubDomainRepository $subDomains,
        OwnedLocator $locator,
    ): JsonResponse {
        $user = $this->currentUser();
        $list = $items->findForOwner($user);

        if (\is_string($domainId = $request->query->get('domainId'))) {
            $list = array_filter($list, static fn (Item $i) => (string) $i->getDomain()->getId() === $domainId);
        }

        if (\is_string($subDomainId = $request->query->get('subDomainId'))) {
            $root = $locator->subDomain($subDomainId, $user);

            if (null === $root) {
                return $this->ok([]);
            }

            // « Parents » doit rapporter aussi ce qui est rangé dans Maman et Papa.
            $branch = array_map(
                static fn ($node) => (string) $node->getId(),
                $subDomains->findBranch($root)
            );
            $list = array_filter($list, static fn (Item $i) => \in_array($i->getSubDomainId(), $branch, true));
        }

        if (\is_string($status = $request->query->get('status')) && \in_array($status, Item::STATUSES, true)) {
            $list = array_filter($list, static fn (Item $i) => $i->getStatus() === $status);
        }

        if ($request->query->getBoolean('open')) {
            $list = array_filter($list, static fn (Item $i) => !$i->isDone());
        }

        if (\is_string($due = $request->query->get('due'))) {
            $today = new \DateTimeImmutable('today');
            $limit = match ($due) {
                'late' => $today,
                'today' => $today,
                'week' => $today->modify('+7 days'),
                default => null,
            };

            if (null !== $limit) {
                $list = array_filter($list, static function (Item $i) use ($due, $today, $limit): bool {
                    $date = $i->getDueDate();

                    if (null === $date || $i->isDone()) {
                        return false;
                    }

                    return 'late' === $due ? $date < $today : ($date >= $today && $date <= $limit);
                });
            }
        }

        return $this->ok(array_values($list));
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(Item $item): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $item);

        return $this->ok($item);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        ItemRepository $items,
        OwnedLocator $locator,
        ItemScheduler $scheduler,
    ): JsonResponse {
        $user = $this->currentUser();
        $input = $this->input($request, ItemInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        if (null !== $input->id) {
            $mine = $locator->item($input->id, $user);

            if (null !== $mine) {
                return $this->ok($mine);
            }

            if (null !== $items->find(Uuid::fromString($input->id))) {
                return $this->json(['error' => 'id_taken', 'message' => 'Cet identifiant est déjà utilisé.'], Response::HTTP_CONFLICT);
            }
        }

        $placement = $locator->placement($input->domainId, $input->subDomainId, $user);

        if (null === $placement) {
            return $this->json(['error' => 'request_error', 'message' => 'Domaine ou sous-domaine introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        [$domain, $subDomain] = $placement;

        $item = new Item(null !== $input->id ? Uuid::fromString($input->id) : null);
        $item->setOwner($user)->setDomain($domain)->setSubDomain($subDomain);
        $input->applyTo($item);
        $item->setStatus($input->status);

        $this->em->persist($item);

        if ($item->isDone()) {
            $this->spawnRecurrence($item, $scheduler);
        }

        $this->em->flush();

        return $this->ok($item, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'])]
    public function update(
        Request $request,
        Item $item,
        OwnedLocator $locator,
        ItemScheduler $scheduler,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $item);

        $user = $this->currentUser();
        $input = $this->input($request, ItemInput::class, ItemInput::fromEntity($item));

        if ($response = $this->violations($input)) {
            return $response;
        }

        $placement = $locator->placement($input->domainId, $input->subDomainId, $user);

        if (null === $placement) {
            return $this->json(['error' => 'request_error', 'message' => 'Domaine ou sous-domaine introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        [$domain, $subDomain] = $placement;
        $wasDone = $item->isDone();

        $item->setDomain($domain)->setSubDomain($subDomain);
        $input->applyTo($item);
        $item->setStatus($input->status);
        $item->touch();

        if (!$wasDone && $item->isDone()) {
            $this->spawnRecurrence($item, $scheduler);
        }

        $this->em->flush();

        return $this->ok($item);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Item $item): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $item);

        $this->em->remove($item);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Coche ou décoche une étape sans renvoyer l'élément entier. */
    #[Route('/{id}/subtasks/{subtaskId}', name: 'toggle_subtask', methods: ['PATCH'])]
    public function toggleSubtask(Request $request, Item $item, string $subtaskId): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $item);

        $payload = json_decode($request->getContent() ?: '{}', true);
        $done = \is_array($payload) ? (bool) ($payload['done'] ?? true) : true;

        foreach ($item->getSubtasks() as $subtask) {
            if ((string) $subtask->getId() === $subtaskId) {
                $subtask->setDone($done);
                $item->touch();
                $this->em->flush();

                return $this->ok($item);
            }
        }

        return $this->json(['error' => 'not_found', 'message' => 'Étape introuvable.'], Response::HTTP_NOT_FOUND);
    }

    private function spawnRecurrence(Item $item, ItemScheduler $scheduler): void
    {
        if ($item->hasSpawnedRecurrence()) {
            return;
        }

        $next = $scheduler->spawnNext($item);

        if (null !== $next) {
            $item->markRecurrenceSpawned();
            $this->em->persist($next);
        }
    }
}
