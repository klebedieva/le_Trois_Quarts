<?php

namespace App\Controller;

use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
#[OA\Tag(name: 'Reviews')]
class ReviewController extends AbstractController
{
    #[Route('/reviews', name: 'api_reviews_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/reviews',
        summary: 'List approved reviews',
        description: 'Returns latest approved reviews for the restaurant. Use dish endpoints for dish-specific reviews.',
        tags: ['Reviews']
    )]
    #[OA\Response(
        response: 200,
        description: 'Successful response',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'reviews',
                    type: 'array',
                    items: new OA\Items(type: 'object', properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 12),
                        new OA\Property(property: 'name', type: 'string', example: 'Alice'),
                        new OA\Property(property: 'rating', type: 'integer', example: 5),
                        new OA\Property(property: 'comment', type: 'string', example: 'Great food!'),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2025-09-20 18:30')
                    ])
                )
            ]
        )
    )]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $qb = $em->getRepository(Review::class)->createQueryBuilder('r')
            ->andWhere('r.menuItem IS NULL')
            ->andWhere('r.isApproved = 1')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(100);

        $reviews = $qb->getQuery()->getResult();

        $data = array_map(static function (Review $r) {
            return [
                'id' => $r->getId(),
                'name' => $r->getName(),
                'rating' => $r->getRating(),
                'comment' => $r->getComment(),
                'createdAt' => $r->getCreatedAt()?->format('Y-m-d H:i'),
            ];
        }, $reviews);

        return $this->json(['success' => true, 'reviews' => $data]);
    }

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
        response: 200,
        description: 'Review accepted for moderation',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Review submitted. Pending approval.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid JSON or validation error',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Validation error'),
                new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'), nullable: true)
            ]
        )
    )]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $name = trim((string)($data['name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $rating = (int)($data['rating'] ?? 0);
        $comment = trim((string)($data['comment'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) < 2) { $errors[] = 'Name must be at least 2 characters'; }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email is not valid'; }
        if ($rating < 1 || $rating > 5) { $errors[] = 'Rating must be between 1 and 5'; }
        if ($comment === '' || mb_strlen($comment) < 10) { $errors[] = 'Comment must be at least 10 characters'; }

        if ($errors) {
            return $this->json(['success' => false, 'message' => 'Validation error', 'errors' => $errors], 400);
        }

        $review = (new Review())
            ->setName($name)
            ->setEmail($email !== '' ? $email : null)
            ->setRating($rating)
            ->setComment($comment)
            ->setIsApproved(false);

        $em->persist($review);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Review submitted. Pending approval.']);
    }
}


