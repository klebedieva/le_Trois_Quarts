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

#[Route('/api/dishes')]
#[OA\Tag(name: 'Reviews')]
class DishReviewApiController extends AbstractController
{
    #[Route('/{id}/reviews', name: 'api_dish_reviews_list', methods: ['GET'])]
    #[OA\Get(path: '/api/dishes/{id}/reviews', summary: 'List approved reviews for a dish', tags: ['Reviews'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'reviews', type: 'array', items: new OA\Items(type: 'object'))
    ]))]
    #[OA\Response(response: 404, description: 'Dish not found', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Dish not found')
    ]))]
    public function list(int $id, ReviewRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $menuItem = $em->find(MenuItem::class, $id);
        if (!$menuItem) {
            return $this->json(['success' => false, 'message' => 'Dish not found'], 404);
        }

        $reviews = $repo->createQueryBuilder('r')
            ->andWhere('r.menuItem = :id')
            ->andWhere('r.isApproved = 1')
            ->setParameter('id', $menuItem->getId())
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $data = array_map(static function (Review $r) {
            return [
                'id' => $r->getId(),
                'name' => $r->getName(),
                'rating' => $r->getRating(),
                'comment' => $r->getComment(),
                'createdAt' => $r->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }, $reviews);

        return $this->json(['success' => true, 'reviews' => $data]);
    }

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
    #[OA\Response(response: 200, description: 'Accepted', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string')
    ]))]
    #[OA\Response(response: 404, description: 'Dish not found', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Dish not found')
    ]))]
    public function add(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $menuItem = $em->find(MenuItem::class, $id);
        if (!$menuItem) {
            return $this->json(['success' => false, 'message' => 'Dish not found'], 404);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $rating = (int) ($data['rating'] ?? 0);
        $comment = trim((string) ($data['comment'] ?? ''));

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
            ->setIsApproved(false)
            ->setMenuItem($menuItem);

        $em->persist($review);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Review submitted. Pending approval.']);
    }
}


