<?php

namespace App\Controller\Api;

use App\Service\AddressValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api', name: 'api_')]
class AddressValidationController extends AbstractController
{
    public function __construct(
        private AddressValidationService $addressValidationService
    ) {}

    /**
     * Validate zip code for delivery
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
    #[OA\Response(
        response: 200,
        description: 'Validation result',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'valid', type: 'boolean', example: true),
                new OA\Property(property: 'error', type: 'string', example: null),
                new OA\Property(property: 'distance', type: 'number', example: 2.5),
                new OA\Property(property: 'coordinates', type: 'object', description: 'Coordinates of the zip code'),
                new OA\Property(property: 'deliveryAvailable', type: 'boolean', example: true)
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Zip code is required')
            ],
            type: 'object'
        )
    )]
    #[OA\Tag(name: 'Delivery')]
    public function validateZipCode(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $zipCode = $data['zipCode'] ?? null;

            if (!$zipCode) {
                return $this->json(['error' => 'Zip code is required'], 400);
            }

            $result = $this->addressValidationService->validateZipCodeForDelivery($zipCode);

            return $this->json([
                'valid' => $result['valid'],
                'error' => $result['error'],
                'distance' => $result['distance'],
                'coordinates' => $result['coordinates'] ?? null,
                'deliveryAvailable' => $result['valid']
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la validation du code postal',
                'valid' => false,
                'deliveryAvailable' => false
            ], 500);
        }
    }

    /**
     * Validate full address for delivery
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
    #[OA\Response(
        response: 200,
        description: 'Validation result',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'valid', type: 'boolean', example: true),
                new OA\Property(property: 'error', type: 'string', example: null),
                new OA\Property(property: 'distance', type: 'number', example: 2.5),
                new OA\Property(property: 'coordinates', type: 'object', description: 'Coordinates of the address'),
                new OA\Property(property: 'deliveryAvailable', type: 'boolean', example: true)
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Address is required')
            ],
            type: 'object'
        )
    )]
    #[OA\Tag(name: 'Delivery')]
    public function validateAddress(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $address = $data['address'] ?? null;
            $zipCode = $data['zipCode'] ?? null;

            if (!$address) {
                return $this->json(['error' => 'Address is required'], 400);
            }

            $result = $this->addressValidationService->validateAddressForDelivery($address, $zipCode);

            return $this->json([
                'valid' => $result['valid'],
                'error' => $result['error'],
                'distance' => $result['distance'],
                'coordinates' => $result['coordinates'] ?? null,
                'deliveryAvailable' => $result['valid']
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la validation de l\'adresse',
                'valid' => false,
                'deliveryAvailable' => false
            ], 500);
        }
    }
}
