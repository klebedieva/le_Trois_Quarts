<?php

namespace App\Controller;

use App\Entity\MenuItem;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Dish Review API Controller
 * 
 * RESTful API endpoints for dish-specific reviews:
 * - List approved reviews for a dish by ID
 * - Create new reviews for a dish (pending moderation)
 * 
 * This is an alternative API endpoint to DishReviewController, following REST conventions.
 * Uses OpenAPI documentation for API specification.
 */
#[Route('/api/dishes')]
#[OA\Tag(name: 'Reviews')]
class DishReviewApiController extends AbstractController
{
    /**
     * List approved reviews for a specific dish by ID
     * 
     * Returns approved reviews associated with the dish identified by the ID parameter.
     * Reviews are ordered by creation date (newest first) and limited to 100 results.
     * 
     * @param int $id Dish ID from route parameter
     * @param ReviewRepository $repo Review repository for database queries
     * @param EntityManagerInterface $em Entity manager for finding menu item
     * @return JsonResponse List of approved dish reviews or 404 if dish not found
     */
    #[Route('/{id}/reviews', name: 'api_dish_reviews_list', methods: ['GET'])]
    #[OA\Get(path: '/api/dishes/{id}/reviews', summary: 'List approved reviews for a dish', tags: ['Reviews'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number (default: 1)', schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Items per page (default: 100, max: 100)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))]
    #[OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 404, description: 'Dish not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    public function list(int $id, ReviewRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        // Verify that the dish exists
        $menuItem = $em->find(MenuItem::class, $id);
        if (!$menuItem) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Plat introuvable');
            return $this->json($response->toArray(), 404);
        }

        // Query approved reviews for this specific dish via repository
        $reviews = $repo->findApprovedForDish((int) $menuItem->getId(), 100, 0);

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

        $response = new \App\DTO\ApiResponseDTO(success: true, data: ['reviews' => $data]);
        return $this->json($response->toArray());
    }

    /**
     * Create a new review for a specific dish
     * 
     * Accepts JSON review submission for a menu item identified by ID.
     * All new reviews are set to isApproved=false and require admin moderation.
     * Includes comprehensive validation of all fields.
     * 
     * @param int $id Dish ID from route parameter
     * @param Request $request HTTP request containing JSON review data
     * @param EntityManagerInterface $em Entity manager for database operations
     * @return JsonResponse Success/error response with validation errors if any
     */
    #[Route('/{id}/review', name: 'api_dish_reviews_add', methods: ['POST'])]
    #[OA\Post(path: '/api/dishes/{id}/review', summary: 'Submit a review for a dish (pending moderation)', tags: ['Reviews'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        type: 'object',
        properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'email', type: 'string', nullable: true),
            new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5),
            new OA\Property(property: 'comment', type: 'string')
        ],
        example: [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'rating' => 5,
            'comment' => 'The dish was delicious and perfectly cooked.'
        ]
    ))]
    #[OA\Response(response: 200, description: 'Accepted', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 400, description: 'Invalid JSON', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 404, description: 'Dish not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    public function add(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Parse JSON request body
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'JSON invalide');
            return $this->json($response->toArray(), 400);
        }

        // Verify that the dish exists
        $menuItem = $em->find(MenuItem::class, $id);
        if (!$menuItem) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Plat introuvable');
            return $this->json($response->toArray(), 404);
        }

        // Validate via DTO + Symfony Validator
        $dto = new \App\DTO\ReviewCreateRequest();
        $dto->name = isset($data['name']) ? trim((string)$data['name']) : null;
        $dto->email = isset($data['email']) ? trim((string)$data['email']) : null;
        $dto->rating = isset($data['rating']) ? (int)$data['rating'] : null;
        $dto->comment = isset($data['comment']) ? trim((string)$data['comment']) : null;

        $violations = $this->container->get('validator')->validate($dto);
        if (count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = $violation->getMessage();
            }
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur de validation', errors: $messages);
            return $this->json($response->toArray(), 400);
        }

        // Create new review entity associated with this dish
        // All new reviews require moderation (isApproved=false)
        $review = (new Review())
            ->setName($dto->name)
            ->setEmail($dto->email !== '' ? $dto->email : null)
            ->setRating((int)$dto->rating)
            ->setComment($dto->comment)
            ->setIsApproved(false) // Requires admin approval before display
            ->setMenuItem($menuItem); // Associate review with the specific dish

        // Persist to database
        $em->persist($review);
        $em->flush();

        $response = new \App\DTO\ApiResponseDTO(success: true, message: 'Avis soumis. En attente de validation.');
        return $this->json($response->toArray());
    }
}


