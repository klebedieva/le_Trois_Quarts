<?php

namespace App\Controller\Api;

use App\DTO\AddressFullValidationRequest;
use App\DTO\AddressValidationRequest;
use App\Service\AddressValidationService;
use App\Service\ValidationHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
 * Uses AddressValidationService to check if addresses are within delivery radius
 * and calculates distance from restaurant location.
 */
#[Route('/api', name: 'api_')]
class AddressValidationController extends AbstractController
{
    public function __construct(
        private AddressValidationService $addressValidationService,
        private ValidatorInterface $validator,
        private ValidationHelper $validationHelper
    ) {}

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
            // Priority 1: Use filtered data from JsonFieldWhitelistSubscriber if available
            // This ensures only authorized fields reach the controller (mass assignment protection)
            // The subscriber filters out unauthorized fields before the request reaches here
            // Priority 2: Fallback to parsing raw content if subscriber didn't process it
            // (This should rarely happen for API endpoints, but provides backward compatibility)
            $data = $request->attributes->get('filtered_json_data');
            if ($data === null) {
                // Fallback: parse raw content if filtered data not available
                // This can happen if request bypassed the subscriber or for non-API endpoints
                $data = json_decode($request->getContent(), true);
            }
            
            if (!is_array($data)) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'JSON invalide');
                return $this->json($response->toArray(), 400);
            }

            // Map JSON payload to DTO using helper service
            // The ValidationHelper automatically handles type conversion based on DTO property types
            // This eliminates repetitive manual mapping code like: isset($data['zipCode']) ? trim((string)$data['zipCode']) : null
            // Note: Data is already filtered by JsonFieldWhitelistSubscriber, so only authorized fields are present
            // This provides defense in depth: subscriber filters at request level, DTO validates at domain level
            $dto = $this->validationHelper->mapArrayToDto($data, AddressValidationRequest::class);
            
            // No manual trimming required: ValidationHelper::mapArrayToDto() already trims strings

            // Validate DTO
            $violations = $this->validator->validate($dto);
            if (count($violations) > 0) {
                $errors = $this->validationHelper->extractViolationMessages($violations);
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur de validation', errors: $errors);
                return $this->json($response->toArray(), 422);
            }

            $zipCode = $dto->zipCode;

            // Validate zip code using service (checks distance, coordinates, etc.)
            $result = $this->addressValidationService->validateZipCodeForDelivery($zipCode);

            // Return validation result with all relevant information
            $response = new \App\DTO\ApiResponseDTO(
                success: true,
                data: [
                    'valid' => $result['valid'],
                    'error' => $result['error'],
                    'distance' => $result['distance'],
                    'coordinates' => $result['coordinates'] ?? null,
                    'deliveryAvailable' => $result['valid']
                ]
            );
            return $this->json($response->toArray(), 200);

        } catch (\Exception $e) {
            // Return error response if validation fails
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur lors de la validation du code postal');
            return $this->json($response->toArray(), 500);
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
            // Priority 1: Use filtered data from JsonFieldWhitelistSubscriber if available
            // This ensures only authorized fields reach the controller (mass assignment protection)
            // The subscriber filters out unauthorized fields before the request reaches here
            // Priority 2: Fallback to parsing raw content if subscriber didn't process it
            // (This should rarely happen for API endpoints, but provides backward compatibility)
            $data = $request->attributes->get('filtered_json_data');
            if ($data === null) {
                // Fallback: parse raw content if filtered data not available
                // This can happen if request bypassed the subscriber or for non-API endpoints
                $data = json_decode($request->getContent(), true);
            }
            
            if (!is_array($data)) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'JSON invalide');
                return $this->json($response->toArray(), 400);
            }

            // Map JSON payload to DTO using helper service
            // The ValidationHelper automatically handles type conversion based on DTO property types
            // This eliminates repetitive manual mapping code for both address and zipCode fields
            // Note: Data is already filtered by JsonFieldWhitelistSubscriber, so only authorized fields are present
            // This provides defense in depth: subscriber filters at request level, DTO validates at domain level
            $dto = $this->validationHelper->mapArrayToDto($data, AddressFullValidationRequest::class);
            
            // No manual trimming required: ValidationHelper::mapArrayToDto() already trims strings

            // Validate DTO
            $violations = $this->validator->validate($dto);
            if (count($violations) > 0) {
                $errors = $this->validationHelper->extractViolationMessages($violations);
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur de validation', errors: $errors);
                return $this->json($response->toArray(), 422);
            }

            $address = $dto->address;
            $zipCode = $dto->zipCode;

            // Validate address using service (geocodes address and checks distance)
            $result = $this->addressValidationService->validateAddressForDelivery($address, $zipCode);

            // Return validation result with all relevant information
            $response = new \App\DTO\ApiResponseDTO(
                success: true,
                data: [
                    'valid' => $result['valid'],
                    'error' => $result['error'],
                    'distance' => $result['distance'],
                    'coordinates' => $result['coordinates'] ?? null,
                    'deliveryAvailable' => $result['valid']
                ]
            );
            return $this->json($response->toArray(), 200);

        } catch (\Exception $e) {
            // Return error response if validation fails
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur lors de la validation de l\'adresse');
            return $this->json($response->toArray(), 500);
        }
    }
}
