<?php

namespace App\Controller;

use App\DTO\ContactCreateRequest;
use App\Entity\ContactMessage;
use App\Form\ContactMessageType;
use App\Service\InputSanitizer;
use App\Service\SymfonyEmailService;
use App\Service\ValidationHelper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Contact Form Controller
 * 
 * Handles contact form submissions:
 * - Display contact form (GET)
 * - Process form submission (POST)
 * - AJAX form submission endpoint
 * 
 * Architecture:
 * - Extends AbstractApiController for common API functionality (CSRF validation, responses)
 * - Uses ContactService for business logic (contact message creation, persistence)
 * - This follows Single Responsibility Principle: controllers don't call persist()/flush() directly
 * 
 * Note: This controller handles both legacy forms (form-encoded) and AJAX endpoints.
 * For AJAX endpoints, it uses base class methods for CSRF validation and response formatting.
 * 
 * All user input is sanitized to prevent XSS attacks before saving to database.
 * Email notifications are sent to admin (non-blocking - failures are logged).
 */
class ContactController extends AbstractApiController
{
    /**
     * Constructor for ContactController
     *
     * Injects dependencies required for contact form handling:
     * - EntityManagerInterface: Used only for legacy form handling (not in AJAX endpoint)
     * - SymfonyEmailService: Sends email notifications to admin
     * - LoggerInterface: Logs errors for debugging
     * - ValidatorInterface and ValidationHelper: Passed to parent for DTO validation
     * - ContactService: Encapsulates contact message creation and persistence (business logic)
     *
     * @param EntityManagerInterface $em Entity manager (legacy form support)
     * @param SymfonyEmailService $emailService Email service for notifications
     * @param LoggerInterface $logger Logger for error tracking
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     * @param \App\Service\ContactService $contactService Service for creating contact messages
     */
    public function __construct(
        private EntityManagerInterface $em,
        private SymfonyEmailService $emailService,
        private LoggerInterface $logger,
        ValidatorInterface $validator,
        ValidationHelper $validationHelper,
        private \App\Service\ContactService $contactService
    ) {
        parent::__construct($validator, $validationHelper);
    }

    /**
     * Display contact form and handle form submission
     * 
     * GET: Renders the contact form page.
     * POST: Processes form data, sanitizes input, saves to database, and sends email notification.
     * 
     * @param Request $request HTTP request containing form data
     * @return Response Rendered contact form or redirect after submission
     */
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        $msg = new ContactMessage();
        $form = $this->createForm(ContactMessageType::class, $msg);
        $form->handleRequest($request);

        // Process form submission if valid
        if ($form->isSubmitted() && $form->isValid()) {
            // Sanitize all user input to prevent XSS attacks before persisting
            $msg->setFirstName(InputSanitizer::sanitize($msg->getFirstName()));
            $msg->setLastName(InputSanitizer::sanitize($msg->getLastName()));
            $msg->setEmail(InputSanitizer::sanitize($msg->getEmail()));
            if ($msg->getPhone()) {
                $msg->setPhone(InputSanitizer::sanitize($msg->getPhone()));
            }
            $msg->setMessage(InputSanitizer::sanitize($msg->getMessage()));
            
            // Delegate persistence to service to keep controller thin
            $this->contactService->createContactMessageFromEntity($msg);

            // Send email notification to admin (non-blocking - failures are logged but don't break the flow)
            // This is a non-blocking operation (email sending)
            // We catch exceptions here because email failure should not break message storage
            // This is different from main business logic exceptions, which are handled by ApiExceptionSubscriber
            try {
                $this->emailService->sendNotificationToAdmin(
                    $msg->getEmail(),
                    $msg->getFirstName() . ' ' . $msg->getLastName(),
                    $msg->getSubject(),
                    $msg->getMessage()
                );
            } catch (\Exception $e) {
                // Log error but don't prevent saving - email failure shouldn't block message storage
                // Note: This catch is intentional - we want to handle email failures gracefully
                // without affecting the main message storage flow
                $this->logger->error('Error sending contact notification: {error}', ['error' => $e->getMessage()]);
            }

            // Show success message and redirect to prevent duplicate submissions
            $this->addFlash('success', 'Merci! Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.');
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('pages/contact.html.twig', [
            'contactForm' => $form->createView(),
            'seo_title' => 'Contact | Le Trois Quarts Marseille',
            'seo_description' => 'Contactez Le Trois Quarts pour toute question, réservation de groupe ou demande d’information. Nous vous répondons rapidement.',
            'seo_og_description' => 'Échangez avec l’équipe du Trois Quarts : téléphone, email et formulaire de contact.',
        ]);
    }

    /**
     * AJAX endpoint for contact form submission
     * 
     * Handles contact form submissions via AJAX requests.
     * Validates CSRF token before processing.
     * 
     * @param Request $request HTTP request containing form data
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF token manager
     * @return JsonResponse Success/error response
     */
    #[Route('/contact-ajax', name: 'app_contact_ajax', methods: ['POST'])]
    public function contactAjax(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        // Validate CSRF token to prevent cross-site request forgery attacks
        // Uses base class method from AbstractApiController
        $csrfError = $this->validateCsrfToken($request, $csrfTokenManager);
        if ($csrfError !== null) {
            return $csrfError;
        }
        
        return $this->handleContactAjax($request);
    }
    
    /**
     * Process AJAX contact form submission
     * 
     * Validates input, sanitizes data, saves to database, and sends email notification.
     * Includes XSS detection and comprehensive validation.
     * 
     * @param Request $request HTTP request containing form data
     * @return JsonResponse Success/error response with validation errors if any
     */
    private function handleContactAjax(Request $request): JsonResponse
    {
        // Ensure this is an AJAX request
        if (!$request->isXmlHttpRequest()) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Requête invalide', 400);
        }
        
        try {
            // Extract form data from request
            $firstName = InputSanitizer::sanitize($request->request->get('firstName', ''));
            $lastName = InputSanitizer::sanitize($request->request->get('lastName', ''));
            $email = InputSanitizer::sanitize($request->request->get('email', ''));
            $phone = InputSanitizer::sanitize($request->request->get('phone', ''));
            $subject = $request->request->get('subject', ''); // Subject doesn't need sanitization as it's from predefined choices
            $message = InputSanitizer::sanitize($request->request->get('message', ''));
            $consent = (bool)$request->request->get('consent', false);

            // Map to DTO and validate via Symfony Validator
            $dto = new ContactCreateRequest();
            $dto->firstName = $firstName;
            $dto->lastName = $lastName;
            $dto->email = $email;
            $dto->phone = $phone ?: null;
            $dto->subject = $subject;
            $dto->message = $message;
            $dto->consent = $consent;

            // Validate DTO using Symfony Validator
            // This checks all validation constraints defined in ContactCreateRequest DTO
            $violations = $this->validator->validate($dto);
            if (count($violations) > 0) {
                // Extract validation error messages from violations
                $errors = $this->validationHelper->extractViolationMessages($violations);
                // Uses base class method from AbstractApiController
                return $this->errorResponse('Erreur de validation. Veuillez vérifier vos données.', 422, $errors);
            }

            // XSS validation for user input fields (defense in depth)
            // Perform XSS validation after DTO validation passes to ensure no malicious content
            // Uses base class method from AbstractApiController
            $xssError = $this->validateXss($dto, ['firstName', 'lastName', 'email', 'phone', 'message']);
            if ($xssError !== null) {
                // XSS detected, return error response
                return $xssError;
            }
            
            // Delegate business logic to ContactService (no direct persist/flush in controller)
            // This follows Single Responsibility Principle: controller handles HTTP, service handles domain logic
            // The service encapsulates entity creation, initialization, and persistence
            // This makes the code more testable (can mock service) and reusable (service can be called from elsewhere)
            $contactMessage = $this->contactService->createContactMessage($dto);

            // Send notification to admin
            // This is a non-blocking operation (email sending)
            // We catch exceptions here because email failure should not break message storage
            // This is different from main business logic exceptions, which are handled by ApiExceptionSubscriber
            try {
                $this->emailService->sendNotificationToAdmin(
                    $contactMessage->getEmail(),
                    $contactMessage->getFirstName() . ' ' . $contactMessage->getLastName(),
                    $contactMessage->getSubject(),
                    $contactMessage->getMessage()
                );
            } catch (\Exception $e) {
                // Note: This catch is intentional - we want to handle email failures gracefully
                // without affecting the main message storage flow
                $this->logger->error('Error sending contact notification: {error}', ['error' => $e->getMessage()]);
            }
            
            // Uses base class method from AbstractApiController
            return $this->successResponse(null, 'Merci! Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.', 201);
            
        } catch (\InvalidArgumentException $e) {
            // Handle validation/business logic errors
            // This should not happen after DTO validation, but serves as defense in depth
            // InvalidArgumentException is thrown by business logic when data is invalid
            // We catch this specifically to provide custom error message
            // Other exceptions are handled by ApiExceptionSubscriber
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Erreur de validation: ' . $e->getMessage(), 422);
        }
        // Note: All other exceptions are automatically handled by ApiExceptionSubscriber,
        // which provides centralized error handling and consistent error response format
    }
}