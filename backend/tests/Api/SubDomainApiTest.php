<?php

declare(strict_types=1);

namespace App\Tests\Api;

/**
 * La hiérarchie Famille › Parents › Maman est la nouveauté structurelle de GPV :
 * ces tests gardent l'arbre droit.
 */
final class SubDomainApiTest extends ApiTestCase
{
    private string $familyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAs($this->createUser());
        $this->familyId = $this->send('POST', '/api/domains', ['name' => 'Famille', 'hue' => 345])['id'];
    }

    public function testThreeLevelsDeep(): void
    {
        $parents = $this->send('POST', '/api/sub-domains', ['name' => 'Parents', 'domainId' => $this->familyId]);
        $this->assertStatus(201);
        self::assertSame(0, $parents['depth']);
        self::assertNull($parents['parentId']);

        $maman = $this->send('POST', '/api/sub-domains', [
            'name' => 'Maman',
            'domainId' => $this->familyId,
            'parentId' => $parents['id'],
            'template' => 'demarche',
        ]);
        $this->assertStatus(201);
        self::assertSame(1, $maman['depth']);
        self::assertSame($parents['id'], $maman['parentId']);
        self::assertSame($this->familyId, $maman['domainId']);
    }

    public function testChildInheritsTheDomainOfItsParent(): void
    {
        $other = $this->send('POST', '/api/domains', ['name' => 'Santé'])['id'];
        $parents = $this->send('POST', '/api/sub-domains', ['name' => 'Parents', 'domainId' => $this->familyId]);

        // domainId contredit parentId : le parent fait foi.
        $child = $this->send('POST', '/api/sub-domains', [
            'name' => 'Maman',
            'domainId' => $other,
            'parentId' => $parents['id'],
        ]);

        $this->assertStatus(201);
        self::assertSame($this->familyId, $child['domainId']);
    }

    public function testASubDomainCannotBecomeItsOwnDescendant(): void
    {
        $parents = $this->send('POST', '/api/sub-domains', ['name' => 'Parents', 'domainId' => $this->familyId]);
        $maman = $this->send('POST', '/api/sub-domains', ['name' => 'Maman', 'domainId' => $this->familyId, 'parentId' => $parents['id']]);

        $this->send('PATCH', '/api/sub-domains/'.$parents['id'], ['parentId' => $maman['id']]);

        $this->assertStatus(400);
    }

    public function testUnknownTemplateIsRejected(): void
    {
        $this->send('POST', '/api/sub-domains', ['name' => 'Maman', 'domainId' => $this->familyId, 'template' => 'nimporte-quoi']);

        $this->assertStatus(422);
    }

    public function testDeletingALevelLiftsItsContentInsteadOfLosingIt(): void
    {
        $parents = $this->send('POST', '/api/sub-domains', ['name' => 'Parents', 'domainId' => $this->familyId]);
        $maman = $this->send('POST', '/api/sub-domains', ['name' => 'Maman', 'domainId' => $this->familyId, 'parentId' => $parents['id']]);

        $this->send('DELETE', '/api/sub-domains/'.$parents['id']);
        $this->assertStatus(204);

        $remaining = $this->send('GET', '/api/sub-domains');
        self::assertCount(1, $remaining);
        self::assertSame($maman['id'], $remaining[0]['id']);
        self::assertNull($remaining[0]['parentId'], 'Maman remonte directement sous Famille.');
    }

    public function testListCanBeFilteredByDomain(): void
    {
        $health = $this->send('POST', '/api/domains', ['name' => 'Santé'])['id'];
        $this->send('POST', '/api/sub-domains', ['name' => 'Parents', 'domainId' => $this->familyId]);
        $this->send('POST', '/api/sub-domains', ['name' => 'Rendez-vous médicaux', 'domainId' => $health, 'template' => 'rdv']);

        $filtered = $this->send('GET', '/api/sub-domains?domainId='.$health);

        $this->assertStatus(200);
        self::assertCount(1, $filtered);
        self::assertSame('Rendez-vous médicaux', $filtered[0]['name']);
    }
}
