<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AddressValidationService
{
    private const RESTAURANT_COORDINATES = [
        'lat' => 43.2965, // Координаты Марселя (примерные)
        'lng' => 5.3698
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private RestaurantSettingsService $restaurantSettings
    ) {}

    /**
     * Валидировать почтовый индекс для доставки
     */
    public function validateZipCodeForDelivery(string $zipCode): array
    {
        // Очистить почтовый индекс
        $cleanZipCode = preg_replace('/[^0-9]/', '', $zipCode);
        
        // Проверить базовый формат французского почтового индекса
        if (!preg_match('/^[0-9]{5}$/', $cleanZipCode)) {
            return [
                'valid' => false,
                'error' => 'Format de code postal invalide',
                'distance' => null
            ];
        }

        // Получить координаты почтового индекса
        $coordinates = $this->getCoordinatesForZipCode($cleanZipCode);
        
        if (!$coordinates) {
            return [
                'valid' => false,
                'error' => 'Code postal introuvable',
                'distance' => null
            ];
        }

        // Рассчитать расстояние
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
     * Получить координаты по почтовому индексу
     */
    private function getCoordinatesForZipCode(string $zipCode): ?array
    {
        try {
            // Используем API Nominatim (OpenStreetMap) - бесплатный
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
            // В случае ошибки API, используем кэш или возвращаем null
            return $this->getFallbackCoordinates($zipCode);
        }
    }

    /**
     * Рассчитать расстояние между двумя точками (формула гаверсинуса)
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Радиус Земли в километрах

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Резервные координаты для популярных почтовых индексов Марселя
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
     * Валидировать полный адрес для доставки
     */
    public function validateAddressForDelivery(string $address, string $zipCode = null): array
    {
        // Если передан почтовый индекс, использовать его для валидации
        if ($zipCode) {
            return $this->validateZipCodeForDelivery($zipCode);
        }

        // Иначе попытаться извлечь почтовый индекс из адреса
        $extractedZipCode = $this->extractZipCodeFromAddress($address);
        if ($extractedZipCode) {
            return $this->validateZipCodeForDelivery($extractedZipCode);
        }

        // Если не удалось извлечь почтовый индекс, попробовать найти координаты по полному адресу
        $coordinates = $this->getCoordinatesForAddress($address);
        
        if (!$coordinates) {
            return [
                'valid' => false,
                'error' => 'Adresse introuvable',
                'distance' => null
            ];
        }

        // Рассчитать расстояние
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
     * Извлечь почтовый индекс из адреса
     */
    private function extractZipCodeFromAddress(string $address): ?string
    {
        // Поиск 5-значного числа в начале адреса или после запятой/пробела
        if (preg_match('/\b(\d{5})\b/', $address, $matches)) {
            $zipCode = $matches[1];
            if ($this->isValidFrenchZipCode($zipCode)) {
                return $zipCode;
            }
        }
        return null;
    }

    /**
     * Получить координаты по полному адресу
     */
    private function getCoordinatesForAddress(string $address): ?array
    {
        try {
            // Очистить адрес
            $cleanAddress = trim($address);
            if (empty($cleanAddress)) {
                return null;
            }

            // Используем Nominatim для поиска по адресу
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
            // В случае ошибки API, попробовать извлечь почтовый индекс и использовать fallback
            $zipCode = $this->extractZipCodeFromAddress($address);
            if ($zipCode) {
                return $this->getFallbackCoordinates($zipCode);
            }
            return null;
        }
    }

    /**
     * Проверить, является ли почтовый индекс французским
     */
    public function isValidFrenchZipCode(string $zipCode): bool
    {
        $cleanZipCode = preg_replace('/[^0-9]/', '', $zipCode);
        return preg_match('/^[0-9]{5}$/', $cleanZipCode);
    }
}
