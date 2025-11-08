// ============================================================================
// ORDER PAGE - Multi-Step Checkout Process (Main Entry Point)
// ============================================================================
// This file is the main entry point for the order page.
// It initializes all modules and coordinates the checkout process.
//
// MODULE STRUCTURE:
// ================
// This file has been refactored into smaller, focused modules:
// - order-constants.js: Application constants and configuration
// - order-utils.js: Utility functions (DOM caching, notifications, XSS protection)
// - order-api.js: API clients (order, coupon, address validation)
// - order-validation.js: Step and field validation functions
// - order-steps.js: Multi-step navigation and management
// - order-coupon.js: Coupon/promo code functionality
// - order-submission.js: Order creation and confirmation
// - order-cart.js: Cart management and order summary
// - order-delivery.js: Delivery options, payment options, time management
// - order-address.js: Address and postal code validation
// - order-field-validation.js: Real-time field validation (name, email, phone)
//
// Design principle: This file intentionally avoids external dependencies.
// All helpers are defined in modules and exposed on window when needed
// to keep the surface area small.

'use strict';

// ============================================================================
// UI STATE
// ============================================================================

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

// Export orderData to global scope for use by modules and inline handlers
window.orderData = orderData;

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
     * Check if all required modules are loaded
     * This ensures modules are loaded in correct order
     */
    if (!window.OrderConstants || !window.OrderUtils || !window.OrderCart) {
        console.error('Required order modules not loaded. Please check script loading order.');
        return;
    }
    
    /**
     * Initialize aria attributes for accessibility
     * Set initial state for step indicators and content
     */
    const steps = document.querySelectorAll('.step');
    const stepContents = document.querySelectorAll('.order-step-content');
    steps.forEach((el, i) => {
        if (i === 0) {
            // First step is active by default
            el.setAttribute('aria-current', 'step');
        } else {
            el.removeAttribute('aria-current');
        }
    });
    stepContents.forEach((el, i) => {
        if (i === 0) {
            // First step content is visible by default
            el.setAttribute('aria-hidden', 'false');
        } else {
            el.setAttribute('aria-hidden', 'true');
        }
    });
    
    /**
     * Load cart items and render order summary
     * This displays current cart contents in checkout
     */
    if (window.OrderCart && window.OrderCart.loadCartItems) {
        await window.OrderCart.loadCartItems(orderData);
    }
    if (window.OrderCart && window.OrderCart.updateOrderSummary) {
        window.OrderCart.updateOrderSummary(orderData);
    }
    
    /**
     * Initialize all form handlers and validators
     * These set up real-time validation and UI updates
     */
    if (window.OrderDelivery) {
        if (window.OrderDelivery.initDeliveryOptions) {
            window.OrderDelivery.initDeliveryOptions(orderData);
        }
        if (window.OrderDelivery.initPaymentOptions) {
            window.OrderDelivery.initPaymentOptions();
        }
        if (window.OrderDelivery.initTimeValidation) {
            window.OrderDelivery.initTimeValidation();
        }
    }
    
    if (window.OrderFieldValidation) {
        if (window.OrderFieldValidation.initPhoneValidation) {
            window.OrderFieldValidation.initPhoneValidation();
        }
        if (window.OrderFieldValidation.initNameEmailValidation) {
            window.OrderFieldValidation.initNameEmailValidation();
        }
    }
    
    if (window.OrderAddress) {
        if (window.OrderAddress.initZipCodeValidation) {
            window.OrderAddress.initZipCodeValidation();
        }
        if (window.OrderAddress.initAddressValidation) {
            window.OrderAddress.initAddressValidation();
        }
    }
    
    if (window.OrderCoupon && window.OrderCoupon.initPromoCode) {
        window.OrderCoupon.initPromoCode(orderData);
    }

    /**
     * Keep cart block in sync with changes from other widgets
     * When cart is updated from sidebar or menu, refresh order page display
     * This ensures checkout always shows current cart state
     */
    window.addEventListener('cartUpdated', async function() {
        if (window.OrderCart) {
            if (window.OrderCart.loadCartItems) {
                await window.OrderCart.loadCartItems(orderData);
            }
            if (window.OrderCart.updateOrderSummary) {
                window.OrderCart.updateOrderSummary(orderData);
            }
        }
    });
}

// Export initialization function for potential external use
window.initOrderPage = initOrderPage;
