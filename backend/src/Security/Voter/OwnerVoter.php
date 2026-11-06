<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Une seule règle d'accès dans GPV : on ne touche qu'à ses propres données.
 *
 * Les requêtes filtrent déjà par propriétaire ; ce voteur est la seconde barrière,
 * celle qui tient même si un jour une requête oublie son filtre.
 *
 * @extends Voter<string, object>
 */
final class OwnerVoter extends Voter
{
    public const VIEW = 'GPV_VIEW';
    public const EDIT = 'GPV_EDIT';
    public const DELETE = 'GPV_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && \is_object($subject)
            && method_exists($subject, 'getOwner');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $subject->getOwner()->getId()->equals($user->getId());
    }
}
