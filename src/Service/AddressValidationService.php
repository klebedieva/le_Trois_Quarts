<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Address Validation Service
 *
 * Validates delivery addresses and postal codes to ensure they are within
 * the restaurant's delivery radius. Uses geocoding APIs to resolve coordinates
 * and calculates distances using the Haversine formula.
 *
 * Features:
 * - Postal code validation for French format (5 digits)
 * - Geocoding via OpenStreetMap Nominatim API (free)
 * - Distance calculation from restaurant location
 * - Delivery radius validation
 * - Full address validation with optional postal code
 *
 * Coordinates are hardcoded for the restaurant location (Marseille, France).
 * Delivery radius is retrieved from RestaurantSettingsService.
 */
class AddressValidationService
{
    private const RESTAURANT_COORDINATES = [
        'lat' => 43.2965, // Marseille coordinates (approximate)
        'lng' => 5.3698
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private RestaurantSettingsService $restaurantSettings
    ) {}

    /**
     * Validate postal code for delivery eligibility
     *
     * Checks if a French postal code is within the restaurant's delivery radius.
     * Validates format, geocodes the postal code, calculates distance, and
     * determines if delivery is available.
     *
     * @param string $zipCode French postal code (5 digits, may contain spaces/dashes)
     * @return array Validation result with keys:
     *   - valid: boolean indicating if delivery is available
     *   - error: string error message if invalid, null if valid
     *   - distance: float distance in kilometers (rounded to 1 decimal)
     *   - coordinates: array with lat, lng, display_name if found
     */
    public function validateZipCodeForDelivery(string $zipCode): array
    {
        // Normalize postal code (remove spaces, dashes, etc.)
        $cleanZipCode = preg_replace('/[^0-9]/', '', $zipCode);
        
        // Validate French postal code format (exactly 5 digits)
        if (!preg_match('/^[0-9]{5}$/', $cleanZipCode)) {
            return [
                'valid' => false,
                'error' => 'Format de code postal invalide',
                'distance' => null
            ];
        }

        // Geocode postal code to get coordinates
        $coordinates = $this->getCoordinatesForZipCode($cleanZipCode);
        
        if (!$coordinates) {
            return [
                'valid' => false,
                'error' => 'Code postal introuvable',
                'distance' => null
            ];
        }

        // Calculate distance from restaurant to postal code location
        $distance = $this->calculateDistance(
            self::RESTAURANT_COORDINATES['lat'],
            self::RESTAURANT_COORDINATES['lng'],
            $coordinates['lat'],
            $coordinates['lng']
        );

        // Check if within delivery radius
        $deliveryRadius = $this->restaurantSettings->getDeliveryRadius();
        $isWithinRadius = $distance <= $deliveryRadius;

        return [
            'valid' => $isWithinRadius,
            'error' => $isWithinRadius ? null : "Livraison non disponible au-delà de {$deliveryRadius}km",
            'distance' => round($distance, 1),
            'coordinates' => $coordinates
        ];
    }

    /**
     * Geocode postal code to get coordinates
     *
     * Uses OpenStreetMap Nominatim API to resolve postal code to coordinates.
     * Returns null if postal code not found or API error occurs.
     *
     * @param string $zipCode Clean 5-digit postal code
     * @return array|null Coordinates with lat, lng, display_name, or null if not found
     */
    private function getCoordinatesForZipCode(string $zipCode): ?array
    {
        try {
            // Use Nominatim API (OpenStreetMap) - free
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'query' => [
                    'postalcode' => $zipCode,
                    'country' => 'France',
                    'format' => 'json',
                    'limit' => 1
                ],
                'headers' => [
                    'User-Agent' => 'LeTroisQuarts/1.0'
                ]
            ]);

            $data = $response->toArray();
            
            if (empty($data)) {
                return null;
            }

            $result = $data[0];
            
            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
                'display_name' => $result['display_name']
            ];

        } catch (\Exception $e) {
            // In case of API error, use cache or return null
            return $this->getFallbackCoordinates($zipCode);
        }
    }

    /**
     * Calculate distance between two geographic coordinates using Haversine formula
     *
     * Computes the great-circle distance between two points on Earth's surface
     * given their latitude and longitude. Result is in kilometers.
     *
     * @param float $lat1 Latitude of first point
     * @param float $lng1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lng2 Longitude of second point
     * @return float Distance in kilometers
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Earth radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Get fallback coordinates for known Marseille postal codes
     *
     * Used when geocoding API fails. Provides hardcoded coordinates for
     * common Marseille postal codes as a backup mechanism.
     *
     * @param string $zipCode Postal code to look up
     * @return array|null Coordinates with lat, lng, name, or null if not found
     */
    private function getFallbackCoordinates(string $zipCode): ?array
    {
        $marseilleZipCodes = [
            '13001' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 1er'],
            '13002' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 2ème'],
            '13003' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 3ème'],
            '13004' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 4ème'],
            '13005' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 5ème'],
            '13006' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 6ème'],
            '13007' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 7ème'],
            '13008' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 8ème'],
            '13009' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 9ème'],
            '13010' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 10ème'],
            '13011' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 11ème'],
            '13012' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 12ème'],
            '13013' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 13ème'],
            '13014' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 14ème'],
            '13015' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 15ème'],
            '13016' => ['lat' => 43.2965, 'lng' => 5.3698, 'name' => 'Marseille 16ème'],
        ];

        if (isset($marseilleZipCodes[$zipCode])) {
            return [
                'lat' => $marseilleZipCodes[$zipCode]['lat'],
                'lng' => $marseilleZipCodes[$zipCode]['lng'],
                'display_name' => $marseilleZipCodes[$zipCode]['name']
            ];
        }

        return null;
    }

    /**
     * Validate full address for delivery eligibility
     *
     * Attempts to validate an address string by:
     * 1. Using provided postal code if available
     * 2. Extracting postal code from address string if not provided
     * 3. Geocoding full address if postal code extraction fails
     *
     * Then calculates distance and checks against delivery radius.
     *
     * @param string $address Full address string
     * @param string|null $zipCode Optional postal code (if provided, used directly)
     * @return array Validation result with valid, error, distance, coordinates
     */
    public function validateAddressForDelivery(string $address, string $zipCode = null): array
    {
        // If postal code is provided, use it for validation
        if ($zipCode) {
            return $this->validateZipCodeForDelivery($zipCode);
        }

        // Otherwise try to extract postal code from address
        $extractedZipCode = $this->extractZipCodeFromAddress($address);
        if ($extractedZipCode) {
            return $this->validateZipCodeForDelivery($extractedZipCode);
        }

        // If failed to extract postal code, try to find coordinates by full address
        $coordinates = $this->getCoordinatesForAddress($address);
        
        if (!$coordinates) {
            return [
                'valid' => false,
                'error' => 'Adresse introuvable',
                'distance' => null
            ];
        }

        // Calculate distance
        $distance = $this->calculateDistance(
            self::RESTAURANT_COORDINATES['lat'],
            self::RESTAURANT_COORDINATES['lng'],
            $coordinates['lat'],
            $coordinates['lng']
        );

        $deliveryRadius = $this->restaurantSettings->getDeliveryRadius();
        $isWithinRadius = $distance <= $deliveryRadius;

        return [
            'valid' => $isWithinRadius,
            'error' => $isWithinRadius ? null : "Livraison non disponible au-delà de {$deliveryRadius}km",
            'distance' => round($distance, 1),
            'coordinates' => $coordinates
        ];
    }

    /**
     * Extract postal code from address
     */
    private function extractZipCodeFromAddress(string $address): ?string
    {
        // Search for 5-digit number at the beginning of address or after comma/space
        if (preg_match('/\b(\d{5})\b/', $address, $matches)) {
            $zipCode = $matches[1];
            if ($this->isValidFrenchZipCode($zipCode)) {
                return $zipCode;
            }
        }
        return null;
    }

    /**
     * Get coordinates by full address
     */
    private function getCoordinatesForAddress(string $address): ?array
    {
        try {
            // Clean address
            $cleanAddress = trim($address);
            if (empty($cleanAddress)) {
                return null;
            }

            // Use Nominatim for address search
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'query' => [
                    'q' => $cleanAddress . ', France',
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 1
                ],
                'headers' => [
                    'User-Agent' => 'LeTroisQuarts/1.0'
                ]
            ]);

            $data = $response->toArray();
            
            if (empty($data)) {
                return null;
            }

            $result = $data[0];
            
            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
                'display_name' => $result['display_name']
            ];

        } catch (\Exception $e) {
            // In case of API error, try to extract postal code and use fallback
            $zipCode = $this->extractZipCodeFromAddress($address);
            if ($zipCode) {
                return $this->getFallbackCoordinates($zipCode);
            }
            return null;
        }
    }

    /**
     * Check if postal code is French
     */
    public function isValidFrenchZipCode(string $zipCode): bool
    {
        $cleanZipCode = preg_replace('/[^0-9]/', '', $zipCode);
        return preg_match('/^[0-9]{5}$/', $cleanZipCode);
    }
}
