<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\DomainInput;
use App\Entity\Domain;
use App\Repository\DomainRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\OwnedLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Les domaines de vie. C'est la tranche de référence : les autres ressources
 * (éléments, accès, vacations) se déclinent sur ce modèle.
 */
#[Route('/api/domains', name: 'api_domain_')]
final class DomainController extends AbstractApiController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(DomainRepository $domains): JsonResponse
    {
        return $this->ok($domains->findForOwner($this->currentUser()));
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(Domain $domain): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $domain);

        return $this->ok($domain);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, DomainRepository $domains, OwnedLocator $locator): JsonResponse
    {
        $input = $this->input($request, DomainInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        // Le client peut imposer l'identifiant pour rejouer une création faite hors ligne.
        // Rejouée, la requête rend le domaine déjà créé au lieu d'un doublon ; si
        // l'identifiant est pris par un autre compte, on refuse sans rien en dire.
        if (null !== $input->id) {
            $mine = $locator->domain($input->id, $this->currentUser());

            if (null !== $mine) {
                return $this->ok($mine);
            }

            if (null !== $domains->find(Uuid::fromString($input->id))) {
                return $this->json([
                    'error' => 'id_taken',
                    'message' => 'Cet identifiant est déjà utilisé. Réessaie avec un autre.',
                ], Response::HTTP_CONFLICT);
            }
        }

        $domain = new Domain(null !== $input->id ? Uuid::fromString($input->id) : null);
        $domain->setOwner($this->currentUser());
        $input->applyTo($domain);

        $this->em->persist($domain);
        $this->em->flush();

        return $this->ok($domain, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'])]
    public function update(Request $request, Domain $domain): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $domain);

        $input = $this->input($request, DomainInput::class, DomainInput::fromEntity($domain));

        if ($response = $this->violations($input)) {
            return $response;
        }

        $input->applyTo($domain);
        $domain->touch();
        $this->em->flush();

        return $this->ok($domain);
    }

    /**
     * Supprimer un domaine ne doit jamais laisser d'orphelins.
     * Sans `moveTo`, tout ce qu'il contient part avec lui (cascade en base) ;
     * avec `moveTo`, éléments et accès rejoignent le domaine indiqué.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request, Domain $domain, DomainRepository $domains): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $domain);

        $moveTo = $request->query->get('moveTo');

        if (\is_string($moveTo) && '' !== $moveTo) {
            if (!Uuid::isValid($moveTo)) {
                return $this->json(['error' => 'request_error', 'message' => 'Domaine de destination invalide.'], Response::HTTP_BAD_REQUEST);
            }

            $target = $domains->find(Uuid::fromString($moveTo));

            if (null === $target || !$this->isGranted(OwnerVoter::EDIT, $target) || $target->getId()->equals($domain->getId())) {
                return $this->json(['error' => 'request_error', 'message' => 'Domaine de destination introuvable.'], Response::HTTP_BAD_REQUEST);
            }

            $this->em->createQuery('UPDATE App\Entity\Item i SET i.domain = :target, i.subDomain = NULL WHERE i.domain = :source')
                ->setParameter('target', $target)->setParameter('source', $domain)->execute();
            $this->em->createQuery('UPDATE App\Entity\AccessLink l SET l.domain = :target, l.subDomain = NULL WHERE l.domain = :source')
                ->setParameter('target', $target)->setParameter('source', $domain)->execute();
        }

        $this->em->remove($domain);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
