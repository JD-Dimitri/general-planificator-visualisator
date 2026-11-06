<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\InboxEntryInput;
use App\Entity\InboxEntry;
use App\Entity\Item;
use App\Repository\InboxEntryRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\OwnedLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * La boîte de dépôt : ce qui arrive d'un fichier ou d'un bloc-notes et attend
 * d'être rangé.
 *
 * L'extraction du texte (Word, PDF, Excel) se fait dans le navigateur ; l'API
 * ne reçoit que les lignes obtenues. Les fichiers de l'utilisateur ne quittent
 * donc pas son poste.
 */
#[Route('/api/inbox', name: 'api_inbox_')]
final class InboxController extends AbstractApiController
{
    private const MAX_BULK = 300;

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(InboxEntryRepository $entries): JsonResponse
    {
        return $this->ok($entries->findForOwner($this->currentUser()));
    }

    /**
     * Accepte une ligne (`{...}`) ou le résultat complet d'un import
     * (`{"entries": [...]}`), jusqu'à 300 lignes par requête.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, OwnedLocator $locator): JsonResponse
    {
        $user = $this->currentUser();
        $payload = json_decode($request->getContent() ?: '{}', true);

        if (!\is_array($payload)) {
            return $this->json(['error' => 'request_error', 'message' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $rows = \is_array($payload['entries'] ?? null) ? $payload['entries'] : [$payload];

        if (\count($rows) > self::MAX_BULK) {
            return $this->json([
                'error' => 'too_many_entries',
                'message' => \sprintf('Pas plus de %d lignes à la fois. Découpe ton fichier.', self::MAX_BULK),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $created = [];

        foreach ($rows as $index => $row) {
            if (!\is_array($row)) {
                continue;
            }

            $input = $this->serializer->denormalize($row, InboxEntryInput::class);
            $errors = $this->validator->validate($input);

            if (\count($errors) > 0) {
                // Une ligne fautive au milieu d'un import ne doit pas en laisser
                // la moitié enregistrée : on refuse tout et on dit laquelle.
                $response = $this->violationResponse($errors);
                $body = json_decode((string) $response->getContent(), true);
                $body['line'] = $index + 1;

                return new JsonResponse($body, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $entry = new InboxEntry(null !== $input->id ? Uuid::fromString($input->id) : null);
            $entry->setOwner($user);
            $input->applyTo($entry);
            $entry->setSuggestedDomain($locator->domain($input->suggestedDomainId, $user));
            $entry->setSuggestedSubDomain($locator->subDomain($input->suggestedSubDomainId, $user));

            $this->em->persist($entry);
            $created[] = $entry;
        }

        $this->em->flush();

        return $this->ok($created, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'])]
    public function update(Request $request, InboxEntry $inboxEntry, OwnedLocator $locator): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $inboxEntry);

        $user = $this->currentUser();
        $input = $this->input($request, InboxEntryInput::class, InboxEntryInput::fromEntity($inboxEntry));

        if ($response = $this->violations($input)) {
            return $response;
        }

        $input->applyTo($inboxEntry);
        $inboxEntry->setSuggestedDomain($locator->domain($input->suggestedDomainId, $user));
        $inboxEntry->setSuggestedSubDomain($locator->subDomain($input->suggestedSubDomainId, $user));
        $inboxEntry->touch();
        $this->em->flush();

        return $this->ok($inboxEntry);
    }

    /**
     * Ranger une ligne : elle devient un élément et quitte la boîte.
     * Le corps peut corriger le classement au dernier moment.
     */
    #[Route('/{id}/file', name: 'file', methods: ['POST'])]
    public function file(Request $request, InboxEntry $inboxEntry, OwnedLocator $locator): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $inboxEntry);

        $user = $this->currentUser();
        $payload = json_decode($request->getContent() ?: '{}', true);
        $payload = \is_array($payload) ? $payload : [];

        $domainId = \is_string($payload['domainId'] ?? null) ? $payload['domainId'] : $inboxEntry->getSuggestedDomainId();
        $subDomainId = \array_key_exists('subDomainId', $payload)
            ? (\is_string($payload['subDomainId']) ? $payload['subDomainId'] : null)
            : $inboxEntry->getSuggestedSubDomainId();

        $placement = $locator->placement($domainId, $subDomainId, $user);

        if (null === $placement) {
            return $this->json([
                'error' => 'destination_required',
                'message' => 'Choisis le domaine où ranger cette ligne.',
            ], Response::HTTP_BAD_REQUEST);
        }

        [$domain, $subDomain] = $placement;

        $item = new Item();
        $item->setOwner($user)
            ->setDomain($domain)
            ->setSubDomain($subDomain)
            ->setTitle($inboxEntry->getText())
            ->setDueDate($inboxEntry->getSuggestedDueDate())
            ->setDueTime($inboxEntry->getSuggestedDueTime())
            ->setNotes('' !== $inboxEntry->getSource() ? 'Importé de '.$inboxEntry->getSource() : '');

        $this->em->persist($item);
        $this->em->remove($inboxEntry);
        $this->em->flush();

        return $this->ok($item, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(InboxEntry $inboxEntry): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $inboxEntry);

        $this->em->remove($inboxEntry);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Vide la boîte sans rien ranger. */
    #[Route('', name: 'clear', methods: ['DELETE'])]
    public function clear(InboxEntryRepository $entries): JsonResponse
    {
        foreach ($entries->findForOwner($this->currentUser()) as $entry) {
            $this->em->remove($entry);
        }

        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
