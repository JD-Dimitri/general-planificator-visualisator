<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ItemScheduler;
use PHPUnit\Framework\TestCase;

/**
 * Le calcul des dates de récurrence, sans base ni conteneur.
 *
 * Les dates sont toutes relatives à aujourd'hui : un test écrit sur une date
 * fixe se met à échouer le jour où cette date devient passée, puisque le
 * calculateur rattrape alors les occurrences manquées.
 */
final class ItemSchedulerTest extends TestCase
{
    private ItemScheduler $scheduler;
    private \DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->scheduler = new ItemScheduler();
        $this->today = new \DateTimeImmutable('today');
    }

    public function testSimpleSteps(): void
    {
        self::assertSame($this->day('+1 day'), $this->next($this->today, 'daily'));
        self::assertSame($this->day('+7 days'), $this->next($this->today, 'weekly'));
    }

    public function testWeekdaysSkipTheWeekend(): void
    {
        $friday = new \DateTimeImmutable('next friday');
        $next = $this->scheduler->nextDate($friday, 'weekdays');

        self::assertNotNull($next);
        self::assertSame('1', $next->format('N'), 'Après un vendredi vient le lundi.');
        self::assertSame($friday->modify('+3 days')->format('Y-m-d'), $next->format('Y-m-d'));
    }

    public function testNoRecurrenceGivesNoDate(): void
    {
        self::assertNull($this->scheduler->nextDate($this->today, ''));
    }

    public function testMonthlyFallsBackToTheLastDayOfAShortMonth(): void
    {
        // Le 31 janvier de l'an prochain : toujours dans le futur, donc aucun rattrapage.
        $january31 = $this->januaryThirtyFirstNextYear();
        $next = $this->scheduler->nextDate($january31, 'monthly');

        self::assertNotNull($next);
        self::assertSame('2', $next->format('n'), 'On atterrit bien en février.');
        self::assertSame($next->format('t'), $next->format('j'), 'Faute de 31 février, le dernier jour du mois.');
    }

    /**
     * Le 31 ramené au 28 février ne doit pas rester au 28 les mois suivants.
     * C'est le rôle du quantième de référence, porté par l'élément et transmis
     * d'occurrence en occurrence.
     */
    public function testMonthlyDoesNotDriftAfterAShortMonth(): void
    {
        $date = $this->januaryThirtyFirstNextYear();
        $days = [];

        for ($i = 0; $i < 5; ++$i) {
            $date = $this->scheduler->nextDate($date, 'monthly', 31);
            self::assertNotNull($date);
            $days[] = (int) $date->format('j');
        }

        // Février (28 ou 29), puis mars 31, avril 30, mai 31, juin 30.
        self::assertContains($days[0], [28, 29]);
        self::assertSame([31, 30, 31, 30], \array_slice($days, 1));
    }

    /** Sans quantième de référence, la suite retient la date ramenée : c'est bien la dérive qu'on évite. */
    public function testWithoutAnAnchorTheMonthlySeriesWouldDrift(): void
    {
        $date = $this->scheduler->nextDate($this->januaryThirtyFirstNextYear(), 'monthly');
        self::assertNotNull($date);

        $second = $this->scheduler->nextDate($date, 'monthly');
        self::assertNotNull($second);
        self::assertSame((int) $date->format('j'), (int) $second->format('j'));
    }

    /** Le rattrapage ne doit pas dépendre du nombre d'occurrences manquées. */
    public function testCatchUpLandsNearTodayWhateverTheGap(): void
    {
        $bounds = ['daily' => 1, 'weekly' => 7, 'weekdays' => 3, 'monthly' => 32];

        foreach ($bounds as $recurrence => $maxDays) {
            $next = $this->scheduler->nextDate($this->today->modify('-5 years'), $recurrence);

            self::assertNotNull($next);
            self::assertLessThanOrEqual(
                $maxDays,
                (int) $this->today->diff($next)->days,
                \sprintf('Récurrence « %s » : le rattrapage saute trop loin.', $recurrence)
            );
        }
    }

    /** Une tâche oubliée pendant deux ans repart aujourd'hui, pas dans le passé. */
    public function testCatchesUpOnLongForgottenItems(): void
    {
        foreach (['daily', 'weekly', 'weekdays', 'monthly'] as $recurrence) {
            $next = $this->scheduler->nextDate($this->today->modify('-2 years'), $recurrence);

            self::assertNotNull($next, \sprintf('Récurrence « %s ».', $recurrence));
            self::assertGreaterThanOrEqual($this->today, $next, \sprintf('Récurrence « %s ».', $recurrence));
        }
    }

    private function next(\DateTimeImmutable $from, string $recurrence): ?string
    {
        return $this->scheduler->nextDate($from, $recurrence)?->format('Y-m-d');
    }

    private function day(string $modifier): string
    {
        return $this->today->modify($modifier)->format('Y-m-d');
    }

    private function januaryThirtyFirstNextYear(): \DateTimeImmutable
    {
        return $this->today->setDate((int) $this->today->format('Y') + 1, 1, 31);
    }
}
