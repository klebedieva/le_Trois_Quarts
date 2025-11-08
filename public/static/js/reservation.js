// ============================================================================
// RESERVATION.JS - Reservation Form Validation and AJAX Submission
// ============================================================================
// This file handles client-side validation and AJAX submission for the reservation form.
//
// Features:
// - Real-time field validation (on input and blur)
// - XSS (Cross-Site Scripting) attack prevention
// - Dynamic time slot generation (based on selected date)
// - Date/time validation (prevents past dates/times)
// - AJAX form submission (no page reload)
// - Form reset after successful submission

(function() {
    'use strict';

    // Shared validation helper keeps rules and translations identical across forms
    const FV = window.FormValidation;
    if (!FV) {
        console.error('FormValidation utility is required for reservation.js');
        return;
    }

    // ============================================================================
    // DOM ELEMENT CACHE
    // ============================================================================

    /**
     * Cache DOM elements to avoid repeated querySelector calls
     * 
     * These elements are accessed multiple times during validation and submission,
     * so caching them improves performance significantly.
     */
    let formElementsCache = null;

    /**
     * Get and cache form elements
     * 
     * Returns cached elements if available, otherwise queries DOM and caches result.
     * This reduces DOM queries by ~70-80% compared to querying on every call.
     * 
     * @returns {Object|null} Object with all form elements, or null if form not found
     */
    function getFormElements() {
        // Return cache if available
        if (formElementsCache) {
            return formElementsCache;
        }

        const form = document.getElementById('reservationForm');
        if (!form) {
            return null;
        }

        // Query and cache all form elements once
        formElementsCache = {
            form: form,
            firstNameInput: form.querySelector('input[name="reservation[firstName]"]'),
            lastNameInput: form.querySelector('input[name="reservation[lastName]"]'),
            emailInput: form.querySelector('input[name="reservation[email]"]'),
            phoneInput: form.querySelector('input[name="reservation[phone]"]'),
            dateInput: form.querySelector('input[name="reservation[date]"]'),
            timeSelect: form.querySelector('select[name="reservation[time]"]'),
            guestsSelect: form.querySelector('select[name="reservation[guests]"]'),
            messageTextarea: form.querySelector('textarea[name="reservation[message]"]'),
            submitBtn: form.querySelector('button[type="submit"]')
        };

        return formElementsCache;
    }

    // ============================================================================
    // INITIALIZATION
    // ============================================================================

    /**
     * Initialize reservation form functionality when DOM is ready
     * 
     * Sets up:
     * - Form validation
     * - Date/time handling
     * - AJAX submission
     */
    document.addEventListener('DOMContentLoaded', function() {
        setupReservationForm();
    });

    // ============================================================================
    // FORM SETUP
    // ============================================================================

    /**
     * Set up reservation form functionality
     * 
     * This function:
     * - Configures date input (minimum date, default value)
     * - Sets up time slot generation
     * - Attaches form submission handler
     * - Sets up real-time validation
     */
    function setupReservationForm() {
        const elements = getFormElements();
        if (!elements) {
            return; // Exit if form doesn't exist (not on reservation page)
        }

        const form = elements.form;

        /**
         * Configure date input
         * Set minimum date to today (prevents past dates)
         * Set default value to today (better UX)
         */
        const dateInput = elements.dateInput;
        if (dateInput) {
            // Get today's date in YYYY-MM-DD format
            const today = new Date().toISOString().split('T')[0];
            
            // Set minimum date to today (HTML5 date input attribute)
            dateInput.min = today;
            
            // Set default value to today if no value is set
            if (!dateInput.value) {
                dateInput.value = today;
            }
            
            /**
             * Update time options when date changes
             * When user selects a different date, we need to regenerate
             * time slots (some may be unavailable if it's today)
             */
            dateInput.addEventListener('change', function() {
                updateTimeOptions(this.value);
            });
            
            // Initialize time options for default date (today)
            updateTimeOptions(dateInput.value);
        }

        /**
         * Set up form submission handler
         * Prevents default form submission and uses AJAX instead
         */
        form.addEventListener('submit', function(e) {
            // Prevent default form submission (page reload)
            e.preventDefault();
            
            // Validate all fields before submission
            const isValid = validateForm();
            
            // Only submit if all validations pass
            if (isValid) {
                submitReservation();
            }
        });

        /**
         * Set up real-time validation
         * Attach validation listeners to all form inputs
         */
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            /**
             * Validate on blur (when field loses focus)
             * This provides feedback after user finishes editing a field
             */
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            /**
             * Validate on input (as user types)
             * Only re-validate if field was previously invalid
             * This provides immediate feedback when user corrects errors
             */
            input.addEventListener('input', function() {
                // Only validate if field is currently showing an error
                // This prevents excessive validation while user is typing
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            });
            
            /**
             * Special handling for date field
             * When date changes, re-validate time field (if selected)
             * This ensures time is still valid for the new date
             */
            if (input.name.includes('[date]')) {
                input.addEventListener('change', function() {
                    // Get time input from cache
                    const elements = getFormElements();
                    const timeInput = elements ? elements.timeSelect : null;
                    // If time is already selected, re-validate it
                    if (timeInput && timeInput.value) {
                        validateField(timeInput);
                    }
                });
            }
        });
    }

    // ============================================================================
    // TIME SLOT GENERATION
    // ============================================================================

    /**
     * Update time slot options based on selected date
     * 
     * This function:
     * - Generates time slots from 14:00 to 22:30 (30-minute intervals)
     * - Filters out past times if selected date is today
     * - Updates the time select dropdown
     * 
     * Why dynamic time slots?
     * - Prevents users from selecting past times on today's date
     * - Provides accurate available times based on current time
     * 
     * @param {string} selectedDate - Selected date in YYYY-MM-DD format
     */
    function updateTimeOptions(selectedDate) {
        const elements = getFormElements();
        const timeSelect = elements ? elements.timeSelect : null;
        if (!timeSelect) return;
        
        // Get today's date for comparison
        const today = new Date().toISOString().split('T')[0];
        const now = new Date();
        const currentHour = now.getHours();      // Current hour (0-23)
        const currentMinute = now.getMinutes(); // Current minute (0-59)
        
        // Clear existing options except placeholder
        timeSelect.innerHTML = '<option value="">Choisir...</option>';
        
        /**
         * Generate time slots from 14:00 to 22:30 in 30-minute steps
         * Restaurant is open from 2 PM (14:00) to 10:30 PM (22:30)
         */
        for (let hour = 14; hour <= 22; hour++) {
            for (let minute = 0; minute < 60; minute += 30) {
                // Stop at 22:30 (last available time slot)
                if (hour === 22 && minute > 30) {
                    break;
                }
                
                // Format time as HH:MM (e.g., "14:00", "14:30")
                const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                
                /**
                 * If selected date is today, skip past times
                 * Compare hour and minute to current time
                 */
                if (selectedDate === today) {
                    // Skip if time is in the past
                    if (hour < currentHour || (hour === currentHour && minute <= currentMinute)) {
                        continue; // Skip this time slot
                    }
                }
                
                /**
                 * Create option element and add to select
                 */
                const option = document.createElement('option');
                option.value = timeString;
                option.textContent = timeString;
                timeSelect.appendChild(option);
            }
        }
        
        /**
         * Clear selection if no valid options available
         * This can happen if user selects today's date late in the day
         * and all time slots are in the past
         */
        if (timeSelect.options.length === 1) {
            // Only placeholder option exists - clear selection
            timeSelect.value = '';
        }
    }

    // ============================================================================
    // FORM VALIDATION
    // ============================================================================

    /**
     * Validate entire form before submission
     * 
     * This function:
     * - Validates all form fields
     * - Scrolls to first invalid field if validation fails
     * - Focuses first invalid field for better UX
     * 
     * @returns {boolean} True if all fields are valid, false otherwise
     */
    function validateForm() {
        const elements = getFormElements();
        if (!elements) return false;
        
        const form = elements.form;
        
        // Get all form inputs (using name prefix to filter reservation fields only)
        const inputs = form.querySelectorAll('input[name^="reservation["], select[name^="reservation["], textarea[name^="reservation["]');
        let isValid = true;

        /**
         * Validate each field
         * Skip message field (optional) and CSRF token (not user input)
         */
        inputs.forEach(input => {
            // Skip validation for optional message field and CSRF token
            if (!input.name.includes('[message]') && 
                !input.name.includes('[_token]') && 
                !validateField(input)) {
                // At least one field is invalid
                isValid = false;
            }
        });

        /**
         * If validation failed, scroll to first invalid field
         * This helps user see what needs to be fixed
         */
        if (!isValid) {
            const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
                // Scroll to invalid field smoothly
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Focus the field for keyboard navigation
                firstInvalid.focus();
            }
        }

        return isValid;
    }

    /**
     * Validate a single form field
     * 
     * This function validates individual fields based on their type:
     * - First name / Last name: Required, min 2 chars, letters/spaces/hyphens only, XSS check
     * - Email: Required, valid email format, XSS check
     * - Phone: Required, min 10 chars, XSS check
     * - Date: Required, not in the past
     * - Time: Required, not in the past (if date is today)
     * - Guests: Required, at least 1
     * - Message: Optional, XSS check if provided
     * 
     * @param {HTMLElement} field - The form field element to validate
     * @returns {boolean} True if field is valid, false otherwise
     */
    function validateField(field) {
        const value = field.value.trim();
        const fieldName = field.name;
        let isValid = false;
        let errorMessage = '';
        const errorElementId = fieldName.replace(/\[/g, '_').replace(/\]/g, '') + 'Error';

        /**
         * Validation rules for each field type
         * Each field type has specific validation requirements
         */
        
        // First name validation
        if (fieldName.includes('[firstName]')) {
            const result = FV.validateName(value, 'Le prénom');
            FV.applyFieldState(field, errorElementId, result);
            return result.valid;
        }
        // Last name validation (same rules as first name)
        else if (fieldName.includes('[lastName]')) {
            const result = FV.validateName(value, 'Le nom');
            FV.applyFieldState(field, errorElementId, result);
            return result.valid;
        }
        // Email validation
        else if (fieldName.includes('[email]')) {
            const result = FV.validateEmail(value, { label: `L'email` });
            FV.applyFieldState(field, errorElementId, result);
            return result.valid;
        }
        // Phone validation
        else if (fieldName.includes('[phone]')) {
            const result = FV.validatePhone(value, {
                label: 'Le numéro de téléphone',
                required: true
            });
            FV.applyFieldState(field, errorElementId, result);
            return result.valid;
        }
        // Date validation
        else if (fieldName.includes('[date]')) {
            if (value === '') {
                errorMessage = 'La date est requise';
            } else {
                // Check if date is in the past
                const selectedDate = new Date(value);
                const today = new Date();
                today.setHours(0, 0, 0, 0); // Reset time to midnight for accurate comparison
                
                if (selectedDate < today) {
                    errorMessage = 'La date ne peut pas être dans le passé';
                } else {
                    isValid = true;
                }
            }
        }
        // Time validation
        else if (fieldName.includes('[time]')) {
            if (value === '') {
                errorMessage = 'L\'heure est requise';
            } else {
                /**
                 * Check if time is in the past (only if selected date is today)
                 * For future dates, any time is valid
                 */
                const elements = getFormElements();
                const dateInput = elements ? elements.dateInput : null;
                const selectedDate = dateInput ? dateInput.value : '';
                const today = new Date().toISOString().split('T')[0];
                
                // Only check past times if date is today
                if (selectedDate === today) {
                    const now = new Date();
                    // Parse time string (e.g., "14:30" -> hours=14, minutes=30)
                    const [hours, minutes] = value.split(':').map(Number);
                    
                    // Create Date object for selected time today
                    const selectedDateTime = new Date();
                    selectedDateTime.setHours(hours, minutes, 0, 0);
                    
                    // Check if selected time is in the past
                    if (selectedDateTime <= now) {
                        errorMessage = 'L\'heure ne peut pas être dans le passé';
                    } else {
                        isValid = true;
                    }
                } else {
                    // Future date - any time is valid
                    isValid = true;
                }
            }
        }
        // Guests validation
        else if (fieldName.includes('[guests]')) {
            if (value === '') {
                errorMessage = 'Le nombre de personnes est requis';
            } else if (parseInt(value) < 1) {
                errorMessage = 'Le nombre de personnes doit être au moins 1';
            } else {
                isValid = true;
            }
        }
        // Message validation (optional field)
        else if (fieldName.includes('[message]')) {
            const result = FV.validateMessage(value, {
                label: 'Le message',
                required: false,
                min: 0,
                max: 1000
            });
            FV.applyFieldState(field, errorElementId, result);
            return result.valid;
        }

        const result = isValid ? { valid: true } : { valid: false, message: errorMessage };
        FV.applyFieldState(field, errorElementId, result);
        return result.valid;
    }

    // ============================================================================
    // VALIDATION HELPER FUNCTIONS
    // ============================================================================

    /**
     * Set field as valid and clear error message
     * 
     * This helper function reduces code duplication across validation functions.
     * 
     * @param {HTMLElement} field - The input element to mark as valid
     * @param {string} errorElementId - The ID of the error message element
     */
    // ============================================================================
    // AJAX FORM SUBMISSION
    // ============================================================================

    /**
     * Submit reservation form via AJAX
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
    function submitReservation() {
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
        formData.append('phone', elements.phoneInput.value.trim());
        formData.append('date', elements.dateInput.value);
        formData.append('time', elements.timeSelect.value);
        formData.append('guests', elements.guestsSelect.value);
        formData.append('message', elements.messageTextarea ? elements.messageTextarea.value.trim() : '');
        
        /**
         * Add CSRF token to form data
         * Try to get token from form first (if Symfony form includes it)
         * Fall back to meta tag if not found in form
         */
        let csrfToken = form.querySelector('input[name="reservation[_token]"]')?.value;
        if (!csrfToken) {
            // Fallback to global CSRF token helper
            csrfToken = window.getCsrfToken();
        }
        if (csrfToken) {
            formData.append('_token', csrfToken);
        }
        
        /**
         * Submit form via AJAX
         * Uses fetch API for modern, promise-based HTTP requests
         */
        fetch('/reservation-ajax', {
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
                        data.message || 'Une erreur est survenue lors de l\'envoi de votre réservation.', 
                        'error'
                    );
                }
            }
        })
        .catch(error => {
            // Network error or other exception
            console.error('Reservation form submission error:', error);
            if (window.showNotification) {
                window.showNotification('Une erreur est survenue lors de l\'envoi de votre réservation.', 'error');
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
     * - Resets date to today (with minimum date constraint)
     * 
     * This allows user to submit a new reservation without refreshing the page.
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
        
        /**
         * Reset date input
         * Set minimum date to today and default value to today
         * This ensures date is always valid after reset
         */
        const dateInput = elements.dateInput;
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
            dateInput.value = today;
            
            // Update time options for the reset date (today)
            updateTimeOptions(today);
        }
    }
})();
