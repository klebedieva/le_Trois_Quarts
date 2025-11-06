<?php

namespace App\Controller;

use App\Entity\MenuItem;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Dish-Specific Review Controller
 * 
 * Handles reviews for individual menu items (dishes):
 * - List approved reviews for a specific dish
 * - Create new reviews for a dish (pending moderation)
 * 
 * These are lightweight JSON endpoints consumed by dish-detail.js on the dish detail page.
 * Reviews are filtered by menuItem relation and must be approved to be displayed.
 */
#[Route('/dish/{id}/reviews')]
class DishReviewController extends AbstractController
{
    /**
     * List approved reviews for a specific menu item (dish)
     * 
     * Returns only approved reviews that are associated with the given dish.
     * Reviews are ordered by creation date (newest first) and limited to 100 results.
     * 
     * @param MenuItem $item Menu item entity (resolved from route parameter)
     * @param ReviewRepository $repo Review repository for database queries
     * @return JsonResponse List of approved dish reviews
     */
    #[Route('', name: 'dish_reviews_list', methods: ['GET'])]
    public function list(MenuItem $item, ReviewRepository $repo): JsonResponse
    {
        // Query approved reviews for this specific dish via repository
        $reviews = $repo->findApprovedForDish((int) $item->getId(), 100, 0);

        // Transform Review entities to array format for JSON response
        $data = array_map(static function (Review $r) {
            return [
                'id' => $r->getId(),
                'name' => $r->getName(),
                'rating' => $r->getRating(),
                'comment' => $r->getComment(),
                'createdAt' => $r->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }, $reviews);

        $response = new \App\DTO\ApiResponseDTO(
            success: true,
            data: ['reviews' => $data]
        );
        return $this->json($response->toArray());
    }

    /**
     * Create a new review for a specific dish
     * 
     * Accepts review submission for a menu item. All new reviews are set to isApproved=false
     * and require admin moderation before being displayed publicly.
     * Supports both JSON and form-encoded request payloads.
     * 
     * @param MenuItem $item Menu item entity (resolved from route parameter)
     * @param Request $request HTTP request containing review data
     * @param EntityManagerInterface $em Entity manager for persisting the review
     * @return JsonResponse Success/error response
     */
    #[Route('', name: 'dish_reviews_add', methods: ['POST'])]
    public function add(MenuItem $item, Request $request, \App\Service\ReviewService $reviewService): JsonResponse
    {
        // Accept both JSON and form-encoded payloads for flexibility
        $data = json_decode($request->getContent(), true);
        if (is_array($data)) {
            $name = trim((string) ($data['name'] ?? ''));
            $email = trim((string) ($data['email'] ?? ''));
            $rating = (int) ($data['rating'] ?? 0);
            $comment = trim((string) ($data['comment'] ?? ''));
        } else {
            $name = trim((string) $request->request->get('name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $rating = (int) $request->request->get('rating', 0);
            $comment = trim((string) $request->request->get('comment', ''));
        }

        // Validate required fields (basic validation)
        if ($name === '' || $rating < 1 || $rating > 5 || mb_strlen($comment) < 10) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Données invalides');
            return $this->json($response->toArray(), 400);
        }

        // Create new review entity associated with this dish
        // All new reviews require moderation (isApproved=false) for consistency
        $review = (new Review())
            ->setName($name)
            ->setEmail($email !== '' ? $email : null)
            ->setRating($rating)
            ->setComment($comment)
            ->setIsApproved(false) // Keep moderation consistent with global reviews
            ->setMenuItem($item); // Associate review with the specific dish

        // Delegate persistence to service to keep controller thin
        $reviewService->createReviewFromEntity($review);

        $response = new \App\DTO\ApiResponseDTO(success: true, message: 'Avis soumis. En attente de validation.');
        return $this->json($response->toArray());
    }
}

