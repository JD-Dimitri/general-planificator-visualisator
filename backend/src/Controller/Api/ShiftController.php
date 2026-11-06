<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\ShiftInput;
use App\Entity\Shift;
use App\Repository\ShiftRepository;
use App\Security\Voter\OwnerVoter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** Les vacations de sécurité, affichées dans le planning des sept jours. */
#[Route('/api/shifts', name: 'api_shift_')]
final class ShiftController extends AbstractApiController
{
    /**
     * `from` et `to` bornent la période. Une vacation de nuit commencée la
     * veille de `from` est incluse : elle court encore pendant la période.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, ShiftRepository $shifts): JsonResponse
    {
        $list = $shifts->findForOwner($this->currentUser());

        $from = $this->date($request->query->get('from'));
        $to = $this->date($request->query->get('to'));

        if (null !== $from) {
            $earliest = $from->modify('-1 day');
            $list = array_filter($list, static function (Shift $s) use ($from, $earliest): bool {
                $date = $s->getDate();

                if (null === $date) {
                    return false;
                }

                return $date >= $from || ($s->spansMidnight() && $date >= $earliest);
            });
        }

        if (null !== $to) {
            $list = array_filter($list, static fn (Shift $s) => null !== $s->getDate() && $s->getDate() <= $to);
        }

        return $this->ok(array_values($list));
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(Shift $shift): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $shift);

        return $this->ok($shift);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $input = $this->input($request, ShiftInput::class);

        if ($response = $this->violations($input)) {
            return $response;
        }

        $shift = new Shift(null !== $input->id ? Uuid::fromString($input->id) : null);
        $shift->setOwner($this->currentUser());
        $input->applyTo($shift);

        $this->em->persist($shift);
        $this->em->flush();

        return $this->ok($shift, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'])]
    public function update(Request $request, Shift $shift): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $shift);

        $input = $this->input($request, ShiftInput::class, ShiftInput::fromEntity($shift));

        if ($response = $this->violations($input)) {
            return $response;
        }

        $input->applyTo($shift);
        $shift->touch();
        $this->em->flush();

        return $this->ok($shift);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Shift $shift): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $shift);

        $this->em->remove($shift);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
