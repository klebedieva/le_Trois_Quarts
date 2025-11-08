// ============================================================================
// CONTACT.JS - Contact Form Validation and AJAX Submission
// ============================================================================
// This file handles client-side validation and AJAX submission for the contact form.
//
// Features:
// - Real-time field validation (on input and blur)
// - XSS (Cross-Site Scripting) attack prevention
// - Pattern-based validation (email, phone, names)
// - AJAX form submission (no page reload)
// - Success message auto-hide
// - Form reset after successful submission

(function() {
    'use strict';

    // ============================================================================
    // VALIDATION PATTERNS
    // ============================================================================

    /**
     * Regular expressions for field validation
     * 
     * These patterns validate user input format before submission.
     * Server-side validation still required (this is client-side only).
     */
    const validationPatterns = {
        /**
         * First name and last name pattern
         * Allows: letters (including accented), spaces, hyphens
         * Example valid: "Jean-Pierre", "José", "Mary-Jane"
         */
        firstName: /^[a-zA-ZÀ-ÿ\s\-]+$/,
        lastName: /^[a-zA-ZÀ-ÿ\s\-]+$/,
        
        /**
         * Email pattern
         * Validates standard email format: user@domain.tld
         * Example valid: "user@example.com", "test.email@domain.co.uk"
         */
        email: /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/,
        
        /**
         * French phone number pattern
         * Supports formats: +33, 0033, or 0 prefix
         * Example valid: "+33 6 12 34 56 78", "0612345678", "06 12 34 56 78"
         */
        phone: /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.\-]*\d{2}){4}$/,
        
        /**
         * Message pattern
         * - Must not contain HTML tags (<.*?>)
         * - Must be between 10 and 1000 characters
         * - Allows any characters including newlines
         */
        message: /^(?!.*<.*?>)[\s\S]{10,1000}$/
    };

    // ============================================================================
    // XSS DETECTION PATTERNS
    // ============================================================================

    /**
     * XSS (Cross-Site Scripting) attack detection patterns
     * 
     * These patterns detect common XSS attack vectors:
     * - HTML tags that could inject scripts
     * - JavaScript protocol handlers
     * - Event handlers (onclick, onload, etc.)
     * - Data URIs with HTML content
     * - CSS expressions
     * 
     * Why this matters:
     * XSS attacks can steal user data, hijack sessions, or deface websites.
     * Client-side detection is the first line of defense, but server-side
     * validation is still required (never trust client-side validation alone).
     */
    const xssPatterns = [
        /<[^>]*>/gi,                    // HTML tags (any tag)
        /javascript:/gi,                // JavaScript protocol (javascript:alert())
        /on\w+\s*=/gi,                  // Event handlers (onclick=, onload=, etc.)
        /vbscript:/gi,                  // VBScript protocol (legacy)
        /data:text\/html/gi,            // Data URI with HTML content
        /expression\s*\(/gi,            // CSS expressions (IE legacy)
        /<script/gi,                    // Script tags
        /<iframe/gi,                    // Iframe tags (can load external content)
        /<object/gi,                    // Object tags (can embed external content)
        /<embed/gi,                     // Embed tags (can embed external content)
        /<form/gi,                      // Form tags (can create nested forms)
        /<link[^>]*href\s*=\s*["\']?javascript:/gi, // Link with JavaScript href
        /<meta[^>]*http-equiv\s*=\s*["\']?refresh/gi // Meta refresh (redirect attacks)
    ];

    // ============================================================================
    // INITIALIZATION
    // ============================================================================

    /**
     * Initialize contact form functionality when DOM is ready
     * 
     * Sets up:
     * - Real-time validation listeners
     * - Form submission handler
     * - Auto-hide success message
     */
    document.addEventListener('DOMContentLoaded', function() {
        setupRealTimeValidation();
        setupFormSubmission();
        setupAutoHideSuccessMessage();
    });

    // ============================================================================
    // XSS DETECTION
    // ============================================================================

    /**
     * Check if a value contains XSS attack patterns
     * 
     * This function tests the input against all XSS patterns.
     * If any pattern matches, the input is considered unsafe.
     * 
     * @param {string} value - The input value to check
     * @returns {boolean} True if XSS attempt detected, false otherwise
     * 
     * @example
     * containsXssAttempt('<script>alert("XSS")</script>'); // true
     * containsXssAttempt('Hello world'); // false
     */
    function containsXssAttempt(value) {
        // Loop through all XSS patterns
        for (let pattern of xssPatterns) {
            // Test if pattern matches the value
            if (pattern.test(value)) {
                // XSS attempt detected
                return true;
            }
        }
        // No XSS patterns found - value is safe
        return false;
    }

    // ============================================================================
    // DOM ELEMENT CACHE
    // ============================================================================

    /**
     * Cache DOM elements to avoid repeated querySelector calls
     * 
     * These elements are accessed multiple times during validation,
     * so caching them improves performance.
     */
    let formElementsCache = null;

    /**
     * Get and cache form elements
     * 
     * Returns cached elements if available, otherwise queries DOM and caches result.
     * 
     * @returns {Object|null} Object with all form elements, or null if form not found
     */
    function getFormElements() {
        // Return cache if available
        if (formElementsCache) {
            return formElementsCache;
        }

        const form = document.querySelector('.contact-form');
        if (!form) {
            return null;
        }

        // Query and cache all form elements
        formElementsCache = {
            form: form,
            firstNameInput: form.querySelector('input[name="contact_message[firstName]"]'),
            lastNameInput: form.querySelector('input[name="contact_message[lastName]"]'),
            emailInput: form.querySelector('input[name="contact_message[email]"]'),
            phoneInput: form.querySelector('input[name="contact_message[phone]"]'),
            subjectInput: form.querySelector('select[name="contact_message[subject]"]'),
            messageInput: form.querySelector('textarea[name="contact_message[message]"]'),
            consentInput: form.querySelector('input[name="contact_message[consent]"]'),
            submitBtn: form.querySelector('button[type="submit"]')
        };

        return formElementsCache;
    }

    // ============================================================================
    // REAL-TIME VALIDATION SETUP
    // ============================================================================

    /**
     * Set up real-time validation for all form fields
     * 
     * This function attaches event listeners to each form field:
     * - 'input' event: Validates as user types (for immediate feedback)
     * - 'blur' event: Validates when field loses focus (for final check)
     * 
     * Real-time validation provides better UX:
     * - Users see errors immediately
     * - Don't have to wait until form submission
     * - Can fix errors before submitting
     */
    function setupRealTimeValidation() {
        const elements = getFormElements();
        if (!elements) return;

        /**
         * First name validation
         * Validates on input (as user types) and blur (when field loses focus)
         */
        if (elements.firstNameInput) {
            elements.firstNameInput.addEventListener('input', () => validateFirstName());
            elements.firstNameInput.addEventListener('blur', () => validateFirstName());
        }
        
        /**
         * Last name validation
         * Same validation approach as first name
         */
        if (elements.lastNameInput) {
            elements.lastNameInput.addEventListener('input', () => validateLastName());
            elements.lastNameInput.addEventListener('blur', () => validateLastName());
        }

        /**
         * Email validation
         * Validates email format and XSS attempts
         */
        if (elements.emailInput) {
            elements.emailInput.addEventListener('input', () => validateEmail());
            elements.emailInput.addEventListener('blur', () => validateEmail());
        }

        /**
         * Phone validation
         * Validates French phone number format
         * Note: Phone is optional, so empty is valid
         */
        if (elements.phoneInput) {
            elements.phoneInput.addEventListener('input', () => validatePhone());
            elements.phoneInput.addEventListener('blur', () => validatePhone());
        }
        
        /**
         * Subject validation
         * Validates that a subject is selected (dropdown)
         */
        if (elements.subjectInput) {
            elements.subjectInput.addEventListener('change', () => validateSubject());
            elements.subjectInput.addEventListener('blur', () => validateSubject());
        }
        
        /**
         * Message validation
         * Validates message length and content (no HTML tags)
         */
        if (elements.messageInput) {
            elements.messageInput.addEventListener('input', () => validateMessage());
            elements.messageInput.addEventListener('blur', () => validateMessage());
        }

        /**
         * Consent checkbox validation
         * Only validates on change (checkbox state change)
         * User must check the box to proceed
         */
        if (elements.consentInput) {
            elements.consentInput.addEventListener('change', () => validateConsent());
        }
    }

    // ============================================================================
    // FORM SUBMISSION SETUP
    // ============================================================================

    /**
     * Set up form submission handler
     * 
     * This function:
     * - Prevents default form submission
     * - Validates all fields before submission
     * - Only submits if all validations pass
     * - Uses AJAX to submit form (no page reload)
     */
    function setupFormSubmission() {
        const elements = getFormElements();
        if (!elements || !elements.submitBtn) return;
        
        elements.submitBtn.addEventListener('click', function(e) {
            // Prevent default form submission (no page reload)
            e.preventDefault();
            
            /**
             * Validate all fields before submission
             * All validations must pass for form to be submitted
             */
            const isFirstNameValid = validateFirstName();
            const isLastNameValid = validateLastName();
            const isEmailValid = validateEmail();
            const isPhoneValid = validatePhone();
            const isSubjectValid = validateSubject();
            const isMessageValid = validateMessage();
            const isConsentValid = validateConsent();
            
            // Only submit if all validations pass
            if (isFirstNameValid && isLastNameValid && isEmailValid && 
                isPhoneValid && isSubjectValid && isMessageValid && isConsentValid) {
                submitContactForm();
            }
        });
    }

    // ============================================================================
    // VALIDATION FUNCTIONS
    // ============================================================================

    /**
     * Validate first name field
     * 
     * Checks:
     * 1. Field is not empty (required)
     * 2. No XSS attempts
     * 3. Matches name pattern (letters, spaces, hyphens only)
     * 
     * @returns {boolean} True if valid, false otherwise
     */
    function validateFirstName() {
        const elements = getFormElements();
        if (!elements || !elements.firstNameInput) return false;
        
        const input = elements.firstNameInput;
        const value = input.value.trim();
        const errorElement = document.getElementById('firstNameError');
        
        // Check if empty (required field)
        if (value === '') {
            setFieldInvalid(input, errorElement, 'Le prénom est requis');
            return false;
        }
        
        // Check for XSS attempts
        if (containsXssAttempt(value)) {
            setFieldInvalid(input, errorElement, 'Le prénom contient des éléments non autorisés');
            return false;
        }
        
        // Check format (letters, spaces, hyphens only)
        if (!validationPatterns.firstName.test(value)) {
            setFieldInvalid(input, errorElement, 'Le prénom ne peut contenir que des lettres, espaces et tirets');
            return false;
        }
        
        // All checks passed - field is valid
        setFieldValid(input, errorElement);
        return true;
    }

    /**
     * Validate last name field
     * 
     * Same validation logic as first name
     * 
     * @returns {boolean} True if valid, false otherwise
     */
    function validateLastName() {
        const elements = getFormElements();
        if (!elements || !elements.lastNameInput) return false;
        
        const input = elements.lastNameInput;
        const value = input.value.trim();
        const errorElement = document.getElementById('lastNameError');
        
        // Check if empty (required field)
        if (value === '') {
            setFieldInvalid(input, errorElement, 'Le nom est requis');
            return false;
        }
        
        // Check for XSS attempts
        if (containsXssAttempt(value)) {
            setFieldInvalid(input, errorElement, 'Le nom contient des éléments non autorisés');
            return false;
        }
        
        // Check format (letters, spaces, hyphens only)
        if (!validationPatterns.lastName.test(value)) {
            setFieldInvalid(input, errorElement, 'Le nom ne peut contenir que des lettres, espaces et tirets');
            return false;
        }
        
        // All checks passed - field is valid
        setFieldValid(input, errorElement);
        return true;
    }

    /**
     * Validate email field
     * 
     * Checks:
     * 1. Field is not empty (required)
     * 2. No XSS attempts
     * 3. Matches email pattern (user@domain.tld)
     * 
     * @returns {boolean} True if valid, false otherwise
     */
    function validateEmail() {
        const elements = getFormElements();
        if (!elements || !elements.emailInput) return false;
        
        const input = elements.emailInput;
        const value = input.value.trim();
        const errorElement = document.getElementById('emailError');
        
        // Check if empty (required field)
        if (value === '') {
            setFieldInvalid(input, errorElement, 'L\'email est requis');
            return false;
        }
        
        // Check for XSS attempts
        if (containsXssAttempt(value)) {
            setFieldInvalid(input, errorElement, 'L\'email contient des éléments non autorisés');
            return false;
        }
        
        // Check email format
        if (!validationPatterns.email.test(value)) {
            setFieldInvalid(input, errorElement, 'L\'email n\'est pas valide');
            return false;
        }
        
        // All checks passed - field is valid
        setFieldValid(input, errorElement);
        return true;
    }

    /**
     * Validate phone field
     * 
     * Checks:
     * 1. Field is empty OR valid (phone is optional)
     * 2. No XSS attempts (if provided)
     * 3. Matches French phone number pattern (if provided)
     * 
     * @returns {boolean} True if valid, false otherwise
     */
    function validatePhone() {
        const elements = getFormElements();
        if (!elements || !elements.phoneInput) return true; // No phone field, consider valid
        
        const input = elements.phoneInput;
        const value = input.value.trim();
        const errorElement = document.getElementById('phoneError');
        
        // Phone is optional - empty is valid
        if (value === '') {
            // Clear validation state (no error, no success)
            input.classList.remove('is-valid', 'is-invalid');
            clearFieldError(errorElement);
            return true;
        }
        
        // If provided, check for XSS attempts
        if (containsXssAttempt(value)) {
            setFieldInvalid(input, errorElement, 'Le numéro de téléphone contient des éléments non autorisés');
            return false;
        }
        
        // If provided, check French phone format
        if (!validationPatterns.phone.test(value)) {
            setFieldInvalid(input, errorElement, 'Le numéro de téléphone n\'est pas valide');
            return false;
        }
        
        // All checks passed - field is valid
        setFieldValid(input, errorElement);
        return true;
    }

    /**
     * Validate subject field (dropdown)
     * 
     * Checks:
     * 1. A subject is selected (not empty)
     * 
     * @returns {boolean} True if valid, false otherwise
     */
    function validateSubject() {
        const elements = getFormElements();
        if (!elements || !elements.subjectInput) return false;
        
        const input = elements.subjectInput;
        const value = input.value;
        const errorElement = document.getElementById('subjectError');
        
        // Check if empty (required field)
        if (value === '') {
            setFieldInvalid(input, errorElement, 'Le sujet est requis');
            return false;
        }
        
        // All checks passed - field is valid
        setFieldValid(input, errorElement);
        return true;
    }

    /**
     * Validate message field
     * 
     * Checks:
     * 1. Field is not empty (required)
     * 2. No XSS attempts
     * 3. Length between 10 and 1000 characters
     * 
     * @returns {boolean} True if valid, false otherwise
     */
    function validateMessage() {
        const elements = getFormElements();
        if (!elements || !elements.messageInput) return false;
        
        const input = elements.messageInput;
        const value = input.value.trim();
        const errorElement = document.getElementById('messageError');
        
        // Check if empty (required field)
        if (value === '') {
            setFieldInvalid(input, errorElement, 'Le message est requis');
            return false;
        }
        
        // Check for XSS attempts
        if (containsXssAttempt(value)) {
            setFieldInvalid(input, errorElement, 'Le message contient des éléments non autorisés (balises HTML, JavaScript, etc.)');
            return false;
        }
        
        // Check minimum length (10 characters)
        if (value.length < 10) {
            setFieldInvalid(input, errorElement, 'Le message doit contenir au moins 10 caractères');
            return false;
        }
        
        // Check maximum length (1000 characters)
        if (value.length > 1000) {
            setFieldInvalid(input, errorElement, 'Le message ne peut pas dépasser 1000 caractères');
            return false;
        }
        
        // All checks passed - field is valid
        setFieldValid(input, errorElement);
        return true;
    }

    /**
     * Validate consent checkbox
     * 
     * Checks:
     * 1. Checkbox is checked (required for GDPR compliance)
     * 
     * @returns {boolean} True if valid, false otherwise
     */
    function validateConsent() {
        const elements = getFormElements();
        if (!elements || !elements.consentInput) return false;
        
        const input = elements.consentInput;
        const errorElement = document.getElementById('consentError');
        
        // Check if checkbox is checked (required)
        if (!input.checked) {
            setFieldInvalid(input, errorElement, 'Vous devez accepter d\'être contacté');
            return false;
        }
        
        // All checks passed - field is valid
        setFieldValid(input, errorElement);
        return true;
    }

    // ============================================================================
    // VALIDATION HELPER FUNCTIONS
    // ============================================================================

    /**
     * Set field as invalid and show error message
     * 
     * This helper function reduces code duplication across validation functions.
     * 
     * @param {HTMLElement} input - The input element to mark as invalid
     * @param {HTMLElement} errorElement - The error message element
     * @param {string} message - The error message to display
     */
    function setFieldInvalid(input, errorElement, message) {
        // Add invalid styling
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        // Show error message
        showFieldError(errorElement, message);
    }

    /**
     * Set field as valid and clear error message
     * 
     * This helper function reduces code duplication across validation functions.
     * 
     * @param {HTMLElement} input - The input element to mark as valid
     * @param {HTMLElement} errorElement - The error message element
     */
    function setFieldValid(input, errorElement) {
        // Add valid styling
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
        // Clear error message
        clearFieldError(errorElement);
    }

    /**
     * Show error message for a field
     * 
     * @param {HTMLElement} errorElement - The error message element
     * @param {string} message - The error message to display
     */
    function showFieldError(errorElement, message) {
        if (errorElement) {
            // Set error message text
            errorElement.textContent = message;
            // Make error visible (CSS class handles styling)
            errorElement.classList.add('show');
        }
    }

    /**
     * Clear error message for a field
     * 
     * @param {HTMLElement} errorElement - The error message element
     */
    function clearFieldError(errorElement) {
        if (errorElement) {
            // Clear error message text
            errorElement.textContent = '';
            // Hide error (remove CSS class)
            errorElement.classList.remove('show');
        }
    }

    // ============================================================================
    // SUCCESS MESSAGE AUTO-HIDE
    // ============================================================================

    /**
     * Set up auto-hide for success message
     * 
     * After form submission, a success message is displayed.
     * This function automatically hides it after 5 seconds with a fade animation.
     * 
     * Why auto-hide?
     * - Keeps UI clean after user reads the message
     * - Prevents stale messages from lingering
     */
    function setupAutoHideSuccessMessage() {
        const successMessage = document.getElementById('successMessage');
        if (successMessage) {
            // Auto-hide after 5 seconds with smooth fade
            setTimeout(() => {
                // Check if message still exists (might have been manually closed)
                if (successMessage && successMessage.parentNode) {
                    // Add fade class for CSS animation
                    successMessage.classList.add('fade');
                    
                    // Remove element after animation completes
                    setTimeout(() => {
                        if (successMessage.parentNode) {
                            successMessage.remove();
                        }
                    }, 500); // Wait for fade animation to complete (500ms)
                }
            }, 5000); // Hide after 5 seconds
        }
    }

    // ============================================================================
    // AJAX FORM SUBMISSION
    // ============================================================================

    /**
     * Submit contact form via AJAX
     * 
     * This function:
     * - Prevents page reload (AJAX submission)
     * - Shows loading state on submit button
     * - Collects form data
     * - Sends POST request to server
     * - Handles success/error responses
     * - Resets form on success
     * 
     * Why AJAX?
     * - Better UX: no page reload, instant feedback
     * - Preserves form state if needed
     * - Can show inline notifications
     */
    function submitContactForm() {
        const elements = getFormElements();
        if (!elements || !elements.submitBtn) return;
        
        const form = elements.form;
        const submitBtn = elements.submitBtn;
        
        // Store original button text for restoration
        const originalText = submitBtn.innerHTML;
        
        /**
         * Show loading state
         * Disable button to prevent double submission
         * Change button text to show progress
         */
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Envoi en cours...';
        
        /**
         * Prepare form data
         * FormData is used instead of JSON to support file uploads (if needed in future)
         * Also automatically handles form encoding
         */
        const formData = new FormData();
        
        // Collect all form field values (using cached elements)
        formData.append('firstName', elements.firstNameInput.value.trim());
        formData.append('lastName', elements.lastNameInput.value.trim());
        formData.append('email', elements.emailInput.value.trim());
        formData.append('phone', elements.phoneInput ? elements.phoneInput.value.trim() : '');
        formData.append('subject', elements.subjectInput.value);
        formData.append('message', elements.messageInput.value.trim());
        // Convert checkbox boolean to string ('1' or '0')
        formData.append('consent', elements.consentInput.checked ? '1' : '0');
        
        /**
         * Add CSRF token to form data
         * CSRF token is required for security (prevents cross-site request forgery)
         */
        const csrfToken = window.getCsrfToken();
        if (csrfToken) {
            formData.append('_token', csrfToken);
        }
        
        /**
         * Submit form via AJAX
         * Uses fetch API for modern, promise-based HTTP requests
         */
        fetch('/contact-ajax', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest' // Identifies request as AJAX
            }
        })
        .then(response => response.json()) // Parse JSON response
        .then(data => {
            if (data.success) {
                // Form submitted successfully
                if (window.showNotification) {
                    window.showNotification(data.message, 'success');
                }
                // Reset form to allow new submission
                resetForm();
            } else {
                // Server returned error
                if (window.showNotification) {
                    window.showNotification(
                        data.message || 'Une erreur est survenue lors de l\'envoi de votre message.', 
                        'error'
                    );
                }
            }
        })
        .catch(error => {
            // Network error or other exception
            console.error('Contact form submission error:', error);
            if (window.showNotification) {
                window.showNotification('Une erreur est survenue lors de l\'envoi de votre message.', 'error');
            }
        })
        .finally(() => {
            /**
             * Restore button state
             * Always runs, regardless of success or error
             * Re-enables button and restores original text
             */
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    // ============================================================================
    // FORM RESET
    // ============================================================================

    /**
     * Reset form after successful submission
     * 
     * This function:
     * - Clears all form fields
     * - Removes validation states (is-valid, is-invalid classes)
     * - Clears all error messages
     * 
     * This allows user to submit a new message without refreshing the page.
     */
    function resetForm() {
        const elements = getFormElements();
        if (!elements) return;
        
        const form = elements.form;
        
        // Reset all form fields to default values
        form.reset();
        
        /**
         * Reset validation states
         * Remove both valid and invalid classes from all inputs
         */
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.classList.remove('is-valid', 'is-invalid');
        });
        
        /**
         * Clear error messages
         * Remove error text and hide error elements
         */
        const errorElements = form.querySelectorAll('.invalid-feedback');
        errorElements.forEach(element => {
            element.textContent = '';
            element.classList.remove('show');
        });
    }
})();
