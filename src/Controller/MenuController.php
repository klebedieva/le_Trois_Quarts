<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MenuItemRepository;
use App\Repository\ReviewRepository;
use App\Repository\DrinkRepository;
use App\Entity\MenuItem;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;

/**
 * Public menu and dish detail pages.
 *
 * Notes:
 * - index() serializes MenuItem entities into lightweight arrays for the frontend JS.
 * - show() prepares dish detail data and uses lightweight queries for related items
 *   plus aggregate ratings to avoid heavy hydration.
 */
final class MenuController extends AbstractController
{
    #[Route('/menu', name: 'app_menu')]
    public function index(MenuItemRepository $menuItemRepository, DrinkRepository $drinkRepository, CacheManager $cacheManager, LoggerInterface $logger): Response
    {
        // Récupérer toutes les entrées du menu depuis la base de données
        $items = $menuItemRepository->findAll();

        // Normaliser les entités pour le front (structure attendue par static/js/menu.js)
        $menuItems = array_map(static function (MenuItem $item) use ($cacheManager, $logger): array {
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
                if (
                    !str_starts_with($image, '/uploads/')
                    && !str_starts_with($image, '/assets/')
                    && !str_starts_with($image, '/static/')
                    && !str_starts_with($image, 'http')
                ) {
                    $image = '/uploads/menu/' . ltrim($image, '/');
                }
                // If path starts with 'assets/' or 'static/', make it absolute under public
                if (str_starts_with($image, 'assets/') || str_starts_with($image, 'static/')) {
                    $image = '/' . ltrim($image, '/');
                }
            }

            $normalizedImage = $image ? ltrim($image, '/') : null;
            $imageJpegPath = $imageWebpPath = $imageHeroPath = $imageHeroWebpPath = null;
            if ($normalizedImage) {
                try {
                    $imageJpegPath = $cacheManager->getBrowserPath($normalizedImage, 'gallery_jpeg');
                    $imageWebpPath = $cacheManager->getBrowserPath($normalizedImage, 'gallery_webp');
                    $imageHeroPath = $cacheManager->getBrowserPath($normalizedImage, 'hero_jpeg');
                    $imageHeroWebpPath = $cacheManager->getBrowserPath($normalizedImage, 'hero_webp');
                } catch (\Throwable $e) {
                    $logger->warning('LiipImagine failed to generate menu image variant', [
                        'path' => $normalizedImage,
                        'error' => $e->getMessage(),
                    ]);
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
                'image_original' => $image,
                'image_optimized' => $imageJpegPath,
                'image_webp' => $imageWebpPath,
                'image_full' => $imageHeroPath ?? $image,
                'image_full_webp' => $imageHeroWebpPath,
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
            'seo_title' => 'Menu restaurant | Le Trois Quarts Marseille',
            'seo_description' => 'Consultez le menu complet du Trois Quarts : plats méditerranéens, desserts gourmands et boissons sélectionnées.',
            'seo_og_description' => 'Une carte de saison, des produits frais et des recettes généreuses : découvrez le menu du Trois Quarts.',
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
            if (
                !str_starts_with($image, '/uploads/')
                && !str_starts_with($image, '/assets/')
                && !str_starts_with($image, '/static/')
                && !str_starts_with($image, 'http')
            ) {
                $image = '/uploads/menu/' . ltrim($image, '/');
            }
            if (str_starts_with($image, 'assets/') || str_starts_with($image, 'static/')) {
                $image = '/' . ltrim($image, '/');
            }
        }

        // Related dishes (same category) - optimized lightweight query
        $related = $menuItemRepository->findRelatedForCard($item->getCategory(), (int) $item->getId(), 3);
        // Normalize image path as previously
        foreach ($related as &$rel) {
            $rImage = $rel['image'] ?? null;
            if ($rImage) {
                if (
                    !str_starts_with($rImage, '/uploads/')
                    && !str_starts_with($rImage, '/assets/')
                    && !str_starts_with($rImage, '/static/')
                    && !str_starts_with($rImage, 'http')
                ) {
                    $rImage = '/uploads/menu/' . ltrim($rImage, '/');
                }
                if (str_starts_with($rImage, 'assets/') || str_starts_with($rImage, 'static/')) {
                    $rImage = '/' . ltrim($rImage, '/');
                }
            }
            $rel['image'] = $rImage;
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

        // Compute rating summary from approved dish reviews - optimized helper
        $approvedStats = $reviewRepository->getApprovedStatsForMenuItem((int) $item->getId());
        $ratingCount = $approvedStats['cnt'];
        $ratingAvg = $approvedStats['avg'];

        $rawDescription = strip_tags($item->getDescription() ?? '');
        $normalizedDescription = trim($rawDescription) !== ''
            ? trim($rawDescription)
            : sprintf('Découvrez %s, une spécialité du Trois Quarts au cœur du Camas.', $item->getName());
        $shortDescription = mb_strlen($normalizedDescription) > 155
            ? mb_substr($normalizedDescription, 0, 152) . '...'
            : $normalizedDescription;

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
            'seo_title' => sprintf('%s | Le Trois Quarts Marseille', $item->getName()),
            'seo_description' => $shortDescription,
            'seo_og_description' => $shortDescription,
            'seo_image' => $image ?? null,
            'seo_og_type' => 'article',
        ]);
    }
}
