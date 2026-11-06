<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\LoginCode;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface as NotifierTransportException;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

/**
 * Envoie les codes et les liens.
 *
 * L'e-mail part par Symfony Mailer ; le SMS par Symfony Notifier, qui demande
 * un fournisseur payant (Twilio, OVH, Vonage). Sans transport SMS configuré,
 * l'envoi échoue proprement et l'appelant bascule sur l'e-mail plutôt que de
 * laisser l'utilisateur devant un écran qui attend un code qui n'arrivera pas.
 */
final class CodeNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $senderEmail,
        private readonly string $appUrl,
        private readonly ?TexterInterface $texter = null,
    ) {
    }

    /** @return bool vrai si le message est parti */
    public function sendCode(LoginCode $challenge, User $user, string $plainCode): bool
    {
        $minutes = (int) ceil(LoginCode::TTL_SECONDS / 60);

        if (LoginCode::CHANNEL_SMS === $challenge->getChannel()) {
            return $this->sendSms(
                (string) $user->getPhone(),
                \sprintf('GPV : ton code de connexion est %s. Il expire dans %d minutes.', $plainCode, $minutes)
            );
        }

        $intro = LoginCode::PURPOSE_REGISTRATION === $challenge->getPurpose()
            ? "Bienvenue dans GPV. Voici le code qui valide la création de ton compte :"
            : 'Voici ton code de connexion à GPV :';

        return $this->sendEmail(
            $user->getEmail(),
            LoginCode::PURPOSE_REGISTRATION === $challenge->getPurpose() ? 'Valide ton compte GPV' : 'Ton code de connexion GPV',
            <<<TEXT
                Bonjour {$user->getDisplayName()},

                {$intro}

                    {$plainCode}

                Ce code expire dans {$minutes} minutes et ne sert qu'une fois.

                Si tu n'es pas à l'origine de cette demande, ignore ce message :
                sans le code, personne ne peut entrer dans ton compte.
                TEXT
        );
    }

    public function sendPasswordReset(User $user, string $plainToken): bool
    {
        $link = rtrim($this->appUrl, '/').'/reinitialiser/'.$plainToken;
        $minutes = (int) ceil(\App\Entity\PasswordResetToken::TTL_SECONDS / 60);

        return $this->sendEmail(
            $user->getEmail(),
            'Réinitialise ton mot de passe GPV',
            <<<TEXT
                Bonjour {$user->getDisplayName()},

                Pour choisir un nouveau mot de passe, ouvre ce lien :

                    {$link}

                Il est valable {$minutes} minutes et ne fonctionne qu'une fois.

                Si tu n'as rien demandé, ignore ce message. Ton mot de passe
                actuel reste valable et personne n'a eu accès à ton compte.
                TEXT
        );
    }

    public function sendPasswordChanged(User $user): bool
    {
        return $this->sendEmail(
            $user->getEmail(),
            'Ton mot de passe GPV a été changé',
            <<<TEXT
                Bonjour {$user->getDisplayName()},

                Le mot de passe de ton compte GPV vient d'être changé, et tous
                les appareils connectés ont été déconnectés.

                Si ce n'est pas toi, réinitialise ton mot de passe tout de suite
                depuis l'écran de connexion.
                TEXT
        );
    }

    private function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            $this->mailer->send(
                (new Email())
                    ->from($this->senderEmail)
                    ->to($to)
                    ->subject($subject)
                    ->text($body)
            );

            return true;
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error("Envoi d'e-mail impossible.", ['exception' => $exception, 'subject' => $subject]);

            return false;
        }
    }

    private function sendSms(string $to, string $body): bool
    {
        if (null === $this->texter || '' === $to) {
            $this->logger->warning('Aucun transport SMS configuré : bascule sur e-mail.');

            return false;
        }

        try {
            $this->texter->send(new SmsMessage($to, $body));

            return true;
        } catch (NotifierTransportException $exception) {
            $this->logger->error('Envoi de SMS impossible.', ['exception' => $exception]);

            return false;
        }
    }
}
