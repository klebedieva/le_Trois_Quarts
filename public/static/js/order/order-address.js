// ============================================================================
// ORDER ADDRESS - Address and Postal Code Validation
// ============================================================================
// This module handles:
// - Postal code validation (format and API)
// - Address validation (format and API)
// - Automatic ZIP code extraction from address
// - Real-time validation with debouncing
//
// Usage: This module manages address validation in the checkout process.

'use strict';

/**
 * Initialize postal code validation
 * 
 * Live validation for ZIP input with async backend check (debounced).
 * Uses cached getElement and centralized debounce delay.
 */
function initZipCodeValidation() {
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const zipInput = getElement('deliveryZip');
    if (!zipInput) return;
    
    /**
     * Get constants
     */
    const DEBOUNCE_DELAYS = window.OrderConstants?.DEBOUNCE_DELAYS || { ZIP_CODE: 500 };
    
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
        if (window.OrderValidation && window.OrderValidation.validateFrenchZipCode) {
            if (!window.OrderValidation.validateFrenchZipCode(zipCode)) {
                this.classList.add('is-invalid');
                showZipCodeError('Format de code postal invalide');
                return;
            }
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
    
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
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
    
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const zipInput = getElement('deliveryZip');
    if (!zipInput) return;
    
    const successDiv = document.createElement('div');
    successDiv.className = 'valid-feedback zip-validation-success';
    successDiv.textContent = message;
    
    zipInput.parentNode.appendChild(successDiv);
}

/**
 * Remove postal code validation messages
 * 
 * Remove both error/success messages for ZIP input
 */
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
         * Get validation patterns from constants
         */
        const VALIDATION_PATTERNS = window.OrderConstants?.VALIDATION_PATTERNS;
        if (VALIDATION_PATTERNS && VALIDATION_PATTERNS.zipCode.test(zipCode)) {
            return zipCode;
        }
    }
    return null;
}

/**
 * Extract only the street part from a full address that may contain ZIP and city
 * 
 * From a free‑form address, keep only the street part (drop ZIP and city)
 * 
 * @param {string} address - Full address string
 * @returns {string} Street part only
 */
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

/**
 * Initialize address validation
 * 
 * Live validation and normalization for address input (auto ZIP extraction).
 * Uses cached getElement and centralized debounce delay.
 */
function initAddressValidation() {
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const addressInput = getElement('deliveryAddress');
    const zipInput = getElement('deliveryZip');
    
    if (!addressInput) return;
    
    /**
     * Get constants
     */
    const DEBOUNCE_DELAYS = window.OrderConstants?.DEBOUNCE_DELAYS || { ADDRESS: 800 };
    
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
        if (window.OrderValidation && window.OrderValidation.validateAddress) {
            if (!window.OrderValidation.validateAddress(address, zipCode)) {
                this.classList.add('is-invalid');
                showAddressError('Adresse trop courte');
                return;
            }
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
    
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
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
    
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const addressInput = getElement('deliveryAddress');
    if (!addressInput) return;
    
    const successDiv = document.createElement('div');
    successDiv.className = 'valid-feedback address-validation-success';
    successDiv.textContent = message;
    
    addressInput.parentNode.appendChild(successDiv);
}

/**
 * Remove address validation messages
 * 
 * Remove both error/success messages for address input
 */
function removeAddressError() {
    const existingError = document.querySelector('.address-validation-error');
    const existingSuccess = document.querySelector('.address-validation-success');
    
    if (existingError) existingError.remove();
    if (existingSuccess) existingSuccess.remove();
}

// Export address functions to global scope
window.OrderAddress = {
    initZipCodeValidation,
    initAddressValidation,
    extractZipCodeFromAddress,
    extractStreetWithoutZipCity,
    showZipCodeError,
    showZipCodeSuccess,
    removeZipCodeError,
    showAddressError,
    showAddressSuccess,
    removeAddressError
};

