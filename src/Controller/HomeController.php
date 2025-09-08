<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Entity\Review;
use App\Form\ContactMessageType;
use App\Form\ReviewType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ReviewRepository $reviewRepository): Response
    {
        // Get approved reviews for display on homepage
        $reviews = $reviewRepository->findApprovedReviews();
        
        return $this->render('home/homepage.html.twig', [
            'reviews' => $reviews
        ]);
    }

    #[Route('/submit-review', name: 'app_submit_review', methods: ['POST'])]
    public function submitReview(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $review = new Review();
        $form = $this->createForm(ReviewType::class, $review, [
            'csrf_protection' => false
        ]);
        
        // Process JSON data
        $data = json_decode($request->getContent(), true);
        
        if ($data === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Données invalides'
            ], 400);
        }
        
        $form->submit($data);
        
        if ($form->isValid()) {
            // By default review is not approved (requires moderation)
            $review->setIsApproved(false);
            
            $entityManager->persist($review);
            $entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Merci pour votre avis ! Il sera publié après modération.'
            ]);
        }
        
        // Get validation errors by field
        $fieldErrors = [];
        foreach ($form->getErrors(true) as $error) {
            $fieldErrors[] = $error->getMessage();
        }
        
        // Get field-specific errors
        $errors = [];
        foreach ($form->all() as $child) {
            if ($child->getErrors()->count() > 0) {
                $fieldName = $child->getName();
                $fieldErrors = [];
                foreach ($child->getErrors() as $error) {
                    $fieldErrors[] = $error->getMessage();
                }
                $errors[$fieldName] = $fieldErrors;
            }
        }
        
        return new JsonResponse([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $fieldErrors,
            'fieldErrors' => $errors
        ], 400);
    }

    #[Route('/menu', name: 'app_menu')]
    public function menu(): Response
    {
        return $this->render('pages/menu.html.twig');
    }

    #[Route('/gallery', name: 'app_gallery')]
    public function gallery(): Response
    {
        return $this->render('pages/gallery.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contactMessage = new ContactMessage();
        $form = $this->createForm(ContactMessageType::class, $contactMessage);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($contactMessage);
            $entityManager->flush();
            
            $this->addFlash('success', 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.');
            
            return $this->redirectToRoute('app_contact');
        }
        
        return $this->render('pages/contact.html.twig', [
            'contactForm' => $form->createView()
        ]);
    }

    #[Route('/reservation', name: 'app_reservation')]
    public function reservation(): Response
    {
        return $this->render('pages/reservation.html.twig');
    }

    #[Route('/reviews', name: 'app_reviews')]
    public function reviews(): Response
    {
        return $this->render('pages/reviews.html.twig');
    }

    #[Route('/404', name: 'app_404')]
    public function notFound(): Response
    {
        return $this->render('pages/404.html.twig');
    }
}
