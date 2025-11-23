// ============================================================================
// ORDER API - API Clients for Order, Coupon, and Address Validation
// ============================================================================
// This module provides API clients for:
// - Order creation and retrieval
// - Coupon validation and application
// - Address and postal code validation
//
// Usage: This module provides API methods to other order modules.

'use strict';

// ============================================================================
// IDEMPOTENCY KEY GENERATION
// ============================================================================

/**
 * Generate a unique idempotency key for order requests
 *
 * Idempotency keys ensure that if a request is retried (e.g., due to network
 * issues or user double-clicking), the server will return the same response
 * instead of creating duplicate orders.
 *
 * The key must be:
 * - Unique for each order attempt (prevents duplicate orders)
 * - Consistent across retries (allows server to recognize duplicate requests)
 * - Stored client-side for the duration of the order submission
 *
 * Implementation:
 * - Uses crypto.randomUUID() if available (modern browsers, RFC 4122 compliant)
 * - Falls back to timestamp + random combination for older browsers
 * - Format: UUID v4 or timestamp-random combination
 *
 * @returns {string} Unique idempotency key (UUID format or timestamp-based)
 *
 * @example
 * // Generate key for new order
 * const key = generateIdempotencyKey();
 * // Result: "550e8400-e29b-41d4-a716-446655440000" (UUID) or "1703123456789-abc123" (fallback)
 */
function generateIdempotencyKey() {
    // Prefer crypto.randomUUID() for modern browsers (Chrome 92+, Firefox 95+, Safari 15.4+)
    // This provides RFC 4122 compliant UUIDs with proper randomness
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    // Fallback for older browsers: timestamp + random string
    // This ensures uniqueness even without crypto.randomUUID support
    // Format: timestamp-randomString (e.g., "1703123456789-a1b2c3d4")
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(2, 15);
    return `${timestamp}-${random}`;
}

// ============================================================================
// ORDER API CLIENT
// ============================================================================

/**
 * Lightweight Order API client
 *
 * This client provides methods to interact with the order API endpoints.
 * Uses the new backend endpoints for order creation and retrieval.
 *
 * Features:
 * - Idempotency support: prevents duplicate orders on retries
 * - Error handling: graceful degradation for network issues
 * - Response validation: ensures data integrity
 */
window.orderAPI = {
    /**
     * Create a new order
     *
     * Submits order data to backend API for processing with idempotency protection.
     *
     * Idempotency:
     * - Generates a unique Idempotency-Key header for each order attempt
     * - If the same request is retried (same key), server returns cached response
     * - Prevents duplicate orders from network retries or user double-clicks
     * - Key is valid for 10 minutes (configured server-side)
     *
     * Flow:
     * 1. Generate unique idempotency key for this order attempt
     * 2. Send request with Idempotency-Key header
     * 3. Server checks cache: if key exists, returns cached response (no duplicate order)
     * 4. If key is new, server processes order and caches response
     * 5. Client receives order confirmation
     *
     * Retry behavior:
     * - If request fails (network error), retry with same key → server returns cached order
     * - If user clicks submit twice, second request uses same key → no duplicate order
     * - Each new order attempt gets a new key (stored in closure or sessionStorage)
     *
     * @param {Object} payload - Order payload with delivery, payment, client info
     * @returns {Promise<Object>} Response with success, message, and order data
     * @throws {Error} If API call fails or order creation fails
     *
     * Response format: { success: boolean, message?: string, order: OrderResponse }
     *
     * @example
     * // Create order with automatic idempotency protection
     * const result = await window.orderAPI.createOrder({
     *   deliveryMode: 'delivery',
     *   clientFirstName: 'John',
     *   // ... other fields
     * });
     * // If this request is retried, server returns same result (no duplicate order)
     */
    async createOrder(payload) {
        // Generate idempotency key for this order attempt
        // This key uniquely identifies this order submission attempt
        // If the same key is sent again (retry), server returns cached response
        const idempotencyKey = generateIdempotencyKey();

        // Send order creation request with Idempotency-Key header
        // The header tells the server: "if you've seen this key before, return cached response"
        // This prevents duplicate orders from:
        // - Network retries (automatic or manual)
        // - User double-clicking submit button
        // - Browser refresh during submission
        const res = await window.apiRequest('/api/order', {
            method: 'POST',
            credentials: 'include',
            // Include Idempotency-Key header for duplicate request prevention
            // Server will cache the response for 10 minutes (configurable)
            // Subsequent requests with same key return cached response (no duplicate order)
            headers: {
                'Idempotency-Key': idempotencyKey,
            },
            body: JSON.stringify(payload || {}),
        });

        // Check if response is JSON before parsing
        // If server returns HTML error page (e.g., 500 error), we need to handle it gracefully
        // This prevents "Unexpected token '<'" errors when trying to parse HTML as JSON
        const contentType = res.headers.get('content-type');
        let data;

        if (contentType && contentType.includes('application/json')) {
            try {
                data = await res.json();
            } catch (e) {
                // If JSON parsing fails despite correct content-type, log for debugging
                console.error('Failed to parse JSON response:', e);
                throw new Error('Erreur lors de la création de la commande. Veuillez réessayer.');
            }
        } else {
            // Server returned non-JSON response (likely HTML error page)
            // This usually means:
            // - Server error (500) - PHP fatal error or uncaught exception
            // - Redirect to error page
            // - Server configuration issue
            const text = await res.text();
            console.error('Server returned non-JSON response:', {
                status: res.status,
                contentType: contentType,
                preview: text.substring(0, 200), // First 200 chars for debugging
            });
            throw new Error(`Erreur serveur (${res.status}). Veuillez réessayer plus tard.`);
        }

        /**
         * Check if request succeeded and response indicates success
         * Throw error if either condition fails
         */
        if (window.OrderUtils) {
            window.OrderUtils.handleApiError(res, data);
        } else {
            // Fallback if utils not loaded
            if (!res.ok || !data.success) {
                const msg = data?.message || data?.error || `Erreur ${res.status}`;
                throw new Error(msg);
            }
        }

        return data;
    },

    /**
     * Get order by ID
     *
     * Retrieves order details from backend API.
     *
     * @param {string|number} id - Order ID
     * @returns {Promise<Object>} Response with success and order data
     * @throws {Error} If API call fails or order not found
     *
     * Response format: { success: boolean, order: OrderResponse }
     */
    async getOrder(id) {
        const res = await fetch(`/api/order/${id}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
        });

        const data = await res.json();

        /**
         * Check if request succeeded and response indicates success
         * Throw error if either condition fails
         */
        if (window.OrderUtils) {
            window.OrderUtils.handleApiError(res, data);
        } else {
            // Fallback if utils not loaded
            if (!res.ok || !data.success) {
                const msg = data?.message || data?.error || `Erreur ${res.status}`;
                throw new Error(msg);
            }
        }

        return data;
    },
};

/**
 * Lightweight Coupon API client
 *
 * This client provides methods to validate and apply coupon codes.
 */
window.couponAPI = {
    /**
     * Validate a coupon code
     *
     * Checks if coupon code is valid and calculates discount.
     *
     * @param {string} code - Coupon code to validate
     * @param {number} orderAmount - Order total amount (before discount)
     * @returns {Promise<Object>} Response with validation result and discount data
     * @throws {Error} If API call fails or coupon is invalid
     *
     * Response format: {
     *   success: boolean,
     *   message: string,
     *   data: { couponId, code, discountAmount, newTotal }
     * }
     */
    async validateCoupon(code, orderAmount) {
        const res = await window.apiRequest('/api/coupon/validate', {
            method: 'POST',
            body: JSON.stringify({ code, orderAmount }),
        });

        const data = await res.json();

        /**
         * Check if request succeeded and response indicates success
         * Throw error if either condition fails
         */
        if (window.OrderUtils) {
            window.OrderUtils.handleApiError(res, data);
        } else {
            // Fallback if utils not loaded
            if (!res.ok || !data.success) {
                const msg = data?.message || data?.error || `Erreur ${res.status}`;
                throw new Error(msg);
            }
        }

        return data;
    },

    /**
     * Apply coupon (increment usage count)
     *
     * Marks coupon as used by incrementing usage count.
     * Called after successful order creation.
     *
     * @param {string|number} couponId - Coupon ID to apply
     * @returns {Promise<Object>} Response with success status
     * @throws {Error} If API call fails
     */
    async applyCoupon(couponId) {
        const res = await window.apiRequest(`/api/coupon/apply/${couponId}`, {
            method: 'POST',
        });

        const data = await res.json();

        /**
         * Check if request succeeded and response indicates success
         * Throw error if either condition fails
         */
        if (window.OrderUtils) {
            window.OrderUtils.handleApiError(res, data);
        } else {
            // Fallback if utils not loaded
            if (!res.ok || !data.success) {
                const msg = data?.message || data?.error || `Erreur ${res.status}`;
                throw new Error(msg);
            }
        }

        return data;
    },
};

/**
 * API for postal code and address validation
 *
 * Zip/Address backend validation helpers.
 * Uses centralized error handling for consistency.
 */
window.zipCodeAPI = {
    /**
     * Validate postal code
     *
     * @param {string} zipCode - Postal code to validate
     * @returns {Promise<Object>} Validation result
     * @throws {Error} If API call fails
     */
    async validateZipCode(zipCode) {
        const res = await fetch('/api/validate-zip-code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ zipCode }),
        });
        const data = await res.json();
        // Consider business-level success flag
        if (!res.ok || data?.success !== true) {
            throw new Error(data?.message || data?.error || `Erreur ${res.status}`);
        }
        // Return normalized payload shape expected by callers
        return data.data || {};
    },

    /**
     * Validate address
     *
     * @param {string} address - Address to validate
     * @param {string|null} zipCode - Optional postal code
     * @returns {Promise<Object>} Validation result
     * @throws {Error} If API call fails
     */
    async validateAddress(address, zipCode = null) {
        const res = await fetch('/api/validate-address', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ address, zipCode }),
        });
        const data = await res.json();
        if (!res.ok || data?.success !== true) {
            throw new Error(data?.message || data?.error || `Erreur ${res.status}`);
        }
        return data.data || {};
    },
};
