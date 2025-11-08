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

/**
 * Lightweight Order API client
 * 
 * This client provides methods to interact with the order API endpoints.
 * Uses the new backend endpoints for order creation and retrieval.
 */
window.orderAPI = {
    /**
     * Create a new order
     * 
     * Submits order data to backend API for processing.
     * 
     * @param {Object} payload - Order payload with delivery, payment, client info
     * @returns {Promise<Object>} Response with success, message, and order data
     * @throws {Error} If API call fails or order creation fails
     * 
     * Response format: { success: boolean, message?: string, order: OrderResponse }
     */
    async createOrder(payload) {
        const res = await window.apiRequest('/api/order', {
            method: 'POST',
            credentials: 'include',
            body: JSON.stringify(payload || {})
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
                preview: text.substring(0, 200) // First 200 chars for debugging
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
            credentials: 'include'
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
    }
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
        const res = await fetch('/api/coupon/validate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ code, orderAmount })
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
        const res = await fetch(`/api/coupon/apply/${couponId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include'
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
    }
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
            body: JSON.stringify({ zipCode })
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
            body: JSON.stringify({ address, zipCode })
        });
        const data = await res.json();
        if (!res.ok || data?.success !== true) {
            throw new Error(data?.message || data?.error || `Erreur ${res.status}`);
        }
        return data.data || {};
    }
};

