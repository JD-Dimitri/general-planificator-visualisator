<?php

declare(strict_types=1);

namespace App\Tests\Api;

/** Accès directs, vacations, boîte de dépôt, séances de focus et instantané. */
final class ResourceApiTest extends ApiTestCase
{
    private string $domainId;
    private string $subDomainId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAs($this->createUser());

        $this->domainId = $this->send('POST', '/api/domains', ['name' => 'Santé', 'hue' => 150])['id'];
        $this->subDomainId = $this->send('POST', '/api/sub-domains', [
            'name' => 'Rendez-vous médicaux',
            'domainId' => $this->domainId,
            'template' => 'rdv',
        ])['id'];
    }

    // ---------- accès directs ----------

    public function testAccessLinkKeepsTheLoginAndNeverAPassword(): void
    {
        $link = $this->send('POST', '/api/access-links', [
            'label' => 'Doctolib',
            'url' => 'https://www.doctolib.fr/',
            'login' => 'marie.janine',
            'domainId' => $this->domainId,
            'subDomainId' => $this->subDomainId,
        ]);

        $this->assertStatus(201);
        self::assertSame('marie.janine', $link['login']);
        self::assertArrayNotHasKey('password', $link);
        self::assertSame($this->subDomainId, $link['subDomainId']);
    }

    public function testJavascriptUrlIsRejected(): void
    {
        $body = $this->send('POST', '/api/access-links', [
            'label' => 'Piège',
            'url' => 'javascript:alert(1)',
        ]);

        $this->assertStatus(422);
        self::assertArrayHasKey('url', $body['fields']);
    }

    public function testAccessLinkCanStayGeneral(): void
    {
        $link = $this->send('POST', '/api/access-links', ['label' => 'Gmail', 'url' => 'https://mail.google.com/']);

        $this->assertStatus(201);
        self::assertNull($link['domainId']);
    }

    // ---------- vacations ----------

    public function testNightShiftIsFlagged(): void
    {
        $shift = $this->send('POST', '/api/shifts', [
            'date' => '2026-11-10',
            'startTime' => '19:00',
            'endTime' => '07:00',
            'place' => 'Site Nord',
        ]);

        $this->assertStatus(201);
        self::assertTrue($shift['spansMidnight'], 'Une fin plus tôt que le début signifie une nuit.');
    }

    public function testDayShiftIsNotFlagged(): void
    {
        $shift = $this->send('POST', '/api/shifts', ['date' => '2026-11-10', 'startTime' => '08:00', 'endTime' => '20:00']);

        self::assertFalse($shift['spansMidnight']);
    }

    public function testPeriodIncludesANightStartedTheDayBefore(): void
    {
        $this->send('POST', '/api/shifts', ['date' => '2026-11-09', 'startTime' => '19:00', 'endTime' => '07:00']);
        $this->send('POST', '/api/shifts', ['date' => '2026-11-09', 'startTime' => '08:00', 'endTime' => '16:00']);

        $week = $this->send('GET', '/api/shifts?from=2026-11-10&to=2026-11-16');

        self::assertCount(1, $week, 'Seule la vacation de nuit déborde sur la période.');
        self::assertTrue($week[0]['spansMidnight']);
    }

    // ---------- boîte de dépôt ----------

    public function testBulkImportThenFileOneEntry(): void
    {
        $created = $this->send('POST', '/api/inbox', [
            'entries' => [
                ['text' => 'RDV dentiste', 'source' => 'notes.txt', 'suggestedDomainId' => $this->domainId, 'suggestedDueDate' => '2026-12-01'],
                ['text' => 'Acheter du pain', 'source' => 'notes.txt'],
            ],
        ]);

        $this->assertStatus(201);
        self::assertCount(2, $created);

        $item = $this->send('POST', '/api/inbox/'.$created[0]['id'].'/file', ['subDomainId' => $this->subDomainId]);

        $this->assertStatus(201);
        self::assertSame('RDV dentiste', $item['title']);
        self::assertSame($this->subDomainId, $item['subDomainId']);
        self::assertSame('2026-12-01', substr($item['dueDate'], 0, 10));
        self::assertStringContainsString('notes.txt', $item['notes']);

        self::assertCount(1, $this->send('GET', '/api/inbox'), 'La ligne rangée quitte la boîte.');
    }

    public function testFilingWithoutADestinationIsRefused(): void
    {
        $created = $this->send('POST', '/api/inbox', ['text' => 'Sans domaine']);

        $this->send('POST', '/api/inbox/'.$created[0]['id'].'/file');

        $this->assertStatus(400);
        self::assertCount(1, $this->send('GET', '/api/inbox'), 'La ligne reste dans la boîte.');
    }

    public function testOneBadLineCancelsTheWholeImport(): void
    {
        $body = $this->send('POST', '/api/inbox', [
            'entries' => [['text' => 'Correcte'], ['text' => '   ']],
        ]);

        $this->assertStatus(422);
        self::assertSame(2, $body['line']);
        self::assertSame([], $this->send('GET', '/api/inbox'), 'Rien n\'est enregistré à moitié.');
    }

    public function testClearingTheInbox(): void
    {
        $this->send('POST', '/api/inbox', ['entries' => [['text' => 'A'], ['text' => 'B']]]);

        $this->send('DELETE', '/api/inbox');
        $this->assertStatus(204);

        self::assertSame([], $this->send('GET', '/api/inbox'));
    }

    // ---------- focus ----------

    public function testFocusSessionAddsTimeToTheItemAndToTheStats(): void
    {
        $item = $this->send('POST', '/api/items', ['title' => 'Réviser', 'domainId' => $this->domainId]);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->send('POST', '/api/focus-sessions', ['day' => $today, 'minutes' => 25, 'itemId' => $item['id']]);
        $this->assertStatus(201);

        $this->send('POST', '/api/focus-sessions', ['day' => $today, 'minutes' => 25, 'itemId' => $item['id']]);

        self::assertSame(50, $this->send('GET', '/api/items/'.$item['id'])['focusMinutes']);

        $stats = $this->send('GET', '/api/focus-sessions/stats?days=7');
        self::assertSame(50, $stats['totalMinutes']);
        self::assertSame(50, $stats['todayMinutes']);
        self::assertCount(7, $stats['perDay'], 'Les jours creux valent zéro plutôt que de manquer.');
        self::assertSame(50, $stats['perDomain'][$this->domainId]);
    }

    public function testAnImpossiblyLongSessionIsRejected(): void
    {
        $body = $this->send('POST', '/api/focus-sessions', [
            'day' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'minutes' => 5000,
        ]);

        $this->assertStatus(422);
        self::assertArrayHasKey('minutes', $body['fields']);
    }

    // ---------- instantané ----------

    public function testSnapshotReturnsTheWholeScreenAtOnce(): void
    {
        $this->send('POST', '/api/items', ['title' => 'Une tâche', 'domainId' => $this->domainId]);
        $this->send('POST', '/api/access-links', ['label' => 'Ameli', 'url' => 'https://www.ameli.fr/']);
        $this->send('POST', '/api/shifts', ['date' => '2026-11-10']);
        $this->send('POST', '/api/inbox', ['text' => 'À ranger']);

        $snapshot = $this->send('GET', '/api/snapshot');

        $this->assertStatus(200);
        self::assertCount(1, $snapshot['domains']);
        self::assertCount(1, $snapshot['subDomains']);
        self::assertCount(1, $snapshot['items']);
        self::assertCount(1, $snapshot['accessLinks']);
        self::assertCount(1, $snapshot['shifts']);
        self::assertCount(1, $snapshot['inbox']);
        self::assertSame('test@gpv.local', $snapshot['user']['email']);
        self::assertNotEmpty($snapshot['fetchedAt']);
    }

    public function testSnapshotShowsNothingOfOtherAccounts(): void
    {
        $this->loginAs($this->createUser('autre@gpv.local'));

        $snapshot = $this->send('GET', '/api/snapshot');

        self::assertSame([], $snapshot['domains']);
        self::assertSame([], $snapshot['items']);
    }
}
