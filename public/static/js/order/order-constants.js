// ============================================================================
// ORDER CONSTANTS - Application Constants and Configuration
// ============================================================================
// This module contains all constants used throughout the order process.
// Centralized constants improve maintainability and self-documentation.
//
// Usage: This module is loaded first and provides constants to other modules.

'use strict';

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

// Export constants to global scope for use by other modules
// This allows other modules to access these constants without duplication
window.OrderConstants = {
    DELIVERY_FEE,
    TAX_RATE,
    DEBOUNCE_DELAYS,
    MIN_TIME_DELAY_HOURS,
    TIME_SLOTS,
    VALIDATION_PATTERNS,
    xssPatterns
};

