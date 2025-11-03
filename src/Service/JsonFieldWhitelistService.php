<?php

namespace App\Service;

/**
 * Service for managing JSON field whitelists per API endpoint
 * Prevents mass assignment attacks by allowing only specified fields
 */
class JsonFieldWhitelistService
{
    /**
     * Whitelist configuration for each API endpoint
     * Format: 'path' => ['allowed_field1', 'allowed_field2', ...]
     */
    private const ENDPOINT_WHITELISTS = [
        // Order endpoints
        // Create order (current API route)
        '/api/order' => [
            'deliveryMode',
            'deliveryAddress',
            'deliveryZip',
            'deliveryInstructions',
            'deliveryFee',
            'paymentMode',
            'clientFirstName',
            'clientLastName',
            'clientPhone',
            'clientEmail',
            'items', // For order items
            'couponId',
            'discountAmount',
        ],
        // Backward-compat: older clients may still call /api/order/create
        '/api/order/create' => [
            'deliveryMode',
            'deliveryAddress',
            'deliveryZip',
            'deliveryInstructions',
            'deliveryFee',
            'paymentMode',
            'clientFirstName',
            'clientLastName',
            'clientPhone',
            'clientEmail',
            'items',
            'couponId',
            'discountAmount',
        ],
        
        // Cart endpoints (allow dynamic segments via wildcard)
        '/api/cart/*' => [
            'itemId',
            'quantity',
        ],
        
        // Coupon endpoints
        '/api/coupon/validate' => [
            'code',
            'orderAmount',
        ],
        
        // Address validation endpoints
        '/api/validate-zip-code' => [
            'zipCode',
        ],
        '/api/validate-address' => [
            'address',
            'zipCode',
        ],
        
        // Review endpoints
        '/api/review/create' => [
            'name',
            'email',
            'rating',
            'comment',
        ],
        
        // Dish review endpoints
        '/api/dish-review/create' => [
            'name',
            'email',
            'rating',
            'comment',
            'dishId',
        ],
    ];

    /**
     * Get allowed fields for a given endpoint path
     *
     * @param string $path Request path (e.g., '/api/order/create')
     * @return array|null Array of allowed field names, or null if no whitelist defined
     */
    public function getAllowedFields(string $path): ?array
    {
        // Normalize path (remove trailing slash, query string, etc.)
        $normalizedPath = $this->normalizePath($path);
        
        // Exact match first
        if (isset(self::ENDPOINT_WHITELISTS[$normalizedPath])) {
            return self::ENDPOINT_WHITELISTS[$normalizedPath];
        }
        
        // Pattern matching for dynamic routes (e.g., /api/cart/*)
        foreach (self::ENDPOINT_WHITELISTS as $pattern => $fields) {
            if ($this->matchesPattern($normalizedPath, $pattern)) {
                return $fields;
            }
        }
        
        return null;
    }

    /**
     * Filter JSON data to include only allowed fields
     *
     * @param array $data Input JSON data
     * @param string $path Request path
     * @return array Filtered data containing only allowed fields
     */
    public function filterFields(array $data, string $path): array
    {
        $allowedFields = $this->getAllowedFields($path);
        
        // If no whitelist defined for this endpoint, return empty (reject all unknown endpoints)
        if ($allowedFields === null) {
            return [];
        }
        
        $filtered = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = $data[$field];
            }
        }
        
        return $filtered;
    }

    /**
     * Check if a field is allowed for a given endpoint
     *
     * @param string $field Field name
     * @param string $path Request path
     * @return bool
     */
    public function isFieldAllowed(string $field, string $path): bool
    {
        $allowedFields = $this->getAllowedFields($path);
        
        if ($allowedFields === null) {
            return false; // Unknown endpoints reject all fields
        }
        
        return in_array($field, $allowedFields, true);
    }

    /**
     * Get all rejected fields (fields in data but not in whitelist)
     *
     * @param array $data Input JSON data
     * @param string $path Request path
     * @return array Array of rejected field names
     */
    public function getRejectedFields(array $data, string $path): array
    {
        $allowedFields = $this->getAllowedFields($path);
        
        if ($allowedFields === null) {
            return array_keys($data); // All fields rejected for unknown endpoints
        }
        
        return array_diff(array_keys($data), $allowedFields);
    }

    /**
     * Normalize path for matching
     */
    private function normalizePath(string $path): string
    {
        // Remove query string
        $path = strtok($path, '?');
        
        // Remove trailing slash
        $path = rtrim($path, '/');
        
        return $path;
    }

    /**
     * Check if path matches a pattern (simple wildcard support)
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Simple wildcard support: /api/cart/* matches /api/cart/add, /api/cart/remove, etc.
        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');
            return str_starts_with($path, $prefix);
        }
        
        return $path === $pattern;
    }
}

