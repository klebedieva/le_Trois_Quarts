<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Address Validation Service
 *
 * Simple address validation for delivery.
 * 
 * How it works:
 * 1. Get address coordinates via geocoding (OpenStreetMap)
 * 2. Calculate distance from restaurant to address
 * 3. Check if distance is within delivery radius
 *
 * Restaurant coordinates are retrieved from RestaurantSettingsService (config-driven).
 * Delivery radius is also retrieved from RestaurantSettingsService.
 */
class AddressValidationService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private RestaurantSettingsService $restaurantSettings
    ) {}

    /**
     * Get restaurant coordinates from config
     */
    private function getRestaurantCoordinates(): array
    {
        try {
            return $this->restaurantSettings->getRestaurantCoordinates();
        } catch (\Exception $e) {
            // If config is not available, use default coordinates
            return [
                'lat' => 43.2965,
                'lng' => 5.3698
            ];
        }
    }

    /**
     * Validate postal code for delivery
     *
     * Simple logic:
     * 1. Check format (5 digits)
     * 2. Get postal code coordinates
     * 3. Calculate distance from restaurant
     * 4. Check if within delivery radius
     *
     * @param string $zipCode Postal code (e.g., "13004")
     * @return array Result: valid (true/false), error (message), distance (km), coordinates
     */
    public function validateZipCodeForDelivery(string $zipCode): array
    {
        // Step 1: Clean postal code from spaces and dashes
        $cleanZipCode = preg_replace('/[^0-9]/', '', $zipCode);
        
        // Step 2: Check format (must be exactly 5 digits)
        if (!preg_match('/^[0-9]{5}$/', $cleanZipCode)) {
            return [
                'valid' => false,
                'error' => 'Format de code postal invalide',
                'distance' => null
            ];
        }

        // Step 3: Get postal code coordinates
        $coordinates = $this->getCoordinatesForZipCode($cleanZipCode);
        
        if (!$coordinates) {
            return [
                'valid' => false,
                'error' => 'Code postal introuvable',
                'distance' => null
            ];
        }

        // Step 4: Calculate distance and check delivery radius
        return $this->checkDistanceAndRadius($coordinates);
    }

    /**
     * Validate full address for delivery
     *
     * Simple logic for beginners:
     * 1. If address is provided - always try to geocode it first (most accurate)
     * 2. If address geocoding fails and zip code is available - use zip code as fallback
     * 3. If only zip code is provided - validate zip code only
     *
     * Why this approach?
     * - Address gives exact coordinates (more accurate than zip code centroid)
     * - We only use zip code when address cannot be found (not as primary validation)
     * - Simple linear flow: try address first, fallback to zip if needed
     *
     * @param string $address Full address (e.g., "5 Bd Eugene Cabassud")
     * @param string|null $zipCode Postal code (optional, e.g., "13004")
     * @return array Result: valid (true/false), error (message), distance (km), coordinates
     */
    public function validateAddressForDelivery(string $address, string $zipCode = null): array
    {
        // Step 1: Clean and validate address input
        $cleanAddress = trim($address);
        if (empty($cleanAddress)) {
            return [
                'valid' => false,
                'error' => 'L\'adresse est requise',
                'distance' => null
            ];
        }

        // Step 2: Always try to geocode address first (most accurate method)
        // If zip code is provided, pass it to improve geocoding accuracy
        $coordinates = $this->getCoordinatesForAddress($cleanAddress, $zipCode);
        
        if ($coordinates) {
            // Address was successfully geocoded - validate distance
            // This is the most accurate validation (uses exact address coordinates)
            return $this->checkDistanceAndRadius($coordinates);
        }

        // Step 3: Address geocoding failed (address not found in API)
        // If zip code is available, use it as fallback (less accurate but better than nothing)
        if ($zipCode) {
            return $this->validateZipCodeForDelivery($zipCode);
        }

        // Step 4: No zip code available and address not found - return error
        return [
            'valid' => false,
            'error' => 'Adresse introuvable',
            'distance' => null
        ];
    }

    /**
     * Check distance and delivery radius
     *
     * This is a common method used for all checks.
     * Calculates distance from restaurant to coordinates and checks if within radius.
     *
     * @param array $coordinates Address coordinates: lat, lng, display_name
     * @return array Result: valid (true/false), error (message), distance (km), coordinates
     */
    private function checkDistanceAndRadius(array $coordinates): array
    {
        // Get restaurant coordinates
        $restaurantCoords = $this->getRestaurantCoordinates();
        
        // Calculate distance from restaurant to address (in kilometers)
        $distance = $this->calculateDistance(
            $restaurantCoords['lat'],
            $restaurantCoords['lng'],
            $coordinates['lat'],
            $coordinates['lng']
        );

        // Get delivery radius from config
        $deliveryRadius = $this->restaurantSettings->getDeliveryRadius();
        
        // Check if distance is within delivery radius
        $isWithinRadius = $distance <= $deliveryRadius;

        return [
            'valid' => $isWithinRadius,
            'error' => $isWithinRadius ? null : "Livraison non disponible au-delà de {$deliveryRadius}km",
            'distance' => round($distance, 1),
            'coordinates' => $coordinates
        ];
    }

    /**
     * Get postal code coordinates
     *
     * Uses OpenStreetMap API to get coordinates.
     * If API is not working - uses fallback coordinates for known Marseille postal codes.
     *
     * @param string $zipCode Clean 5-digit postal code
     * @return array|null Coordinates: lat, lng, display_name or null
     */
    private function getCoordinatesForZipCode(string $zipCode): ?array
    {
        try {
            // Request to OpenStreetMap API
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'query' => [
                    'postalcode' => $zipCode,
                    'city' => 'Marseille',
                    'countrycodes' => 'fr',
                    'format' => 'json',
                    'limit' => 1
                ],
                'headers' => [
                    'User-Agent' => 'LeTroisQuarts/1.0'
                ],
                'timeout' => 5
            ]);

            $data = $response->toArray();
            
            // If API returned empty result - use fallback coordinates
            if (empty($data)) {
                return $this->getFallbackCoordinates($zipCode);
            }

            // Extract coordinates from response
            $result = $data[0];
            
            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
                'display_name' => $result['display_name']
            ];

        } catch (\Exception $e) {
            // If API is not working - use fallback coordinates
            return $this->getFallbackCoordinates($zipCode);
        }
    }

    /**
     * Get full address coordinates
     *
     * Geocodes address via OpenStreetMap API.
     * Tries to use structured query (more accurate), if it doesn't work - uses simple query.
     *
     * @param string $address Address (e.g., "5 Bd Eugene Cabassud")
     * @param string|null $zipCode Postal code (optional, improves accuracy)
     * @return array|null Coordinates: lat, lng, display_name or null
     */
    private function getCoordinatesForAddress(string $address, ?string $zipCode = null): ?array
    {
        $cleanAddress = trim($address);
        if (empty($cleanAddress)) {
            return null;
        }

        try {
            // Try to use structured query (more accurate)
            $coordinates = $this->geocodeAddressStructured($cleanAddress, $zipCode);
            if ($coordinates) {
                return $coordinates;
            }

            // If structured query didn't work - use simple query
            return $this->geocodeAddressSimple($cleanAddress, $zipCode);

        } catch (\Exception $e) {
            // If everything failed - try to extract zip code and use fallback coordinates
            $extractedZipCode = $this->extractZipCodeFromAddress($address);
            if ($extractedZipCode) {
                return $this->getFallbackCoordinates($extractedZipCode);
            }
            return null;
        }
    }

    /**
     * Geocode address via structured query (more accurate)
     *
     * Breaks address into parts (house number, street, zip code, city) for better accuracy.
     */
    private function geocodeAddressStructured(string $address, ?string $zipCode = null): ?array
    {
        $queryParams = [
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
            'countrycodes' => 'fr',
            'city' => 'Marseille'
        ];
        if (preg_match('/^(\d+)\s+(.+)$/i', $address, $matches)) {
            $queryParams['street'] = $matches[1] . ' ' . trim($matches[2]);
        } else {
            $queryParams['street'] = $address;
        }
        if ($zipCode) {
            $cleanZipCode = preg_replace('/[^0-9]/', '', $zipCode);
            if (preg_match('/^[0-9]{5}$/', $cleanZipCode)) {
                $queryParams['postalcode'] = $cleanZipCode;
            }
        }
        try {
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'query' => $queryParams,
                'headers' => ['User-Agent' => 'LeTroisQuarts/1.0'],
                'timeout' => 5
            ]);
            $data = $response->toArray();
            if (empty($data)) {
                return null;
            }
            $result = $data[0];
            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
                'display_name' => $result['display_name'] ?? $address
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Geocode address via simple query (fallback option)
     *
     * Uses simple text query if structured query didn't work.
     */
    private function geocodeAddressSimple(string $address, ?string $zipCode = null): ?array
    {
        $queryParams = [
            'q' => $address . ', Marseille, France',
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
            'countrycodes' => 'fr'
        ];

        if ($zipCode) {
            $cleanZipCode = preg_replace('/[^0-9]/', '', $zipCode);
            if (preg_match('/^[0-9]{5}$/', $cleanZipCode)) {
                $queryParams['postalcode'] = $cleanZipCode;
            }
        }

        try {
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'query' => $queryParams,
                'headers' => ['User-Agent' => 'LeTroisQuarts/1.0'],
                'timeout' => 5
            ]);

            $data = $response->toArray();
            if (empty($data)) {
                return null;
            }

            $result = $data[0];
            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
                'display_name' => $result['display_name'] ?? $address
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract postal code from address
     *
     * Searches for 5-digit number in address text.
     * Example: "5 Bd Eugene Cabassud, 13004 Marseille" -> "13004"
     *
     * @param string $address Address
     * @return string|null Postal code or null
     */
    private function extractZipCodeFromAddress(string $address): ?string
    {
        // Search for 5-digit number in address
        if (preg_match('/\b(\d{5})\b/', $address, $matches)) {
            $zipCode = $matches[1];
            // Check that it's a valid French postal code
            if ($this->isValidFrenchZipCode($zipCode)) {
                return $zipCode;
            }
        }
        return null;
    }

    /**
     * Fallback coordinates for known Marseille postal codes
     *
     * Used when API is not working or cannot find postal code.
     * Contains approximate coordinates of Marseille district centers.
     */
    private function getFallbackCoordinates(string $zipCode): ?array
    {
        $marseilleZipCodes = [
            '13001' => ['lat' => 43.2969, 'lng' => 5.3756, 'name' => 'Marseille 1er'],
            '13002' => ['lat' => 43.3025, 'lng' => 5.3679, 'name' => 'Marseille 2ème'],
            '13003' => ['lat' => 43.3088, 'lng' => 5.3730, 'name' => 'Marseille 3ème'],
            '13004' => ['lat' => 43.3086, 'lng' => 5.4001, 'name' => 'Marseille 4ème'],
            '13005' => ['lat' => 43.2898, 'lng' => 5.3941, 'name' => 'Marseille 5ème'],
            '13006' => ['lat' => 43.2855, 'lng' => 5.3789, 'name' => 'Marseille 6ème'],
            '13007' => ['lat' => 43.2768, 'lng' => 5.3495, 'name' => 'Marseille 7ème'],
            '13008' => ['lat' => 43.2679, 'lng' => 5.3843, 'name' => 'Marseille 8ème'],
            '13009' => ['lat' => 43.2459, 'lng' => 5.4207, 'name' => 'Marseille 9ème'],
            '13010' => ['lat' => 43.2785, 'lng' => 5.4096, 'name' => 'Marseille 10ème'],
            '13011' => ['lat' => 43.2902, 'lng' => 5.4664, 'name' => 'Marseille 11ème'],
            '13012' => ['lat' => 43.2964, 'lng' => 5.4393, 'name' => 'Marseille 12ème'],
            '13013' => ['lat' => 43.3335, 'lng' => 5.4102, 'name' => 'Marseille 13ème'],
            '13014' => ['lat' => 43.3419, 'lng' => 5.3831, 'name' => 'Marseille 14ème'],
            '13015' => ['lat' => 43.3524, 'lng' => 5.3542, 'name' => 'Marseille 15ème'],
            '13016' => ['lat' => 43.3539, 'lng' => 5.3202, 'name' => 'Marseille 16ème'],
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
     * Calculate distance between two points on Earth
     *
     * Uses Haversine formula to calculate straight-line distance
     * (not by roads, but "as the crow flies").
     *
     * @param float $lat1 Latitude of first point (restaurant)
     * @param float $lng1 Longitude of first point (restaurant)
     * @param float $lat2 Latitude of second point (address)
     * @param float $lng2 Longitude of second point (address)
     * @return float Distance in kilometers
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // Earth radius in kilometers
        $earthRadius = 6371;

        // Convert coordinate difference to radians
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        // Haversine formula for distance calculation
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        // Return distance in kilometers
        return $earthRadius * $c;
    }

    /**
     * Check if postal code is French (5 digits)
     *
     * @param string $zipCode Postal code
     * @return bool true if valid French postal code
     */
    public function isValidFrenchZipCode(string $zipCode): bool
    {
        $cleanZipCode = preg_replace('/[^0-9]/', '', $zipCode);
        return preg_match('/^[0-9]{5}$/', $cleanZipCode);
    }
}
