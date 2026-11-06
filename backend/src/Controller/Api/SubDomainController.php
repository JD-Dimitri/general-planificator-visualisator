<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\SubDomainInput;
use App\Entity\Domain;
use App\Entity\SubDomain;
use App\Repository\DomainRepository;
use App\Repository\SubDomainRepository;
use App\Security\Voter\OwnerVoter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Les sous-domaines, imbriquables sans limite : Famille › Parents › Maman.
 *
 * Deux règles tiennent l'arbre droit et sont vérifiées à chaque écriture :
 * un sous-domaine ne peut pas devenir son propre descendant, et il porte
 * toujours le domaine de son parent.
 */
#[Route('/api/sub-domains', name: 'api_sub_domain_')]
final class SubDomainController extends AbstractApiController
{
    private const MAX_DEPTH = 6;

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, SubDomainRepository $subDomains): JsonResponse
    {
        $all = $subDomains->findForOwner($this->currentUser());
        $domainId = $request->query->get('domainId');

        if (\is_string($domainId) && Uuid::isValid($domainId)) {
            $all = array_values(array_filter($all, static fn (SubDomain $s) => (string) $s->getDomain()->getId() === $domainId));
        }

        return $this->ok($all);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(SubDomain $subDomain): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $subDomain);

        return $this->ok($subDomain);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, DomainRepository $domains, SubDomainRepository $subDomains): JsonResponse
    {
        $input = $this->input($request, SubDomainInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        $parent = null;
        if (null !== $input->parentId) {
            $parent = $this->ownedSubDomain($input->parentId, $subDomains);
            if (null === $parent) {
                return $this->badRequest('Sous-domaine parent introuvable.');
            }
            if ($parent->getDepth() + 1 > self::MAX_DEPTH) {
                return $this->badRequest(\sprintf('Un sous-domaine ne peut pas dépasser %d niveaux.', self::MAX_DEPTH));
            }
        }

        $domain = $parent?->getDomain() ?? $this->ownedDomain($input->domainId, $domains);
        if (null === $domain) {
            return $this->badRequest('Domaine introuvable.');
        }

        $subDomain = new SubDomain(null !== $input->id ? Uuid::fromString($input->id) : null);
        $subDomain->setOwner($this->currentUser())->setDomain($domain)->setParent($parent);
        $input->applyTo($subDomain);

        $this->em->persist($subDomain);
        $this->em->flush();

        return $this->ok($subDomain, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'])]
    public function update(Request $request, SubDomain $subDomain, DomainRepository $domains, SubDomainRepository $subDomains): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $subDomain);

        $input = $this->input($request, SubDomainInput::class, SubDomainInput::fromEntity($subDomain));

        if ($response = $this->violations($input)) {
            return $response;
        }

        $branch = $subDomains->findBranch($subDomain);
        $branchIds = array_map(static fn (SubDomain $s) => (string) $s->getId(), $branch);

        $parent = null;
        if (null !== $input->parentId) {
            if (\in_array($input->parentId, $branchIds, true)) {
                return $this->badRequest('Un sous-domaine ne peut pas être rangé dans lui-même.');
            }

            $parent = $this->ownedSubDomain($input->parentId, $subDomains);
            if (null === $parent) {
                return $this->badRequest('Sous-domaine parent introuvable.');
            }
        }

        $domain = $parent?->getDomain() ?? $this->ownedDomain($input->domainId, $domains);
        if (null === $domain) {
            return $this->badRequest('Domaine introuvable.');
        }

        $subDomain->setParent($parent);
        $input->applyTo($subDomain);
        $subDomain->touch();

        // Un sous-domaine déplacé emmène sa descendance, ses éléments et ses accès.
        if (!$domain->getId()->equals($subDomain->getDomain()->getId())) {
            foreach ($branch as $node) {
                $node->setDomain($domain);
            }

            $this->em->createQuery('UPDATE App\Entity\Item i SET i.domain = :domain WHERE i.subDomain IN (:branch)')
                ->setParameter('domain', $domain)->setParameter('branch', $branch)->execute();
            $this->em->createQuery('UPDATE App\Entity\AccessLink l SET l.domain = :domain WHERE l.subDomain IN (:branch)')
                ->setParameter('domain', $domain)->setParameter('branch', $branch)->execute();
        }

        $this->em->flush();

        return $this->ok($subDomain);
    }

    /**
     * À la suppression, le contenu remonte d'un niveau plutôt que de disparaître :
     * enfants, éléments et accès rejoignent le parent du sous-domaine supprimé.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(SubDomain $subDomain): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $subDomain);

        $parent = $subDomain->getParent();

        $this->em->createQuery('UPDATE App\Entity\SubDomain s SET s.parent = :parent WHERE s.parent = :current')
            ->setParameter('parent', $parent)->setParameter('current', $subDomain)->execute();
        $this->em->createQuery('UPDATE App\Entity\Item i SET i.subDomain = :parent WHERE i.subDomain = :current')
            ->setParameter('parent', $parent)->setParameter('current', $subDomain)->execute();
        $this->em->createQuery('UPDATE App\Entity\AccessLink l SET l.subDomain = :parent WHERE l.subDomain = :current')
            ->setParameter('parent', $parent)->setParameter('current', $subDomain)->execute();

        $this->em->remove($subDomain);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function ownedDomain(?string $id, DomainRepository $domains): ?Domain
    {
        if (null === $id || !Uuid::isValid($id)) {
            return null;
        }

        $domain = $domains->find(Uuid::fromString($id));

        return null !== $domain && $this->isGranted(OwnerVoter::EDIT, $domain) ? $domain : null;
    }

    private function ownedSubDomain(string $id, SubDomainRepository $subDomains): ?SubDomain
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $subDomain = $subDomains->find(Uuid::fromString($id));

        return null !== $subDomain && $this->isGranted(OwnerVoter::EDIT, $subDomain) ? $subDomain : null;
    }

    private function badRequest(string $message): JsonResponse
    {
        return $this->json(['error' => 'request_error', 'message' => $message], Response::HTTP_BAD_REQUEST);
    }
}
