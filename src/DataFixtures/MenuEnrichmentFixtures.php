<?php

namespace App\DataFixtures;

use App\Entity\MenuItem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Enrich existing MenuItem records with ingredients, preparation and nutrition facts.
 * Data is derived from the static "Restaurant" project (static/js/main.js and dish-detail mappings).
 *
 * This fixture is idempotent and non-destructive:
 * - Finds items by name only, does NOT create new MenuItem rows
 * - Updates nullable fields if an item exists
 */
class MenuEnrichmentFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['enrichment'];
    }

    public function load(ObjectManager $manager): void
    {
        $repo = $manager->getRepository(MenuItem::class);

        // Ingredients (arrays) by dish name
        $ingredients = [
            'Asperges Printemps à la Ricotta' => [
                "asperges vertes fraîches",
                "ricotta maison",
                "oignons rouges",
                "graines de moutarde",
                "vinaigre de cidre",
                "huile d'olive extra vierge",
                "sel",
                "poivre",
                "herbes fraîches",
                "citron",
                "sucre de canne"
            ],
            'Œuf Mollet au Safran et Petits Pois' => [
                "œufs frais",
                "safran de qualité",
                "petits pois frais",
                "graines de sésame noir",
                "crème fraîche",
                "beurre",
                "huile d'olive extra vierge",
                "sel",
                "poivre",
                "herbes fraîches",
                "citron"
            ],
            'Seiches Sautées à la Chermoula' => ["seiches", "jeunes pousses d'épinards", "betteraves", "fêta", "ail", "coriandre", "citron", "huile d'olive", "épices marocaines", "sel", "poivre"],
            "Boulette d'agneau" => ["agneau haché", "oignon", "ail", "persil", "cumin", "paprika", "carotte", "miel", "yaourt grec", "riz basmati"],
            "Galinette poêlée à l'ajo blanco" => ["galinette", "ail", "amandes", "pain rassis", "huile d'olive", "poivre du Sichuan", "ciboulette", "vinaigre de cidre", "sel", "beurre"],
            'Sashimi de ventrèche de thon fumé' => ["ventrèche de thon", "crème fumée", "charbon actif", "herbes fraîches", "sauce soja", "wasabi", "gingembre", "citron", "huile de sésame", "sel", "poivre"],
            'Magret de canard au fenouil confit' => ["magret de canard", "fenouil", "vin blanc", "betterave", "crème fraîche", "herbes fraîches", "beurre", "sel", "poivre"],
            'Velouté de châtaignes aux pleurottes' => ["châtaignes", "pleurottes", "coppa", "crème fraîche", "oignon", "ail", "herbes de Provence", "beurre", "huile d'olive", "sel", "poivre", "bouillon de légumes"],
            "Spaghettis à l'ail noir et parmesan" => ["spaghettis", "jus de veau", "ail noir", "citron confit", "parmesan", "beurre", "huile d'olive", "sel", "poivre", "herbes fraîches"],
            'Loup de mer aux pois chiches' => ["loup de mer", "pois chiches", "tomates séchées", "petits pois", "olives de Kalamata", "huile d'olive", "citron", "ail", "herbes fraîches", "sel", "poivre"],
            "Potimarron Rôti aux Saveurs d'Asie" => ["potimarron", "chou-fleur", "roquette", "œufs", "sauce soja", "nori", "beurre", "crème fraîche", "sel", "poivre", "huile d'olive"],
            'Tartelette aux Marrons Suisses' => ["marrons suisses", "pâte sablée", "meringue italienne", "crème pâtissière", "sucre", "beurre", "œufs"],
            'Tartelette Ricotta au Miel et Fraises' => ["ricotta", "miel", "fraises", "rhubarbe", "pâte sablée", "sucre", "beurre", "œufs", "vanille"],
            'Crémeux Yuzu aux Fruits Rouges' => ["yuzu", "fruits rouges", "meringues", "noisettes", "crème fraîche", "sucre", "œufs", "vanille"],
            'Gaspacho Tomates et Melon' => ["tomates", "melon", "basilic", "fêta", "huile d'olive", "vinaigre", "ail", "sel", "poivre"],
        ];

        // Preparation (concise description) by dish name
        $preparation = [
            'Asperges Printemps à la Ricotta' => "Nos asperges vertes sont cuites à la vapeur pour préserver leur croquant et leur saveur naturelle. La ricotta est préparée maison avec du lait frais et la crème est assaisonnée avec des herbes fraîches. Les oignons sont marinés dans un vinaigre parfumé et les graines de moutarde sont toastées pour un contraste de textures.",
            'Œuf Mollet au Safran et Petits Pois' => "Notre œuf mollet est cuit à la perfection avec du safran de qualité pour une couleur dorée et un goût unique. La crème de petits pois est préparée avec des pois frais et de la crème fraîche pour une texture onctueuse. Les tuiles noires au sésame ajoutent un contraste de texture et de saveur.",
            'Seiches Sautées à la Chermoula' => "Nos seiches sont sautées à la perfection pour préserver leur tendreté et leur saveur naturelle. La chermoula est préparée avec des jeunes pousses d'épinards fraîches, de l'ail, de la coriandre et des épices marocaines authentiques. Le coulis de betteraves apporte une touche de douceur et de couleur, tandis que la fêta ajoute une note salée et crémeuse.",
            "Galinette poêlée à l'ajo blanco" => "Notre galinette est poêlée à la perfection avec du beurre et des herbes fraîches. L'ajo blanco est préparé selon la tradition andalouse avec de l'ail frais et des amandes torréfiées, créant un contraste unique entre chaud et froid.",
            "Boulette d'agneau" => "Nos boulettes d'agneau sont préparées à la main avec de l'agneau haché frais, parfumées aux herbes de Provence et épices traditionnelles. Les carottes sont rôties au cumin et miel pour un goût unique.",
            'Spaghettis à l\'ail noir et parmesan' => "Nos spaghettis sont cuits al dente selon la tradition italienne. Le jus de veau est réduit avec de l'ail noir pour une saveur profonde et complexe, rehaussé par le citron confit et le parmesan affiné.",
            'Loup de mer aux pois chiches' => "Notre loup de mer est grillé à la perfection selon les traditions méditerranéennes. La salade de pois chiches est préparée avec des tomates séchées, petits pois et olives de Kalamata pour un goût authentique.",
            'Magret de canard au fenouil confit' => "Notre magret de canard est préparé selon la tradition française, servi avec du fenouil confit au vin blanc et une crème de betterave parfumée aux herbes fraîches.",
            'Velouté de châtaignes aux pleurottes' => "Notre velouté de châtaignes est préparé avec des châtaignes fraîches de saison, crémeux et parfumé aux herbes de Provence. Les pleurottes sont sautées à la perfection et la coppa grillée ajoute une touche de saveur unique.",
            'Sashimi de ventrèche de thon fumé' => "Notre sashimi de ventrèche de thon est fumé au charbon actif pour une saveur unique et intense. La crème fumée ajoute une touche crémeuse et les herbes fraîches apportent fraîcheur et équilibre.",
            "Potimarron Rôti aux Saveurs d'Asie" => "Notre potimarron est rôti au four pour développer ses saveurs naturelles sucrées. La mousseline de chou-fleur est préparée avec de la crème fraîche et du beurre pour une texture onctueuse. Le jaune d'œuf est confit dans la sauce soja pour un goût umami unique, et le nori ajoute une touche japonaise authentique.",
            'Gaspacho Tomates et Melon' => "Notre gaspacho est préparé avec des tomates fraîches et du melon de saison pour une soupe froide rafraîchissante. Le basilic frais apporte une touche aromatique et la fêta ajoute une note salée qui équilibre parfaitement la douceur du melon.",
            'Tartelette aux Marrons Suisses' => "Notre tartelette aux marrons suisses est préparée avec une pâte sablée maison et des marrons suisses de qualité. La crème pâtissière est parfumée à la vanille et la meringue italienne est préparée à la perfection pour un contraste de textures et de saveurs.",
            'Tartelette Ricotta au Miel et Fraises' => "Notre tartelette ricotta est préparée avec une ricotta fraîche et du miel de qualité. Les fraises fraîches et la compotée de rhubarbe apportent une touche de fraîcheur et d'acidité qui équilibre parfaitement la douceur du miel et de la ricotta.",
            'Crémeux Yuzu aux Fruits Rouges' => "Notre crémeux yuzu est préparé avec du yuzu frais importé du Japon pour une saveur authentique et unique. Les fruits rouges frais apportent une touche de fraîcheur et d'acidité, tandis que les meringues et noisettes ajoutent un contraste de textures et de saveurs.",
        ];

        // Chef tips by dish name
        $chefTips = [
            'Asperges Printemps à la Ricotta' => "Vin blanc sec pour accompagner les saveurs fraîches.",
            'Œuf Mollet au Safran et Petits Pois' => "Vin blanc sec pour accompagner les saveurs délicates.",
            'Seiches Sautées à la Chermoula' => "Vin blanc sec, parfait pour les notes méditerranéennes.",
            "Galinette poêlée à l'ajo blanco" => "Huile à la ciboulette et poivre du Sichuan en touche finale.",
            "Boulette d'agneau" => "Servir avec yaourt à la citronnelle; touche de miel pour l'équilibre.",
            'Spaghettis à l\'ail noir et parmesan' => "Un rouge léger accompagne très bien les saveurs du jus de veau.",
            'Loup de mer aux pois chiches' => "Vin blanc sec pour sublimer le poisson.",
            'Magret de canard au fenouil confit' => "Un Bordeaux rouge accompagne les notes riches du canard.",
            'Velouté de châtaignes aux pleurottes' => "Vin blanc sec pour les saveurs terreuses.",
            'Sashimi de ventrèche de thon fumé' => "Saké ou vin blanc sec pour les notes fumées et japonaises.",
            "Potimarron Rôti aux Saveurs d'Asie" => "Vin blanc sec ou saké pour l'accord franco-japonais.",
            'Gaspacho Tomates et Melon' => "Servir très frais pour un meilleur équilibre.",
            'Tartelette aux Marrons Suisses' => "Accompagner d'un café ou d'un thé.",
            'Tartelette Ricotta au Miel et Fraises' => "Thé vert ou café léger.",
            'Crémeux Yuzu aux Fruits Rouges' => "Thé vert japonais pour souligner le yuzu.",
        ];

        // Prep time ranges (min, max) in minutes by dish name
        $prepTimes = [
            'Asperges Printemps à la Ricotta' => [20, 25],
            'Œuf Mollet au Safran et Petits Pois' => [15, 20],
            'Seiches Sautées à la Chermoula' => [20, 25],
            'Carpaccio de bœuf' => [10, 15],
            'Bouillabaisse marseillaise' => [25, 30],
            "Boulette d'agneau" => [25, 30],
            "Galinette poêlée à l'ajo blanco" => [25, 30],
            "Spaghettis à l'ail noir et parmesan" => [20, 25],
            'Loup de mer aux pois chiches' => [25, 30],
            'Magret de canard au fenouil confit' => [30, 35],
            'Velouté de châtaignes aux pleurottes' => [25, 30],
            'Sashimi de ventrèche de thon fumé' => [15, 20],
            "Potimarron Rôti aux Saveurs d'Asie" => [30, 35],
            'Tartelette aux Marrons Suisses' => [30, 35],
            'Tartelette Ricotta au Miel et Fraises' => [25, 30],
            'Crémeux Yuzu aux Fruits Rouges' => [20, 25],
            'Gaspacho Tomates et Melon' => [15, 20],
        ];

        // Nutrition facts by dish name (from Restaurant/dish-detail nutrition map)
        $nutrition = [
            'Asperges Printemps à la Ricotta' => [280, 12, 15, 16, 6, 450],
            'Œuf Mollet au Safran et Petits Pois' => [240, 14, 12, 14, 4, 400],
            'Seiches Sautées à la Chermoula' => [260, 22, 8, 14, 6, 550],
            'Carpaccio de bœuf' => [280, 25, 8, 16, 2, 600],
            'Bouillabaisse marseillaise' => [420, 35, 15, 25, 3, 1200],
            'Entrecôte grillée' => [580, 45, 20, 35, 2, 900],
            "Boulette d'agneau" => [480, 28, 25, 22, 4, 750],
            "Galinette poêlée à l'ajo blanco" => [520, 35, 22, 32, 6, 850],
            "Spaghettis à l'ail noir et parmesan" => [480, 18, 65, 15, 3, 750],
            'Loup de mer aux pois chiches' => [380, 32, 28, 16, 8, 650],
            'Magret de canard au fenouil confit' => [580, 35, 12, 38, 4, 800],
            'Velouté de châtaignes aux pleurottes' => [320, 12, 28, 18, 6, 650],
            'Sashimi de ventrèche de thon fumé' => [280, 32, 8, 12, 2, 850],
            "Potimarron Rôti aux Saveurs d'Asie" => [320, 8, 25, 18, 8, 600],
            'Tartelette aux Marrons Suisses' => [320, 6, 45, 12, 3, 200],
            'Tartelette Ricotta au Miel et Fraises' => [280, 8, 35, 14, 4, 180],
            'Crémeux Yuzu aux Fruits Rouges' => [260, 6, 30, 16, 5, 150],
            'Gaspacho Tomates et Melon' => [180, 8, 15, 12, 6, 400],
        ];

        foreach (array_keys($ingredients + $preparation + $nutrition) as $name) {
            /** @var MenuItem|null $item */
            $item = $repo->findOneBy(['name' => $name]);
            if (!$item) {
                continue; // do not create new rows
            }

            if (isset($ingredients[$name])) {
                $item->setIngredients(json_encode($ingredients[$name], JSON_UNESCAPED_UNICODE));
            }

            if (isset($preparation[$name])) {
                $item->setPreparation($preparation[$name]);
            }

            if (isset($chefTips[$name])) {
                $item->setChefTip($chefTips[$name]);
            }

            if (isset($prepTimes[$name])) {
                [$minT, $maxT] = $prepTimes[$name];
                $item->setPrepTimeMin($minT);
                $item->setPrepTimeMax($maxT);
            }

            if (isset($nutrition[$name])) {
                [$cal, $prot, $carb, $fat, $fiber, $sodium] = $nutrition[$name];
                $facts = $item->getNutrition();
                $facts->caloriesKcal = $cal;
                $facts->proteinsG = (string)$prot;
                $facts->carbsG = (string)$carb;
                $facts->fatsG = (string)$fat;
                $facts->fiberG = (string)$fiber;
                $facts->sodiumMg = $sodium;
                $item->setNutrition($facts);
            }

            $manager->persist($item);
        }

        $manager->flush();
    }
}


