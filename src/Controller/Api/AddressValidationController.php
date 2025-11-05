<?php

namespace App\Controller\Api;

use App\Service\AddressValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
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
        private AddressValidationService $addressValidationService
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
    #[OA\Response(response: 200, description: 'Validation result', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 400, description: 'Invalid request', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Tag(name: 'Delivery')]
    public function validateZipCode(Request $request): JsonResponse
    {
        try {
            // Parse JSON request body
            $data = json_decode($request->getContent(), true);
            $zipCode = $data['zipCode'] ?? null;

            // Validate required zip code parameter
            if (!$zipCode) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Code postal requis');
                return $this->json($response->toArray(), 400);
            }

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
            return $this->json($response->toArray());

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
    #[OA\Response(response: 200, description: 'Validation result', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 400, description: 'Invalid request', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Tag(name: 'Delivery')]
    public function validateAddress(Request $request): JsonResponse
    {
        try {
            // Parse JSON request body
            $data = json_decode($request->getContent(), true);
            $address = $data['address'] ?? null;
            $zipCode = $data['zipCode'] ?? null; // Optional zip code for additional validation

            // Validate required address parameter
            if (!$address) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Adresse requise');
                return $this->json($response->toArray(), 400);
            }

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
            return $this->json($response->toArray());

        } catch (\Exception $e) {
            // Return error response if validation fails
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur lors de la validation de l\'adresse');
            return $this->json($response->toArray(), 500);
        }
    }
}
