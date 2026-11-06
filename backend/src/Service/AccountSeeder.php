<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AccessLink;
use App\Entity\Domain;
use App\Entity\Item;
use App\Entity\SubDomain;
use App\Entity\Subtask;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Garnit un compte neuf : les domaines du cahier des charges, leurs sous-domaines
 * et quelques éléments d'exemple.
 *
 * Un GPV vide ne montre pas ce qu'il sait faire ; les exemples portent le drapeau
 * `sample` pour que l'utilisateur puisse tous les effacer d'un clic.
 */
final class AccountSeeder
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function seed(User $user): void
    {
        $today = new \DateTimeImmutable('today');
        $domains = [];
        $subs = [];

        $domainSpecs = [
            ['sante', 'Santé', 150, 'Suivre mes rendez-vous et traitements'],
            ['sport', 'Sport', 30, 'Tenir mon programme chaque semaine'],
            ['religion', 'Religion', 300, 'Suivre mes cours régulièrement'],
            ['spiritualite', 'Spiritualité', 195, 'Garder une pratique quotidienne'],
            ['formation', 'Formation', 250, 'Valider mes formations dans les délais'],
            ['famille', 'Famille', 345, 'Accompagner mes parents dans leurs démarches'],
            ['securite', 'Sécurité', 10, 'Organiser mes vacations et mes papiers'],
        ];

        foreach ($domainSpecs as $position => [$key, $name, $hue, $goal]) {
            $domain = new Domain();
            $domain->setOwner($user)->setName($name)->setHue($hue)->setGoal($goal)->setPosition($position + 1);
            $this->em->persist($domain);
            $domains[$key] = $domain;
        }

        // [clé, domaine, clé du parent ou null, nom, gabarit]
        $subSpecs = [
            ['rdv', 'sante', null, 'Rendez-vous médicaux', 'rdv'],
            ['traitements', 'sante', null, 'Traitements', 'tache'],
            ['programme', 'sport', null, 'Programme sportif', 'programme'],
            ['cours', 'religion', null, 'Cours', 'cours'],
            ['pratiques', 'spiritualite', null, 'Pratiques régulières', 'habitude'],
            ['lectures', 'spiritualite', null, 'Lectures', 'tache'],
            ['formation1', 'formation', null, 'Formation (exemple)', 'formation'],
            ['parents', 'famille', null, 'Parents', 'tache'],
            ['maman', 'famille', 'parents', 'Maman', 'demarche'],
            ['papa', 'famille', 'parents', 'Papa', 'demarche'],
            ['administratif', 'securite', null, 'Administratif', 'demarche'],
        ];

        foreach ($subSpecs as $position => [$key, $domainKey, $parentKey, $name, $template]) {
            $sub = new SubDomain();
            $sub->setOwner($user)
                ->setDomain($domains[$domainKey])
                ->setParent(null !== $parentKey ? $subs[$parentKey] : null)
                ->setName($name)
                ->setTemplate($template)
                ->setPosition($position + 1);
            $this->em->persist($sub);
            $subs[$key] = $sub;
        }

        $itemSpecs = [
            ['sante', 'rdv', 'Rendez-vous médecin traitant', '+4 days', '10:30', 'normale',
                ['praticien' => 'Médecin traitant', 'lieu' => 'Cabinet médical', 'docs' => 'Carte Vitale, ordonnances'], []],
            ['sport', 'programme', 'Séance course 5 km', '+1 day', '07:00', 'normale',
                ['objectif' => '5 km en moins de 30 min'], []],
            ['religion', 'cours', 'Cours hebdomadaire', '+2 days', '14:00', 'normale', [], []],
            ['spiritualite', 'pratiques', 'Méditation 10 minutes', 'today', null, 'normale', [], []],
            ['formation', 'formation1', 'Rendu du module 2', '+6 days', null, 'haute',
                ['module' => 'Module 2', 'nature' => 'Rendu'],
                [['Relire le cours', true], ['Rédiger le devoir', false], ['Déposer sur la plateforme', false]]],
            ['famille', 'papa', "Prendre rendez-vous en mairie pour sa carte d'identité", '+10 days', null, 'normale',
                ['organisme' => 'Mairie'], []],
            ['securite', 'administratif', 'Renouveler la carte professionnelle', '-2 days', null, 'haute', [], []],
        ];

        foreach ($itemSpecs as [$domainKey, $subKey, $title, $due, $time, $priority, $fields, $steps]) {
            $item = new Item();
            $item->setOwner($user)
                ->setDomain($domains[$domainKey])
                ->setSubDomain($subs[$subKey])
                ->setTitle($title)
                ->setPriority($priority)
                ->setDueDate($today->modify($due))
                ->setDueTime($time)
                ->setFields($fields)
                ->setSample(true);

            foreach ($steps as $position => [$label, $done]) {
                $subtask = new Subtask();
                $subtask->setLabel($label)->setDone($done)->setPosition($position + 1);
                $item->addSubtask($subtask);
            }

            $this->em->persist($item);
        }

        $linkSpecs = [
            ['Gmail', 'https://mail.google.com/', null, null],
            ['Outlook / Hotmail', 'https://outlook.live.com/mail/0/', null, null],
            ['Google Drive', 'https://drive.google.com/', null, null],
            ['Doctolib', 'https://www.doctolib.fr/', 'sante', 'rdv'],
            ['Ameli', 'https://www.ameli.fr/', 'sante', 'rdv'],
        ];

        foreach ($linkSpecs as $position => [$label, $url, $domainKey, $subKey]) {
            $link = new AccessLink();
            $link->setOwner($user)
                ->setLabel($label)
                ->setUrl($url)
                ->setDomain(null !== $domainKey ? $domains[$domainKey] : null)
                ->setSubDomain(null !== $subKey ? $subs[$subKey] : null)
                ->setPosition($position + 1);
            $this->em->persist($link);
        }
    }
}
