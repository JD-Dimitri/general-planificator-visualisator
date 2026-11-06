<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class AuthApiTest extends ApiTestCase
{
    public function testRegisterCreatesAnAccountAlreadyFurnished(): void
    {
        $body = $this->send('POST', '/api/auth/register', [
            'email' => 'Nouveau@GPV.local',
            'password' => 'un-mot-de-passe-assez-long',
            'displayName' => 'Nouveau',
        ]);

        $this->assertStatus(201);
        self::assertSame('nouveau@gpv.local', $body['email'], "L'adresse est normalisée en minuscules.");
        self::assertSame('free', $body['plan']);
        self::assertArrayNotHasKey('password', $body, 'Le mot de passe ne sort jamais de l\'API.');

        self::assertNotEmpty($this->em->getRepository(\App\Entity\Domain::class)->findAll());
    }

    public function testShortPasswordIsRejected(): void
    {
        $body = $this->send('POST', '/api/auth/register', ['email' => 'court@gpv.local', 'password' => 'court']);

        $this->assertStatus(422);
        self::assertArrayHasKey('password', $body['fields']);
    }

    public function testSameEmailTwiceIsRefused(): void
    {
        $this->createUser('deja@gpv.local');

        $this->send('POST', '/api/auth/register', ['email' => 'deja@gpv.local', 'password' => 'un-mot-de-passe-assez-long']);

        $this->assertStatus(409);
    }

    public function testLoginWithCookieThenReadMe(): void
    {
        $this->createUser('session@gpv.local');

        $this->send('POST', '/api/auth/login', ['email' => 'session@gpv.local', 'password' => 'motdepassedetest']);
        $this->assertStatus(200);

        $me = $this->send('GET', '/api/auth/me');
        $this->assertStatus(200);
        self::assertSame('session@gpv.local', $me['email']);
    }

    public function testWrongPasswordSaysNothingUseful(): void
    {
        $this->createUser('session@gpv.local');

        $body = $this->send('POST', '/api/auth/login', ['email' => 'session@gpv.local', 'password' => 'faux']);

        $this->assertStatus(401);
        self::assertSame('invalid_credentials', $body['error']);
    }

    public function testTokenThenRefreshRotatesAndInvalidatesTheOldToken(): void
    {
        $this->createUser('mobile@gpv.local');

        $issued = $this->send('POST', '/api/auth/token', ['email' => 'mobile@gpv.local', 'password' => 'motdepassedetest']);
        $this->assertStatus(200);
        self::assertNotEmpty($issued['token']);
        self::assertNotEmpty($issued['refreshToken']);

        $refreshed = $this->send('POST', '/api/auth/refresh', ['refreshToken' => $issued['refreshToken']]);
        $this->assertStatus(200);
        self::assertNotSame($issued['refreshToken'], $refreshed['refreshToken']);

        // Rejouer l'ancien jeton doit échouer : c'est ce qui rend un vol détectable.
        $this->send('POST', '/api/auth/refresh', ['refreshToken' => $issued['refreshToken']]);
        $this->assertStatus(401);
    }

    public function testBearerTokenOpensTheApi(): void
    {
        $this->createUser('mobile@gpv.local');
        $issued = $this->send('POST', '/api/auth/token', ['email' => 'mobile@gpv.local', 'password' => 'motdepassedetest']);

        $this->client->request('GET', '/api/domains', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$issued['token'],
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertStatus(200);
    }

    public function testHealthIsPublic(): void
    {
        $body = $this->send('GET', '/api/health');

        $this->assertStatus(200);
        self::assertSame('ok', $body['status']);
    }
}
