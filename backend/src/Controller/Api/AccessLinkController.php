<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\AccessLinkInput;
use App\Entity\AccessLink;
use App\Repository\AccessLinkRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\OwnedLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** Les accès directs : Doctolib dans Santé, la plateforme de formation, la messagerie. */
#[Route('/api/access-links', name: 'api_access_link_')]
final class AccessLinkController extends AbstractApiController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, AccessLinkRepository $links): JsonResponse
    {
        $list = $links->findForOwner($this->currentUser());

        if (\is_string($domainId = $request->query->get('domainId'))) {
            $list = array_values(array_filter($list, static fn (AccessLink $l) => $l->getDomainId() === $domainId));
        }

        return $this->ok($list);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(AccessLink $accessLink): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $accessLink);

        return $this->ok($accessLink);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, OwnedLocator $locator): JsonResponse
    {
        $user = $this->currentUser();
        $input = $this->input($request, AccessLinkInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        // Un accès peut rester général, sans domaine : la messagerie ne tient
        // pas plus à Santé qu'à Formation.
        $placement = null !== $input->domainId || null !== $input->subDomainId
            ? $locator->placement($input->domainId, $input->subDomainId, $user)
            : [null, null];

        if (null === $placement) {
            return $this->json(['error' => 'request_error', 'message' => 'Domaine ou sous-domaine introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        [$domain, $subDomain] = $placement;

        $link = new AccessLink(null !== $input->id ? Uuid::fromString($input->id) : null);
        $link->setOwner($user)->setDomain($domain)->setSubDomain($subDomain);
        $input->applyTo($link);

        $this->em->persist($link);
        $this->em->flush();

        return $this->ok($link, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'])]
    public function update(Request $request, AccessLink $accessLink, OwnedLocator $locator): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $accessLink);

        $user = $this->currentUser();
        $input = $this->input($request, AccessLinkInput::class, AccessLinkInput::fromEntity($accessLink));

        if ($response = $this->violations($input)) {
            return $response;
        }

        $placement = null !== $input->domainId || null !== $input->subDomainId
            ? $locator->placement($input->domainId, $input->subDomainId, $user)
            : [null, null];

        if (null === $placement) {
            return $this->json(['error' => 'request_error', 'message' => 'Domaine ou sous-domaine introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        [$domain, $subDomain] = $placement;

        $accessLink->setDomain($domain)->setSubDomain($subDomain);
        $input->applyTo($accessLink);
        $accessLink->touch();
        $this->em->flush();

        return $this->ok($accessLink);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(AccessLink $accessLink): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $accessLink);

        $this->em->remove($accessLink);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
