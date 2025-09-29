<?php

namespace App\DataFixtures;

use App\Entity\Review;
use App\Entity\MenuItem;
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
        // 1) 9 отзывов о ресторане (без привязки к блюду)
        $restaurantReviews = [
            ['Camille B.', 'camille@example.com', 5, "Ambiance chaleureuse et cuisine savoureuse. On s'est régalés !", '-14 days', true],
            ['Louis M.', 'louis@example.com', 4, "Très bons plats, service efficace. On reviendra.", '-12 days', true],
            ['Sofia R.', 'sofia@example.com', 5, "Desserts excellents et carte variée. Parfait en famille.", '-10 days', true],
            ['Alex P.', null, 3, "Cadre agréable, portions un peu justes pour moi.", '-9 days', true],
            ['Nina D.', 'nina@example.com', 5, "Meilleur restaurant du quartier, bravo à l'équipe !", '-8 days', true],
            ['Hugo T.', null, 4, "Très bon rapport qualité-prix.", '-7 days', true],
            ['Marine L.', 'marine@example.com', 5, "Service adorable et plats délicats.", '-6 days', true],
            ['Pierre C.', null, 4, "Belle carte et vins sympas.", '-5 days', true],
            ['Iris V.', 'iris@example.com', 5, "Une super expérience de bout en bout.", '-4 days', true],
        ];

        foreach ($restaurantReviews as [$name, $email, $rating, $comment, $rel, $ok]) {
            $review = (new Review())
                ->setName($name)
                ->setEmail($email)
                ->setRating($rating)
                ->setComment($comment)
                ->setCreatedAt(new \DateTime($rel))
                ->setIsApproved($ok);
            $manager->persist($review);
        }

        // 2) Отзывы по блюдам: 3-4 на каждое из ключевых блюд
        $byDishTexts = [
            'Asperges Printemps à la Ricotta' => [
                [5, "Asperges parfaitement cuites, ricotta maison délicieuse !"],
                [4, "Très frais et équilibré, j'ai adoré."],
                [5, "Un must pour les végétariens."],
            ],
            'Œuf Mollet au Safran et Petits Pois' => [
                [5, "Cuisson de l'œuf impeccable, saveur de safran subtile."],
                [4, "Crémeux et gourmand, très bon plat."],
                [4, "Belle découverte !"],
            ],
            'Seiches Sautées à la Chermoula' => [
                [5, "Seiches tendres et assaisonnement parfait."],
                [4, "Superbes saveurs marocaines."],
                [5, "Coup de cœur !"],
            ],
            "Boulette d'agneau" => [
                [5, "Boulettes parfumées, yaourt à la citronnelle top."],
                [4, "Très bon, j'en reprendrais."],
                [4, "Carottes rôties excellentes."],
            ],
            "Galinette poêlée à l'ajo blanco" => [
                [5, "Cuisson parfaite, contraste chaud/froid réussi."],
                [4, "Délicat et original."],
                [5, "Magnifique plat."],
            ],
            "Spaghettis à l'ail noir et parmesan" => [
                [5, "Alliance ail noir et parmesan incroyable."],
                [4, "Très savoureux, portions généreuses."],
                [4, "Bonne maîtrise des sauces."],
            ],
            'Loup de mer aux pois chiches' => [
                [5, "Poisson grillé à la perfection, salade de pois chiches top."],
                [4, "Très bon équilibre des saveurs."],
                [4, "Je recommande."],
            ],
            'Magret de canard au fenouil confit' => [
                [5, "Magret tendre, sauce betterave délicieuse."],
                [4, "Super cuisson, fenouil confit réussi."],
                [5, "Excellent."],
            ],
            'Velouté de châtaignes aux pleurottes' => [
                [4, "Velouté onctueux, pleurottes bien saisies."],
                [4, "Réconfortant et parfumé."],
                [5, "Parfait en entrée."],
            ],
            'Sashimi de ventrèche de thon fumé' => [
                [5, "Fumage maîtrisé, texture fondante."],
                [4, "Très fin et original."],
                [5, "Explosion de saveurs."],
            ],
            "Potimarron Rôti aux Saveurs d'Asie" => [
                [5, "Magnifique accord franco-japonais."],
                [4, "Très équilibré, textures intéressantes."],
                [4, "Belle découverte végétarienne."],
            ],
            'Gaspacho Tomates et Melon' => [
                [5, "Ultra frais, parfait l'été."],
                [4, "Le basilic et la fêta apportent un plus."],
                [4, "Très agréable."],
            ],
            'Tartelette aux Marrons Suisses' => [
                [5, "Meringue parfaite, saveur de marrons au top."],
                [4, "Très bon dessert de saison."],
                [5, "Un régal !"],
            ],
            'Tartelette Ricotta au Miel et Fraises' => [
                [5, "Ricotta/miel/fraises : accord gagnant."],
                [4, "Léger et gourmand."],
                [4, "Très bon."],
            ],
            'Crémeux Yuzu aux Fruits Rouges' => [
                [5, "Yuzu bien présent, textures au top."],
                [4, "Délicieux et original."],
                [5, "Mon dessert préféré !"],
            ],
        ];

        $menuRepo = $manager->getRepository(MenuItem::class);
        // Build a normalized name → entity map to avoid issues with accents/apostrophes
        $normalize = function (string $s): string {
            $s = trim(mb_strtolower($s));
            // unify quotes
            $s = str_replace(["’", "`", "´", "ʻ", "ʼ"], "'", $s);
            // transliterate accents when possible
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
            if ($t !== false) { $s = $t; }
            // keep letters, numbers and spaces only
            $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s) ?? $s;
            // collapse spaces
            $s = preg_replace('/\s+/', ' ', $s) ?? $s;
            return trim($s);
        };
        $map = [];
        foreach ($menuRepo->findAll() as $mi) {
            $map[$normalize($mi->getName())] = $mi;
        }
        $clientNames = [
            'Marie L.', 'Thomas G.', 'Claire P.', 'Julien R.', 'Sophie D.',
            'Antoine M.', 'Léa C.', 'Nicolas B.', 'Élodie F.', 'Hugo V.',
            'Camille S.', 'Pauline T.', 'Arthur K.', 'Manon J.', 'Lucas D.'
        ];
        $nameIdx = 0;
        foreach ($byDishTexts as $dishName => $entries) {
            /** @var MenuItem|null $item */
            $key = $normalize($dishName);
            $item = $map[$key] ?? null;
            if (!$item) { continue; }
            $offset = 3; // распределим даты
            foreach ($entries as [$rating, $text]) {
                $r = (new Review())
                    ->setName($clientNames[$nameIdx++ % count($clientNames)])
                    ->setEmail(null)
                    ->setRating($rating)
                    ->setComment($text)
                    ->setCreatedAt(new \DateTime('-' . ($offset++) . ' days'))
                    ->setIsApproved(true)
                    ->setMenuItem($item);
                $manager->persist($r);
            }
        }

        $manager->flush();
    }
}







