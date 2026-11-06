<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Domain;
use App\Entity\LoginCode;
use App\Entity\User;

/**
 * Connexion en deux temps et mot de passe oublié.
 *
 * En environnement de test, aucun e-mail ne part : l'API renvoie le code dans
 * `challenge.devCode` et le lien dans `devToken`, ce que `EXPOSE_DEV_CODES`
 * autorise uniquement hors production.
 */
final class AuthApiTest extends ApiTestCase
{
    // ---------- création de compte ----------

    public function testRegisterCreatesAnUnverifiedAccountAndSendsACode(): void
    {
        $body = $this->send('POST', '/api/auth/register', [
            'email' => 'Nouveau@GPV.local',
            'password' => 'un-mot-de-passe-assez-long',
            'displayName' => 'Nouveau',
        ]);

        $this->assertStatus(201);
        self::assertSame('code_sent', $body['status']);
        self::assertSame('email', $body['challenge']['channel']);
        self::assertStringContainsString('@gpv.local', $body['challenge']['destination']);
        self::assertStringStartsWith('n•', $body['challenge']['destination'], "L'adresse est masquée.");
        self::assertMatchesRegularExpression('/^\d{6}$/', $body['challenge']['devCode']);

        $user = $this->em->getRepository(User::class)->findOneByEmail('nouveau@gpv.local');
        self::assertNotNull($user);
        self::assertFalse($user->isVerified(), 'Le compte attend son code.');
        self::assertNotEmpty($this->em->getRepository(Domain::class)->findAll(), 'Le compte est déjà garni.');
    }

    public function testTheAccountOnlyOpensOnceTheCodeIsGiven(): void
    {
        $sent = $this->send('POST', '/api/auth/register', [
            'email' => 'nouveau@gpv.local',
            'password' => 'un-mot-de-passe-assez-long',
        ]);

        // Sans la deuxième étape, aucune session n'a été ouverte.
        $this->send('GET', '/api/auth/me');
        $this->assertStatus(401);

        $me = $this->send('POST', '/api/auth/verify', [
            'challengeId' => $sent['challenge']['challengeId'],
            'code' => $sent['challenge']['devCode'],
        ]);

        $this->assertStatus(200);
        self::assertSame('nouveau@gpv.local', $me['email']);
        self::assertTrue($me['verified']);

        $this->send('GET', '/api/auth/me');
        $this->assertStatus(200);
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

    public function testBadPhoneIsRejected(): void
    {
        $body = $this->send('POST', '/api/auth/register', [
            'email' => 'tel@gpv.local',
            'password' => 'un-mot-de-passe-assez-long',
            'phone' => '0612345678',
        ]);

        $this->assertStatus(422);
        self::assertArrayHasKey('phone', $body['fields']);
    }

    // ---------- connexion ----------

    public function testPasswordAloneNeverOpensASession(): void
    {
        $this->createUser('session@gpv.local');

        $body = $this->send('POST', '/api/auth/login', ['email' => 'session@gpv.local', 'password' => 'motdepassedetest']);

        $this->assertStatus(200);
        self::assertSame('code_sent', $body['status']);
        self::assertArrayNotHasKey('email', $body, "L'étape 1 ne renvoie pas le compte.");

        $this->send('GET', '/api/auth/me');
        $this->assertStatus(401, 'Le mot de passe seul ne donne accès à rien.');
    }

    public function testFullLoginJourney(): void
    {
        $this->createUser('session@gpv.local');

        $step1 = $this->send('POST', '/api/auth/login', ['email' => 'session@gpv.local', 'password' => 'motdepassedetest']);
        $me = $this->send('POST', '/api/auth/login/verify', [
            'challengeId' => $step1['challenge']['challengeId'],
            'code' => $step1['challenge']['devCode'],
        ]);

        $this->assertStatus(200);
        self::assertSame('session@gpv.local', $me['email']);

        $this->send('GET', '/api/domains');
        $this->assertStatus(200);
    }

    public function testEveryLoginGetsAFreshCode(): void
    {
        $this->createUser('session@gpv.local');

        $first = $this->send('POST', '/api/auth/login', ['email' => 'session@gpv.local', 'password' => 'motdepassedetest']);
        $second = $this->send('POST', '/api/auth/login', ['email' => 'session@gpv.local', 'password' => 'motdepassedetest']);

        self::assertNotSame($first['challenge']['challengeId'], $second['challenge']['challengeId']);

        // Le premier code ne vaut plus rien : un seul est valable à la fois.
        $this->send('POST', '/api/auth/login/verify', [
            'challengeId' => $first['challenge']['challengeId'],
            'code' => $first['challenge']['devCode'],
        ]);
        $this->assertStatus(401);

        $this->send('POST', '/api/auth/login/verify', [
            'challengeId' => $second['challenge']['challengeId'],
            'code' => $second['challenge']['devCode'],
        ]);
        $this->assertStatus(200);
    }

    public function testACodeCannotServeTwice(): void
    {
        $this->createUser('session@gpv.local');
        $step1 = $this->send('POST', '/api/auth/login', ['email' => 'session@gpv.local', 'password' => 'motdepassedetest']);
        $payload = ['challengeId' => $step1['challenge']['challengeId'], 'code' => $step1['challenge']['devCode']];

        $this->send('POST', '/api/auth/login/verify', $payload);
        $this->assertStatus(200);

        $this->send('POST', '/api/auth/login/verify', $payload);
        $this->assertStatus(401);
    }

    public function testWrongCodeCountsDownThenLocksTheChallenge(): void
    {
        $this->createUser('session@gpv.local');
        $step1 = $this->send('POST', '/api/auth/login', ['email' => 'session@gpv.local', 'password' => 'motdepassedetest']);
        $id = $step1['challenge']['challengeId'];

        $first = $this->send('POST', '/api/auth/login/verify', ['challengeId' => $id, 'code' => '000000']);
        $this->assertStatus(401);
        self::assertStringContainsString('essai', $first['message']);

        for ($i = 0; $i < LoginCode::MAX_ATTEMPTS; ++$i) {
            $this->send('POST', '/api/auth/login/verify', ['challengeId' => $id, 'code' => '000000']);
        }

        // Même le bon code ne passe plus : la demande est brûlée.
        $body = $this->send('POST', '/api/auth/login/verify', ['challengeId' => $id, 'code' => $step1['challenge']['devCode']]);
        $this->assertStatus(401);
        self::assertStringContainsString('nouveau code', $body['message']);
    }

    public function testWrongPasswordSaysNothingUseful(): void
    {
        $this->createUser('session@gpv.local');

        $body = $this->send('POST', '/api/auth/login', ['email' => 'session@gpv.local', 'password' => 'faux']);

        $this->assertStatus(401);
        self::assertSame('invalid_credentials', $body['error']);
    }

    public function testUnknownEmailAnswersLikeAWrongPassword(): void
    {
        $body = $this->send('POST', '/api/auth/login', ['email' => 'personne@gpv.local', 'password' => 'motdepassedetest']);

        $this->assertStatus(401);
        self::assertSame('invalid_credentials', $body['error'], "Le formulaire ne dit pas qui est inscrit.");
    }

    public function testResendGivesANewCodeAndRetiresTheOld(): void
    {
        $this->createUser('session@gpv.local');
        $step1 = $this->send('POST', '/api/auth/login', ['email' => 'session@gpv.local', 'password' => 'motdepassedetest']);

        $again = $this->send('POST', '/api/auth/code/resend', ['challengeId' => $step1['challenge']['challengeId']]);
        $this->assertStatus(200);
        self::assertNotSame($step1['challenge']['devCode'], $again['challenge']['devCode']);

        $this->send('POST', '/api/auth/login/verify', [
            'challengeId' => $again['challenge']['challengeId'],
            'code' => $again['challenge']['devCode'],
        ]);
        $this->assertStatus(200);
    }

    public function testSmsWithoutANumberFallsBackToEmail(): void
    {
        $this->createUser('session@gpv.local');

        $body = $this->send('POST', '/api/auth/login', [
            'email' => 'session@gpv.local',
            'password' => 'motdepassedetest',
            'channel' => 'sms',
        ]);

        self::assertSame('email', $body['challenge']['channel'], "Sans numéro, le code part par e-mail plutôt que nulle part.");
    }

    // ---------- jeton pour mobile ----------

    public function testTokenAlsoNeedsTheCode(): void
    {
        $this->createUser('mobile@gpv.local');

        $step1 = $this->send('POST', '/api/auth/token', ['email' => 'mobile@gpv.local', 'password' => 'motdepassedetest']);
        $this->assertStatus(200);
        self::assertArrayNotHasKey('token', $step1);

        $issued = $this->send('POST', '/api/auth/token/verify', [
            'challengeId' => $step1['challenge']['challengeId'],
            'code' => $step1['challenge']['devCode'],
        ]);

        $this->assertStatus(200);
        self::assertNotEmpty($issued['token']);
        self::assertNotEmpty($issued['refreshToken']);

        $this->client->request('GET', '/api/domains', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$issued['token'],
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertStatus(200);
    }

    public function testRefreshRotatesAndInvalidatesTheOldToken(): void
    {
        $this->createUser('mobile@gpv.local');
        $step1 = $this->send('POST', '/api/auth/token', ['email' => 'mobile@gpv.local', 'password' => 'motdepassedetest']);
        $issued = $this->send('POST', '/api/auth/token/verify', [
            'challengeId' => $step1['challenge']['challengeId'],
            'code' => $step1['challenge']['devCode'],
        ]);

        $refreshed = $this->send('POST', '/api/auth/refresh', ['refreshToken' => $issued['refreshToken']]);
        $this->assertStatus(200);
        self::assertNotSame($issued['refreshToken'], $refreshed['refreshToken']);

        $this->send('POST', '/api/auth/refresh', ['refreshToken' => $issued['refreshToken']]);
        $this->assertStatus(401);
    }

    // ---------- mot de passe oublié ----------

    public function testForgottenPasswordAnswersTheSameForAnyAddress(): void
    {
        $this->createUser('connu@gpv.local');

        $known = $this->send('POST', '/api/auth/password/forgot', ['email' => 'connu@gpv.local']);
        $knownStatus = $this->client->getResponse()->getStatusCode();

        $unknown = $this->send('POST', '/api/auth/password/forgot', ['email' => 'inconnu@gpv.local']);
        $unknownStatus = $this->client->getResponse()->getStatusCode();

        self::assertSame($knownStatus, $unknownStatus);
        self::assertSame($known['message'], $unknown['message'], 'La réponse ne révèle pas qui a un compte.');
        self::assertArrayHasKey('devToken', $known);
        self::assertArrayNotHasKey('devToken', $unknown);
    }

    public function testResetJourneyEndsLoggedIn(): void
    {
        $this->createUser('oubli@gpv.local');
        $asked = $this->send('POST', '/api/auth/password/forgot', ['email' => 'oubli@gpv.local']);
        $token = $asked['devToken'];

        $check = $this->send('GET', '/api/auth/password/reset/'.$token);
        $this->assertStatus(200);
        self::assertTrue($check['valid']);
        self::assertSame('oubli@gpv.local', $check['email']);

        $me = $this->send('POST', '/api/auth/password/reset', ['token' => $token, 'password' => 'mon-nouveau-mot-de-passe']);
        $this->assertStatus(200);
        self::assertSame('oubli@gpv.local', $me['email']);

        // La réinitialisation ouvre directement la session : pas de code en plus.
        $this->send('GET', '/api/auth/me');
        $this->assertStatus(200);
    }

    public function testTheNewPasswordWorksAndTheOldOneDoesNot(): void
    {
        $this->createUser('oubli@gpv.local');
        $token = $this->send('POST', '/api/auth/password/forgot', ['email' => 'oubli@gpv.local'])['devToken'];
        $this->send('POST', '/api/auth/password/reset', ['token' => $token, 'password' => 'mon-nouveau-mot-de-passe']);

        $this->send('POST', '/api/auth/logout');

        $this->send('POST', '/api/auth/login', ['email' => 'oubli@gpv.local', 'password' => 'motdepassedetest']);
        $this->assertStatus(401, "L'ancien mot de passe ne vaut plus rien.");

        $this->send('POST', '/api/auth/login', ['email' => 'oubli@gpv.local', 'password' => 'mon-nouveau-mot-de-passe']);
        $this->assertStatus(200);
    }

    public function testAResetLinkOnlyWorksOnce(): void
    {
        $this->createUser('oubli@gpv.local');
        $token = $this->send('POST', '/api/auth/password/forgot', ['email' => 'oubli@gpv.local'])['devToken'];

        $this->send('POST', '/api/auth/password/reset', ['token' => $token, 'password' => 'mon-nouveau-mot-de-passe']);
        $this->assertStatus(200);

        $this->send('POST', '/api/auth/password/reset', ['token' => $token, 'password' => 'encore-un-autre-mot-de-passe']);
        $this->assertStatus(404);
    }

    public function testAskingAgainRetiresThePreviousLink(): void
    {
        $this->createUser('oubli@gpv.local');
        $first = $this->send('POST', '/api/auth/password/forgot', ['email' => 'oubli@gpv.local'])['devToken'];
        $second = $this->send('POST', '/api/auth/password/forgot', ['email' => 'oubli@gpv.local'])['devToken'];

        $this->send('GET', '/api/auth/password/reset/'.$first);
        $this->assertStatus(404);

        $this->send('GET', '/api/auth/password/reset/'.$second);
        $this->assertStatus(200);
    }

    public function testAnInventedLinkIsRefused(): void
    {
        $this->send('GET', '/api/auth/password/reset/'.str_repeat('a', 64));

        $this->assertStatus(404);
    }

    public function testResettingDisconnectsTheOtherDevices(): void
    {
        $this->createUser('oubli@gpv.local');
        $step1 = $this->send('POST', '/api/auth/token', ['email' => 'oubli@gpv.local', 'password' => 'motdepassedetest']);
        $issued = $this->send('POST', '/api/auth/token/verify', [
            'challengeId' => $step1['challenge']['challengeId'],
            'code' => $step1['challenge']['devCode'],
        ]);

        $token = $this->send('POST', '/api/auth/password/forgot', ['email' => 'oubli@gpv.local'])['devToken'];
        $this->send('POST', '/api/auth/password/reset', ['token' => $token, 'password' => 'mon-nouveau-mot-de-passe']);

        $this->send('POST', '/api/auth/refresh', ['refreshToken' => $issued['refreshToken']]);
        $this->assertStatus(401, 'Le téléphone resté connecté est déconnecté lui aussi.');
    }

    // ---------- divers ----------

    public function testHealthIsPublic(): void
    {
        $body = $this->send('GET', '/api/health');

        $this->assertStatus(200);
        self::assertSame('ok', $body['status']);
    }

    public function testApiIsClosedWithoutASession(): void
    {
        $this->send('GET', '/api/snapshot');

        $this->assertStatus(401);
    }
}
