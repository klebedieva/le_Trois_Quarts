<?php

namespace App\Controller;

use App\Entity\MenuItem;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\ValidationHelper;
use App\Service\ReviewService;

/**
 * Dish-Specific Review Controller
 * 
 * Handles reviews for individual menu items (dishes):
 * - List approved reviews for a specific dish
 * - Create new reviews for a dish (pending moderation)
 * 
 * Architecture:
 * - Extends AbstractApiController for common API functionality (JSON parsing, DTO validation, responses)
 * - Uses ReviewService for business logic (review creation, persistence)
 * - Supports both JSON and form-encoded request payloads (for backward compatibility)
 * 
 * These are lightweight JSON endpoints consumed by dish-detail.js on the dish detail page.
 * Reviews are filtered by menuItem relation and must be approved to be displayed.
 * 
 * Note: This endpoint is not under /api/*, so JsonFieldWhitelistSubscriber won't process it,
 * but we use the same pattern for consistency and future-proofing.
 */
#[Route('/dish/{id}/reviews')]
class DishReviewController extends AbstractApiController
{
    /**
     * Constructor
     *
     * Injects dependencies required for dish review operations:
     * - ValidatorInterface and ValidationHelper: Passed to parent for DTO validation
     * - ReviewService: Encapsulates review creation and persistence (business logic)
     *
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     * @param ReviewService $reviewService Service for creating reviews
     */
    public function __construct(
        ValidatorInterface $validator,
        ValidationHelper $validationHelper,
        private ReviewService $reviewService
    ) {
        parent::__construct($validator, $validationHelper);
    }
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

        // Return flat structure expected by frontend: { success: true, reviews: [...] }
        return $this->json([
            'success' => true,
            'reviews' => $data,
        ], 200);
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
     * @return JsonResponse Success/error response
     */
    #[Route('', name: 'dish_reviews_add', methods: ['POST'])]
    public function add(MenuItem $item, Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        // Validate CSRF token (supports header X-CSRF-Token or _token form field)
        $csrfError = $this->validateCsrfToken($request, $csrfTokenManager, 'review_submit');
        if ($csrfError !== null) {
            return $csrfError;
        }

        // Build a normalized array supporting both JSON and form-encoded payloads
        // Priority 1: Use filtered data from JsonFieldWhitelistSubscriber if available (for API requests)
        // Priority 2: Parse JSON content if available
        // Priority 3: Fallback to form-encoded data
        // Note: This endpoint is not under /api/*, so JsonFieldWhitelistSubscriber won't process it
        // But we use the same pattern for consistency and future-proofing
        $data = $request->attributes->get('filtered_json_data');
        if ($data === null) {
            // Try to parse JSON content
            $data = json_decode($request->getContent(), true);
        }
        
        if (!is_array($data)) {
            // Fallback to form-encoded data if JSON parsing failed
            $data = [
                'name' => $request->request->get('name'),
                'email' => $request->request->get('email'),
                'rating' => $request->request->get('rating'),
                'comment' => $request->request->get('comment'),
            ];
        }

        // Map to DTO and validate
        // Uses base class method from AbstractApiController
        // Returns DTO or JsonResponse (error if validation fails)
        // Note: If data came from JsonFieldWhitelistSubscriber, it's already filtered
        // Otherwise, DTO validation will handle any unauthorized fields
        $validationResult = $this->validateDto($data, \App\DTO\ReviewCreateRequest::class);
        if ($validationResult instanceof JsonResponse) {
            // Validation failed, return error response
            return $validationResult;
        }
        $dto = $validationResult;

        // XSS validation for user input fields (defense in depth)
        $xssError = $this->validateXss($dto, ['name', 'email', 'comment']);
        if ($xssError !== null) {
            return $xssError;
        }

        // Create new review entity associated with this dish via service
        $review = (new Review())
            ->setName($dto->name)
            ->setEmail($dto->email !== '' ? $dto->email : null)
            ->setRating((int) $dto->rating)
            ->setComment($dto->comment)
            ->setIsApproved(false)
            ->setMenuItem($item);

        // Delegate persistence to service to keep controller thin
        $this->reviewService->createReviewFromEntity($review);

        // Uses base class method from AbstractApiController
        return $this->successResponse(null, 'Avis soumis. En attente de validation.', 201);
    }
}

