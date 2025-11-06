<?php

namespace App\Service;

use App\DTO\ReviewCreateRequest;
use App\Entity\MenuItem;
use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Review Service
 *
 * Handles creation and persistence of reviews (general and per dish).
 * Keeps controllers focused on I/O while this service encapsulates domain changes.
 */
class ReviewService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Create and persist a Review from validated DTO data.
     *
     * Behavior:
     * - Stores optional email as null when empty string is provided
     * - New reviews are created as not approved (moderation required)
     * - Optionally binds the review to a specific MenuItem
     *
     * @param ReviewCreateRequest $dto Validated review data
     * @param MenuItem|null $menuItem Optional dish association
     * @return Review Persisted review entity
     */
    public function createReview(ReviewCreateRequest $dto, ?MenuItem $menuItem = null): Review
    {
        $review = (new Review())
            ->setName($dto->name)
            ->setEmail($dto->email !== '' ? $dto->email : null)
            ->setRating((int) $dto->rating)
            ->setComment($dto->comment)
            ->setIsApproved(false);

        if ($menuItem !== null) {
            $review->setMenuItem($menuItem);
        }

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        return $review;
    }

    /**
     * Persist a Review entity coming from legacy forms, ensuring moderation flag default.
     */
    public function createReviewFromEntity(Review $review): Review
    {
        if ($review->getIsApproved() === null) {
            $review->setIsApproved(false);
        }

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        return $review;
    }
}


