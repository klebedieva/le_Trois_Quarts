<?php

namespace App\Controller;

use App\DTO\ApiResponseDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\ValidationHelper;

/**
 * Abstract Base Controller for API Endpoints
 *
 * This abstract controller provides common functionality for all API controllers,
 * eliminating code duplication and ensuring consistent behavior across endpoints.
 *
 * Responsibilities:
 * - JSON data extraction from requests (with mass assignment protection support)
 * - DTO validation with automatic error response formatting
 * - CSRF token validation
 * - Standardized success/error response creation
 *
 * Design principles:
 * - DRY (Don't Repeat Yourself): Common patterns extracted to base class
 * - Single Responsibility: Each method handles one specific concern
 * - Consistent API responses: All endpoints use same response format
 * - Security by default: CSRF validation and mass assignment protection built-in
 *
 * Usage:
 * All API controllers should extend this class and use its protected methods
 * instead of duplicating validation and response logic.
 */
abstract class AbstractApiController extends AbstractController
{
    /**
     * Constructor
     *
     * Injects dependencies required for API controller operations:
     * - ValidatorInterface: Validates DTO data using Symfony Validator
     * - ValidationHelper: Centralizes validation message extraction and DTO mapping
     *
     * Note: Child controllers should call parent::__construct() if they override constructor.
     *
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     */
    public function __construct(
        protected ValidatorInterface $validator,
        protected ValidationHelper $validationHelper
    ) {}

    /**
     * Get JSON data from request with mass assignment protection
     *
     * This method retrieves JSON data from the request, prioritizing filtered data
     * from JsonFieldWhitelistSubscriber if available. This ensures mass assignment
     * protection works effectively by only allowing whitelisted fields.
     *
     * Priority order:
     * 1. Filtered data from JsonFieldWhitelistSubscriber (if request was processed by subscriber)
     * 2. Parsed raw JSON content (fallback for non-API endpoints or if subscriber didn't process)
     *
     * If data cannot be parsed or is not an array, returns an error response.
     *
     * Side effects:
     * - None (read-only operation)
     *
     * @param Request $request HTTP request containing JSON data
     * @return array|JsonResponse Parsed JSON data as array, or JsonResponse with error if parsing failed
     */
    protected function getJsonDataFromRequest(Request $request): array|JsonResponse
    {
        // Priority 1: Use filtered data from JsonFieldWhitelistSubscriber if available
        // This ensures only authorized fields reach the controller (mass assignment protection)
        // The subscriber filters out unauthorized fields before the request reaches here
        $data = $request->attributes->get('filtered_json_data');
        
        if ($data !== null) {
            // Filtered data is already an array (subscriber ensures this)
            return $data;
        }

        // Priority 2: Fallback to parsing raw content if subscriber didn't process it
        // This should rarely happen for API endpoints (subscriber processes /api/* routes),
        // but provides backward compatibility for non-API endpoints or edge cases
        $rawContent = $request->getContent();
        $data = json_decode($rawContent, true);
        
        // Validate that parsed data is an array
        // If json_decode fails or returns non-array, return error response
        if (!is_array($data)) {
            return $this->errorResponse('JSON invalide', 400);
        }
        
        return $data;
    }

    /**
     * Validate DTO data and return DTO or error response
     *
     * This method performs complete DTO validation workflow:
     * 1. Maps array data to DTO instance using ValidationHelper
     * 2. Validates DTO using Symfony Validator
     * 3. Returns validated DTO if validation passes
     * 4. Returns error response with validation messages if validation fails
     *
     * This eliminates repetitive validation code in controllers:
     * - No need to manually call mapArrayToDto, validate, extractViolationMessages
     * - No need to manually create error responses for validation failures
     * - Consistent error format across all endpoints
     *
     * Side effects:
     * - None (read-only validation, does not modify data)
     *
     * @param array $data Array data to map to DTO (typically from getJsonDataFromRequest)
     * @param string $dtoClass Fully qualified DTO class name (e.g., OrderCreateRequest::class)
     * @return mixed Validated DTO instance if validation passes, or JsonResponse with errors if validation fails
     */
    protected function validateDto(array $data, string $dtoClass): mixed
    {
        // Map JSON payload to DTO using helper service
        // The ValidationHelper automatically handles type conversion based on DTO property types
        // This eliminates repetitive manual mapping code like: isset($data['name']) ? trim((string)$data['name']) : null
        // Note: If data came from JsonFieldWhitelistSubscriber, it's already filtered
        // Otherwise, DTO validation will handle any unauthorized fields
        $dto = $this->validationHelper->mapArrayToDto($data, $dtoClass);
        
        // Validate DTO using Symfony Validator
        // This checks all validation constraints defined in the DTO class
        $violations = $this->validator->validate($dto);
        
        // If validation fails, extract error messages and return error response
        if (count($violations) > 0) {
            $errors = $this->validationHelper->extractViolationMessages($violations);
            return $this->errorResponse('Erreur de validation', 422, $errors);
        }
        
        // Validation passed, return validated DTO
        return $dto;
    }

    /**
     * Validate CSRF token from request headers
     *
     * Validates CSRF token to protect state-changing operations (POST, PUT, DELETE, PATCH)
     * from Cross-Site Request Forgery attacks.
     *
     * Token can be provided in:
     * - X-CSRF-Token header (preferred for API requests)
     * - _token form field (for traditional form submissions)
     *
     * If token is missing or invalid, returns error response.
     * If token is valid, returns null (allowing request to proceed).
     *
     * Side effects:
     * - None (read-only validation)
     *
     * @param Request $request HTTP request containing CSRF token
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF token manager
     * @return JsonResponse|null Error response if token is invalid, null if valid
     */
    protected function validateCsrfToken(Request $request, CsrfTokenManagerInterface $csrfTokenManager): ?JsonResponse
    {
        // Try to get token from header first (preferred for API requests)
        $csrfToken = $request->headers->get('X-CSRF-Token');
        
        // Fallback to form field if header not present (for traditional form submissions)
        if (!$csrfToken) {
            $csrfToken = $request->request->get('_token');
        }
        
        // Validate token using Symfony's CSRF token manager
        // Token name 'submit' is standard for form submissions
        if (!$csrfToken || !$csrfTokenManager->isTokenValid(new CsrfToken('submit', $csrfToken))) {
            return $this->errorResponse('Token CSRF invalide', 403);
        }
        
        // Token is valid, return null to allow request to proceed
        return null;
    }

    /**
     * Validate DTO for XSS (Cross-Site Scripting) attempts
     *
     * This method performs XSS validation on specified fields of a DTO object.
     * It checks for malicious script injection attempts in user input fields.
     *
     * This is a security layer that provides defense in depth:
     * - Even if basic DTO validation passes, XSS validation ensures no malicious content
     * - This double-check prevents XSS attacks that might bypass initial validation
     * - XSS attempts are treated as security violations, not validation errors
     *
     * Usage pattern:
     * ```php
     * $xssError = $this->validateXss($dto, ['field1', 'field2']);
     * if ($xssError !== null) {
     *     return $xssError; // XSS detected, return error response
     * }
     * // Continue processing if no XSS detected
     * ```
     *
     * Side effects:
     * - None (read-only validation, does not modify data)
     *
     * @param mixed $dto DTO object to validate (typically from validateDto method)
     * @param array $fields Array of field names to check for XSS attempts
     * @return JsonResponse|null Error response with 400 status if XSS detected, null if validation passes
     */
    protected function validateXss(mixed $dto, array $fields): ?JsonResponse
    {
        // Validate DTO fields for XSS attempts using ValidationHelper
        // This checks for malicious script injection patterns in user input
        // The ValidationHelper uses pattern matching to detect common XSS attack vectors
        $xssErrors = $this->validationHelper->validateXssAttempts($dto, $fields);
        
        // If XSS attempts detected, return error response
        // XSS attempts are treated as security violations (400 Bad Request)
        // This is different from validation errors (422 Unprocessable Entity)
        // Security violations indicate malicious intent, not user mistakes
        if (!empty($xssErrors)) {
            return $this->errorResponse('Données invalides détectées', 400, $xssErrors);
        }
        
        // No XSS detected, return null to allow request to proceed
        return null;
    }

    /**
     * Create standardized error response
     *
     * Creates a consistent error response format using ApiResponseDTO.
     * All API endpoints should use this method for error responses to ensure
     * consistent structure across the entire API.
     *
     * Response format:
     * {
     *   "success": false,
     *   "message": "Error message",
     *   "errors": ["Error 1", "Error 2"] // Optional, only if $errors provided
     * }
     *
     * Side effects:
     * - None (creates response object, does not send it)
     *
     * @param string $message Error message to display to client
     * @param int $status HTTP status code (default: 400 Bad Request)
     * @param array|null $errors Optional array of detailed error messages (for validation errors)
     * @return JsonResponse Error response with standardized format
     */
    protected function errorResponse(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $response = new ApiResponseDTO(
            success: false,
            message: $message,
            errors: $errors
        );
        
        return $this->json($response->toArray(), $status);
    }

    /**
     * Create standardized success response
     *
     * Creates a consistent success response format using ApiResponseDTO.
     * All API endpoints should use this method for success responses to ensure
     * consistent structure across the entire API.
     *
     * Response format:
     * {
     *   "success": true,
     *   "message": "Success message", // Optional
     *   "data": {...}, // Optional, only if $data provided
     *   "count": 123 // Optional, only if count provided
     * }
     *
     * Side effects:
     * - None (creates response object, does not send it)
     *
     * @param mixed $data Optional data to include in response (can be array, object, or null)
     * @param string|null $message Optional success message
     * @param int $status HTTP status code (default: 200 OK, use 201 for creation)
     * @return JsonResponse Success response with standardized format
     */
    protected function successResponse(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $response = new ApiResponseDTO(
            success: true,
            message: $message,
            data: $data
        );
        
        return $this->json($response->toArray(), $status);
    }
}

