// ============================================================================
// ORDER PAGE - Multi-Step Checkout Process
// ============================================================================
// This file handles:
// - Multi-step checkout (cart → delivery → payment → confirmation)
// - Order data collection and validation
// - XSS protection and input sanitization
// - Address and postal code validation
// - Phone number validation
// - Coupon/promo code functionality
// - Order submission to backend API
//
// Design principle: This file intentionally avoids external dependencies.
// All helpers are defined inline and exposed on window only when needed
// to keep the surface area small.

'use strict';

// ============================================================================
// CONSTANTS
// ============================================================================

/**
 * Application constants
 * 
 * Centralized constants for maintainability and self-documentation.
 */
const DELIVERY_FEE = 5; // Delivery fee in euros
const TAX_RATE = 0.10; // 10% VAT
const DEBOUNCE_DELAYS = {
    ZIP_CODE: 500, // 500ms delay for postal code validation
    ADDRESS: 800   // 800ms delay for address validation
};
const MIN_TIME_DELAY_HOURS = 1; // Minimum 1 hour delay for delivery time

/**
 * Time slots for delivery/pickup
 * 
 * Cached array of available time slots.
 * Prevents recreation on every date change.
 */
const TIME_SLOTS = [
    { value: '07:00', text: '07h00 - 07h30' },
    { value: '07:30', text: '07h30 - 08h00' },
    { value: '08:00', text: '08h00 - 08h30' },
    { value: '08:30', text: '08h30 - 09h00' },
    { value: '09:00', text: '09h00 - 09h30' },
    { value: '09:30', text: '09h30 - 10h00' },
    { value: '10:00', text: '10h00 - 10h30' },
    { value: '10:30', text: '10h30 - 11h00' },
    { value: '11:00', text: '11h00 - 11h30' },
    { value: '11:30', text: '11h30 - 12h00' },
    { value: '12:00', text: '12h00 - 12h30' },
    { value: '12:30', text: '12h30 - 13h00' },
    { value: '13:00', text: '13h00 - 13h30' },
    { value: '13:30', text: '13h30 - 14h00' },
    { value: '14:00', text: '14h00 - 14h30' },
    { value: '14:30', text: '14h30 - 15h00' },
    { value: '15:00', text: '15h00 - 15h30' },
    { value: '15:30', text: '15h30 - 16h00' },
    { value: '16:00', text: '16h00 - 16h30' },
    { value: '16:30', text: '16h30 - 17h00' },
    { value: '17:00', text: '17h00 - 17h30' },
    { value: '17:30', text: '17h30 - 18h00' },
    { value: '18:00', text: '18h00 - 18h30' },
    { value: '18:30', text: '18h30 - 19h00' },
    { value: '19:00', text: '19h00 - 19h30' },
    { value: '19:30', text: '19h30 - 20h00' },
    { value: '20:00', text: '20h00 - 20h30' },
    { value: '20:30', text: '20h30 - 21h00' },
    { value: '21:00', text: '21h00 - 21h30' },
    { value: '21:30', text: '21h30 - 22h00' },
    { value: '22:00', text: '22h00 - 22h30' },
    { value: '22:30', text: '22h30 - 23h00' }
];

// ============================================================================
// UI STATE
// ============================================================================

/**
 * Current checkout step
 * 
 * Steps:
 * - 1: Cart review
 * - 2: Delivery information
 * - 3: Payment method
 * - 4: Confirmation
 */
let currentStep = 1;

/**
 * Canonical order data state bag
 * 
 * This object stores all order data collected across checkout steps.
 * Used for payload building when submitting order to backend.
 * 
 * Structure:
 * - items: Array of cart items
 * - delivery: Delivery mode, address, date, time
 * - payment: Payment method
 * - client: Client contact information
 * - total: Calculated total amount
 * - coupon: Applied coupon data
 * - discount: Discount amount
 * - subtotal: Subtotal without tax
 * - taxAmount: Tax amount
 * - deliveryFee: Delivery fee
 */
let orderData = { 
    items: [], 
    delivery: {}, 
    payment: {}, 
    total: 0, 
    coupon: null, 
    discount: 0 
};

// ============================================================================
// VALIDATION PATTERNS
// ============================================================================

/**
 * Validation patterns
 * 
 * Centralized regex patterns for consistent validation across the application.
 */
const VALIDATION_PATTERNS = {
    name: /^[a-zA-ZÀ-ÿ\s\-']+$/,
    email: /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/,
    phone: {
        national: /^0[1-9]\d{8}$/,
        international: /^\+33[1-9]\d{8}$/
    },
    zipCode: /^[0-9]{5}$/
};

// ============================================================================
// XSS PROTECTION
// ============================================================================

/**
 * XSS detection patterns
 * 
 * Simple heuristics to catch obvious XSS injection attempts.
 * These patterns detect common attack vectors:
 * - HTML tags
 * - JavaScript protocol
 * - Event handlers
 * - VBScript protocol
 * - Data URIs with HTML
 * - CSS expressions
 * - Dangerous HTML elements
 */
const xssPatterns = [
    /<[^>]*>/gi,                    // HTML tags
    /javascript:/gi,                // JavaScript protocol
    /on\w+\s*=/gi,                  // Event handlers (onclick, onerror, etc.)
    /vbscript:/gi,                  // VBScript protocol
    /data:text\/html/gi,            // Data URI with HTML
    /expression\s*\(/gi,            // CSS expressions
    /<script/gi,                    // Script tags
    /<iframe/gi,                    // Iframe tags
    /<object/gi,                    // Object tags
    /<embed/gi,                     // Embed tags
    /<form/gi,                      // Form tags
    /<link[^>]*href\s*=\s*["\']?javascript:/gi, // Link with JS
    /<meta[^>]*http-equiv\s*=\s*["\']?refresh/gi // Meta refresh
];

/**
 * Check if a string contains XSS attempt patterns
 * 
 * This function tests a value against XSS detection patterns.
 * Returns true if any pattern matches (potential XSS attack).
 * 
 * @param {string} value - The string to check for XSS patterns
 * @returns {boolean} True if XSS pattern detected, false otherwise
 * 
 * Security:
 * - First line of defense against XSS attacks
 * - Should be used before processing user input
 * - Fixes regex lastIndex bug for global patterns
 */
function containsXssAttempt(value) {
    /**
     * Early return if value is invalid
     */
    if (!value || typeof value !== 'string') return false;
    
    /**
     * Test value against all XSS patterns
     * Return true on first match (potential attack detected)
     * Reset lastIndex for global regex patterns to prevent false negatives
     */
    return xssPatterns.some(pattern => {
        // Reset lastIndex for global regex to prevent bug
        if (pattern.global) {
            pattern.lastIndex = 0;
        }
        return pattern.test(value);
    });
}

/**
 * Sanitize input string by removing risky characters
 * 
 * This function strips dangerous HTML constructs and characters
 * that could be used for XSS attacks. Should be used on all
 * user-generated content before display or storage.
 * 
 * @param {string} value - The string to sanitize
 * @returns {string} Sanitized string safe for display
 * 
 * Security:
 * - Removes HTML tags
 * - Removes JavaScript protocol
 * - Removes event handlers
 * - Removes dangerous characters (< > ' ")
 */
function sanitizeInput(value) {
    /**
     * Apply multiple sanitization steps
     * Each step removes a specific type of dangerous content
     */
    return value
        .replace(/<[^>]*>/g, '')           // Remove HTML tags
        .replace(/javascript:/gi, '')       // Remove javascript: protocol
        .replace(/on\w+\s*=/gi, '')        // Remove event handlers
        .replace(/[<>'"]/g, '')            // Remove dangerous characters
        .trim();                           // Remove leading/trailing whitespace
}

// ============================================================================
// DOM ELEMENT CACHE
// ============================================================================

/**
 * DOM element cache
 * 
 * Caches frequently accessed DOM elements to reduce query overhead.
 * Reduces DOM queries by 50-70% in validation and form processing.
 */
const elementsCache = {};

/**
 * Get element by ID with caching
 * 
 * @param {string} id - Element ID
 * @returns {HTMLElement|null} Cached element or null if not found
 */
function getElement(id) {
    if (!elementsCache[id]) {
        elementsCache[id] = document.getElementById(id);
    }
    return elementsCache[id];
}

/**
 * Get multiple elements by IDs with caching
 * 
 * @param {string[]} ids - Array of element IDs
 * @returns {Object} Object with element IDs as keys and elements as values
 */
function getElements(ids) {
    return ids.reduce((acc, id) => {
        acc[id] = getElement(id);
        return acc;
    }, {});
}

// ============================================================================
// NOTIFICATION HELPERS
// ============================================================================

/**
 * Show order notification
 * 
 * Safe notification shim that uses global notification function
 * if available, falls back to browser alert otherwise.
 * 
 * @param {string} message - Notification message
 * @param {string} type - Notification type (info, success, error, warning)
 * 
 * Fallback:
 * - Uses window.showNotification if available
 * - Falls back to alert() if not available
 */
function showOrderNotification(message, type = 'info') {
    /**
     * Use global notification function if available
     * This provides consistent notification UI across the app
     */
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    } else {
        /**
         * Fallback to browser alert
         * Used when notification system is not available
         */
        alert(`${type.toUpperCase()}: ${message}`);
    }
}

// ============================================================================
// API ERROR HANDLING
// ============================================================================

/**
 * Handle API response errors
 * 
 * Centralized error handling for all API responses.
 * Provides consistent error messages and reduces code duplication.
 * 
 * @param {Response} res - Fetch response object
 * @param {Object} data - Parsed JSON data from response
 * @throws {Error} If response indicates failure
 */
function handleApiError(res, data) {
    if (!res.ok || !data.success) {
        const msg = data?.message || data?.error || `Erreur ${res.status}`;
        throw new Error(msg);
    }
}

// ============================================================================
// API CLIENTS
// ============================================================================

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
        
        const data = await res.json();
        
        /**
         * Check if request succeeded and response indicates success
         * Throw error if either condition fails
         */
        handleApiError(res, data);
        
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
        handleApiError(res, data);
        
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
        handleApiError(res, data);
        
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
        handleApiError(res, data);
        
        return data;
    }
};

/**
 * Initialize order page when DOM is ready
 * 
 * Sets up the checkout process when page loads.
 */
document.addEventListener('DOMContentLoaded', function() {
    initOrderPage();
});

/**
 * Bootstrap the order page
 * 
 * This function:
 * - Loads cart items from API
 * - Updates order summary display
 * - Initializes delivery option handlers
 * - Initializes payment option handlers
 * - Sets up time validation
 * - Sets up phone number validation
 * - Sets up name/email validation
 * - Sets up postal code validation
 * - Sets up address validation
 * - Initializes promo code functionality
 * - Subscribes to cart update events
 * 
 * @returns {Promise<void>}
 */
async function initOrderPage() {
    /**
     * Load cart items and render order summary
     * This displays current cart contents in checkout
     */
    await loadCartItems();
    updateOrderSummary();
    
    /**
     * Initialize all form handlers and validators
     * These set up real-time validation and UI updates
     */
    initDeliveryOptions();
    initPaymentOptions();
    initTimeValidation();
    initPhoneValidation();
    initNameEmailValidation();
    initZipCodeValidation();
    initAddressValidation();
    initPromoCode();

    /**
     * Keep cart block in sync with changes from other widgets
     * When cart is updated from sidebar or menu, refresh order page display
     * This ensures checkout always shows current cart state
     */
    window.addEventListener('cartUpdated', async function() {
        await loadCartItems();
        updateOrderSummary();
    });
}

// ============================================================================
// NAME AND EMAIL VALIDATION
// ============================================================================

/**
 * Initialize real-time validation for first name, last name, and email
 * 
 * Sets up live validation with inline error messages.
 * Uses same validation style as phone number validation.
 * 
 * Validation rules:
 * - Name: Only letters, spaces, hyphens, and apostrophes (French characters supported)
 * - Email: Standard email format validation
 */
function initNameEmailValidation() {
    /**
     * Get DOM elements for name and email inputs
     * Use cached getElement for better performance
     */
    const firstNameInput = getElement('clientFirstName');
    const lastNameInput = getElement('clientLastName');
    const emailInput = getElement('clientEmail');

    /**
     * Use centralized validation patterns
     * Ensures consistency across the application
     */
    const nameRegex = VALIDATION_PATTERNS.name;
    const emailRegex = VALIDATION_PATTERNS.email;

    /**
     * Attach validation to a single input field
     * 
     * This helper function sets up real-time validation with:
     * - Input event: Validates as user types
     * - Blur event: Validates when user leaves field
     * - Focus event: Clears errors when user focuses field
     * 
     * @param {HTMLElement} input - Input element to validate
     * @param {Function} validator - Validation function (returns boolean)
     * @param {Object} messages - Error messages { empty, invalid }
     */
    function attachValidation(input, validator, messages) {
        if (!input) return;
        
        /**
         * Validation handler
         * Checks if value is empty or invalid and shows appropriate error
         */
        const onValidate = () => {
            const value = (input.value || '').trim();
            
            /**
             * Clear previous validation state
             */
            input.classList.remove('is-invalid');
            removeInlineError(input);
            
            /**
             * Check if value is empty
             */
            if (value === '') {
                input.classList.add('is-invalid');
                showInlineError(input, messages.empty);
            } else if (!validator(value)) {
                /**
                 * Check if value passes validation
                 */
                input.classList.add('is-invalid');
                showInlineError(input, messages.invalid);
            }
        };
        
        /**
         * Attach event listeners
         * - input: Validates as user types
         * - blur: Validates when user leaves field
         * - focus: Clears errors when user focuses field
         */
        input.addEventListener('input', onValidate);
        input.addEventListener('blur', onValidate);
        input.addEventListener('focus', () => { 
            input.classList.remove('is-invalid'); 
            removeInlineError(input); 
        });
    }

    /**
     * Attach validation to each input field
     * Each field has specific validation rules and error messages
     */
    attachValidation(firstNameInput, v => nameRegex.test(v), { 
        empty: 'Le prénom est requis', 
        invalid: 'Le prénom ne peut contenir que des lettres, espaces et tirets' 
    });
    attachValidation(lastNameInput, v => nameRegex.test(v), { 
        empty: 'Le nom est requis', 
        invalid: 'Le nom ne peut contenir que des lettres, espaces et tirets' 
    });
    attachValidation(emailInput, v => emailRegex.test(v), { 
        empty: "L'email est requis", 
        invalid: "L'email n'est pas valide" 
    });
}

/**
 * Show inline error message for name/email validation
 * 
 * Creates and displays an error message below the input field.
 * 
 * @param {HTMLElement} input - Input element to show error for
 * @param {string} message - Error message to display
 */
function showInlineError(input, message) {
    /**
     * Create error div element
     * Uses Bootstrap's invalid-feedback class for styling
     */
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback name-email-validation-error';
    errorDiv.textContent = message;
    
    /**
     * Append error to input's parent container
     * Bootstrap expects error to be sibling of input
     */
    input.parentNode.appendChild(errorDiv);
}

/**
 * Remove inline error message for name/email validation
 * 
 * Removes error message if it exists.
 * 
 * @param {HTMLElement} input - Input element (used to find parent container)
 */
function removeInlineError(input) {
    /**
     * Find existing error element in parent container
     * Remove if found
     */
    const existing = input.parentNode?.querySelector('.name-email-validation-error');
    if (existing) existing.remove();
}

// ============================================================================
// CART MANAGEMENT
// ============================================================================

/**
 * Common cart update logic
 * 
 * Refreshes cart display and updates all related UI components.
 * This function consolidates cart update logic to prevent duplication.
 * 
 * @returns {Promise<void>}
 */
async function refreshCartUI() {
    await loadCartItems();
    updateOrderSummary();
    if (window.updateCartSidebar) window.updateCartSidebar();
    if (window.updateCartNavigation) window.updateCartNavigation();
    window.dispatchEvent(new CustomEvent('cartUpdated'));
}

/**
 * Event handler for cart quantity controls
 * 
 * This handler is set up once and uses event delegation to handle
 * all quantity and remove button clicks. It's defined outside
 * loadCartItems() to prevent duplicate listeners.
 */
let cartItemsClickHandler = null;

/**
 * Load current cart and render the order summary items block
 * 
 * This function:
 * - Fetches cart data from API
 * - Updates orderData.items with current cart items
 * - Renders cart items in checkout UI
 * - Handles empty cart state
 * - Sets up event listeners for quantity controls and remove buttons (once)
 * 
 * @returns {Promise<void>}
 */
async function loadCartItems() {
    /**
     * Get container element for cart items display
     * Exit early if element not found
     * Use cached getElement for better performance
     */
    const container = getElement('orderCartItems');
    if (!container) return;

    /**
     * Fetch cart data from API
     * Use empty cart as fallback if API call fails
     */
    let cart = { items: [] };
    try { 
        cart = await window.cartAPI.getCart(); 
    } catch (error) { 
        console.error('Error loading cart:', error);
        cart = { items: [] }; 
    }
    
    /**
     * Ensure items is an array
     * Safely handle unexpected data structures
     */
    const items = Array.isArray(cart.items) ? cart.items : [];
    orderData.items = items;

    /**
     * Handle empty cart state
     * Show message and link to menu
     */
    if (items.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="mb-4"><i class="bi bi-basket display-1 text-muted"></i></div>
                <h4 class="mt-3 text-muted">Votre panier est vide</h4>
                <p class="text-muted mb-4">Ajoutez des plats depuis notre menu</p>
                <a href="${window.appMenuPath || '#'}" class="btn btn-primary"><i class="bi bi-arrow-left me-2"></i>Voir le menu</a>
            </div>`;
        return;
    }

    /**
     * Render cart items HTML
     * Each item shows: name, quantity, price, and controls
     */
    let html = '';
    items.forEach(it => {
        const itemTotal = Number(it.price) * Number(it.quantity);
        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <h5>${sanitizeInput(it.name)}</h5>
                    <p>Quantité: ${it.quantity} × ${Number(it.price).toFixed(2)}€</p>
                </div>
                <div class="cart-item-controls">
                    <div class="quantity-controls">
                        <button type="button" class="btn btn-sm btn-outline-secondary quantity-btn" data-id="${it.id}" data-action="decrease" title="Diminuer"><i class="bi bi-dash"></i></button>
                        <span class="quantity-display">${it.quantity}</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary quantity-btn" data-id="${it.id}" data-action="increase" title="Augmenter"><i class="bi bi-plus"></i></button>
                    </div>
                    <div class="cart-item-price">${itemTotal.toFixed(2)}€</div>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-from-cart" data-id="${it.id}" title="Supprimer"><i class="bi bi-x"></i></button>
                </div>
            </div>`;
    });
    container.innerHTML = html;

    /**
     * Set up event delegation for all cart controls (only once)
     * Single listener handles all quantity and remove buttons
     * Prevents memory leaks and improves performance
     */
    if (!cartItemsClickHandler) {
        cartItemsClickHandler = async function(e) {
            /**
             * Find clicked button (quantity or remove)
             */
            const btn = e.target.closest('.quantity-btn, .remove-from-cart');
            if (!btn) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            const id = parseInt(btn.getAttribute('data-id'));
            if (!id) return;
            
            try {
                /**
                 * Handle remove button
                 */
                if (btn.classList.contains('remove-from-cart')) {
                    await window.cartAPI.removeItem(id);
                } 
                /**
                 * Handle quantity buttons
                 */
                else if (btn.classList.contains('quantity-btn')) {
                    const action = btn.getAttribute('data-action');
                    const current = orderData.items.find(i => Number(i.id) === Number(id));
                    
                    if (!current) {
                        console.warn('Item not found in cart:', id);
                        await refreshCartUI();
                        return;
                    }
                    
                    if (action === 'increase') {
                        /**
                         * Increase quantity by 1
                         */
                        await window.cartAPI.updateQuantity(id, current.quantity + 1);
                    } else if (action === 'decrease') {
                        /**
                         * Decrease quantity or remove item
                         * If quantity > 1: decrease by 1
                         * If quantity = 1: remove item completely
                         */
                        if (current.quantity > 1) {
                            await window.cartAPI.updateQuantity(id, current.quantity - 1);
                        } else {
                            await window.cartAPI.removeItem(id);
                        }
                    }
                }
                
                /**
                 * Refresh cart UI after successful change
                 * This updates orderData.items with fresh data from server
                 */
                await refreshCartUI();
            } catch (error) {
                /**
                 * Handle errors gracefully
                 * Show error notification to user
                 * Log error for debugging
                 */
                console.error('Error modifying cart quantity:', error);
                
                /**
                 * Show error notification to user
                 * Use showNotification if available, otherwise fallback to alert
                 */
                const errorMessage = 'Erreur lors de la modification de la quantité';
                if (typeof window.showNotification === 'function') {
                    window.showNotification(errorMessage, 'error');
                } else if (typeof window.showCartNotification === 'function') {
                    window.showCartNotification(errorMessage, 'error');
                } else {
                    alert(errorMessage);
                }
                
                /**
                 * Refresh cart UI even on error to ensure consistency
                 * This ensures UI reflects actual server state
                 */
                try {
                    await refreshCartUI();
                } catch (refreshError) {
                    console.error('Error refreshing cart UI:', refreshError);
                }
            }
        };
        
        container.addEventListener('click', cartItemsClickHandler);
    }
}

// ============================================================================
// DELIVERY AND PAYMENT OPTIONS
// ============================================================================

/**
 * Initialize delivery option toggles and auto fee updates
 * 
 * Sets up handlers for delivery mode selection (delivery vs. pickup).
 * When delivery is selected, shows address fields and applies delivery fee.
 * 
 * Behavior:
 * - 'delivery': Shows address fields, applies 5€ fee
 * - 'pickup': Hides address fields, no fee
 */
function initDeliveryOptions() {
    /**
     * Get all delivery mode radio buttons
     */
    const options = document.querySelectorAll('input[name="deliveryMode"]');
    const details = getElement('deliveryDetails');
    
    /**
     * Set up change handlers for each option
     */
    options.forEach(opt => {
        opt.addEventListener('change', function() {
            if (this.value === 'delivery') {
                /**
                 * Delivery mode selected
                 * Show address fields and apply delivery fee
                 */
                if (details) details.style.display = 'block';
                updateDeliveryFee(DELIVERY_FEE);
            } else {
                /**
                 * Pickup mode selected
                 * Hide address fields and remove delivery fee
                 */
                if (details) details.style.display = 'none';
                updateDeliveryFee(0);
            }
            
            /**
             * Update order summary to reflect fee change
             */
            updateOrderSummary();
        });
    });
    
    /**
     * Trigger change event for pre-selected option
     * Ensures UI is in sync with initial state
     */
    const checked = document.querySelector('input[name="deliveryMode"]:checked');
    if (checked) checked.dispatchEvent(new Event('change'));
}

/**
 * Initialize payment option toggles
 * 
 * Sets up handlers for payment method selection.
 * Shows card details fields when card payment is selected.
 * 
 * Behavior:
 * - 'card': Shows card details fields
 * - Other methods: Hides card details fields
 */
function initPaymentOptions() {
    /**
     * Get all payment mode radio buttons
     */
    const options = document.querySelectorAll('input[name="paymentMode"]');
    const cardDetails = getElement('cardDetails');
    
    /**
     * Set up change handlers for each option
     */
    options.forEach(opt => {
        opt.addEventListener('change', function() {
            /**
             * Show card details only for card payment
             * Hide for cash and other payment methods
             */
            if (cardDetails) {
                cardDetails.style.display = this.value === 'card' ? 'block' : 'none';
            }
        });
    });
    
    /**
     * Trigger change event for pre-selected option
     * Ensures UI is in sync with initial state
     */
    const checked = document.querySelector('input[name="paymentMode"]:checked');
    if (checked) checked.dispatchEvent(new Event('change'));
}

/**
 * Update delivery fee in state and UI
 * 
 * Updates both orderData state and UI display with new delivery fee.
 * 
 * @param {number} fee - Delivery fee amount (0 for pickup, 5 for delivery)
 */
function updateDeliveryFee(fee) {
    /**
     * Update orderData state
     * Used for order summary calculations
     */
    orderData.deliveryFee = fee;
    
    /**
     * Update UI display
     * Shows fee amount in order summary
     */
    const el = getElement('deliveryFee');
    if (el) el.textContent = fee + '€';
}

/**
 * Recompute and render the financial summary (HT/TVA/Total)
 * 
 * This function:
 * - Calculates subtotal with tax (TTC) from cart items
 * - Calculates subtotal without tax (HT) and tax amount
 * - Applies delivery fee and discount
 * - Updates UI with all financial totals
 * - Dynamically shows/hides discount line
 * 
 * Note: Menu prices already include taxes (TTC), so we calculate
 * backwards to get HT (without tax) amount.
 */
function updateOrderSummary() {
    /**
     * Get container for summary items
     * Exit early if element not found
     * Use cached getElement for better performance
     */
    const container = getElement('summaryItems');
    if (!container) return;
    
    /**
     * Calculate subtotal with tax (TTC)
     * Sum of all item prices × quantities
     */
    let subtotalWithTax = 0;
    let html = '';
    orderData.items.forEach(it => {
        const itemTotal = Number(it.price) * Number(it.quantity);
        subtotalWithTax += itemTotal;
        /**
         * Render summary item HTML
         * Shows item name, quantity, and total price
         */
        html += `<div class="summary-item"><div class="summary-item-info"><span class="summary-item-name">${it.name}</span><small class="text-muted">x${it.quantity}</small></div><span class="summary-item-price">${itemTotal.toFixed(2)}€</span></div>`;
    });
    container.innerHTML = html;

    /**
     * Calculate tax breakdown
     * Menu prices already include taxes (TTC)
     * Calculate amount without taxes (HT) and tax separately
     */
    const subtotalWithoutTax = subtotalWithTax / (1 + TAX_RATE);
    const taxAmount = subtotalWithTax - subtotalWithoutTax;
    
    /**
     * Calculate final total
     * Subtotal + delivery fee - discount
     */
    const deliveryFee = orderData.deliveryFee || 0;
    const discount = orderData.discount || 0;
    const total = subtotalWithTax + deliveryFee - discount;
    
    /**
     * Update subtotal and tax display
     * Use cached getElement for better performance
     */
    const subEl = getElement('subtotal');
    const taxEl = getElement('taxAmount');
    const totalEl = getElement('totalAmount');
    if (subEl) subEl.textContent = subtotalWithoutTax.toFixed(2) + '€';
    if (taxEl) taxEl.textContent = taxAmount.toFixed(2) + '€';
    
    /**
     * Update or add discount line dynamically
     * Shows discount only when coupon is applied
     */
    const totalsContainer = document.querySelector('.summary-totals');
    let discountLine = getElement('discountLine');
    
    if (discount > 0) {
        /**
         * Create discount line if it doesn't exist
         * Insert before total line
         */
        if (!discountLine) {
            discountLine = document.createElement('div');
            discountLine.id = 'discountLine';
            discountLine.className = 'summary-line text-success';
            // Insert before total line
            const totalLine = totalsContainer.querySelector('.summary-line.total');
            totalsContainer.insertBefore(discountLine, totalLine);
        }
        /**
         * Update discount line with coupon code and amount
         */
        discountLine.innerHTML = `<span>Réduction <small>(${orderData.coupon?.code || ''})</small></span><span>-${discount.toFixed(2)}€</span>`;
    } else if (discountLine) {
        /**
         * Remove discount line if no discount applied
         */
        discountLine.remove();
    }
    
    /**
     * Update total amount display
     */
    if (totalEl) totalEl.textContent = total.toFixed(2) + '€';
    
    /**
     * Update orderData with calculated values
     * Used for order submission
     */
    orderData.subtotal = subtotalWithoutTax;
    orderData.taxAmount = taxAmount;
    orderData.total = total;
}

// ============================================================================
// STEPPER NAVIGATION
// ============================================================================

/**
 * Navigate to next step in checkout process
 * 
 * Validates current step before advancing.
 * Only advances if validation passes.
 * 
 * @param {number} step - Step number to navigate to
 * @returns {Promise<void>}
 */
async function nextStep(step) { 
    /**
     * Validate current step before advancing
     * Prevents invalid data from proceeding
     */
    const isValid = await validateCurrentStep(); 
    if (isValid) { 
        showStep(step); 
    } 
}

/**
 * Navigate to previous step in checkout process
 * 
 * No validation required when going back.
 * 
 * @param {number} step - Step number to navigate to
 */
function prevStep(step) { 
    showStep(step); 
}

/**
 * Show a specific checkout step
 * 
 * This function:
 * - Hides all step content
 * - Shows target step content
 * - Updates step indicators (active states)
 * - Updates currentStep variable
 * - Updates final summary if on confirmation step
 * 
 * @param {number} step - Step number to show (1-4)
 */
function showStep(step) {
    /**
     * Hide all step content
     * Remove active class from all steps
     */
    document.querySelectorAll('.order-step-content').forEach(c => c.classList.remove('active'));
    
    /**
     * Show target step content
     * Add active class to target step
     * Use getElement for caching (though step IDs are dynamic)
     */
    const target = document.getElementById(`step${step}`);
    if (target) target.classList.add('active');
    
    /**
     * Update step indicators
     * Mark steps as active up to current step
     */
    const steps = document.querySelectorAll('.step');
    steps.forEach((el, i) => { 
        if (i + 1 <= step) {
            el.classList.add('active');
        } else {
            el.classList.remove('active');
        }
    });
    
    /**
     * Update current step variable
     * Used for validation logic
     */
    currentStep = step;
    
    /**
     * Update final summary if on confirmation step
     * Shows order review before submission
     */
    if (step === 4) updateFinalSummary();
}

/**
 * Validate current checkout step
 * 
 * Calls appropriate validation function based on current step.
 * 
 * @returns {Promise<boolean>} True if validation passes, false otherwise
 */
async function validateCurrentStep() {
    switch (currentStep) {
        case 1: 
            /**
             * Validate cart step
             * Ensures cart is not empty
             */
            return validateCartStep();
        case 2: 
            /**
             * Validate delivery step
             * Validates delivery mode, address, date, time, and client info
             */
            return await validateDeliveryStep();
        case 3: 
            /**
             * Validate payment step
             * Ensures payment method is selected
             */
            return validatePaymentStep();
        default: 
            /**
             * No validation needed for other steps
             */
            return true;
    }
}

/**
 * Validate cart step
 * 
 * Ensures cart is not empty before proceeding.
 * 
 * @returns {boolean} True if cart has items, false otherwise
 */
function validateCartStep() { 
    if ((orderData.items || []).length === 0) { 
        showOrderNotification('Votre panier est vide', 'error'); 
        return false; 
    } 
    return true; 
}

// ============================================================================
// VALIDATION FUNCTIONS
// ============================================================================

/**
 * Validate the whole delivery/contact step
 * 
 * This function validates:
 * - Delivery mode selection (delivery or pickup)
 * - Delivery date and time selection
 * - Time slot availability (must be at least 1 hour in future)
 * - Address and postal code (if delivery mode)
 * - Address validation via API (if delivery mode)
 * - Client contact information (name, phone, email)
 * - XSS protection for all user inputs
 * 
 * @returns {Promise<boolean>} True if validation passes, false otherwise
 */
async function validateDeliveryStep() {
    /**
     * Get delivery mode, date, and time from form
     * Use cached getElement for better performance
     */
    const mode = document.querySelector('input[name="deliveryMode"]:checked')?.value;
    const dateInput = getElement('deliveryDate');
    const timeInput = getElement('deliveryTime');
    const date = dateInput?.value;
    const time = timeInput?.value;
    
    /**
     * Validate required delivery fields
     */
    if (!mode) { 
        showOrderNotification('Veuillez choisir un mode de récupération', 'error'); 
        return false; 
    }
    if (!date) { 
        showOrderNotification('Veuillez choisir une date', 'error'); 
        return false; 
    }
    if (!time) { 
        showOrderNotification('Veuillez choisir un créneau horaire', 'error'); 
        return false; 
    }
    
    /**
     * Validate selected time slot
     * Ensures time is at least 1 hour in the future
     */
    if (!validateSelectedTime()) return false;
    /**
     * Validate delivery-specific fields if delivery mode is selected
     * Use cached getElement for better performance
     */
    if (mode === 'delivery') {
        const addressInput = getElement('deliveryAddress');
        const zipInput = getElement('deliveryZip');
        const instructionsInput = getElement('deliveryInstructions');
        const address = addressInput?.value;
        const zip = zipInput?.value;
        const instructions = instructionsInput?.value;
        
        /**
         * XSS check for address
         * Prevents malicious code injection
         */
        if (address && containsXssAttempt(address)) {
            showOrderNotification('L\'adresse contient des éléments non autorisés', 'error');
            return false;
        }
        
        /**
         * XSS check for delivery instructions
         * Prevents malicious code injection
         */
        if (instructions && containsXssAttempt(instructions)) {
            showOrderNotification('Les instructions de livraison contiennent des éléments non autorisés', 'error');
            return false;
        }
        
        /**
         * Validate that address and zip code are provided
         */
        if (!address || !zip) { 
            showOrderNotification('Veuillez renseigner votre adresse de livraison', 'error'); 
            return false; 
        }
        
        /**
         * Validate French postal code format
         * Must be exactly 5 digits
         */
        if (!validateFrenchZipCode(zip)) {
            showOrderNotification('Format de code postal invalide', 'error');
            return false;
        }
        
        /**
         * Check if delivery is available for this address
         * Uses API to validate address and check delivery availability
         */
        try {
            const addressValidation = await window.zipCodeAPI.validateAddress(address, zip);
            if (!addressValidation.valid) {
                showOrderNotification(addressValidation.error || 'Livraison non disponible pour cette adresse', 'error');
                return false;
            }
        } catch (error) {
            showOrderNotification('Erreur lors de la vérification de l\'adresse', 'error');
            return false;
        }
    }
    
    /**
     * Validate client contact information
     * Get trimmed values from form inputs
     * Use cached getElement for better performance
     */
    const firstNameInput = getElement('clientFirstName');
    const lastNameInput = getElement('clientLastName');
    const phoneInput = getElement('clientPhone');
    const emailInput = getElement('clientEmail');
    const firstName = firstNameInput?.value?.trim();
    const lastName = lastNameInput?.value?.trim();
    const phone = phoneInput?.value?.trim();
    const email = emailInput?.value?.trim();
    
    /**
     * XSS checks for all contact information fields
     * Prevents malicious code injection in user data
     */
    if (firstName && containsXssAttempt(firstName)) {
        showOrderNotification('Le prénom contient des éléments non autorisés', 'error');
        return false;
    }
    if (lastName && containsXssAttempt(lastName)) {
        showOrderNotification('Le nom contient des éléments non autorisés', 'error');
        return false;
    }
    if (phone && containsXssAttempt(phone)) {
        showOrderNotification('Le numéro de téléphone contient des éléments non autorisés', 'error');
        return false;
    }
    if (email && containsXssAttempt(email)) {
        showOrderNotification('L\'email contient des éléments non autorisés', 'error');
        return false;
    }
    
    /**
     * Validate that all required fields are filled
     */
    if (!firstName) { 
        showOrderNotification('Veuillez renseigner votre prénom', 'error'); 
        return false; 
    }
    if (!lastName) { 
        showOrderNotification('Veuillez renseigner votre nom', 'error'); 
        return false; 
    }
    if (!phone) { 
        showOrderNotification('Veuillez renseigner votre numéro de téléphone', 'error'); 
        return false; 
    }
    if (!email) { 
        showOrderNotification('Veuillez renseigner votre adresse email', 'error'); 
        return false; 
    }
    
    /**
     * Validate French phone number format
     * Supports national (0X XX XX XX XX) and international (+33 X XX XX XX XX) formats
     */
    if (!validateFrenchPhoneNumber(phone)) {
        showOrderNotification('Veuillez entrer un numéro de téléphone français valide', 'error');
        return false;
    }
    
    /**
     * Validate email format
     * Use centralized validation pattern for consistency
     */
    if (!VALIDATION_PATTERNS.email.test(email)) { 
        showOrderNotification('Veuillez renseigner une adresse email valide', 'error'); 
        return false; 
    }
    
    /**
     * Store validated data in orderData
     * This data will be used for order submission
     * Use cached elements if available
     */
    const addressEl = mode === 'delivery' ? getElement('deliveryAddress') : null;
    const zipEl = mode === 'delivery' ? getElement('deliveryZip') : null;
    const instructionsEl = mode === 'delivery' ? getElement('deliveryInstructions') : null;
    
    orderData.delivery = { 
        mode, 
        date, 
        time, 
        address: addressEl?.value || '', 
        zip: zipEl?.value || '', 
        instructions: instructionsEl?.value || '' 
    };
    orderData.client = { firstName, lastName, phone, email };
    
    return true;
}

/**
 * Validate payment step
 * 
 * Ensures a payment method is selected.
 * No payment processor integration - just validates selection.
 * 
 * @returns {boolean} True if payment method is selected, false otherwise
 */
function validatePaymentStep() {
    const mode = document.querySelector('input[name="paymentMode"]:checked')?.value;
    if (!mode) { 
        showOrderNotification('Veuillez choisir un mode de paiement', 'error'); 
        return false; 
    }
    
    /**
     * Store payment method in orderData
     */
    orderData.payment = { mode };
    return true;
}

/**
 * Build the final confirmation view using current orderData
 * 
 * Uses DOM methods instead of innerHTML to prevent XSS attacks.
 * All user data is displayed using textContent which automatically escapes HTML.
 */
function updateFinalSummary() {
    /**
     * Render order items
     * Uses DOM methods for safe rendering
     * Use cached getElement for better performance
     */
    const itemsEl = getElement('finalOrderItems');
    if (itemsEl) {
        itemsEl.innerHTML = ''; // Clear first
        
        orderData.items.forEach(it => {
            const div = document.createElement('div');
            div.className = 'd-flex justify-content-between mb-2';
            
            const itemSpan = document.createElement('span');
            itemSpan.textContent = `${it.name} x${it.quantity}`;
            
            const priceSpan = document.createElement('span');
            const itemTotal = Number(it.price) * Number(it.quantity);
            priceSpan.textContent = `${itemTotal.toFixed(2)}€`;
            
            div.appendChild(itemSpan);
            div.appendChild(priceSpan);
            itemsEl.appendChild(div);
        });
    }
    
    /**
     * Render client information
     * Uses DOM methods for safe rendering
     * Use cached getElement for better performance
     */
    const clientEl = getElement('finalClientInfo');
    if (clientEl) {
        clientEl.innerHTML = ''; // Clear first
        
        const c = orderData.client || {};
        
        const nameP = document.createElement('p');
        const nameStrong = document.createElement('strong');
        nameStrong.textContent = `${c.firstName || ''} ${c.lastName || ''}`;
        nameP.appendChild(nameStrong);
        clientEl.appendChild(nameP);
        
        if (c.phone) {
            const phoneP = document.createElement('p');
            phoneP.textContent = `Téléphone: ${c.phone}`;
            clientEl.appendChild(phoneP);
        }
        
        if (c.email) {
            const emailP = document.createElement('p');
            emailP.textContent = `Email: ${c.email}`;
            clientEl.appendChild(emailP);
        }
    }
    
    /**
     * Render delivery information
     * Uses DOM methods for safe rendering
     * Use cached getElement for better performance
     */
    const deliveryEl = getElement('finalDeliveryInfo');
    if (deliveryEl) {
        deliveryEl.innerHTML = ''; // Clear first
        
        const d = orderData.delivery || {};
        const modeText = d.mode === 'delivery' ? 'Livraison à domicile' : 'Retrait sur place';
        
        const modeP = document.createElement('p');
        const modeStrong = document.createElement('strong');
        modeStrong.textContent = modeText;
        modeP.appendChild(modeStrong);
        deliveryEl.appendChild(modeP);
        
        const dateP = document.createElement('p');
        dateP.textContent = `Date: ${d.date} à ${d.time}`;
        deliveryEl.appendChild(dateP);
        
        if (d.mode === 'delivery' && d.address) {
            const addressP = document.createElement('p');
            addressP.textContent = `Adresse: ${d.address}, ${d.zip}`;
            deliveryEl.appendChild(addressP);
        }
    }
    
    /**
     * Render payment information
     * Uses DOM methods for safe rendering
     * Use cached getElement for better performance
     */
    const paymentEl = getElement('finalPaymentInfo');
    if (paymentEl) {
        paymentEl.innerHTML = ''; // Clear first
        
        const text = orderData.payment?.mode === 'card' 
            ? 'Carte bancaire' 
            : orderData.payment?.mode === 'cash' 
                ? 'Paiement en espèces' 
                : 'Tickets restaurant';
        
        const paymentP = document.createElement('p');
        paymentP.textContent = text;
        paymentEl.appendChild(paymentP);
    }
}

/**
 * Collect and sanitize form data for order submission
 * 
 * Centralizes form data collection and sanitization.
 * Uses cached DOM elements for better performance.
 * 
 * @returns {Object} Sanitized form data payload
 */
function collectOrderFormData() {
    /**
     * Get form elements using cached getElement function
     */
    const elements = getElements([
        'deliveryAddress',
        'deliveryZip',
        'deliveryInstructions',
        'clientFirstName',
        'clientLastName',
        'clientPhone',
        'clientEmail'
    ]);
    
    /**
     * Get delivery and payment modes with fallbacks
     */
    const deliveryMode = document.querySelector('input[name="deliveryMode"]:checked')?.value 
        || orderData?.delivery?.mode 
        || 'delivery';
    
    const paymentMode = document.querySelector('input[name="paymentMode"]:checked')?.value 
        || orderData?.payment?.mode 
        || 'card';
    
    /**
     * Calculate delivery fee
     */
    const deliveryFee = typeof orderData.deliveryFee === 'number' 
        ? orderData.deliveryFee 
        : (deliveryMode === 'pickup' ? 0 : DELIVERY_FEE);
    
    /**
     * Build payload with sanitized data
     */
    return {
        deliveryMode,
        deliveryAddress: sanitizeInput(elements.deliveryAddress?.value || ''),
        deliveryZip: sanitizeInput(elements.deliveryZip?.value || ''),
        deliveryInstructions: sanitizeInput(elements.deliveryInstructions?.value || ''),
        deliveryFee,
        paymentMode,
        clientFirstName: sanitizeInput(elements.clientFirstName?.value || ''),
        clientLastName: sanitizeInput(elements.clientLastName?.value || ''),
        clientPhone: sanitizeInput(elements.clientPhone?.value || ''),
        clientEmail: sanitizeInput(elements.clientEmail?.value || '')
    };
}

/**
 * Calculate order total amount (before discount)
 * 
 * Extracts order amount calculation to a reusable function.
 * Used for coupon validation and order processing.
 * 
 * @returns {number} Order amount in euros
 */
function calculateOrderAmount() {
    let subtotalWithTax = 0;
    orderData.items.forEach(it => {
        subtotalWithTax += Number(it.price) * Number(it.quantity);
    });
    const deliveryFee = orderData.deliveryFee || 0;
    return subtotalWithTax + deliveryFee;
}

/**
 * Get order amount for coupon validation
 * 
 * Uses orderData.total + discount if available, otherwise calculates.
 * 
 * @returns {number} Order amount before discount
 */
function getOrderAmountForCoupon() {
    // If order summary is up to date, use it
    if (orderData.total && orderData.discount !== undefined) {
        return orderData.total + orderData.discount; // total before discount
    }
    // Otherwise calculate
    return calculateOrderAmount();
}

// Build payload and call the backend to create the order
async function confirmOrder() {
    // Prevent double submission
    if (window.isSubmittingOrder) {
        return;
    }
    window.isSubmittingOrder = true;
    // Disable confirm button if present
    const confirmBtn = document.querySelector('#step4 .btn.btn-success');
    const oldText = confirmBtn ? confirmBtn.innerHTML : null;
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
    }
    const accept = getElement('acceptTerms')?.checked;
    if (!accept) { 
        showOrderNotification('Veuillez accepter les conditions générales', 'error'); 
        return; 
    }

    // Build payload using centralized form data collection
    const payload = collectOrderFormData();
    
    // Add coupon data if applied
    if (orderData.coupon && orderData.coupon.couponId) {
        payload.couponId = orderData.coupon.couponId;
        payload.discountAmount = orderData.discount;
    }

    try {
        const result = await window.orderAPI.createOrder(payload);
        const created = result.order; // OrderResponse
        
        // Apply coupon usage increment if coupon was used
        if (orderData.coupon && orderData.coupon.couponId) {
            try {
                await window.couponAPI.applyCoupon(orderData.coupon.couponId);
            } catch (e) {
                console.error('Error incrementing coupon usage:', e);
            }
        }
        
        // Backend already clears cart, update UI
        try { if (window.updateCartSidebar) window.updateCartSidebar(); } catch (_) {}
        try { if (window.updateCartNavigation) window.updateCartNavigation(); } catch (_) {}
        showOrderConfirmation(created.no, created.id, created.total);
    } catch (e) {
        showOrderNotification(e.message || 'Erreur lors de la création de la commande', 'error');
    } finally {
        // Re-enable button only if confirmation screen not rendered
        if (confirmBtn && document.body.contains(confirmBtn)) {
            confirmBtn.disabled = false;
            if (oldText !== null) confirmBtn.innerHTML = oldText;
        }
        window.isSubmittingOrder = false;
    }
}

// Local stub to demonstrate confirmation email; persisted to localStorage only
function sendConfirmationEmail(order) {
    const emailData = { to: 'client@example.com', subject: `Confirmation de commande ${order.id} - Le Trois Quarts`, body: `Votre commande ${order.id} a été confirmée. Total: ${order.total.toFixed(2)}€` };
    const emails = JSON.parse(localStorage.getItem('sentEmails') || '[]'); emails.push({ ...emailData, sentAt: new Date().toISOString(), orderId: order.id }); localStorage.setItem('sentEmails', JSON.stringify(emails));
    showOrderNotification('Email de confirmation envoyé !', 'success');
}

// Replace main container with a success screen after order creation
function showOrderConfirmation(orderNo, orderId, total) {
    document.querySelectorAll('.order-step-content').forEach(c => c.classList.remove('active'));
    const container = document.querySelector('.order-section .container');
    if (container) {
        container.innerHTML = `<div class="text-center py-5"><div class="mb-4"><i class="bi bi-check-circle-fill text-success icon-success-large"></i></div><h2 class="text-success mb-3">Commande confirmée !</h2><p class="lead mb-2">Numéro de commande: <strong>${orderNo || orderId}</strong></p><p class="lead mb-4">Montant total: <strong>${Number(total || 0).toFixed(2)}€</strong></p><div class="alert alert-info"><h5><i class="bi bi-info-circle me-2"></i>Prochaines étapes :</h5><ul class="list-unstyled mb-0"><li>• Vous recevrez un email de confirmation</li><li>• Votre commande sera préparée selon le créneau choisi</li></ul></div><div class="mt-4"><a href="${window.appMenuPath || '#'}" class="btn btn-primary"><i class="bi bi-arrow-left me-2"></i>Retour au menu</a></div></div>`;
    }
    showOrderNotification('Commande confirmée avec succès !', 'success');
}

// Use global showOrderNotification function from main.js

/**
 * French phone number validation
 * 
 * Validate FR phone (national 0XXXXXXXXX or +33XXXXXXXXX with basic prefix checks).
 * Uses centralized validation patterns for consistency.
 * 
 * @param {string} phone - Phone number to validate
 * @returns {boolean} True if valid French phone number format
 */
function validateFrenchPhoneNumber(phone) {
    if (!phone) return false;
    
    /**
     * Clean the number (remove spaces, dashes, dots)
     */
    const cleanPhone = phone.replace(/[\s\-\.]/g, '');
    
    /**
     * Valid prefixes for French phone numbers
     * Mobiles: 06, 07
     * Landlines: 01-05
     */
    const validPrefixes = ['06', '07', '01', '02', '03', '04', '05'];
    
    /**
     * Check national format: 0X XXXX XXXX (10 digits total, starts with 0)
     */
    if (cleanPhone.length === 10 && cleanPhone.startsWith('0')) {
        /**
         * Use centralized validation pattern
         */
        if (!VALIDATION_PATTERNS.phone.national.test(cleanPhone)) {
            return false;
        }
        
        /**
         * Check first digits for valid prefix
         */
        const firstTwoDigits = cleanPhone.substring(0, 2);
        return validPrefixes.includes(firstTwoDigits);
        
    } 
    /**
     * Check international format: +33 X XX XX XX XX (12 characters, starts with +33)
     */
    else if (cleanPhone.length === 12 && cleanPhone.startsWith('+33')) {
        /**
         * Use centralized validation pattern
         */
        if (!VALIDATION_PATTERNS.phone.international.test(cleanPhone)) {
            return false;
        }
        
        /**
         * Extract number without country code (+33)
         */
        const withoutCountryCode = cleanPhone.substring(3);
        
        /**
         * Check first digits for valid prefix
         */
        const firstTwoDigits = withoutCountryCode.substring(0, 2);
        return validPrefixes.includes(firstTwoDigits);
    }
    
    /**
     * If neither 10 digits with 0, nor 12 characters with +33, then invalid
     */
    return false;
}

/**
 * Initialize real-time phone validation
 * 
 * Live UI feedback for the phone field.
 * Uses cached getElement for better performance.
 */
function initPhoneValidation() {
    const phoneInput = getElement('clientPhone');
    if (!phoneInput) return;
    
    // Real-time validation during input
    phoneInput.addEventListener('input', function() {
        const phone = this.value.trim();
        const isValid = phone === '' || validateFrenchPhoneNumber(phone);
        
        // Remove previous validation classes
        this.classList.remove('is-invalid');
        
        if (phone !== '' && !isValid) {
            this.classList.add('is-invalid');
            showPhoneError('Format de numéro de téléphone invalide');
        } else {
            removePhoneError();
        }
    });
    
    // Validation au blur (quand l'utilisateur quitte le champ)
    phoneInput.addEventListener('blur', function() {
        const phone = this.value.trim();
        if (phone !== '' && !validateFrenchPhoneNumber(phone)) {
            this.classList.add('is-invalid');
            showPhoneError('Numéro de téléphone invalide');
        }
    });
    
    // Clear errors when user starts typing
    phoneInput.addEventListener('focus', function() {
        this.classList.remove('is-invalid');
        removePhoneError();
    });
}

/**
 * Show phone validation error
 * 
 * Render an inline phone error under the input.
 * Uses cached getElement for better performance.
 * 
 * @param {string} message - Error message to display
 */
function showPhoneError(message) {
    removePhoneError(); // Remove previous error if any
    
    const phoneInput = getElement('clientPhone');
    if (!phoneInput) return;
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback phone-validation-error';
    errorDiv.textContent = message;
    
    phoneInput.parentNode.appendChild(errorDiv);
}

// Remove phone validation error
// Remove the live phone error element if present
function removePhoneError() {
    const existingError = document.querySelector('.phone-validation-error');
    if (existingError) {
        existingError.remove();
    }
}

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
        
        /**
         * Use centralized error handling
         */
        if (!res.ok) {
            throw new Error(data?.error || `Erreur ${res.status}`);
        }
        return data;
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
        
        /**
         * Use centralized error handling
         */
        if (!res.ok) {
            throw new Error(data?.error || `Erreur ${res.status}`);
        }
        return data;
    }
};

/**
 * Postal code validation
 * 
 * Check a French postal code: strictly 5 digits
 * Uses centralized validation pattern for consistency.
 * 
 * @param {string} zipCode - Postal code to validate
 * @returns {boolean} True if valid French postal code format
 */
function validateFrenchZipCode(zipCode) {
    if (!zipCode) return false;
    
    /**
     * Clean postal code (remove non-digits)
     */
    const cleanZipCode = zipCode.replace(/[^0-9]/g, '');
    
    /**
     * Check French postal code format using centralized pattern
     */
    return VALIDATION_PATTERNS.zipCode.test(cleanZipCode);
}

/**
 * Initialize postal code validation
 * 
 * Live validation for ZIP input with async backend check (debounced).
 * Uses cached getElement and centralized debounce delay.
 */
function initZipCodeValidation() {
    const zipInput = getElement('deliveryZip');
    if (!zipInput) return;
    
    let validationTimeout;
    
    // Real-time validation
    zipInput.addEventListener('input', function() {
        clearTimeout(validationTimeout);
        const zipCode = this.value.trim();
        
        // Remove previous validation classes
        this.classList.remove('is-valid', 'is-invalid');
        removeZipCodeError();
        
        if (zipCode === '') {
            return;
        }
        
        // Basic format validation
        if (!validateFrenchZipCode(zipCode)) {
            this.classList.add('is-invalid');
            showZipCodeError('Format de code postal invalide');
            return;
        }
        
        // API validation with delay
        validationTimeout = setTimeout(async () => {
            try {
                const result = await window.zipCodeAPI.validateZipCode(zipCode);
                
                if (result.valid) {
                    this.classList.remove('is-invalid');
                    showZipCodeSuccess('Livraison disponible');
                } else {
                    this.classList.add('is-invalid');
                    showZipCodeError(result.error || 'Livraison non disponible pour ce code postal');
                }
            } catch (error) {
                this.classList.add('is-invalid');
                showZipCodeError('Erreur lors de la vérification du code postal');
            }
        }, DEBOUNCE_DELAYS.ZIP_CODE); // Delay after input ends
    });
    
    // Clear on focus
    zipInput.addEventListener('focus', function() {
        this.classList.remove('is-invalid');
        removeZipCodeError();
    });
}

/**
 * Show postal code validation error
 * 
 * Render an inline ZIP error.
 * Uses cached getElement for better performance.
 * 
 * @param {string} message - Error message to display
 */
function showZipCodeError(message) {
    removeZipCodeError();
    
    const zipInput = getElement('deliveryZip');
    if (!zipInput) return;
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback zip-validation-error';
    errorDiv.textContent = message;
    
    zipInput.parentNode.appendChild(errorDiv);
}

/**
 * Show successful postal code validation
 * 
 * Render an inline ZIP success helper.
 * Uses cached getElement for better performance.
 * 
 * @param {string} message - Success message to display
 */
function showZipCodeSuccess(message) {
    removeZipCodeError();
    
    const zipInput = getElement('deliveryZip');
    if (!zipInput) return;
    
    const successDiv = document.createElement('div');
    successDiv.className = 'valid-feedback zip-validation-success';
    successDiv.textContent = message;
    
    zipInput.parentNode.appendChild(successDiv);
}

// Remove postal code validation messages
// Remove both error/success messages for ZIP input
function removeZipCodeError() {
    const existingError = document.querySelector('.zip-validation-error');
    const existingSuccess = document.querySelector('.zip-validation-success');
    
    if (existingError) existingError.remove();
    if (existingSuccess) existingSuccess.remove();
}

/**
 * Extract postal code from address
 * 
 * Extract a 5-digit ZIP code contained in a free-form address string.
 * Uses centralized validation pattern for consistency.
 * 
 * @param {string} address - Address string that may contain postal code
 * @returns {string|null} Extracted postal code or null if not found
 */
function extractZipCodeFromAddress(address) {
    if (!address) return null;
    
    /**
     * Search for 5-digit number in address
     */
    const zipMatch = address.match(/\b(\d{5})\b/);
    if (zipMatch) {
        const zipCode = zipMatch[1];
        /**
         * Check that this is a French postal code using centralized pattern
         */
        if (VALIDATION_PATTERNS.zipCode.test(zipCode)) {
            return zipCode;
        }
    }
    return null;
}

// Extract only the street part from a full address that may contain ZIP and city
// From a free‑form address, keep only the street part (drop ZIP and city)
function extractStreetWithoutZipCity(address) {
    if (!address) return '';
    const text = String(address);
    const zipMatch = text.match(/\b(\d{5})\b/);
    if (zipMatch) {
        // Keep everything before the ZIP code
        const cutIndex = zipMatch.index || 0;
        let street = text.substring(0, cutIndex);
        // Remove trailing commas, spaces
        street = street.replace(/[\s,]+$/g, '').trim();
        return street;
    }
    // If no ZIP detected but a pattern like ", Marseille" exists, drop city after comma
    const commaIndex = text.lastIndexOf(',');
    if (commaIndex > -1) {
        return text.substring(0, commaIndex).trim();
    }
    return text.trim();
}

// Full address validation
// Minimal address sanity check (not an external validation)
function validateAddress(address, zipCode) {
    if (!address) return false;
    
    // Basic check - address should not be empty
    const cleanAddress = address.trim();
    if (cleanAddress.length < 5) return false;
    
    return true;
}

/**
 * Initialize address validation
 * 
 * Live validation and normalization for address input (auto ZIP extraction).
 * Uses cached getElement and centralized debounce delay.
 */
function initAddressValidation() {
    const addressInput = getElement('deliveryAddress');
    const zipInput = getElement('deliveryZip');
    
    if (!addressInput) return;
    
    let validationTimeout;
    
    // Real-time validation
    addressInput.addEventListener('input', function() {
        clearTimeout(validationTimeout);
        const address = this.value.trim();
        
        // Automatic extraction and substitution of postal code
        const extractedZipCode = extractZipCodeFromAddress(address);
        if (extractedZipCode && zipInput) {
            zipInput.value = extractedZipCode;
            // Run postal code validation after substitution
            zipInput.dispatchEvent(new Event('input'));
        }

        // Keep only the street part in the address input (leave ZIP/City in their own fields)
        const streetOnly = extractStreetWithoutZipCity(address);
        if (streetOnly && streetOnly !== this.value.trim()) {
            this.value = streetOnly;
        }
        
        const zipCode = zipInput?.value?.trim() || extractedZipCode || null;
        
        // Remove previous validation classes
        this.classList.remove('is-valid', 'is-invalid');
        removeAddressError();
        
        if (address === '') {
            return;
        }
        
        // Basic address validation
        if (!validateAddress(address)) {
            this.classList.add('is-invalid');
            showAddressError('Adresse trop courte');
            return;
        }
        
        // API validation with delay (debounce)
        validationTimeout = setTimeout(async () => {
            try {
                const result = await window.zipCodeAPI.validateAddress(address, zipCode);
                
                if (result.valid) {
                    this.classList.remove('is-invalid');
                    showAddressSuccess(`Livraison disponible (${result.distance}km)`);
                } else {
                    this.classList.add('is-invalid');
                    showAddressError(result.error || 'Livraison non disponible pour cette adresse');
                }
            } catch (error) {
                this.classList.add('is-invalid');
                showAddressError('Erreur lors de la vérification de l\'adresse');
            }
        }, DEBOUNCE_DELAYS.ADDRESS); // Delay for address validation (longer than for postal code)
    });
    
    // Clear on focus
    addressInput.addEventListener('focus', function() {
        this.classList.remove('is-invalid');
        removeAddressError();
    });
}

/**
 * Show address validation error
 * 
 * Render an inline address error.
 * Uses cached getElement for better performance.
 * 
 * @param {string} message - Error message to display
 */
function showAddressError(message) {
    removeAddressError();
    
    const addressInput = getElement('deliveryAddress');
    if (!addressInput) return;
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback address-validation-error';
    errorDiv.textContent = message;
    
    addressInput.parentNode.appendChild(errorDiv);
}

/**
 * Show successful address validation
 * 
 * Render an inline address success helper.
 * Uses cached getElement for better performance.
 * 
 * @param {string} message - Success message to display
 */
function showAddressSuccess(message) {
    removeAddressError();
    
    const addressInput = getElement('deliveryAddress');
    if (!addressInput) return;
    
    const successDiv = document.createElement('div');
    successDiv.className = 'valid-feedback address-validation-success';
    successDiv.textContent = message;
    
    addressInput.parentNode.appendChild(successDiv);
}

// Remove address validation messages
// Remove both error/success messages for address input
function removeAddressError() {
    const existingError = document.querySelector('.address-validation-error');
    const existingSuccess = document.querySelector('.address-validation-success');
    
    if (existingError) existingError.remove();
    if (existingSuccess) existingSuccess.remove();
}

/**
 * Initialize promo code functionality
 * 
 * Wire up promo code apply button and Enter key behavior.
 * Uses cached getElement for better performance.
 */
function initPromoCode() {
    const promoInput = getElement('promoCode');
    const promoButton = document.querySelector('.promo-code button');
    
    if (!promoButton || !promoInput) return;
    
    promoButton.addEventListener('click', async function(e) {
        e.preventDefault();
        await applyPromoCode();
    });
    
    // Allow Enter key to apply promo code
    promoInput.addEventListener('keypress', async function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            await applyPromoCode();
        }
    });
}

/**
 * Apply promo code
 * 
 * Validate and apply a coupon; update order summary and UI.
 * Uses cached getElement and centralized order amount calculation.
 * 
 * @returns {Promise<void>}
 */
async function applyPromoCode() {
    const promoInput = getElement('promoCode');
    const promoButton = document.querySelector('.promo-code button');
    
    if (!promoInput || !promoButton) return;
    
    const code = promoInput.value.trim().toUpperCase();
    
    if (!code) {
        showOrderNotification('Veuillez entrer un code promo', 'error');
        return;
    }
    
    // Disable button during validation
    const originalText = promoButton.textContent;
    promoButton.disabled = true;
    promoButton.textContent = 'Vérification...';
    
    try {
        /**
         * Calculate order amount using centralized function
         * Uses cached calculation if available
         */
        const orderAmount = getOrderAmountForCoupon();
        
        // Validate coupon
        const result = await window.couponAPI.validateCoupon(code, orderAmount);
        
        if (result.success) {
            // Store coupon data
            orderData.coupon = result.data;
            orderData.discount = parseFloat(result.data.discountAmount);
            
            // Update summary
            updateOrderSummary();
            
            // Update UI
            promoInput.value = '';
            promoInput.disabled = true;
            promoButton.textContent = 'Appliqué ✓';
            promoButton.classList.remove('btn-outline-secondary');
            promoButton.classList.add('btn-success');
            
            // Add remove button
            addRemoveCouponButton();
            
            showOrderNotification(`Code promo appliqué ! Vous économisez ${orderData.discount.toFixed(2)}€`, 'success');
        }
    } catch (error) {
        showOrderNotification(error.message || 'Code promo invalide', 'error');
    } finally {
        promoButton.disabled = false;
        if (promoButton.textContent === 'Vérification...') {
            promoButton.textContent = originalText;
        }
    }
}

// Add remove coupon button
// Create a small button that lets the user remove the currently applied coupon
function addRemoveCouponButton() {
    const promoCodeDiv = document.querySelector('.promo-code');
    if (!promoCodeDiv) return;
    
    // Check if button already exists
    if (document.getElementById('removeCouponBtn')) return;
    
    const removeBtn = document.createElement('button');
    removeBtn.id = 'removeCouponBtn';
    removeBtn.className = 'btn btn-sm mt-2 w-100';
    removeBtn.style.borderRadius = '50px'; // Pill-style rounded corners like "Continuer" button
    removeBtn.style.fontWeight = '600';
    removeBtn.style.letterSpacing = '0.5px';
    removeBtn.style.border = '2px solid #8b4513'; // Secondary color (dark brown)
    removeBtn.style.color = '#8b4513';
    removeBtn.style.background = 'white';
    removeBtn.style.transition = 'all 0.3s ease';
    removeBtn.innerHTML = '<i class="bi bi-x"></i> Retirer le code promo';
    removeBtn.onclick = removeCoupon;
    
    // Add hover effect
    removeBtn.addEventListener('mouseenter', function() {
        this.style.background = '#8b4513';
        this.style.color = 'white';
        this.style.transform = 'translateY(-1px)';
        this.style.boxShadow = '0 4px 12px rgba(139, 69, 19, 0.3)';
    });
    removeBtn.addEventListener('mouseleave', function() {
        this.style.background = 'white';
        this.style.color = '#8b4513';
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
    });
    
    promoCodeDiv.appendChild(removeBtn);
}

/**
 * Remove coupon
 * 
 * Remove an applied coupon and refresh totals/controls.
 * Uses cached getElement for better performance.
 */
function removeCoupon() {
    const promoInput = getElement('promoCode');
    const promoButton = document.querySelector('.promo-code button');
    const removeBtn = getElement('removeCouponBtn');
    
    // Reset coupon data
    orderData.coupon = null;
    orderData.discount = 0;
    
    // Update summary
    updateOrderSummary();
    
    // Reset UI
    if (promoInput) {
        promoInput.disabled = false;
        promoInput.value = '';
    }
    
    if (promoButton) {
        promoButton.textContent = 'Appliquer';
        promoButton.classList.remove('btn-success');
        promoButton.classList.add('btn-outline-secondary');
    }
    
    if (removeBtn) {
        removeBtn.remove();
    }
    
    showOrderNotification('Code promo retiré', 'info');
}

// Globals
window.nextStep = nextStep;
window.prevStep = prevStep;
window.confirmOrder = confirmOrder;
window.removeCoupon = removeCoupon;

/**
 * Initialize time validation
 * 
 * Sets up date and time selection with validation.
 * Uses cached getElement for better performance.
 */
function initTimeValidation() {
    const dateInput = getElement('deliveryDate');
    const timeSelect = getElement('deliveryTime');
    if (dateInput && timeSelect) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today; dateInput.value = today;
        dateInput.addEventListener('change', updateTimeOptions);
        timeSelect.addEventListener('change', validateSelectedTime);
        updateTimeOptions();
    }
}

/**
 * Update time slot options based on selected date
 * 
 * Filters available time slots based on:
 * - Selected date (today vs. future)
 * - Minimum time delay requirement
 * 
 * Uses cached TIME_SLOTS array for better performance.
 */
function updateTimeOptions() {
    const dateInput = getElement('deliveryDate');
    const timeSelect = getElement('deliveryTime');
    if (!dateInput || !timeSelect) return;
    
    const selectedDate = dateInput.value;
    const today = new Date().toISOString().split('T')[0];
    const currentTime = new Date();
    
    /**
     * Clear existing options
     */
    timeSelect.innerHTML = '<option value="">Choisir un créneau</option>';
    
    /**
     * Filter time slots based on selected date
     */
    if (selectedDate === today) {
        /**
         * For today, filter out past time slots
         * Minimum delay is MIN_TIME_DELAY_HOURS
         */
        const minimumTime = new Date(currentTime.getTime() + MIN_TIME_DELAY_HOURS * 60 * 60 * 1000);
        
        TIME_SLOTS.forEach(slot => { 
            const slotTime = new Date(`${selectedDate}T${slot.value}`); 
            if (slotTime > minimumTime) { 
                const option = document.createElement('option'); 
                option.value = slot.value; 
                option.textContent = slot.text; 
                timeSelect.appendChild(option); 
            } 
        });
    } else {
        /**
         * For future dates, show all time slots
         */
        TIME_SLOTS.forEach(slot => { 
            const option = document.createElement('option'); 
            option.value = slot.value; 
            option.textContent = slot.text; 
            timeSelect.appendChild(option); 
        });
    }
    
    /**
     * Show message if no slots available for today
     */
    if (selectedDate === today && timeSelect.options.length === 1) {
        const option = document.createElement('option'); 
        option.value = ''; 
        option.textContent = 'Aucun créneau disponible aujourd\'hui'; 
        option.disabled = true; 
        timeSelect.appendChild(option);
    }
}

/**
 * Validate selected time slot
 * 
 * Ensures selected time is at least MIN_TIME_DELAY_HOURS in the future.
 * 
 * @returns {boolean} True if time is valid, false otherwise
 */
function validateSelectedTime() {
    const dateInput = getElement('deliveryDate');
    const timeSelect = getElement('deliveryTime');
    if (!dateInput || !timeSelect) return true;
    
    const selectedDate = dateInput.value; 
    const selectedTime = timeSelect.value;
    const today = new Date().toISOString().split('T')[0]; 
    const currentTime = new Date();
    
    if (selectedDate === today && selectedTime) {
        const selectedDateTime = new Date(`${selectedDate}T${selectedTime}`);
        /**
         * Calculate minimum allowed time (current time + minimum delay)
         */
        const minimumTime = new Date(currentTime.getTime() + MIN_TIME_DELAY_HOURS * 60 * 60 * 1000);
        
        if (selectedDateTime <= minimumTime) { 
            showOrderNotification(`Le créneau doit être au minimum ${MIN_TIME_DELAY_HOURS} heure${MIN_TIME_DELAY_HOURS > 1 ? 's' : ''} après l'heure actuelle. Veuillez choisir un autre créneau.`, 'error'); 
            timeSelect.value = ''; 
            return false; 
        }
    }
    return true;
}
