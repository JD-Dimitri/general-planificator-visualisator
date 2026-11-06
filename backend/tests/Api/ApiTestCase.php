<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Service\AccountSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base des tests d'API : une base SQLite neuve par test, et un helper
 * pour envoyer du JSON authentifié.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    protected function createUser(string $email = 'test@gpv.local', bool $withSampleData = false): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email)
            ->setDisplayName('Testeur')
            ->setPassword($hasher->hashPassword($user, 'motdepassedetest'));
        $user->markVerified();

        $this->em->persist($user);

        if ($withSampleData) {
            static::getContainer()->get(AccountSeeder::class)->seed($user);
        }

        $this->em->flush();

        return $user;
    }

    protected function loginAs(User $user): void
    {
        $this->client->loginUser($user);
    }

    /** @return array<mixed> le corps JSON décodé de la réponse */
    protected function send(string $method, string $uri, ?array $body = null): array
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: null !== $body ? json_encode($body, \JSON_THROW_ON_ERROR) : null,
        );

        $content = $this->client->getResponse()->getContent();

        return '' === $content || false === $content ? [] : (array) json_decode($content, true);
    }

    protected function assertStatus(int $expected): void
    {
        self::assertSame(
            $expected,
            $this->client->getResponse()->getStatusCode(),
            'Corps de la réponse : '.substr((string) $this->client->getResponse()->getContent(), 0, 600)
        );
    }
}
