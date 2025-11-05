<?php

namespace App\Controller;

use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Review API Controller
 * 
 * Handles restaurant review operations:
 * - List approved reviews with pagination
 * - Create new reviews (pending moderation)
 * 
 * Note: Only returns approved reviews for general listing.
 * Dish-specific reviews are handled by DishReviewController.
 */
#[Route('/api')]
#[OA\Tag(name: 'Reviews')]
class ReviewController extends AbstractController
{
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
    #[OA\Response(response: 200, description: 'Successful response', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
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

        return $this->json($response->toArray());
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
    #[OA\Response(response: 200, description: 'Review accepted for moderation', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 400, description: 'Invalid JSON', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Parse JSON request body
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'JSON invalide');
            return $this->json($response->toArray(), 400);
        }

        // Map payload to DTO and validate via Symfony Validator
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

        // Create new review entity (not approved by default - requires moderation)
        $review = (new Review())
            ->setName($dto->name)
            ->setEmail($dto->email !== '' ? $dto->email : null)
            ->setRating((int)$dto->rating)
            ->setComment($dto->comment)
            ->setIsApproved(false); // All new reviews require admin approval

        // Persist to database
        $em->persist($review);
        $em->flush();

        $response = new \App\DTO\ApiResponseDTO(success: true, message: 'Avis soumis. En attente de validation.');
        return $this->json($response->toArray());
    }
}


