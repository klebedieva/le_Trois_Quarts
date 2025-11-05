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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
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
 * All user input is sanitized to prevent XSS attacks before saving to database.
 * Email notifications are sent to admin (non-blocking - failures are logged).
 */
class ContactController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SymfonyEmailService $emailService,
        private LoggerInterface $logger,
        private ValidatorInterface $validator,
        private ValidationHelper $validationHelper
    ) {}

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
            
            // Persist contact message to database
            $this->em->persist($msg);
            $this->em->flush();

            // Send email notification to admin (non-blocking - failures are logged but don't break the flow)
            try {
                $this->emailService->sendNotificationToAdmin(
                    $msg->getEmail(),
                    $msg->getFirstName() . ' ' . $msg->getLastName(),
                    $msg->getSubject(),
                    $msg->getMessage()
                );
            } catch (\Exception $e) {
                // Log error but don't prevent saving - email failure shouldn't block message storage
                $this->logger->error('Error sending contact notification: {error}', ['error' => $e->getMessage()]);
            }

            // Show success message and redirect to prevent duplicate submissions
            $this->addFlash('success', 'Merci! Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.');
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('pages/contact.html.twig', [
            'contactForm' => $form->createView(),
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
        $csrfToken = $request->request->get('_token') ?: $request->headers->get('X-CSRF-Token');
        if (!$csrfToken || !$csrfTokenManager->isTokenValid(new CsrfToken('submit', $csrfToken))) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Token CSRF invalide');
            return $this->json($response->toArray(), 403);
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
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Requête invalide');
            return $this->json($response->toArray(), 400);
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

            // Additional XSS check after validation (defense in depth)
            // Perform XSS validation again after DTO validation passes to ensure no malicious content
            // This double-check prevents XSS attacks that might bypass initial validation
            $xssErrors = $this->validationHelper->validateXssAttempts($dto, ['firstName', 'lastName', 'email', 'phone', 'message']);
            
            if (!empty($xssErrors)) {
                // Return 400 Bad Request for security violations (not 422, as this is a security issue)
                $response = new \App\DTO\ApiResponseDTO(
                    success: false,
                    message: 'Données invalides détectées',
                    errors: $xssErrors
                );
                return $this->json($response->toArray(), 400);
            }
            
            // Create and save contact message
            $contactMessage = new ContactMessage();
            $contactMessage->setFirstName($dto->firstName);
            $contactMessage->setLastName($dto->lastName);
            $contactMessage->setEmail($dto->email);
            $contactMessage->setPhone($dto->phone);
            $contactMessage->setSubject($dto->subject);
            $contactMessage->setMessage($dto->message);
            $contactMessage->setConsent($dto->consent);
            
            $this->em->persist($contactMessage);
            $this->em->flush();

            // Send notification to admin
            try {
                $this->emailService->sendNotificationToAdmin(
                    $contactMessage->getEmail(),
                    $contactMessage->getFirstName() . ' ' . $contactMessage->getLastName(),
                    $contactMessage->getSubject(),
                    $contactMessage->getMessage()
                );
            } catch (\Exception $e) {
                $this->logger->error('Error sending contact notification: {error}', ['error' => $e->getMessage()]);
            }
            
            $response = new \App\DTO\ApiResponseDTO(success: true, message: 'Merci! Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.');
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
            $this->logger->error('Unexpected error in contact form submission', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return generic error message to user (don't expose internal errors for security)
            // Exposing internal error details could help attackers understand system internals
            $response = new \App\DTO\ApiResponseDTO(
                success: false,
                message: 'Une erreur est survenue lors de l\'envoi de votre message.'
            );
            return $this->json($response->toArray(), 500);
        }
    }
}