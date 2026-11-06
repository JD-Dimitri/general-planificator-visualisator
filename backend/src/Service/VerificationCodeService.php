<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LoginCode;
use App\Entity\User;
use App\Repository\LoginCodeRepository;
use App\Service\Notification\CodeNotifier;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Émission et vérification des codes à usage unique.
 *
 * Un code est exigé à la création du compte et à chaque connexion. Le code en
 * clair n'existe que le temps de l'envoi ; la base n'en garde qu'une empreinte
 * HMAC, calculée avec le secret de l'application, de sorte qu'une copie de la
 * base ne suffit pas à retrouver les codes en circulation.
 */
final class VerificationCodeService
{
    /** Au-delà, on arrête d'envoyer : quelqu'un s'acharne, ou harcèle une boîte mail. */
    private const MAX_CODES_PER_HOUR = 8;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoginCodeRepository $codes,
        private readonly CodeNotifier $notifier,
        private readonly string $appSecret,
        private readonly bool $exposeCodes,
    ) {
    }

    /**
     * Crée un code, l'envoie, et rend de quoi afficher l'écran de saisie.
     *
     * @return array{challenge: LoginCode, code: string, sent: bool}|null null quand la limite horaire est atteinte
     */
    public function issue(User $user, string $purpose, string $channel = LoginCode::CHANNEL_EMAIL): ?array
    {
        if ($this->codes->countSince($user, new \DateTimeImmutable('-1 hour')) >= self::MAX_CODES_PER_HOUR) {
            return null;
        }

        // Sans numéro vérifié, le SMS n'a nulle part où aller.
        if (LoginCode::CHANNEL_SMS === $channel && null === $user->getPhone()) {
            $channel = LoginCode::CHANNEL_EMAIL;
        }

        $this->codes->consumePendingFor($user, $purpose);

        $plain = $this->randomCode();
        $challenge = new LoginCode(
            $user,
            $purpose,
            $channel,
            LoginCode::CHANNEL_SMS === $channel ? $this->maskPhone((string) $user->getPhone()) : $this->maskEmail($user->getEmail()),
            $this->hash($plain),
            new \DateTimeImmutable(\sprintf('+%d seconds', LoginCode::TTL_SECONDS)),
        );

        $this->em->persist($challenge);
        $this->em->flush();

        $sent = $this->notifier->sendCode($challenge, $user, $plain);

        // Le SMS a échoué (pas de fournisseur, numéro refusé) : on repasse par l'e-mail
        // plutôt que de laisser quelqu'un attendre un code qui n'arrivera jamais.
        if (!$sent && LoginCode::CHANNEL_SMS === $channel) {
            $fallback = new LoginCode(
                $user,
                $purpose,
                LoginCode::CHANNEL_EMAIL,
                $this->maskEmail($user->getEmail()),
                $this->hash($plain),
                $challenge->getExpiresAt(),
            );
            $challenge->consume();
            $this->em->persist($fallback);
            $this->em->flush();

            $challenge = $fallback;
            $sent = $this->notifier->sendCode($challenge, $user, $plain);
        }

        return ['challenge' => $challenge, 'code' => $plain, 'sent' => $sent];
    }

    /**
     * Vérifie un code présenté.
     *
     * @return array{0: bool, 1: string} succès, puis un message à afficher en cas d'échec
     */
    public function verify(LoginCode $challenge, string $submitted, string $expectedPurpose): array
    {
        if ($challenge->getPurpose() !== $expectedPurpose) {
            return [false, 'Ce code ne correspond pas à cette étape. Recommence la connexion.'];
        }

        if ($challenge->isConsumed()) {
            return [false, 'Ce code a déjà servi. Demande-en un nouveau.'];
        }

        if ($challenge->isExpired()) {
            return [false, 'Ce code a expiré. Demande-en un nouveau.'];
        }

        if ($challenge->isExhausted()) {
            return [false, 'Trop de tentatives sur ce code. Demande-en un nouveau.'];
        }

        $challenge->recordAttempt();

        if (!hash_equals($challenge->getCodeHash(), $this->hash(trim($submitted)))) {
            $this->em->flush();
            $left = LoginCode::MAX_ATTEMPTS - $challenge->getAttempts();

            return [false, $left > 0
                ? \sprintf('Code incorrect. Il te reste %d essai%s.', $left, $left > 1 ? 's' : '')
                : 'Code incorrect. Demande un nouveau code.'];
        }

        $challenge->consume();
        $this->em->flush();

        return [true, ''];
    }

    /** Ce que le front a besoin de savoir, sans jamais le code lui-même. */
    public function describe(LoginCode $challenge, ?string $plainCode = null, bool $sent = true): array
    {
        $payload = [
            'challengeId' => (string) $challenge->getId(),
            'channel' => $challenge->getChannel(),
            'destination' => $challenge->getMaskedDestination(),
            'expiresIn' => $challenge->secondsLeft(),
            'sent' => $sent,
        ];

        // En développement, aucun e-mail ne part : le code est rendu ici pour
        // pouvoir essayer le parcours. Jamais hors développement.
        if ($this->exposeCodes && null !== $plainCode) {
            $payload['devCode'] = $plainCode;
        }

        return $payload;
    }

    private function randomCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', \STR_PAD_LEFT);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, $this->appSecret);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2) + ['', ''];
        $visible = mb_substr($name, 0, 1);

        return $visible.str_repeat('•', max(1, mb_strlen($name) - 1)).'@'.$domain;
    }

    private function maskPhone(string $phone): string
    {
        return str_repeat('•', max(0, mb_strlen($phone) - 4)).mb_substr($phone, -4);
    }
}
