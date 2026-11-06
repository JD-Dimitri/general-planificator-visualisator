<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\FocusSessionInput;
use App\Entity\FocusSession;
use App\Repository\FocusSessionRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\OwnedLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Les séances du mode Focus. Une séance enregistrée alimente à la fois les
 * statistiques du jour et le temps cumulé sur l'élément travaillé.
 */
#[Route('/api/focus-sessions', name: 'api_focus_')]
final class FocusSessionController extends AbstractApiController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, FocusSessionRepository $sessions): JsonResponse
    {
        $list = $sessions->findForOwner($this->currentUser());
        $days = $request->query->getInt('days', 0);

        if ($days > 0) {
            $since = new \DateTimeImmutable(\sprintf('today -%d days', $days - 1));
            $list = array_values(array_filter($list, static fn (FocusSession $s) => null !== $s->getDay() && $s->getDay() >= $since));
        }

        return $this->ok($list);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, OwnedLocator $locator): JsonResponse
    {
        $user = $this->currentUser();
        $input = $this->input($request, FocusSessionInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        $item = $locator->item($input->itemId, $user);

        if (null !== $input->itemId && null === $item) {
            return $this->json(['error' => 'request_error', 'message' => 'Élément introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        // Le domaine suit l'élément travaillé ; sans élément, le client peut le préciser.
        $domain = $item?->getDomain() ?? $locator->domain($input->domainId, $user);

        $session = new FocusSession(null !== $input->id ? Uuid::fromString($input->id) : null);
        $session->setOwner($user)
            ->setDay(new \DateTimeImmutable((string) $input->day))
            ->setMinutes($input->minutes)
            ->setMode($input->mode)
            ->setItem($item)
            ->setDomain($domain);

        $this->em->persist($session);

        $item?->addFocusMinutes($input->minutes);

        $this->em->flush();

        return $this->ok($session, Response::HTTP_CREATED);
    }

    /**
     * Temps de concentration agrégé : par jour et par domaine sur la période
     * demandée (sept jours par défaut).
     */
    #[Route('/stats', name: 'stats', methods: ['GET'], priority: 1)]
    public function stats(Request $request, FocusSessionRepository $sessions): JsonResponse
    {
        $days = max(1, min(365, $request->query->getInt('days', 7)));
        $since = new \DateTimeImmutable(\sprintf('today -%d days', $days - 1));
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $perDay = [];
        $perDomain = [];
        $total = 0;
        $todayTotal = 0;

        foreach ($sessions->findForOwner($this->currentUser()) as $session) {
            $day = $session->getDay();

            if (null === $day || $day < $since) {
                continue;
            }

            $key = $day->format('Y-m-d');
            $perDay[$key] = ($perDay[$key] ?? 0) + $session->getMinutes();
            $total += $session->getMinutes();

            if ($key === $today) {
                $todayTotal += $session->getMinutes();
            }

            $domainId = $session->getDomainId() ?? 'sans-domaine';
            $perDomain[$domainId] = ($perDomain[$domainId] ?? 0) + $session->getMinutes();
        }

        // Les jours sans séance valent zéro : une courbe avec des trous se lit mal.
        $series = [];
        for ($offset = $days - 1; $offset >= 0; --$offset) {
            $key = (new \DateTimeImmutable(\sprintf('today -%d days', $offset)))->format('Y-m-d');
            $series[] = ['day' => $key, 'minutes' => $perDay[$key] ?? 0];
        }

        return new JsonResponse([
            'days' => $days,
            'totalMinutes' => $total,
            'todayMinutes' => $todayTotal,
            'perDay' => $series,
            'perDomain' => $perDomain,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(FocusSession $focusSession): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $focusSession);

        $this->em->remove($focusSession);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
