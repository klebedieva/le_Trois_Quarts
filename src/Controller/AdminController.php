<?php

namespace App\Controller;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Admin Review Management Controller
 * 
 * Provides custom admin endpoints for review moderation:
 * - Display all reviews (for moderation)
 * - Approve reviews
 * - Reject reviews
 * - Delete reviews
 * 
 * Note: Most admin functionality is handled by EasyAdmin CRUD controllers.
 * This controller provides additional custom actions for review moderation workflow.
 */
#[Route('/admin')]
class AdminController extends AbstractController
{
    /**
     * Display all reviews for moderation
     * 
     * Shows all reviews (approved and pending) ordered by creation date.
     * Used by admins to review and moderate user-submitted reviews.
     * 
     * @param ReviewRepository $reviewRepository Review repository for fetching reviews
     * @return Response Rendered reviews moderation page
     */
    #[Route('/reviews', name: 'app_admin_reviews')]
    public function reviews(ReviewRepository $reviewRepository): Response
    {
        // Get all reviews ordered by date (newest first) for moderation
        $reviews = $reviewRepository->findAllOrderedByDate();
        
        return $this->render('admin/reviews.html.twig', [
            'reviews' => $reviews
        ]);
    }

    /**
     * Approve a review
     * 
     * Sets the review's isApproved flag to true, making it visible on the public site.
     * 
     * @param Review $review Review entity (resolved from route parameter)
     * @param EntityManagerInterface $entityManager Entity manager for persisting changes
     * @return Response Redirect to reviews list with success message
     */
    #[Route('/reviews/{id}/approve', name: 'app_admin_review_approve', methods: ['POST'])]
    public function approveReview(Review $review, EntityManagerInterface $entityManager): Response
    {
        // Mark review as approved
        $review->setIsApproved(true);
        $entityManager->flush();
        
        $this->addFlash('success', 'Avis approuvé avec succès !');
        
        return $this->redirectToRoute('app_admin_reviews');
    }

    /**
     * Reject a review
     * 
     * Sets the review's isApproved flag to false, hiding it from public display.
     * The review remains in the database but won't be shown to users.
     * 
     * @param Review $review Review entity (resolved from route parameter)
     * @param EntityManagerInterface $entityManager Entity manager for persisting changes
     * @return Response Redirect to reviews list with success message
     */
    #[Route('/reviews/{id}/reject', name: 'app_admin_review_reject', methods: ['POST'])]
    public function rejectReview(Review $review, EntityManagerInterface $entityManager): Response
    {
        // Mark review as rejected (not approved)
        $review->setIsApproved(false);
        $entityManager->flush();
        
        $this->addFlash('success', 'Avis rejeté avec succès !');
        
        return $this->redirectToRoute('app_admin_reviews');
    }

    /**
     * Delete a review permanently
     * 
     * Completely removes the review from the database.
     * This action cannot be undone.
     * 
     * @param Review $review Review entity (resolved from route parameter)
     * @param EntityManagerInterface $entityManager Entity manager for deletion
     * @return Response Redirect to reviews list with success message
     */
    #[Route('/reviews/{id}/delete', name: 'app_admin_review_delete', methods: ['POST'])]
    public function deleteReview(Review $review, EntityManagerInterface $entityManager): Response
    {
        // Permanently delete the review from database
        $entityManager->remove($review);
        $entityManager->flush();
        
        $this->addFlash('success', 'Avis supprimé avec succès !');
        
        return $this->redirectToRoute('app_admin_reviews');
    }

}
