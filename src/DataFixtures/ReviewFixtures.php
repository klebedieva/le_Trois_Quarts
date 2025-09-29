<?php

namespace App\DataFixtures;

use App\Entity\Review;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ReviewFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        // Séparons отзывы от меню — отдельная группа 'reviews'
        return ['reviews'];
    }

    public function load(ObjectManager $manager): void
    {
        // Exemples inspirés du projet Restaurant (noms/textes adaptés)
        $rows = [
            [
                'name' => 'Camille B.',
                'email' => 'camille@example.com',
                'rating' => 5,
                'comment' => 'Cuisine excellente et service chaleureux. Je recommande vivement Le Trois Quarts !',
                'createdAt' => new \DateTime('-7 days'),
                'approved' => true,
            ],
            [
                'name' => 'Louis M.',
                'email' => 'louis@example.com',
                'rating' => 4,
                'comment' => 'Très bons plats, ambiance conviviale. Un léger temps d’attente mais ça vaut le coup.',
                'createdAt' => new \DateTime('-5 days'),
                'approved' => true,
            ],
            [
                'name' => 'Sofia R.',
                'email' => 'sofia@example.com',
                'rating' => 5,
                'comment' => 'Desserts fabuleux et carte variée. Nous reviendrons bientôt en famille !',
                'createdAt' => new \DateTime('-2 days'),
                'approved' => true,
            ],
            [
                'name' => 'Alex P.',
                'email' => null,
                'rating' => 3,
                'comment' => 'Cadre agréable mais j’aurais préféré des portions un peu plus généreuses.',
                'createdAt' => new \DateTime('-1 day'),
                'approved' => false,
            ],
        ];

        foreach ($rows as $r) {
            $review = (new Review())
                ->setName($r['name'])
                ->setEmail($r['email'])
                ->setRating($r['rating'])
                ->setComment($r['comment'])
                ->setCreatedAt($r['createdAt'])
                ->setIsApproved($r['approved']);

            $manager->persist($review);
        }

        $manager->flush();
    }
}







