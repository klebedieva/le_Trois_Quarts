// ============================================================================
// ORDER FIELD VALIDATION - Real-time Field Validation (Name, Email, Phone)
// ============================================================================
// This module handles:
// - Real-time validation for first name, last name, and email
// - Real-time validation for phone number
// - Inline error message display
//
// Usage: This module provides real-time field validation in the checkout process.

'use strict';

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
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const firstNameInput = getElement('clientFirstName');
    const lastNameInput = getElement('clientLastName');
    const emailInput = getElement('clientEmail');

    /**
     * Get validation patterns from constants
     */
    const VALIDATION_PATTERNS = window.OrderConstants?.VALIDATION_PATTERNS;
    if (!VALIDATION_PATTERNS) {
        console.error('OrderConstants not loaded');
        return;
    }

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

/**
 * Initialize real-time phone validation
 * 
 * Live UI feedback for the phone field.
 * Uses cached getElement for better performance.
 */
function initPhoneValidation() {
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const phoneInput = getElement('clientPhone');
    if (!phoneInput) return;
    
    // Real-time validation during input
    phoneInput.addEventListener('input', function() {
        const phone = this.value.trim();
        const isValid = phone === '' || (window.OrderValidation && window.OrderValidation.validateFrenchPhoneNumber && window.OrderValidation.validateFrenchPhoneNumber(phone));
        
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
        if (phone !== '' && window.OrderValidation && window.OrderValidation.validateFrenchPhoneNumber) {
            if (!window.OrderValidation.validateFrenchPhoneNumber(phone)) {
                this.classList.add('is-invalid');
                showPhoneError('Numéro de téléphone invalide');
            }
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
    
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const phoneInput = getElement('clientPhone');
    if (!phoneInput) return;
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback phone-validation-error';
    errorDiv.textContent = message;
    
    phoneInput.parentNode.appendChild(errorDiv);
}

/**
 * Remove phone validation error
 * 
 * Remove the live phone error element if present
 */
function removePhoneError() {
    const existingError = document.querySelector('.phone-validation-error');
    if (existingError) {
        existingError.remove();
    }
}

// Export field validation functions to global scope
window.OrderFieldValidation = {
    initNameEmailValidation,
    initPhoneValidation,
    showInlineError,
    removeInlineError,
    showPhoneError,
    removePhoneError
};

