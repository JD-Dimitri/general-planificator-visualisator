<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Service\AccountSeeder;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Compte de démonstration pour le développement local.
 * Identifiants : demo@gpv.local / motdepassededemo
 */
final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AccountSeeder $seeder,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('demo@gpv.local')
            ->setDisplayName('Compte de démonstration')
            ->setPlan(User::PLAN_CONNECTED)
            ->setPassword($this->hasher->hashPassword($user, 'motdepassededemo'));

        $manager->persist($user);
        $this->seeder->seed($user);
        $manager->flush();
    }
}
