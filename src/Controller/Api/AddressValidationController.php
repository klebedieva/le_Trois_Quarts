<?php

namespace App\Controller\Api;

use App\Controller\AbstractApiController;
use App\DTO\AddressFullValidationRequest;
use App\DTO\AddressValidationRequest;
use App\Service\AddressValidationService;
use App\Service\ValidationHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

/**
 * Address Validation API Controller
 * 
 * Handles delivery address validation for order processing:
 * - Validate zip codes for delivery eligibility
 * - Validate full addresses for delivery
 * 
 * Architecture:
 * - Extends AbstractApiController for common API functionality (JSON parsing, DTO validation, responses)
 * - Uses AddressValidationService to check if addresses are within delivery radius
 * - Calculates distance from restaurant location
 */
#[Route('/api', name: 'api_')]
class AddressValidationController extends AbstractApiController
{
    /**
     * Constructor
     *
     * Injects dependencies required for address validation:
     * - AddressValidationService: Handles address validation logic (geocoding, distance calculation)
     * - ValidatorInterface and ValidationHelper: Passed to parent for DTO validation
     *
     * @param AddressValidationService $addressValidationService Service for address validation
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     */
    public function __construct(
        private AddressValidationService $addressValidationService,
        ValidatorInterface $validator,
        ValidationHelper $validationHelper
    ) {
        parent::__construct($validator, $validationHelper);
    }

    /**
     * Validate zip code for delivery eligibility
     * 
     * Checks if a French zip code is within the restaurant's delivery radius.
     * Returns validation result, distance, coordinates, and delivery availability status.
     * 
     * @param Request $request HTTP request containing zip code in JSON body
     * @return JsonResponse Validation result with distance and coordinates
     */
    #[Route('/validate-zip-code', name: 'validate_zip_code', methods: ['POST'])]
    /**
     * Validate zip code via DTO + Validator
     *
     * Accepts JSON with zipCode and validates it using AddressValidationRequest.
     * Returns 422 with validation errors on failure; 400 on invalid JSON.
     */
    #[OA\Post(
        path: '/api/validate-zip-code',
        summary: 'Validate zip code for delivery',
        description: 'Checks if a zip code is within the delivery radius',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'zipCode',
                        type: 'string',
                        example: '13001',
                        description: 'French zip code to validate'
                    )
                ],
                type: 'object'
            )
        ),
        tags: ['Delivery']
    )]
    #[OA\Response(
        response: 200,
        description: 'Validation result',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', nullable: true, example: 'Opération réussie'),
                new OA\Property(property: 'data', type: 'object'),
                new OA\Property(property: 'count', type: 'integer', nullable: true, example: 1),
                new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'), nullable: true)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid JSON',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'JSON invalide')
            ],
            example: ['success' => false, 'message' => 'JSON invalide']
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Erreur de validation'),
                new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
            ],
            example: ['success' => false, 'message' => 'Erreur de validation', 'errors' => ['Le code postal est invalide']]
        )
    )]
    #[OA\Tag(name: 'Delivery')]
    public function validateZipCode(Request $request): JsonResponse
    {
        try {
            // Get JSON data from request
            // Uses base class method from AbstractApiController
            // Returns array or JsonResponse (error if JSON invalid)
            $jsonResult = $this->getJsonDataFromRequest($request);
            if ($jsonResult instanceof JsonResponse) {
                // JSON parsing failed, return error response
                return $jsonResult;
            }
            $data = $jsonResult;

            // Map JSON payload to DTO and validate
            // Uses base class method from AbstractApiController
            // Returns DTO or JsonResponse (error if validation fails)
            $validationResult = $this->validateDto($data, AddressValidationRequest::class);
            if ($validationResult instanceof JsonResponse) {
                // Validation failed, return error response
                return $validationResult;
            }
            $dto = $validationResult;

            $zipCode = $dto->zipCode;

            // Validate zip code using service (checks distance, coordinates, etc.)
            $result = $this->addressValidationService->validateZipCodeForDelivery($zipCode);

            // Return validation result with all relevant information
            // Uses base class method from AbstractApiController
            return $this->successResponse([
                'valid' => $result['valid'],
                'error' => $result['error'],
                'distance' => $result['distance'],
                'coordinates' => $result['coordinates'] ?? null,
                'deliveryAvailable' => $result['valid']
            ], null, 200);

        } catch (\Exception $e) {
            // Return error response if validation fails
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Erreur lors de la validation du code postal', 500);
        }
    }

    /**
     * Validate full address for delivery eligibility
     * 
     * Checks if a complete address (with optional zip code) is within the restaurant's delivery radius.
     * Uses geocoding to resolve address coordinates and calculates distance.
     * 
     * @param Request $request HTTP request containing address and optional zip code in JSON body
     * @return JsonResponse Validation result with distance and coordinates
     */
    #[Route('/validate-address', name: 'validate_address', methods: ['POST'])]
    /**
     * Validate full address via DTO + Validator
     *
     * Accepts JSON with address and optional zipCode, validates with
     * AddressFullValidationRequest. Returns 422 with validation errors on failure;
     * 400 on invalid JSON. If zipCode is valid, it is used directly; otherwise the
     * service attempts to geocode the address.
     */
    #[OA\Post(
        path: '/api/validate-address',
        summary: 'Validate full address for delivery',
        description: 'Checks if a full address is within the delivery radius',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'address',
                        type: 'string',
                        example: '123 Rue de la République, Marseille',
                        description: 'Full address to validate'
                    ),
                    new OA\Property(
                        property: 'zipCode',
                        type: 'string',
                        example: '13001',
                        description: 'Optional zip code for additional validation'
                    )
                ],
                type: 'object'
            )
        ),
        tags: ['Delivery']
    )]
    #[OA\Response(
        response: 200,
        description: 'Validation result',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', nullable: true, example: 'Opération réussie'),
                new OA\Property(property: 'data', type: 'object'),
                new OA\Property(property: 'count', type: 'integer', nullable: true, example: 1),
                new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'), nullable: true)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid JSON',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'JSON invalide')
            ],
            example: ['success' => false, 'message' => 'JSON invalide']
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Erreur de validation'),
                new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
            ],
            example: ['success' => false, 'message' => 'Erreur de validation', 'errors' => ['L\'adresse est requise']]
        )
    )]
    #[OA\Tag(name: 'Delivery')]
    public function validateAddress(Request $request): JsonResponse
    {
        try {
            // Get JSON data from request
            // Uses base class method from AbstractApiController
            // Returns array or JsonResponse (error if JSON invalid)
            $jsonResult = $this->getJsonDataFromRequest($request);
            if ($jsonResult instanceof JsonResponse) {
                // JSON parsing failed, return error response
                return $jsonResult;
            }
            $data = $jsonResult;

            // Map JSON payload to DTO and validate
            // Uses base class method from AbstractApiController
            // Returns DTO or JsonResponse (error if validation fails)
            $validationResult = $this->validateDto($data, AddressFullValidationRequest::class);
            if ($validationResult instanceof JsonResponse) {
                // Validation failed, return error response
                return $validationResult;
            }
            $dto = $validationResult;

            $address = $dto->address;
            $zipCode = $dto->zipCode;

            // Validate address using service (geocodes address and checks distance)
            $result = $this->addressValidationService->validateAddressForDelivery($address, $zipCode);

            // Return validation result with all relevant information
            // Uses base class method from AbstractApiController
            return $this->successResponse([
                'valid' => $result['valid'],
                'error' => $result['error'],
                'distance' => $result['distance'],
                'coordinates' => $result['coordinates'] ?? null,
                'deliveryAvailable' => $result['valid']
            ], null, 200);

        } catch (\Exception $e) {
            // Return error response if validation fails
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Erreur lors de la validation de l\'adresse', 500);
        }
    }
}
