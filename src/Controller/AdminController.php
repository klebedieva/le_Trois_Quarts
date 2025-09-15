<?php

namespace App\Controller;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/reviews', name: 'app_admin_reviews')]
    public function reviews(ReviewRepository $reviewRepository): Response
    {
        $reviews = $reviewRepository->findAllOrderedByDate();
        
        return $this->render('admin/reviews.html.twig', [
            'reviews' => $reviews
        ]);
    }

    #[Route('/reviews/{id}/approve', name: 'app_admin_review_approve', methods: ['POST'])]
    public function approveReview(Review $review, EntityManagerInterface $entityManager): Response
    {
        $review->setIsApproved(true);
        $entityManager->flush();
        
        $this->addFlash('success', 'Avis approuvé avec succès !');
        
        return $this->redirectToRoute('app_admin_reviews');
    }

    #[Route('/reviews/{id}/reject', name: 'app_admin_review_reject', methods: ['POST'])]
    public function rejectReview(Review $review, EntityManagerInterface $entityManager): Response
    {
        $review->setIsApproved(false);
        $entityManager->flush();
        
        $this->addFlash('success', 'Avis rejeté avec succès !');
        
        return $this->redirectToRoute('app_admin_reviews');
    }

    #[Route('/reviews/{id}/delete', name: 'app_admin_review_delete', methods: ['POST'])]
    public function deleteReview(Review $review, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($review);
        $entityManager->flush();
        
        $this->addFlash('success', 'Avis supprimé avec succès !');
        
        return $this->redirectToRoute('app_admin_reviews');
    }

}
