<?php

namespace App\Controller\Api;

use App\Service\RestaurantSettingsService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Restaurant Settings API Controller
 * 
 * Provides access to restaurant configuration settings:
 * - Delivery fees and thresholds
 * - Tax rates (VAT)
 * - Contact information
 * - Business hours
 * 
 * These settings are used by the frontend to calculate delivery costs,
 * display contact information, and validate order timing.
 */
#[Route('/api/restaurant', name: 'api_restaurant_')]
class RestaurantSettingsController extends AbstractController
{
    public function __construct(
        private RestaurantSettingsService $restaurantSettings
    ) {
    }

    /**
     * Get all restaurant settings
     * 
     * Returns complete restaurant configuration including:
     * - Delivery settings (fee, free delivery threshold, radius)
     * - Tax rates (VAT)
     * - Contact information (phone, email, address)
     * - Business hours
     * 
     * @return JsonResponse Restaurant settings object
     */
    #[Route('/settings', name: 'settings', methods: ['GET'])]
    #[OA\Get(
        path: '/api/restaurant/settings',
        summary: 'Get restaurant settings',
        description: 'Get all restaurant configuration settings including delivery fees, tax rates, etc.',
        tags: ['Restaurant Settings']
    )]
    #[OA\Response(response: 200, description: 'Restaurant settings retrieved successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    public function getSettings(): JsonResponse
    {
        // Build settings object from service (all settings are retrieved from RestaurantSettingsService)
        $settings = [
            'delivery' => [
                'fee' => $this->restaurantSettings->getDeliveryFee(), // Standard delivery fee
                'freeDeliveryThreshold' => $this->restaurantSettings->getFreeDeliveryThreshold(), // Order amount for free delivery
                'radiusKm' => $this->restaurantSettings->getDeliveryRadius(), // Delivery radius in kilometers
            ],
            'tax' => [
                'vatRate' => $this->restaurantSettings->getVatRate(), // VAT rate (e.g., 0.1 for 10%)
            ],
            'contact' => [
                'phone' => $this->restaurantSettings->getContactPhone(), // Restaurant phone number
                'email' => $this->restaurantSettings->getContactEmail(), // Restaurant email address
                'address' => $this->restaurantSettings->getContactAddress(), // Restaurant physical address
            ],
            'businessHours' => $this->restaurantSettings->getBusinessHours(), // Operating hours structure
        ];

        // Return settings in standard API response format
        $response = new \App\DTO\ApiResponseDTO(success: true, data: ['settings' => $settings]);
        return $this->json($response->toArray());
    }
}
