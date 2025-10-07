<?php

namespace App\DataFixtures;

use App\Entity\Drink;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class DrinkFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        // Можем грузить вместе с меню
        return ['menu'];
    }

    public function load(ObjectManager $manager): void
    {
        $data = [
            'vins' => [
                ['name' => 'Côtes du Rhône rouge', 'price' => '5€ / 25€'],
                ['name' => 'Rosé de Provence', 'price' => '4€ / 20€'],
                ['name' => 'Blanc de Cassis', 'price' => '5€ / 24€'],
            ],
            'bieres' => [
                ['name' => 'Pression 25cl', 'price' => '3€'],
                ['name' => 'Pression 50cl', 'price' => '5€'],
                ['name' => 'Bière artisanale', 'price' => '6€'],
            ],
            'chaudes' => [
                ['name' => 'Café expresso', 'price' => '2€'],
                ['name' => 'Cappuccino', 'price' => '3€'],
                ['name' => 'Thé / Infusion', 'price' => '2.5€'],
            ],
            'fraiches' => [
                ['name' => 'Jus de fruits frais', 'price' => '4€'],
                ['name' => 'Sodas', 'price' => '3€'],
                ['name' => 'Eau minérale', 'price' => '2€'],
            ],
        ];

        foreach ($data as $type => $items) {
            foreach ($items as $row) {
                $drink = new Drink();
                $drink->setName($row['name']);
                $drink->setPrice($row['price']);
                $drink->setType($type);
                $manager->persist($drink);
            }
        }

        $manager->flush();
    }
}















