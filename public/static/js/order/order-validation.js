// ============================================================================
// ORDER VALIDATION - Field and Step Validation Functions
// ============================================================================
// This module contains all validation functions for the order process:
// - Step validation (cart, delivery, payment)
// - Field validation (name, email, phone, zip code, address, time)
// - XSS protection checks
//
// Usage: This module provides validation functions to other order modules.

'use strict';

// ============================================================================
// STEP VALIDATION
// ============================================================================

/**
 * Validate cart step
 * 
 * Ensures cart is not empty before proceeding.
 * 
 * @param {Array} items - Cart items array
 * @returns {boolean} True if cart has items, false otherwise
 */
function validateCartStep(items) {
    if ((items || []).length === 0) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Votre panier est vide', 'error');
        }
        return false;
    }
    return true;
}

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
 * @param {Object} orderData - Current order data state
 * @returns {Promise<boolean>} True if validation passes, false otherwise
 */
async function validateDeliveryStep(orderData) {
    /**
     * Get delivery mode, date, and time from form
     * Use cached getElement for better performance
     */
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const mode = document.querySelector('input[name="deliveryMode"]:checked')?.value;
    const dateInput = getElement('deliveryDate');
    const timeInput = getElement('deliveryTime');
    const date = dateInput?.value;
    const time = timeInput?.value;
    
    /**
     * Validate required delivery fields
     */
    if (!mode) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez choisir un mode de récupération', 'error');
        }
        return false;
    }
    if (!date) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez choisir une date', 'error');
        }
        return false;
    }
    if (!time) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez choisir un créneau horaire', 'error');
        }
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
        const containsXssAttempt = window.OrderUtils?.containsXssAttempt || (() => false);
        if (address && containsXssAttempt(address)) {
            if (window.OrderUtils) {
                window.OrderUtils.showOrderNotification('L\'adresse contient des éléments non autorisés', 'error');
            }
            return false;
        }
        
        /**
         * XSS check for delivery instructions
         * Prevents malicious code injection
         */
        if (instructions && containsXssAttempt(instructions)) {
            if (window.OrderUtils) {
                window.OrderUtils.showOrderNotification('Les instructions de livraison contiennent des éléments non autorisés', 'error');
            }
            return false;
        }
        
        /**
         * Validate that address and zip code are provided
         */
        if (!address || !zip) {
            if (window.OrderUtils) {
                window.OrderUtils.showOrderNotification('Veuillez renseigner votre adresse de livraison', 'error');
            }
            return false;
        }
        
        /**
         * Validate French postal code format
         * Must be exactly 5 digits
         */
        if (!validateFrenchZipCode(zip)) {
            if (window.OrderUtils) {
                window.OrderUtils.showOrderNotification('Format de code postal invalide', 'error');
            }
            return false;
        }
        
        /**
         * Check if delivery is available for this address
         * Uses API to validate address and check delivery availability
         */
        try {
            const addressValidation = await window.zipCodeAPI.validateAddress(address, zip);
            if (!addressValidation.valid) {
                if (window.OrderUtils) {
                    window.OrderUtils.showOrderNotification(addressValidation.error || 'Livraison non disponible pour cette adresse', 'error');
                }
                return false;
            }
        } catch (error) {
            if (window.OrderUtils) {
                window.OrderUtils.showOrderNotification('Erreur lors de la vérification de l\'adresse', 'error');
            }
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
    const containsXssAttempt = window.OrderUtils?.containsXssAttempt || (() => false);
    if (firstName && containsXssAttempt(firstName)) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Le prénom contient des éléments non autorisés', 'error');
        }
        return false;
    }
    if (lastName && containsXssAttempt(lastName)) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Le nom contient des éléments non autorisés', 'error');
        }
        return false;
    }
    if (phone && containsXssAttempt(phone)) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Le numéro de téléphone contient des éléments non autorisés', 'error');
        }
        return false;
    }
    if (email && containsXssAttempt(email)) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('L\'email contient des éléments non autorisés', 'error');
        }
        return false;
    }
    
    /**
     * Validate that all required fields are filled
     */
    if (!firstName) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez renseigner votre prénom', 'error');
        }
        return false;
    }
    if (!lastName) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez renseigner votre nom', 'error');
        }
        return false;
    }
    if (!phone) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez renseigner votre numéro de téléphone', 'error');
        }
        return false;
    }
    if (!email) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez renseigner votre adresse email', 'error');
        }
        return false;
    }
    
    /**
     * Validate French phone number format
     * Supports national (0X XX XX XX XX) and international (+33 X XX XX XX XX) formats
     */
    if (!validateFrenchPhoneNumber(phone)) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez entrer un numéro de téléphone français valide', 'error');
        }
        return false;
    }
    
    /**
     * Validate email format
     * Use centralized validation pattern for consistency
     */
    const VALIDATION_PATTERNS = window.OrderConstants?.VALIDATION_PATTERNS;
    if (VALIDATION_PATTERNS && !VALIDATION_PATTERNS.email.test(email)) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez renseigner une adresse email valide', 'error');
        }
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
 * @param {Object} orderData - Current order data state
 * @returns {boolean} True if payment method is selected, false otherwise
 */
function validatePaymentStep(orderData) {
    const mode = document.querySelector('input[name="paymentMode"]:checked')?.value;
    if (!mode) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez choisir un mode de paiement', 'error');
        }
        return false;
    }
    
    /**
     * Store payment method in orderData
     */
    orderData.payment = { mode };
    return true;
}

// ============================================================================
// FIELD VALIDATION
// ============================================================================

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
     * Get validation patterns from constants
     */
    const VALIDATION_PATTERNS = window.OrderConstants?.VALIDATION_PATTERNS;
    if (!VALIDATION_PATTERNS) return false;
    
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
     * Get validation patterns from constants
     */
    const VALIDATION_PATTERNS = window.OrderConstants?.VALIDATION_PATTERNS;
    if (!VALIDATION_PATTERNS) return false;
    
    /**
     * Check French postal code format using centralized pattern
     */
    return VALIDATION_PATTERNS.zipCode.test(cleanZipCode);
}

/**
 * Full address validation
 * Minimal address sanity check (not an external validation)
 * 
 * @param {string} address - Address to validate
 * @param {string} zipCode - Postal code (optional)
 * @returns {boolean} True if address is valid
 */
function validateAddress(address, zipCode) {
    if (!address) return false;
    
    // Basic check - address should not be empty
    const cleanAddress = address.trim();
    if (cleanAddress.length < 5) return false;
    
    return true;
}

/**
 * Validate selected time slot
 * 
 * Ensures selected time is at least MIN_TIME_DELAY_HOURS in the future.
 * 
 * @returns {boolean} True if time is valid, false otherwise
 */
function validateSelectedTime() {
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const dateInput = getElement('deliveryDate');
    const timeSelect = getElement('deliveryTime');
    if (!dateInput || !timeSelect) return true;
    
    const selectedDate = dateInput.value;
    const selectedTime = timeSelect.value;
    const today = new Date().toISOString().split('T')[0];
    const currentTime = new Date();
    
    /**
     * Get minimum delay from constants
     */
    const MIN_TIME_DELAY_HOURS = window.OrderConstants?.MIN_TIME_DELAY_HOURS || 1;
    
    if (selectedDate === today && selectedTime) {
        const selectedDateTime = new Date(`${selectedDate}T${selectedTime}`);
        /**
         * Calculate minimum allowed time (current time + minimum delay)
         */
        const minimumTime = new Date(currentTime.getTime() + MIN_TIME_DELAY_HOURS * 60 * 60 * 1000);
        
        if (selectedDateTime <= minimumTime) {
            if (window.OrderUtils) {
                window.OrderUtils.showOrderNotification(`Le créneau doit être au minimum ${MIN_TIME_DELAY_HOURS} heure${MIN_TIME_DELAY_HOURS > 1 ? 's' : ''} après l'heure actuelle. Veuillez choisir un autre créneau.`, 'error');
            }
            timeSelect.value = '';
            return false;
        }
    }
    return true;
}

// Export validation functions to global scope
window.OrderValidation = {
    validateCartStep,
    validateDeliveryStep,
    validatePaymentStep,
    validateFrenchPhoneNumber,
    validateFrenchZipCode,
    validateAddress,
    validateSelectedTime
};

