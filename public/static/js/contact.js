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

(function () {
    'use strict';

    // Reuse the shared helper so validation messages stay consistent
    const FV = window.FormValidation;
    if (!FV) {
        console.error('FormValidation utility is required for contact.js');
        return;
    }

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
    document.addEventListener('DOMContentLoaded', function () {
        setupRealTimeValidation();
        setupFormSubmission();
        setupAutoHideSuccessMessage();
    });

    // ============================================================================
    // XSS DETECTION
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
            submitBtn: form.querySelector('button[type="submit"]'),
        };

        return formElementsCache;
    }

    /**
     * Helper to get element from cache
     * Simplifies element access throughout the code
     *
     * @param {string} elementKey - Key in cached elements object
     * @returns {HTMLElement|null} The element or null if not found
     */
    function getElement(elementKey) {
        const elements = getFormElements();
        return elements?.[elementKey] ?? null;
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

        elements.submitBtn.addEventListener('click', function (e) {
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
            if (
                isFirstNameValid &&
                isLastNameValid &&
                isEmailValid &&
                isPhoneValid &&
                isSubjectValid &&
                isMessageValid &&
                isConsentValid
            ) {
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
        const input = getElement('firstNameInput');
        if (!input) return false;

        const value = input.value.trim();
        const errorElement = document.getElementById('firstNameError');
        const result = FV.validateName(value, 'Le prénom');
        FV.applyFieldState(input, errorElement, result);
        return result.valid;
    }

    /**
     * Validate last name field
     *
     * Same validation logic as first name
     *
     * @returns {boolean} True if valid, false otherwise
     */
    function validateLastName() {
        const input = getElement('lastNameInput');
        if (!input) return false;

        const value = input.value.trim();
        const errorElement = document.getElementById('lastNameError');
        const result = FV.validateName(value, 'Le nom');
        FV.applyFieldState(input, errorElement, result);
        return result.valid;
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
        const input = getElement('emailInput');
        if (!input) return false;

        const value = input.value.trim();
        const errorElement = document.getElementById('emailError');
        const result = FV.validateEmail(value, { label: `L'email` });
        FV.applyFieldState(input, errorElement, result);
        return result.valid;
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
        const input = getElement('phoneInput');
        if (!input) return true; // No phone field, consider valid

        const value = input.value.trim();
        const errorElement = document.getElementById('phoneError');

        // Phone is optional - empty is valid
        if (value === '') {
            // Clear validation state (no error, no success)
            FV.clearFieldState(input, errorElement);
            return true;
        }

        const result = FV.validatePhone(value, {
            label: 'Le numéro de téléphone',
            required: false,
        });
        FV.applyFieldState(input, errorElement, result);
        return result.valid;
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
        const input = getElement('subjectInput');
        if (!input) return false;

        const value = input.value;
        const errorElement = document.getElementById('subjectError');

        // Check if empty (required field)
        if (value === '') {
            FV.applyFieldState(input, errorElement, {
                valid: false,
                message: 'Le sujet est requis',
            });
            return false;
        }

        // All checks passed - field is valid
        FV.applyFieldState(input, errorElement, { valid: true });
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
        const input = getElement('messageInput');
        if (!input) return false;

        const value = input.value.trim();
        const errorElement = document.getElementById('messageError');

        const result = FV.validateMessage(value, {
            label: 'Le message',
            required: true,
            min: 10,
            max: 1000,
        });
        FV.applyFieldState(input, errorElement, result);
        return result.valid;
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
        const input = getElement('consentInput');
        if (!input) return false;

        const errorElement = document.getElementById('consentError');

        // Check if checkbox is checked (required)
        if (!input.checked) {
            FV.applyFieldState(input, errorElement, {
                valid: false,
                message: "Vous devez accepter d'être contacté",
            });
            return false;
        }

        // All checks passed - field is valid
        FV.applyFieldState(input, errorElement, { valid: true });
        return true;
    }

    // ============================================================================
    // AUTO-HIDE SUCCESS MESSAGE
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
        const submitBtn = getElement('submitBtn');
        if (!submitBtn) return;

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
        const firstNameInput = getElement('firstNameInput');
        const lastNameInput = getElement('lastNameInput');
        const emailInput = getElement('emailInput');
        const phoneInput = getElement('phoneInput');
        const subjectInput = getElement('subjectInput');
        const messageInput = getElement('messageInput');
        const consentInput = getElement('consentInput');

        formData.append('firstName', firstNameInput?.value.trim() || '');
        formData.append('lastName', lastNameInput?.value.trim() || '');
        formData.append('email', emailInput?.value.trim() || '');
        formData.append('phone', phoneInput?.value.trim() || '');
        formData.append('subject', subjectInput?.value || '');
        formData.append('message', messageInput?.value.trim() || '');
        // Convert checkbox boolean to string ('1' or '0')
        formData.append('consent', consentInput?.checked ? '1' : '0');

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
                'X-Requested-With': 'XMLHttpRequest', // Identifies request as AJAX
            },
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
                            data.message ||
                                "Une erreur est survenue lors de l'envoi de votre message.",
                            'error'
                        );
                    }
                }
            })
            .catch(error => {
                // Network error or other exception
                console.error('Contact form submission error:', error);
                if (window.showNotification) {
                    window.showNotification(
                        "Une erreur est survenue lors de l'envoi de votre message.",
                        'error'
                    );
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
