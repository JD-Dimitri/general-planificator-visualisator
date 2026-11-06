<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Item;

final class ItemApiTest extends ApiTestCase
{
    private string $familyId;
    private string $parentsId;
    private string $mamanId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAs($this->createUser());

        $this->familyId = $this->send('POST', '/api/domains', ['name' => 'Famille', 'hue' => 345])['id'];
        $this->parentsId = $this->send('POST', '/api/sub-domains', ['name' => 'Parents', 'domainId' => $this->familyId])['id'];
        $this->mamanId = $this->send('POST', '/api/sub-domains', [
            'name' => 'Maman',
            'domainId' => $this->familyId,
            'parentId' => $this->parentsId,
            'template' => 'demarche',
        ])['id'];
    }

    public function testCreateWithTemplateFieldsAndSteps(): void
    {
        $item = $this->send('POST', '/api/items', [
            'title' => 'Payer la créance AP-HP',
            'domainId' => $this->familyId,
            'subDomainId' => $this->mamanId,
            'priority' => 'haute',
            'dueDate' => '2026-11-06',
            'fields' => ['organisme' => 'AP-HP', 'montant' => '30,52 €'],
            'subtasks' => [
                ['label' => 'Vérifier le paiement', 'done' => true],
                ['label' => 'Payer en ligne'],
                ['label' => 'Garder le justificatif'],
            ],
        ]);

        $this->assertStatus(201);
        self::assertSame('haute', $item['priority']);
        self::assertSame('2026-11-06', substr($item['dueDate'], 0, 10));
        self::assertSame('AP-HP', $item['fields']['organisme']);
        self::assertCount(3, $item['subtasks']);
        self::assertSame(33, $item['progress'], 'Une étape sur trois faite donne 33 %.');
    }

    public function testProgressComesFromStepsWhenThereAreSome(): void
    {
        $item = $this->send('POST', '/api/items', [
            'title' => 'Dossier',
            'domainId' => $this->familyId,
            'manualProgress' => 90,
            'subtasks' => [['label' => 'A'], ['label' => 'B']],
        ]);

        self::assertSame(0, $item['progress'], 'Les étapes priment sur le curseur manuel.');

        $patched = $this->send('PATCH', '/api/items/'.$item['id'], [
            'subtasks' => [
                ['id' => $item['subtasks'][0]['id'], 'label' => 'A', 'done' => true],
                ['id' => $item['subtasks'][1]['id'], 'label' => 'B', 'done' => false],
            ],
        ]);

        self::assertSame(50, $patched['progress']);
    }

    public function testManualProgressAppliesWithoutSteps(): void
    {
        $item = $this->send('POST', '/api/items', [
            'title' => 'Lecture',
            'domainId' => $this->familyId,
            'manualProgress' => 40,
        ]);

        self::assertSame(40, $item['progress']);
    }

    public function testItemInASubDomainTakesItsDomain(): void
    {
        $other = $this->send('POST', '/api/domains', ['name' => 'Santé'])['id'];

        $item = $this->send('POST', '/api/items', [
            'title' => 'Rangé par son sous-domaine',
            'domainId' => $other,
            'subDomainId' => $this->mamanId,
        ]);

        $this->assertStatus(201);
        self::assertSame($this->familyId, $item['domainId']);
    }

    public function testFilterBySubDomainIncludesItsDescendants(): void
    {
        $this->send('POST', '/api/items', ['title' => 'Pour maman', 'domainId' => $this->familyId, 'subDomainId' => $this->mamanId]);
        $this->send('POST', '/api/items', ['title' => 'Pour les parents', 'domainId' => $this->familyId, 'subDomainId' => $this->parentsId]);

        $branch = $this->send('GET', '/api/items?subDomainId='.$this->parentsId);
        $leaf = $this->send('GET', '/api/items?subDomainId='.$this->mamanId);

        self::assertCount(2, $branch, 'Parents rapporte aussi ce qui est rangé dans Maman.');
        self::assertCount(1, $leaf);
    }

    public function testFilterOnLateItems(): void
    {
        $this->send('POST', '/api/items', ['title' => 'En retard', 'domainId' => $this->familyId, 'dueDate' => '2020-01-01']);
        $this->send('POST', '/api/items', ['title' => 'Plus tard', 'domainId' => $this->familyId, 'dueDate' => '2099-01-01']);

        $late = $this->send('GET', '/api/items?due=late');

        self::assertCount(1, $late);
        self::assertSame('En retard', $late[0]['title']);
    }

    public function testCompletingAWeeklyItemCreatesTheNextOne(): void
    {
        $item = $this->send('POST', '/api/items', [
            'title' => 'Séance course 5 km',
            'domainId' => $this->familyId,
            'dueDate' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'dueTime' => '07:00',
            'recurrence' => 'weekly',
            'subtasks' => [['label' => 'Échauffement', 'done' => true]],
        ]);

        $this->send('PATCH', '/api/items/'.$item['id'], ['status' => 'done']);
        $this->assertStatus(200);

        $all = $this->send('GET', '/api/items');
        self::assertCount(2, $all);

        $next = array_values(array_filter($all, static fn (array $i) => 'todo' === $i['status']))[0];
        self::assertSame('Séance course 5 km', $next['title']);
        self::assertSame('07:00', $next['dueTime']);
        self::assertSame(
            (new \DateTimeImmutable('today +7 days'))->format('Y-m-d'),
            substr($next['dueDate'], 0, 10)
        );
        self::assertFalse($next['subtasks'][0]['done'], 'Les étapes repartent décochées.');
    }

    public function testReopeningThenCompletingAgainDoesNotDuplicate(): void
    {
        $item = $this->send('POST', '/api/items', [
            'title' => 'Méditation',
            'domainId' => $this->familyId,
            'dueDate' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'recurrence' => 'daily',
        ]);

        $this->send('PATCH', '/api/items/'.$item['id'], ['status' => 'done']);
        $this->send('PATCH', '/api/items/'.$item['id'], ['status' => 'todo']);
        $this->send('PATCH', '/api/items/'.$item['id'], ['status' => 'done']);

        self::assertCount(2, $this->send('GET', '/api/items'), 'Une seule occurrence suivante, pas deux.');
    }

    public function testNonRecurringItemSpawnsNothing(): void
    {
        $item = $this->send('POST', '/api/items', ['title' => 'Une fois', 'domainId' => $this->familyId]);

        $this->send('PATCH', '/api/items/'.$item['id'], ['status' => 'done']);

        self::assertCount(1, $this->send('GET', '/api/items'));
    }

    public function testToggleOneStep(): void
    {
        $item = $this->send('POST', '/api/items', [
            'title' => 'Dossier',
            'domainId' => $this->familyId,
            'subtasks' => [['label' => 'A'], ['label' => 'B']],
        ]);

        $updated = $this->send('PATCH', '/api/items/'.$item['id'].'/subtasks/'.$item['subtasks'][1]['id'], ['done' => true]);

        $this->assertStatus(200);
        self::assertSame(50, $updated['progress']);
    }

    public function testBadTimeIsRejected(): void
    {
        $body = $this->send('POST', '/api/items', [
            'title' => 'Rendez-vous',
            'domainId' => $this->familyId,
            'dueTime' => '25:99',
        ]);

        $this->assertStatus(422);
        self::assertArrayHasKey('dueTime', $body['fields']);
    }

    public function testAnotherAccountCannotTouchMyItem(): void
    {
        $item = $this->send('POST', '/api/items', ['title' => 'Privé', 'domainId' => $this->familyId]);

        $this->loginAs($this->createUser('autre@gpv.local'));

        $this->send('GET', '/api/items/'.$item['id']);
        $this->assertStatus(403);

        self::assertSame([], $this->send('GET', '/api/items'));
    }

    public function testUnknownDomainIsRefusedWithoutSayingWhy(): void
    {
        $mine = $this->createUser('voisin@gpv.local');
        $this->em->persist($mine);
        $this->em->flush();

        $this->send('POST', '/api/items', [
            'title' => 'Chez le voisin',
            'domainId' => '0192f1a0-0000-7000-8000-000000000000',
        ]);

        $this->assertStatus(400);
    }

    public function testDeleteRemovesTheItemAndItsSteps(): void
    {
        $item = $this->send('POST', '/api/items', [
            'title' => 'À supprimer',
            'domainId' => $this->familyId,
            'subtasks' => [['label' => 'A']],
        ]);

        $this->send('DELETE', '/api/items/'.$item['id']);
        $this->assertStatus(204);

        self::assertCount(0, $this->em->getRepository(Item::class)->findAll());
        self::assertCount(0, $this->em->getRepository(\App\Entity\Subtask::class)->findAll());
    }
}
