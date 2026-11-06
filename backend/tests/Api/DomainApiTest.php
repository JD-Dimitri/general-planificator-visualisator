<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Domain;

final class DomainApiTest extends ApiTestCase
{
    public function testAnonymousGetsJsonUnauthorized(): void
    {
        $this->send('GET', '/api/domains');

        $this->assertStatus(401);
        self::assertStringContainsString('application/json', (string) $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testNewAccountGetsItsStarterDomains(): void
    {
        $this->loginAs($this->createUser(withSampleData: true));

        $domains = $this->send('GET', '/api/domains');

        $this->assertStatus(200);
        self::assertCount(7, $domains);
        self::assertSame(['Santé', 'Sport', 'Religion', 'Spiritualité', 'Formation', 'Famille', 'Sécurité'], array_column($domains, 'name'));
    }

    public function testCreateReadUpdateDelete(): void
    {
        $this->loginAs($this->createUser());

        $created = $this->send('POST', '/api/domains', ['name' => 'Finances', 'hue' => 90, 'goal' => 'Tenir mon budget']);
        $this->assertStatus(201);
        self::assertSame('Finances', $created['name']);
        self::assertSame(90, $created['hue']);
        self::assertNotEmpty($created['id']);

        $id = $created['id'];

        $read = $this->send('GET', '/api/domains/'.$id);
        $this->assertStatus(200);
        self::assertSame('Tenir mon budget', $read['goal']);

        // Un PATCH ne porte que le champ modifié : le reste doit survivre.
        $patched = $this->send('PATCH', '/api/domains/'.$id, ['goal' => 'Mettre de côté chaque mois']);
        $this->assertStatus(200);
        self::assertSame('Mettre de côté chaque mois', $patched['goal']);
        self::assertSame('Finances', $patched['name']);
        self::assertSame(90, $patched['hue']);

        $this->send('DELETE', '/api/domains/'.$id);
        $this->assertStatus(204);

        $this->send('GET', '/api/domains/'.$id);
        $this->assertStatus(404);
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->loginAs($this->createUser());

        $body = $this->send('POST', '/api/domains', ['name' => '  ']);

        $this->assertStatus(422);
        self::assertSame('validation_failed', $body['error']);
        self::assertArrayHasKey('name', $body['fields']);
    }

    public function testHueOutsideTheWheelIsRejected(): void
    {
        $this->loginAs($this->createUser());

        $this->send('POST', '/api/domains', ['name' => 'Test', 'hue' => 400]);

        $this->assertStatus(422);
    }

    public function testAnotherAccountCannotReadOrWriteMyDomain(): void
    {
        $mine = $this->createUser('moi@gpv.local');
        $domain = new Domain();
        $domain->setOwner($mine)->setName('Privé');
        $this->em->persist($domain);
        $this->em->flush();

        $this->loginAs($this->createUser('autre@gpv.local'));

        $this->send('GET', '/api/domains/'.$domain->getId());
        $this->assertStatus(403);

        $this->send('PATCH', '/api/domains/'.$domain->getId(), ['name' => 'Volé']);
        $this->assertStatus(403);

        $list = $this->send('GET', '/api/domains');
        self::assertSame([], $list);
    }

    public function testDeletingADomainCanMoveItsItemsInsteadOfLosingThem(): void
    {
        $user = $this->createUser(withSampleData: true);
        $this->loginAs($user);

        $domains = $this->send('GET', '/api/domains');
        $source = $domains[0]['id'];
        $target = $domains[1]['id'];

        $this->send('DELETE', '/api/domains/'.$source.'?moveTo='.$target);
        $this->assertStatus(204);

        $remaining = $this->send('GET', '/api/domains');
        self::assertCount(6, $remaining);

        // Les éléments du domaine supprimé ont suivi, aucun n'a disparu.
        $moved = $this->em->getRepository(\App\Entity\Item::class)->findBy(['domain' => $target]);
        self::assertNotEmpty($moved);
        foreach ($moved as $item) {
            self::assertNull($item->getSubDomain(), 'Un élément déplacé ne garde pas un sous-domaine qui a disparu.');
        }
    }
}
