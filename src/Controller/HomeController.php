<?php

namespace App\Controller;

use App\DTO\ReservationCreateRequest;
use App\Entity\Review;
use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use App\Form\ReviewType;
use App\Form\ReservationType;
use App\Repository\ReviewRepository;
use App\Repository\GalleryImageRepository;
use App\Service\InputSanitizer;
use App\Service\SymfonyEmailService;
use App\Service\TableAvailabilityService;
use App\Service\ValidationHelper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Public site pages: home, menu, gallery, reservation, reviews.
 *
 * Architecture:
 * - Controller is thin: handles request/response, validation, and delegates to services
 * - Business logic (entity creation, persistence) is encapsulated in ReservationService
 * - This follows Single Responsibility Principle: controllers don't call persist()/flush() directly
 *
 * Notes:
 * - Uses repositories/services only for read operations and simple form handling.
 * - Security-sensitive endpoints (AJAX reservation) include CSRF checks and input sanitization.
 */
class HomeController extends AbstractController
{
    /**
     * Constructor for HomeController
     *
     * Injects dependencies required for public pages and reservation handling:
     * - SymfonyEmailService: Sends email notifications for reservations
     * - TableAvailabilityService: Checks table availability for reservations
     * - LoggerInterface: Logs errors for debugging
     * - ValidatorInterface: Validates DTO data using Symfony Validator
     * - ValidationHelper: Centralizes validation message extraction and DTO mapping
     * - ReservationService: Encapsulates reservation creation and persistence (business logic)
     *
     * @param SymfonyEmailService $emailService Email service for notifications
     * @param TableAvailabilityService $availability Service for checking table availability
     * @param LoggerInterface $logger Logger for error tracking
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     * @param \App\Service\ReservationService $reservationService Service for creating reservations
     * @param \App\Service\ReviewService $reviewService Service for handling reviews
     */
    public function __construct(
        private SymfonyEmailService $emailService,
        private TableAvailabilityService $availability,
        private LoggerInterface $logger,
        private ValidatorInterface $validator,
        private ValidationHelper $validationHelper,
        private \App\Service\ReservationService $reservationService,
        private \App\Service\ReviewService $reviewService
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

    /**
     * Use GalleryImageRepository instead of EntityManager::getRepository() to
     * reduce coupling with Doctrine internals and make the action more testable.
     */
    #[Route('/gallery', name: 'app_gallery')]
    public function gallery(GalleryImageRepository $galleryRepository): Response
    {
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

            try {
                // Delegate persistence to service to keep controller thin
                $this->reservationService->createReservationFromEntity($reservation);
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

            // Map to DTO and validate via Symfony Validator
            $dto = new ReservationCreateRequest();
            $dto->firstName = $firstName;
            $dto->lastName = $lastName;
            $dto->email = $email;
            $dto->phone = $phone;
            $dto->date = $date;
            $dto->time = $time;
            $dto->guests = !empty($guests) ? (int)$guests : null;
            $dto->message = $message ?: null;

            // Validate DTO using Symfony Validator
            // This checks all validation constraints defined in ReservationCreateRequest DTO
            $violations = $this->validator->validate($dto);
            if (count($violations) > 0) {
                // Extract validation error messages from violations
                $errors = $this->validationHelper->extractViolationMessages($violations);
                
                // Also check for XSS attempts (additional security layer)
                // This provides defense in depth - even if basic validation passes, we check for malicious content
                $xssErrors = $this->validationHelper->validateXssAttempts($dto, ['firstName', 'lastName', 'email', 'phone', 'message']);
                
                // Merge validation errors and XSS errors into single array
                $allErrors = array_merge($errors, $xssErrors);
                $response = new \App\DTO\ApiResponseDTO(
                    success: false,
                    message: 'Erreur de validation. Veuillez vérifier vos données.',
                    errors: $allErrors
                );
                return $this->json($response->toArray(), 422);
            }

            // Additional validation: check if date/time are not in the past
            // This business logic validation cannot be easily expressed in DTO constraints,
            // so we perform it here after DTO validation
            $validationErrors = [];
            if (!empty($dto->date)) {
                $selectedDate = new \DateTime($dto->date);
                $today = new \DateTime();
                $today->setTime(0, 0, 0); // Set to midnight for date comparison
                
                // Check if selected date is in the past
                if ($selectedDate < $today) {
                    $validationErrors[] = 'La date ne peut pas être dans le passé';
                }
                
                // Check if time is not in the past for today's date
                // If reservation is for today, the time must be in the future
                if (!empty($dto->time) && $selectedDate->format('Y-m-d') === $today->format('Y-m-d')) {
                    $selectedDateTime = new \DateTime($dto->date . ' ' . $dto->time);
                    if ($selectedDateTime <= new \DateTime()) {
                        $validationErrors[] = 'L\'heure ne peut pas être dans le passé';
                    }
                }
            }

            // Additional XSS check after validation (defense in depth)
            // Perform XSS validation again after DTO validation passes to ensure no malicious content
            // This double-check prevents XSS attacks that might bypass initial validation
            $xssErrors = $this->validationHelper->validateXssAttempts($dto, ['firstName', 'lastName', 'email', 'phone', 'message']);
            
            // Merge date/time validation errors and XSS errors
            $allErrors = array_merge($validationErrors, $xssErrors);
            if (!empty($allErrors)) {
                // Return 422 for validation errors, 400 for security violations (XSS)
                $response = new \App\DTO\ApiResponseDTO(
                    success: false,
                    message: !empty($validationErrors) ? 'Erreur de validation' : 'Données invalides détectées',
                    errors: $allErrors
                );
                return $this->json($response->toArray(), !empty($validationErrors) ? 422 : 400);
            }
            
            // Variant B: do not block on availability in the public endpoint.
            // Admin will check availability and confirm later.

            // Delegate creation to ReservationService (controller stays thin)
            // This follows Single Responsibility Principle: controller handles HTTP, service handles domain logic
            // The service encapsulates entity creation, status initialization (PENDING), and persistence
            // This makes the code more testable (can mock service) and reusable (service can be called from elsewhere)
            $reservation = $this->reservationService->createReservation($dto);

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
            
        } catch (\InvalidArgumentException $e) {
            // Handle validation/business logic errors
            // This should not happen after DTO validation, but serves as defense in depth
            // InvalidArgumentException is thrown by business logic when data is invalid
            $response = new \App\DTO\ApiResponseDTO(
                success: false,
                message: 'Erreur de validation: ' . $e->getMessage()
            );
            return $this->json($response->toArray(), 422);
        } catch (\Exception $e) {
            // Log unexpected errors for debugging and monitoring
            // This catches any unexpected exceptions that might occur during processing
            // We log full error details for developers, but only return generic message to user
            $this->logger->error('Unexpected error in reservation submission', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return generic error message to user (don't expose internal errors for security)
            // Exposing internal error details could help attackers understand system internals
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

            // Delegate persistence to service to keep controller thin
            $this->reviewService->createReviewFromEntity($review);
            
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
