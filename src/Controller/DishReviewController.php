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
 * Endpoints for dish-specific reviews (list and add).
 * These are lightweight JSON endpoints consumed by dish-detail.js on the dish page.
 */
#[Route('/dish/{id}/reviews')]
class DishReviewController extends AbstractController
{
    /**
     * Return approved reviews for the given MenuItem (dish).
     */
    #[Route('', name: 'dish_reviews_list', methods: ['GET'])]
    public function list(MenuItem $item, ReviewRepository $repo): JsonResponse
    {
        $reviews = $repo->createQueryBuilder('r')
            ->andWhere('r.menuItem = :id')
            ->andWhere('r.isApproved = 1')
            ->setParameter('id', $item->getId())
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

    /**
     * Create a new review for a dish; newly created reviews are pending moderation.
     */
    #[Route('', name: 'dish_reviews_add', methods: ['POST'])]
    public function add(MenuItem $item, Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Accept both JSON and form-encoded payloads
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

        if ($name === '' || $rating < 1 || $rating > 5 || mb_strlen($comment) < 10) {
            return $this->json(['success' => false, 'message' => 'Invalid data'], 400);
        }

        $review = (new Review())
            ->setName($name)
            ->setEmail($email !== '' ? $email : null)
            ->setRating($rating)
            ->setComment($comment)
            ->setIsApproved(false) // keep moderation consistent with global reviews
            ->setMenuItem($item);

        $em->persist($review);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Review submitted. Pending approval.']);
    }
}

