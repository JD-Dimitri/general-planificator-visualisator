<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Item;
use App\Entity\Subtask;

/**
 * Récurrence des éléments.
 *
 * Un élément récurrent terminé engendre son occurrence suivante : la séance de
 * sport hebdomadaire ou la méditation du jour réapparaissent sans que personne
 * ait à les recréer. L'occurrence naît à la clôture et non à l'avance, pour que
 * la liste ne se remplisse pas de dates lointaines.
 *
 * Deux exigences guident le calcul :
 *
 * - **Pas de date passée.** Une tâche oubliée pendant deux ans repart
 *   aujourd'hui. Le rattrapage se calcule d'un coup, jamais pas à pas : sept
 *   cents itérations pour deux ans de récurrence quotidienne seraient à la fois
 *   lentes et bornées par une limite arbitraire.
 * - **Pas de dérive.** « Le 31 de chaque mois » ramené au 28 février doit
 *   revenir au 31 en mars. Le quantième de départ est donc conservé sur
 *   l'élément et transmis d'occurrence en occurrence.
 */
final class ItemScheduler
{
    /**
     * Date de l'occurrence suivante : la première qui soit à la fois après
     * `$from` et aujourd'hui au plus tôt. Rend null sans récurrence.
     *
     * @param int|null $anchorDay quantième de référence d'une récurrence mensuelle
     */
    public function nextDate(\DateTimeImmutable $from, string $recurrence, ?int $anchorDay = null): ?\DateTimeImmutable
    {
        $today = new \DateTimeImmutable('today');
        $from = $from->setTime(0, 0);
        $anchorDay ??= (int) $from->format('j');

        return match ($recurrence) {
            'daily' => $this->laterOf($from->modify('+1 day'), $today),
            'weekly' => $this->nextWeekly($from, $today),
            'weekdays' => $this->toWeekday($this->laterOf($from->modify('+1 day'), $today)),
            'monthly' => $this->nextMonthly($from, $today, $anchorDay),
            default => null,
        };
    }

    /**
     * Copie l'élément sur sa date suivante, étapes décochées et compteurs remis
     * à zéro. Rend null quand l'élément ne se répète pas.
     */
    public function spawnNext(Item $item): ?Item
    {
        if ('' === $item->getRecurrence()) {
            return null;
        }

        $anchorDay = $item->getRecurrenceAnchorDay();
        $next = $this->nextDate(
            $item->getDueDate() ?? new \DateTimeImmutable('today'),
            $item->getRecurrence(),
            $anchorDay,
        );

        if (null === $next) {
            return null;
        }

        $copy = new Item();
        $copy->setOwner($item->getOwner())
            ->setDomain($item->getDomain())
            ->setSubDomain($item->getSubDomain())
            ->setTitle($item->getTitle())
            ->setPriority($item->getPriority())
            ->setDueDate($next)
            ->setDueTime($item->getDueTime())
            ->setRecurrence($item->getRecurrence())
            ->setRecurrenceAnchorDay($anchorDay)
            ->setFields($item->getFields())
            ->setNotes($item->getNotes());

        foreach ($item->getSubtasks() as $subtask) {
            $step = new Subtask();
            $step->setLabel($subtask->getLabel())->setDone(false)->setPosition($subtask->getPosition());
            $copy->addSubtask($step);
        }

        return $copy;
    }

    private function laterOf(\DateTimeImmutable $candidate, \DateTimeImmutable $floor): \DateTimeImmutable
    {
        return $candidate >= $floor ? $candidate : $floor;
    }

    /** Le premier multiple de sept jours après `$from` qui ne soit pas passé. */
    private function nextWeekly(\DateTimeImmutable $from, \DateTimeImmutable $today): \DateTimeImmutable
    {
        $candidate = $from->modify('+7 days');

        if ($candidate >= $today) {
            return $candidate;
        }

        $missedDays = (int) $candidate->diff($today)->days;
        $weeks = intdiv($missedDays + 6, 7);

        return $candidate->modify(\sprintf('+%d days', $weeks * 7));
    }

    /** Avance jusqu'au prochain jour ouvré, en restant sur place si c'en est déjà un. */
    private function toWeekday(\DateTimeImmutable $date): \DateTimeImmutable
    {
        while (\in_array((int) $date->format('N'), [6, 7], true)) {
            $date = $date->modify('+1 day');
        }

        return $date;
    }

    /** Le même quantième les mois suivants, sans jamais revenir en arrière. */
    private function nextMonthly(\DateTimeImmutable $from, \DateTimeImmutable $today, int $anchorDay): \DateTimeImmutable
    {
        $candidate = $this->anchored($from->modify('first day of next month'), $anchorDay);

        if ($candidate < $today) {
            $months = ((int) $today->format('Y') - (int) $candidate->format('Y')) * 12
                + ((int) $today->format('n') - (int) $candidate->format('n'));

            $candidate = $this->anchored(
                $candidate->modify('first day of this month')->modify(\sprintf('+%d months', max(0, $months))),
                $anchorDay,
            );
        }

        // Le mois d'aujourd'hui peut rester trop tôt : le 5 alors qu'on est le 20.
        if ($candidate < $today) {
            $candidate = $this->anchored($candidate->modify('first day of next month'), $anchorDay);
        }

        return $candidate;
    }

    /** Le quantième voulu dans le mois de `$inMonth`, ramené au dernier jour si le mois est trop court. */
    private function anchored(\DateTimeImmutable $inMonth, int $anchorDay): \DateTimeImmutable
    {
        $firstOfMonth = $inMonth->modify('first day of this month');

        return $firstOfMonth->setDate(
            (int) $firstOfMonth->format('Y'),
            (int) $firstOfMonth->format('n'),
            min($anchorDay, (int) $firstOfMonth->format('t')),
        );
    }
}
