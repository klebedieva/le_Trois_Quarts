<?php

namespace App\Controller;

use App\Entity\Review;
use App\Service\ValidationHelper;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Review API Controller
 * 
 * Handles restaurant review operations:
 * - List approved reviews with pagination
 * - Create new reviews (pending moderation)
 * 
 * Architecture:
 * - Controller is thin: only handles request/response, validation, and delegates to services
 * - Business logic (entity creation, persistence) is encapsulated in ReviewService
 * - This follows Single Responsibility Principle: controllers don't call persist()/flush() directly
 * 
 * Note: Only returns approved reviews for general listing.
 * Dish-specific reviews are handled by DishReviewController.
 */
#[Route('/api')]
#[OA\Tag(name: 'Reviews')]
class ReviewController extends AbstractController
{
    /**
     * Constructor for ReviewController
     *
     * Injects dependencies required for review API operations:
     * - ValidatorInterface: Validates DTO data using Symfony Validator
     * - ValidationHelper: Centralizes validation message extraction and DTO mapping
     * - ReviewService: Encapsulates review creation and persistence (business logic)
     *
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     * @param \App\Service\ReviewService $reviewService Service for creating reviews
     */
    public function __construct(
        private ValidatorInterface $validator,
        private ValidationHelper $validationHelper,
        private \App\Service\ReviewService $reviewService
    ) {}
    /**
     * List approved reviews with pagination support
     * 
     * Returns only approved reviews (not dish-specific) for the restaurant homepage/reviews page.
     * Supports pagination via query parameters.
     * 
     * @param Request $request HTTP request containing pagination parameters
     * @param EntityManagerInterface $em Entity manager for database operations
     * @return JsonResponse Paginated list of approved reviews
     */
    #[Route('/reviews', name: 'api_reviews_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/reviews',
        summary: 'List approved reviews',
        description: 'Returns latest approved reviews for the restaurant. Use dish endpoints for dish-specific reviews.',
        tags: ['Reviews']
    )]
    #[OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number (default: 1)', schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Items per page (default: 6)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))]
    #[OA\Response(response: 200, description: 'Successful response', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'data', type: 'object')]))]
    #[OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Extract pagination parameters from query string (defaults: page=1, limit=6)
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 6);
        $offset = ($page - 1) * $limit;

        // Query only general reviews (not dish-specific) that are approved
        // menuItem IS NULL means it's a general restaurant review, not a dish review
        /** @var \App\Repository\ReviewRepository $repo */
        $repo = $em->getRepository(Review::class);
        $reviews = $repo->findApprovedGeneralPaginated($page, $limit);
        $totalCount = $repo->countApprovedGeneral();

        // Determine if there are more pages available
        $hasMore = ($offset + count($reviews)) < $totalCount;

        // Transform Review entities to array format for JSON response
        $data = array_map(static function (Review $r) {
            return [
                'id' => $r->getId(),
                'name' => $r->getName(),
                'rating' => $r->getRating(),
                'comment' => $r->getComment(),
                'createdAt' => $r->getCreatedAt()?->format('Y-m-d H:i'),
            ];
        }, $reviews);

        $response = new \App\DTO\ApiResponseDTO(
            success: true,
            data: [
                'reviews' => $data,
                'pagination' => [
                    'current_page' => $page,
                    'total_count' => (int) $totalCount,
                    'per_page' => $limit,
                    'has_more' => $hasMore
                ]
            ]
        );

        return $this->json($response->toArray(), 200);
    }

    /**
     * Create a new review (pending moderation)
     * 
     * Accepts review submission from users. All new reviews are set to isApproved=false
     * and require admin moderation before being displayed publicly.
     * 
     * @param Request $request HTTP request containing review data (name, email, rating, comment)
     * @param EntityManagerInterface $em Entity manager for persisting the review
     * @return JsonResponse Success/error response
     */
    #[Route('/review', name: 'api_review_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/review',
        summary: 'Submit a new review (pending moderation)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Alice'),
                    new OA\Property(property: 'email', type: 'string', example: 'alice@example.com', nullable: true),
                    new OA\Property(property: 'rating', type: 'integer', example: 5, minimum: 1, maximum: 5),
                    new OA\Property(property: 'comment', type: 'string', example: 'Great food and service!')
                ]
            )
        ),
        tags: ['Reviews']
    )]
    #[OA\Response(
        response: 201,
        description: 'Review created and accepted for moderation',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ],
            example: [
                'success' => true,
                'message' => 'Avis soumis. En attente de validation.'
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid JSON',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ],
            example: [
                'success' => false,
                'message' => 'JSON invalide'
            ]
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
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Get JSON data from request
        // Priority 1: Use filtered data from JsonFieldWhitelistSubscriber if available
        // This ensures only authorized fields reach the controller (mass assignment protection)
        // The subscriber filters out unauthorized fields before the request reaches here
        // Priority 2: Fallback to parsing raw content if subscriber didn't process it
        // (This should rarely happen for API endpoints, but provides backward compatibility)
        $data = $request->attributes->get('filtered_json_data');
        if ($data === null) {
            // Fallback: parse raw content if filtered data not available
            // This can happen if request bypassed the subscriber or for non-API endpoints
            $data = json_decode($request->getContent(), true);
        }
        
        if (!is_array($data)) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'JSON invalide');
            return $this->json($response->toArray(), 400);
        }

        // Map JSON payload to DTO using helper service
        // The ValidationHelper automatically handles type conversion (e.g., string '5' -> int 5)
        // This eliminates repetitive manual mapping code like: isset($data['name']) ? trim((string)$data['name']) : null
        // Note: Data is already filtered by JsonFieldWhitelistSubscriber, so only authorized fields are present
        // This provides defense in depth: subscriber filters at request level, DTO validates at domain level
        $dto = $this->validationHelper->mapArrayToDto($data, \App\DTO\ReviewCreateRequest::class);
        
        // No manual trimming: ValidationHelper::mapArrayToDto() already normalizes
        // all public string properties (trims whitespace) to avoid duplication.

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $errors = $this->validationHelper->extractViolationMessages($violations);
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur de validation', errors: $errors);
            return $this->json($response->toArray(), 422);
        }

        // Delegate creation to ReviewService (no direct persist/flush in controller)
        // This follows Single Responsibility Principle: controller handles HTTP, service handles domain logic
        // The service encapsulates entity creation, approval flag initialization (false), and persistence
        // This makes the code more testable (can mock service) and reusable (service can be called from elsewhere)
        // Pass null as menuItem since this is a general restaurant review (not dish-specific)
        $review = $this->reviewService->createReview($dto, null);

        $response = new \App\DTO\ApiResponseDTO(success: true, message: 'Avis soumis. En attente de validation.');
        return $this->json($response->toArray(), 201);
    }
}


