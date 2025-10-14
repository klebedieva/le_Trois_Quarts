<?php

namespace App\DataFixtures;

use App\Entity\GalleryImage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class GalleryImageFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $images = [
            // Terrasse
            [
                'title' => 'Terrasse conviviale',
                'description' => 'Un espace agréable pour vos repas en extérieur',
                'imagePath' => 'terrasse_2.jpg',
                'category' => 'terrasse',
                'displayOrder' => 1,
            ],
            [
                'title' => 'Vue de la terrasse',
                'description' => 'Détente et convivialité au soleil',
                'imagePath' => 'terrasse_3.jpg',
                'category' => 'terrasse',
                'displayOrder' => 2,
            ],
            [
                'title' => 'Espace extérieur',
                'description' => 'Profitez de notre terrasse toute l\'année',
                'imagePath' => 'terrasse_4.jpg',
                'category' => 'terrasse',
                'displayOrder' => 8,
            ],
            [
                'title' => 'Notre terrasse',
                'description' => 'Un lieu convivial pour se détendre',
                'imagePath' => 'terrasse_5.jpg',
                'category' => 'terrasse',
                'displayOrder' => 9,
            ],

            // Intérieur
            [
                'title' => 'Salle principale',
                'description' => 'L\'intérieur chaleureux de notre brasserie',
                'imagePath' => 'interieur_1.jpg',
                'category' => 'interieur',
                'displayOrder' => 3,
            ],
            [
                'title' => 'Ambiance intérieure',
                'description' => 'Un cadre accueillant pour vos repas',
                'imagePath' => 'interieur_2.jpg',
                'category' => 'interieur',
                'displayOrder' => 4,
            ],

            // Ambiance
            [
                'title' => 'Ambiance chaleureuse',
                'description' => 'L\'atmosphère conviviale du Trois Quarts',
                'imagePath' => 'ambiance_1.jpg',
                'category' => 'ambiance',
                'displayOrder' => 5,
            ],
            [
                'title' => 'Moments conviviaux',
                'description' => 'Des instants de partage et de plaisir',
                'imagePath' => 'ambiance_2.jpg',
                'category' => 'ambiance',
                'displayOrder' => 6,
            ],
            [
                'title' => 'Décoration soignée',
                'description' => 'Un décor chaleureux pour vos moments au restaurant',
                'imagePath' => 'ambiance_4.jpg',
                'category' => 'ambiance',
                'displayOrder' => 7,
            ],

            // Plats
            [
                'title' => 'Nos plats',
                'description' => 'Une cuisine généreuse et savoureuse',
                'imagePath' => 'plat_1.jpg',
                'category' => 'plats',
                'displayOrder' => 10,
            ],
            [
                'title' => 'Spécialités maison',
                'description' => 'Des recettes traditionnelles avec créativité',
                'imagePath' => 'plat_2.jpg',
                'category' => 'plats',
                'displayOrder' => 11,
            ],
            [
                'title' => 'Cuisine du marché',
                'description' => 'Des produits frais et de saison',
                'imagePath' => 'plat_3.jpg',
                'category' => 'plats',
                'displayOrder' => 12,
            ],
            [
                'title' => 'Nos délices',
                'description' => 'Des plats préparés avec passion',
                'imagePath' => 'plat_4.jpg',
                'category' => 'plats',
                'displayOrder' => 13,
            ],
        ];

        foreach ($images as $imageData) {
            $image = new GalleryImage();
            $image->setTitle($imageData['title'])
                ->setDescription($imageData['description'])
                ->setImagePath($imageData['imagePath'])
                ->setCategory($imageData['category'])
                ->setDisplayOrder($imageData['displayOrder'])
                ->setIsActive(true);

            $manager->persist($image);
        }

        $manager->flush();
    }
}

