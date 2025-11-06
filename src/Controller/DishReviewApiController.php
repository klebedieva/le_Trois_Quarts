<?php

namespace App\Controller;

use App\Entity\MenuItem;
use App\Repository\MenuItemRepository;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use App\Service\ValidationHelper;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Dish Review API Controller
 * 
 * RESTful API endpoints for dish-specific reviews:
 * - List approved reviews for a dish by ID
 * - Create new reviews for a dish (pending moderation)
 * 
 * Architecture:
 * - Extends AbstractApiController for common API functionality (JSON parsing, DTO validation, responses)
 * - Uses ReviewService for business logic (review creation, persistence)
 * - This follows Single Responsibility Principle: controllers don't call persist()/flush() directly
 * 
 * This is an alternative API endpoint to DishReviewController, following REST conventions.
 * Uses OpenAPI documentation for API specification.
 */
#[Route('/api/dishes')]
#[OA\Tag(name: 'Reviews')]
class DishReviewApiController extends AbstractApiController
{
    /**
     * Constructor for DishReviewApiController
     *
     * Injects dependencies required for dish review API operations:
     * - ValidatorInterface and ValidationHelper: Passed to parent for DTO validation
     * - ReviewService: Encapsulates review creation and persistence (business logic)
     * - MenuItemRepository: For finding menu items by ID
     *
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     * @param \App\Service\ReviewService $reviewService Service for creating reviews
     * @param MenuItemRepository $menuItemRepository Repository for menu item queries
     */
    public function __construct(
        ValidatorInterface $validator,
        ValidationHelper $validationHelper,
        private \App\Service\ReviewService $reviewService,
        private MenuItemRepository $menuItemRepository
    ) {
        parent::__construct($validator, $validationHelper);
    }
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
    #[OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'data', type: 'object')]))]
    #[OA\Response(response: 404, description: 'Dish not found', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
    /**
     * Use MenuItemRepository instead of EntityManager to reduce coupling and make
     * the action easier to unit test (repository can be mocked directly).
     */
    public function list(int $id, ReviewRepository $repo): JsonResponse
    {
        // Verify that the dish exists
        $menuItem = $this->menuItemRepository->find($id);
        if (!$menuItem) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Plat introuvable', 404);
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

        // Uses base class method from AbstractApiController
        return $this->successResponse(['reviews' => $data], null, 200);
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
    #[OA\Response(
        response: 200,
        description: 'Accepted',
        content: new OA\JsonContent(
            type: 'object',
            properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')],
            example: ['success' => true, 'message' => 'Avis soumis. En attente de validation.']
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid JSON',
        content: new OA\JsonContent(
            type: 'object',
            properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')],
            example: ['success' => false, 'message' => 'JSON invalide']
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
            ],
            example: [
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => ['Le nom est requis', 'La note doit être entre 1 et 5']
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Dish not found',
        content: new OA\JsonContent(
            type: 'object',
            properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')],
            example: ['success' => false, 'message' => 'Plat introuvable']
        )
    )]
    /**
     * Use MenuItemRepository instead of EntityManager to reduce coupling and make
     * the action easier to unit test (repository can be mocked directly).
     */
    public function add(int $id, Request $request): JsonResponse
    {
        // Get JSON data from request
        // Uses base class method from AbstractApiController
        // Returns array or JsonResponse (error if JSON invalid)
        $jsonResult = $this->getJsonDataFromRequest($request);
        if ($jsonResult instanceof JsonResponse) {
            // JSON parsing failed, return error response
            return $jsonResult;
        }
        $data = $jsonResult;

        // Verify that the dish exists
        $menuItem = $this->menuItemRepository->find($id);
        if (!$menuItem) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Plat introuvable', 404);
        }

        // Map JSON payload to DTO and validate
        // Uses base class method from AbstractApiController
        // Returns DTO or JsonResponse (error if validation fails)
        $validationResult = $this->validateDto($data, \App\DTO\ReviewCreateRequest::class);
        if ($validationResult instanceof JsonResponse) {
            // Validation failed, return error response
            return $validationResult;
        }
        $dto = $validationResult;

        // Delegate creation to ReviewService (no direct persist/flush in controller)
        // This follows Single Responsibility Principle: controller handles HTTP, service handles domain logic
        // The service encapsulates entity creation, approval flag initialization (false), and persistence
        // This makes the code more testable (can mock service) and reusable (service can be called from elsewhere)
        // Pass menuItem to associate the review with the specific dish
        $review = $this->reviewService->createReview($dto, $menuItem);

        // Uses base class method from AbstractApiController
        return $this->successResponse(null, 'Avis soumis. En attente de validation.', 201);
    }
}


