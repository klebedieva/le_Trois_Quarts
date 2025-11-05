<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\Reservation;
use App\Form\ReviewType;
use App\Form\ReservationType;
use App\Repository\ReviewRepository;
use App\Repository\GalleryImageRepository;
use App\Service\InputSanitizer;
use App\Service\SymfonyEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Psr\Log\LoggerInterface;
use App\Service\TableAvailabilityService;

/**
 * Public site pages: home, menu, gallery, reservation, reviews.
 *
 * Notes:
 * - Uses repositories/services only for read operations and simple form handling.
 * - Security-sensitive endpoints (AJAX reservation) include CSRF checks and input sanitization.
 */
class HomeController extends AbstractController
{
    public function __construct(
        private SymfonyEmailService $emailService,
        private TableAvailabilityService $availability,
        private LoggerInterface $logger
    ) {}

    #[Route('/', name: 'app_home')]
    public function index(ReviewRepository $reviewRepository, GalleryImageRepository $galleryRepository): Response
    {
        // Get approved reviews for display on homepage
        $reviews = $reviewRepository->findApprovedReviews();
        
        // Get latest 6 gallery images for homepage
        $galleryImages = $galleryRepository->findLatestForHomepage(6);
        
        return $this->render('home/homepage.html.twig', [
            'reviews' => $reviews,
            'galleryImages' => $galleryImages
        ]);
    }

    

    #[Route('/menu', name: 'app_menu')]
    public function menu(): Response
    {
        return $this->render('pages/menu.html.twig');
    }

    #[Route('/gallery', name: 'app_gallery')]
    public function gallery(EntityManagerInterface $entityManager): Response
    {
        $galleryRepository = $entityManager->getRepository(\App\Entity\GalleryImage::class);
        
        // Get all active images
        $images = $galleryRepository->findAllActive();
        
        // Get counts by category for filter display
        $categoryCounts = $galleryRepository->countByCategory();
        
        return $this->render('pages/gallery.html.twig', [
            'images' => $images,
            'categoryCounts' => $categoryCounts
        ]);
    }

    #[Route('/reservation', name: 'app_reservation', methods: ['GET','POST'])]
    public function reservation(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reservation = new Reservation();
        $reservationForm = $this->createForm(ReservationType::class, $reservation);
        
        $reservationForm->handleRequest($request);
        
        if ($reservationForm->isSubmitted() && $reservationForm->isValid()) {
            // Variant B: always accept the request on the public side.
            // Availability will be validated by an admin later when confirming the reservation.

            $entityManager->persist($reservation);
            try {
                $entityManager->flush();
                $this->emailService->sendReservationNotificationToAdmin($reservation);
            } catch (\Throwable $e) {
                $this->logger->error('Error sending reservation notification to admin: {error}', ['error' => $e->getMessage()]);
            }

            $this->addFlash('success', 'Your reservation request has been accepted!');
            return $this->redirectToRoute('app_reservation');
        }
        
        return $this->render('pages/reservation.html.twig', [
            'reservationForm' => $reservationForm->createView()
        ]);
    }

    #[Route('/reservation-ajax', name: 'app_reservation_ajax', methods: ['POST'])]
    public function reservationAjax(Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        // CSRF Protection
        $csrfToken = $request->request->get('_token') ?: $request->headers->get('X-CSRF-Token');
        
        if (!$csrfToken || !$csrfTokenManager->isTokenValid(new CsrfToken('submit', $csrfToken))) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Token CSRF invalide');
            return $this->json($response->toArray(), 403);
        }
        
        return $this->handleReservationAjax($request, $entityManager);
    }
    
    private function handleReservationAjax(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Check if it's an AJAX request
        if (!$request->isXmlHttpRequest()) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Requête invalide');
            return $this->json($response->toArray(), 400);
        }
        
        try {
            // Get form data directly from request and sanitize
            $firstName = InputSanitizer::sanitize($request->request->get('firstName', ''));
            $lastName = InputSanitizer::sanitize($request->request->get('lastName', ''));
            $email = InputSanitizer::sanitize($request->request->get('email', ''));
            $phone = InputSanitizer::sanitize($request->request->get('phone', ''));
            $date = $request->request->get('date', '');
            $time = $request->request->get('time', '');
            $guests = $request->request->get('guests', '');
            $message = InputSanitizer::sanitize($request->request->get('message', ''));
            
            // Basic validation
            $errors = [];
            
            // XSS check
            if (InputSanitizer::containsXssAttempt($firstName)) {
                $errors[] = 'Le prénom contient des éléments non autorisés';
            }
            if (InputSanitizer::containsXssAttempt($lastName)) {
                $errors[] = 'Le nom contient des éléments non autorisés';
            }
            if (InputSanitizer::containsXssAttempt($email)) {
                $errors[] = 'L\'email contient des éléments non autorisés';
            }
            if (InputSanitizer::containsXssAttempt($phone)) {
                $errors[] = 'Le numéro de téléphone contient des éléments non autorisés';
            }
            if (InputSanitizer::containsXssAttempt($message)) {
                $errors[] = 'Le message contient des éléments non autorisés';
            }
            
            if (empty($firstName) || strlen($firstName) < 2) {
                $errors[] = 'Le prénom est requis';
            }
            
            if (empty($lastName) || strlen($lastName) < 2) {
                $errors[] = 'Le nom est requis';
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'L\'email est requis et doit être valide';
            }
            
            if (empty($phone) || strlen($phone) < 10) {
                $errors[] = 'Le numéro de téléphone est requis';
            }
            
            if (empty($date)) {
                $errors[] = 'La date est requise';
            } else {
                // Check if date is not in the past
                $selectedDate = new \DateTime($date);
                $today = new \DateTime();
                $today->setTime(0, 0, 0);
                
                if ($selectedDate < $today) {
                    $errors[] = 'La date ne peut pas être dans le passé';
                }
            }
            
            if (empty($time)) {
                $errors[] = 'L\'heure est requise';
            } else if (!empty($date)) {
                // Check if time is not in the past for today
                $selectedDate = new \DateTime($date);
                $today = new \DateTime();
                
                if ($selectedDate->format('Y-m-d') === $today->format('Y-m-d')) {
                    $selectedDateTime = new \DateTime($date . ' ' . $time);
                    
                    if ($selectedDateTime <= $today) {
                        $errors[] = 'L\'heure ne peut pas être dans le passé';
                    }
                }
            }
            
            if (empty($guests) || $guests < 1) {
                $errors[] = 'Le nombre de personnes est requis';
            }
            
            if (!empty($errors)) {
                $response = new \App\DTO\ApiResponseDTO(
                    success: false,
                    message: 'Erreur de validation. Veuillez vérifier vos données.',
                    errors: $errors
                );
                return $this->json($response->toArray(), 400);
            }
            
            // Variant B: do not block on availability in the public endpoint.
            // Admin will check availability and confirm later.

            // Create and save reservation
            $reservation = new Reservation();
            $reservation->setFirstName($firstName);
            $reservation->setLastName($lastName);
            $reservation->setEmail($email);
            $reservation->setPhone($phone);
            $reservation->setDate(new \DateTime($date));
            $reservation->setTime($time);
            $reservation->setGuests((int)$guests);
            $reservation->setMessage($message ?: null);
            $reservation->setStatus('pending');
            $reservation->setIsConfirmed(false);
            
            $entityManager->persist($reservation);
            $entityManager->flush();

            // Send notification to admin
            try {
                $this->emailService->sendReservationNotificationToAdmin($reservation);
            } catch (\Exception $e) {
                // Log error but don't prevent saving
                $this->logger->error('Error sending reservation notification to admin: {error}', ['error' => $e->getMessage()]);
            }
            
            $response = new \App\DTO\ApiResponseDTO(
                success: true,
                message: 'Votre réservation a été enregistrée. Nous vous contacterons pour confirmation.'
            );
            return $this->json($response->toArray());
            
        } catch (\Exception $e) {
            
            $response = new \App\DTO\ApiResponseDTO(
                success: false,
                message: 'Une erreur est survenue lors de l\'enregistrement de votre réservation.'
            );
            return $this->json($response->toArray(), 500);
        }
    }

    #[Route('/reviews', name: 'app_reviews')]
    public function reviews(Request $request, ReviewRepository $reviewRepository, EntityManagerInterface $entityManager): Response
    {
        // Get only approved reviews for display on reviews page
        $reviews = $reviewRepository->findApprovedOrderedByDate();
        
        // Create review form
        $review = new Review();
        $reviewForm = $this->createForm(ReviewType::class, $review);
        
        $reviewForm->handleRequest($request);
        
        if ($reviewForm->isSubmitted() && $reviewForm->isValid()) {
            // By default review is not approved (requires moderation)
            $review->setIsApproved(false);
            
            $entityManager->persist($review);
            $entityManager->flush();
            
            $this->addFlash('success', 'Merci pour votre avis ! Il sera publié après modération.');
            
            return $this->redirectToRoute('app_reviews');
        }
        
        return $this->render('pages/reviews.html.twig', [
            'reviews' => $reviews,
            'reviewForm' => $reviewForm->createView()
        ]);
    }

    #[Route('/404', name: 'app_404')]
    public function notFound(): Response
    {
        throw $this->createNotFoundException('Page not found');
    }
}
