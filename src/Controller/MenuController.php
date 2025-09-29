<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MenuItemRepository;
use App\Repository\ReviewRepository;
use App\Repository\DrinkRepository;
use App\Entity\MenuItem;

final class MenuController extends AbstractController
{
    #[Route('/menu', name: 'app_menu')]
    public function index(MenuItemRepository $menuItemRepository, DrinkRepository $drinkRepository): Response
    {
        // Récupérer toutes les entrées du menu depuis la base de données
        $items = $menuItemRepository->findAll();

        // Normaliser les entités pour le front (structure attendue par assets/js/menu.js)
        $menuItems = array_map(static function (MenuItem $item): array {
            // Extraire les badges (ex. noms ou slugs)
            $badges = [];
            if (method_exists($item, 'getBadges')) {
                foreach ($item->getBadges() as $badge) {
                    $badges[] = method_exists($badge, 'getName') ? $badge->getName() : (string) $badge;
                }
            }

            // Extraire les tags (ex. codes techniques pour le filtrage)
            $tags = [];
            if (method_exists($item, 'getTags')) {
                foreach ($item->getTags() as $tag) {
                    $tags[] = method_exists($tag, 'getCode') ? $tag->getCode() : (string) $tag;
                }
            }

            // Resolve public image path
            $image = $item->getImage();
            if ($image) {
                // If it's just a filename from upload, prefix the uploads base path
                if (!str_starts_with($image, '/uploads/') && !str_starts_with($image, '/assets/') && !str_starts_with($image, 'http')) {
                    $image = '/uploads/menu/' . ltrim($image, '/');
                }
                // If path starts with 'assets/', make it absolute under public
                if (str_starts_with($image, 'assets/')) {
                    $image = '/' . $image;
                }
            }

            return [
                // Forcer l'ID en string pour correspondre au JS (comparaisons strictes)
                'id' => (string) $item->getId(),
                'name' => $item->getName(),
                'description' => $item->getDescription(),
                'price' => (float) $item->getPrice(),
                'category' => $item->getCategory(), // valeurs attendues: entrees|plats|desserts|boissons
                'image' => $image,
                'badges' => $badges,
                'tags' => $tags,
            ];
        }, $items);

        // Encoder en JSON (sans échapper les caractères Unicode et les slashs)
        $menuItemsJson = json_encode($menuItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Récupérer les boissons et les regrouper par type
        $drinks = $drinkRepository->findAll();
        $drinksGrouped = [
            'vins' => [],
            'chaudes' => [],
            'bieres' => [],
            'fraiches' => [],
        ];
        foreach ($drinks as $drink) {
            $type = method_exists($drink, 'getType') ? $drink->getType() : 'autres';
            $entry = [
                'name' => method_exists($drink, 'getName') ? $drink->getName() : '',
                'price' => method_exists($drink, 'getPrice') ? $drink->getPrice() : '',
            ];
            if (!isset($drinksGrouped[$type])) {
                $drinksGrouped[$type] = [];
            }
            $drinksGrouped[$type][] = $entry;
        }
        $drinksJson = json_encode($drinksGrouped, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Rendre le template de la page Menu et exposer les données côté client
        return $this->render('pages/menu.html.twig', [
            'menuItemsJson' => $menuItemsJson,
            'drinksJson' => $drinksJson,
        ]);
    }

    #[Route('/dish/{id}', name: 'app_dish_detail', requirements: ['id' => '\\d+'])]
    public function show(MenuItem $item, MenuItemRepository $menuItemRepository, ReviewRepository $reviewRepository): Response
    {
        // Préparer structure pour le template
        $badges = [];
        foreach ($item->getBadges() as $badge) {
            $badges[] = method_exists($badge, 'getName') ? $badge->getName() : (string) $badge;
        }

        $allergens = [];
        if (method_exists($item, 'getAllergens')) {
            foreach ($item->getAllergens() as $allergen) {
                $allergens[] = method_exists($allergen, 'getName') ? $allergen->getName() : (string) $allergen;
            }
        }

        $image = $item->getImage();
        if ($image) {
            if (!str_starts_with($image, '/uploads/') && !str_starts_with($image, '/assets/') && !str_starts_with($image, 'http')) {
                $image = '/uploads/menu/' . ltrim($image, '/');
            }
            if (str_starts_with($image, 'assets/')) {
                $image = '/' . $image;
            }
        }

        // Related dishes (same category)
        $related = [];
        $sameCategory = $menuItemRepository->findBy(['category' => $item->getCategory()]);
        foreach ($sameCategory as $other) {
            if ($other->getId() === $item->getId()) { continue; }
            $rImage = $other->getImage();
            if ($rImage) {
                if (!str_starts_with($rImage, '/uploads/') && !str_starts_with($rImage, '/assets/') && !str_starts_with($rImage, 'http')) {
                    $rImage = '/uploads/menu/' . ltrim($rImage, '/');
                }
                if (str_starts_with($rImage, 'assets/')) {
                    $rImage = '/' . $rImage;
                }
            }
            $related[] = [
                'id' => (string) $other->getId(),
                'name' => $other->getName(),
                'description' => $other->getDescription(),
                'price' => (float) $other->getPrice(),
                'image' => $rImage,
            ];
            if (count($related) >= 3) { break; }
        }

        // Get ingredients as array
        $ingredients = [];
        if (method_exists($item, 'getIngredientsAsArray')) {
            $ingredients = $item->getIngredientsAsArray();
        }

        // Prepare prep time display
        $prepTimeDisplay = null;
        if ($item->getPrepTimeMin() && $item->getPrepTimeMax()) {
            $prepTimeDisplay = $item->getPrepTimeMin() . ' - ' . $item->getPrepTimeMax();
        } elseif ($item->getPrepTimeMin()) {
            $prepTimeDisplay = (string) $item->getPrepTimeMin();
        } elseif ($item->getPrepTimeMax()) {
            $prepTimeDisplay = (string) $item->getPrepTimeMax();
        } elseif ($item->getPrepTimeMinutes()) {
            $prepTimeDisplay = (string) $item->getPrepTimeMinutes();
        }

        // Compute rating summary from approved dish reviews
        $approved = $reviewRepository->createQueryBuilder('r')
            ->select('COUNT(r.id) as cnt, COALESCE(AVG(r.rating), 0) as avgRating')
            ->andWhere('r.menuItem = :id')
            ->andWhere('r.isApproved = 1')
            ->setParameter('id', $item->getId())
            ->getQuery()
            ->getSingleResult();
        $ratingCount = (int)($approved['cnt'] ?? 0);
        $ratingAvg = (float)($approved['avgRating'] ?? 0.0);

        return $this->render('pages/dish_detail.html.twig', [
            'item' => $item,
            'image' => $image,
            'badges' => $badges,
            'allergens' => $allergens,
            'ingredients' => $ingredients,
            'prepTimeDisplay' => $prepTimeDisplay,
            'related' => $related,
            'ratingCount' => $ratingCount,
            'ratingAvg' => $ratingAvg,
        ]);
    }
}
