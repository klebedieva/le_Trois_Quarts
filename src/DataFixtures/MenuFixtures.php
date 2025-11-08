<?php

namespace App\DataFixtures;

use App\Entity\MenuItem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MenuFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public static function getGroups(): array
    {
        return ['menu'];
    }

    public function getDependencies(): array
    {
        return [
            AllergenFixtures::class,
            BadgeFixtures::class,
            TagFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $rows = [
            // Entrées
            [
                'name' => 'Asperges Printemps à la Ricotta',
                'description' => 'Asperges vertes fraîches, crème de ricotta maison, oignons marinés et graines de moutarde toastées. Un contraste de textures et de saveurs végétariennes.',
                'price' => '14.00',
                'category' => 'entrees',
                'image' => '/static/img/entrees/entree_1.png',
                'badges' => ['Végétarien', 'Fait maison'],
                'tags' => ['vegetarian'],
            ],
            [
                'name' => 'Œuf Mollet au Safran et Petits Pois',
                'description' => 'Œuf mollet au safran, crème onctueuse de petits pois et tuiles noires au sésame. Un plat végétarien raffiné aux saveurs printanières.',
                'price' => '13.00',
                'category' => 'entrees',
                'image' => '/static/img/entrees/entree_2.png',
                'badges' => ['Végétarien', 'Fait maison'],
                'tags' => ['vegetarian'],
            ],
            [
                'name' => 'Seiches Sautées à la Chermoula',
                'description' => "Seiches sautées, chermoula aux jeunes pousses d'épinards, coulis de betteraves et fêta. Un plat méditerranéen aux saveurs marocaines.",
                'price' => '15.00',
                'category' => 'entrees',
                'image' => '/static/img/entrees/entree_3.png',
                'badges' => ['Méditerranéen', 'Fait maison'],
                'tags' => [],
            ],
            // Plats
            [
                'name' => "Boulette d'agneau",
                'description' => "Boulettes d'agneau parfumées aux herbes, carottes rôties au cumin et miel, yaourt grec à la citronnelle et miel, accompagné de riz basmati.",
                'price' => '22.00',
                'category' => 'plats',
                'image' => '/static/img/plats/plat_1.png',
                'badges' => ['Maison'],
                'tags' => [],
            ],
            [
                'name' => "Galinette poêlée à l'ajo blanco",
                'description' => "Filet de galinette poêlé à la perfection, servi avec une soupe froide traditionnelle à l'ail et amandes, poivre du Sichuan et huile parfumée à la ciboulette.",
                'price' => '24.00',
                'category' => 'plats',
                'image' => '/static/img/plats/plat_2.png',
                'badges' => ['Traditionnel', 'Spécialité'],
                'tags' => [],
            ],
            [
                'name' => 'Sashimi de ventrèche de thon fumé',
                'description' => 'Sashimi de ventrèche de thon fumé au charbon, crème fumée et herbes fraîches, servi avec une sauce soja et wasabi.',
                'price' => '24.00',
                'category' => 'plats',
                'image' => '/static/img/plats/plat_9.png',
                'badges' => ['Fusion', 'Spécialité'],
                'tags' => [],
            ],
            [
                'name' => 'Magret de canard au fenouil confit',
                'description' => 'Magret de canard, fenouil confit au vin blanc, crème de betterave et herbes fraîches.',
                'price' => '28.00',
                'category' => 'plats',
                'image' => '/static/img/plats/plat_7.png',
                'badges' => ['Traditionnel', 'Spécialité'],
                'tags' => [],
            ],
            [
                'name' => 'Velouté de châtaignes aux pleurottes',
                'description' => 'Velouté crémeux de châtaignes, pleurottes sautées et coppa grillée, parfumé aux herbes de Provence.',
                'price' => '16.00',
                'category' => 'plats',
                'image' => '/static/img/plats/plat_8.png',
                'badges' => ['Traditionnel', 'Saison'],
                'tags' => [],
            ],
            [
                'name' => "Spaghettis à l'ail noir et parmesan",
                'description' => "Spaghettis al dente, sauce au jus de veau parfumé à l'ail noir, citron confit et parmesan affiné.",
                'price' => '20.00',
                'category' => 'plats',
                'image' => '/static/img/plats/plat_3.png',
                'badges' => ['Traditionnel'],
                'tags' => [],
            ],
            [
                'name' => 'Loup de mer aux pois chiches',
                'description' => 'Loup de mer grillé, salade de pois chiches, tomates séchées, petits pois et olives de Kalamata.',
                'price' => '26.00',
                'category' => 'plats',
                'image' => '/static/img/plats/plat_5.png',
                'badges' => ['Traditionnel', 'Méditerranéen'],
                'tags' => [],
            ],
            [
                'name' => "Potimarron Rôti aux Saveurs d'Asie",
                'description' => "Potimarron rôti au four, mousseline de chou-fleur, roquette fraîche et jaune d'œuf confit au soja, parsemé de nori. Un plat végétarien fusion.",
                'price' => '18.00',
                'category' => 'plats',
                'image' => '/static/img/plats/plat_10.png',
                'badges' => ['Végétarien', 'Fusion'],
                'tags' => ['vegetarian'],
            ],
            [
                'name' => 'Gaspacho Tomates et Melon',
                'description' => 'Gaspacho tomates, melon, basilic et fêta. Un plat rafraîchissant sans gluten aux saveurs méditerranéennes.',
                'price' => '12.00',
                'category' => 'plats',
                'image' => '/static/img/plats/plat_12.png',
                'badges' => ['Sans Gluten', 'Méditerranéen'],
                'tags' => ['vegetarian', 'glutenFree'],
            ],

            // Desserts
            [
                'name' => 'Tartelette aux Marrons Suisses',
                'description' => 'Tartelette aux marrons suisses, meringuée. Un dessert traditionnel aux saveurs automnales.',
                'price' => '8.00',
                'category' => 'desserts',
                'image' => '/static/img/desserts/dessert_1.png',
                'badges' => ['Fait maison', 'Saison'],
                'tags' => ['vegetarian'],
            ],
            [
                'name' => 'Tartelette Ricotta au Miel et Fraises',
                'description' => 'Tartelette ricotta au miel, fraises fraîches et compotée de rhubarbe. Un dessert printanier raffiné.',
                'price' => '9.00',
                'category' => 'desserts',
                'image' => '/static/img/desserts/dessert_2.png',
                'badges' => ['Fait maison', 'Saison'],
                'tags' => ['vegetarian'],
            ],
            [
                'name' => 'Crémeux Yuzu aux Fruits Rouges',
                'description' => 'Crémeux yuzu, fruits rouges frais, meringues et noisettes. Un dessert fusion aux saveurs japonaises.',
                'price' => '10.00',
                'category' => 'desserts',
                'image' => '/static/img/desserts/dessert_3.png',
                'badges' => ['Fait maison', 'Fusion'],
                'tags' => ['vegetarian'],
            ],
        ];

        foreach ($rows as $r) {
            $item = (new MenuItem())
                ->setName($r['name'])
                ->setDescription($r['description'] ?? null)
                ->setPrice($r['price'])
                ->setCategory($r['category'])
                ->setImage($r['image']);

            // Lier badges/tags via références
            foreach (($r['badges'] ?? []) as $bn) {
                $ref = BadgeFixtures::REFERENCE_PREFIX . $bn;
                try {
                    $entityRef = $this->getReference($ref);
                    if ($entityRef && method_exists($item, 'addBadge')) {
                        $item->addBadge($entityRef);
                    }
                } catch (\Throwable $e) {
                    // Référence manquante: ignorer silencieusement
                }
            }
            foreach (($r['tags'] ?? []) as $tc) {
                $ref = TagFixtures::REFERENCE_PREFIX . $tc;
                try {
                    $entityRef = $this->getReference($ref);
                    if ($entityRef && method_exists($item, 'addTag')) {
                        $item->addTag($entityRef);
                    }
                } catch (\Throwable $e) {
                    // Référence manquante: ignorer
                }
            }

            // Optionally attach some example allergens based on dish name keywords
            try {
                $name = strtolower($r['name']);
                $maybe = [];
                if (str_contains($name, 'thon') || str_contains($name, 'loup de mer') || str_contains($name, 'poêlée') || str_contains($name, 'seiches')) {
                    $maybe[] = 'fish';
                }
                if (str_contains($name, 'ricotta') || str_contains($r['description'] ?? '', 'crème')) {
                    $maybe[] = 'lactose';
                }
                if (str_contains($r['description'] ?? '', 'amandes') || str_contains($r['description'] ?? '', 'noisettes')) {
                    $maybe[] = 'nuts';
                }
                foreach (array_unique($maybe) as $code) {
                    /** @var \App\Entity\Allergen|null $a */
                    $a = $this->getReference(AllergenFixtures::REFERENCE_PREFIX . $code) ?? null;
                    if ($a && method_exists($item, 'addAllergen')) {
                        $item->addAllergen($a);
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $manager->persist($item);
        }

        $manager->flush();
    }
}


