// ============================================================================
// REVIEWS.JS - Review Form Validation and AJAX Submission
// ============================================================================
// This file handles client-side validation and AJAX submission for review forms.
//
// Features:
// - Star rating system (interactive rating selection)
// - Real-time field validation (on input and blur)
// - Multiple review forms support (page reviews, dish reviews)
// - AJAX form submission (no page reload)
// - Dynamic review loading (pagination support)
// - Modal management (Bootstrap modal integration)

(function () {
    'use strict';

    // Import the shared helper so review forms follow the same validation rules/messages
    const FV = window.FormValidation;
    if (!FV) {
        console.error('FormValidation utility is required for reviews.js');
        return;
    }

    // ============================================================================
    // DOM ELEMENT CACHE
    // ============================================================================

    /**
     * Cache for form elements by modal ID
     *
     * These elements are accessed multiple times during validation and submission,
     * so caching them improves performance significantly.
     */
    const formElementsCache = {};

    /**
     * Get and cache form elements for a specific modal
     *
     * Returns cached elements if available, otherwise queries DOM and caches result.
     * This reduces DOM queries by ~70-80% compared to querying on every call.
     *
     * @param {string} modalId - The modal ID
     * @returns {Object|null} Object with all form elements, or null if modal not found
     */
    function getFormElements(modalId) {
        // Return cache if available
        if (formElementsCache[modalId]) {
            return formElementsCache[modalId];
        }

        const form = document.getElementById(modalId + 'Form');
        if (!form) {
            return null;
        }

        // Query and cache all form elements once
        formElementsCache[modalId] = {
            form: form,
            nameInput: document.getElementById(modalId + 'Name'),
            emailInput: document.getElementById(modalId + 'Email'),
            ratingInput: document.getElementById(modalId + 'Rating'),
            textInput: document.getElementById(modalId + 'Text'),
            submitBtn: document.getElementById(modalId + 'Submit'),
            nameError: document.getElementById(modalId + 'NameError'),
            emailError: document.getElementById(modalId + 'EmailError'),
            ratingError: document.getElementById(modalId + 'RatingError'),
            textError: document.getElementById(modalId + 'TextError'),
        };

        return formElementsCache[modalId];
    }

    /**
     * Helper to get element from cache or fallback to direct query
     * Simplifies element access throughout the code
     *
     * @param {string} modalId - The modal ID
     * @param {string} elementKey - Key in cached elements object
     * @param {string} fallbackId - Fallback element ID if cache unavailable
     * @returns {HTMLElement|null} The element or null if not found
     */
    function getElement(modalId, elementKey, fallbackId) {
        const elements = getFormElements(modalId);
        return elements?.[elementKey] ?? (fallbackId ? document.getElementById(fallbackId) : null);
    }

    // ============================================================================
    // INITIALIZATION
    // ============================================================================

    /**
     * Initialize review form functionality when DOM is ready
     *
     * Sets up:
     * - Star rating system
     * - Real-time validation
     * - Form submission handlers
     * - Modal event listeners
     * - Review list loading (if endpoint provided)
     */
    document.addEventListener('DOMContentLoaded', function () {
        setupStarRating();
        setupRealTimeValidation();
        setupFormSubmission();

        /**
         * Set up star rating for modals when they are shown
         * Bootstrap modals can be dynamically added, so we need to
         * reinitialize star rating when modal becomes visible
         */
        document.addEventListener('shown.bs.modal', function (e) {
            const modal = e.target;
            const modalId = modal.id;

            // Initialize star rating for this specific modal
            setupModalStarRating(modalId);
        });

        /**
         * Clean up modal styles when modal is hidden
         * Bootstrap sometimes leaves modal styles behind, so we manually
         * clean them up to prevent styling issues
         */
        document.addEventListener('hidden.bs.modal', cleanupModalStyles);

        /**
         * Load reviews from API if endpoint is provided
         * This allows dynamic review loading from the server
         */
        if (window.REVIEWS_LIST_ENDPOINT) {
            // Fetch initial reviews, then initialize load more functionality
            fetchAndRenderReviews(window.REVIEWS_LIST_ENDPOINT).then(() => {
                initLoadMoreExistingReviews();
            });
        } else {
            // No API endpoint - just initialize load more (for static reviews)
            initLoadMoreExistingReviews();
        }
    });

    // ============================================================================
    // STAR RATING SYSTEM
    // ============================================================================

    /**
     * Set up star rating system with event delegation
     *
     * This function uses event delegation to handle star clicks and hover effects
     * for dynamically added elements. This is more efficient than attaching
     * listeners to each star individually.
     *
     * Features:
     * - Click to select rating
     * - Hover to preview rating
     * - Works with multiple modals (page reviews, dish reviews)
     */
    function setupStarRating() {
        /**
         * Handle star clicks (rating selection)
         * Event delegation allows this to work with dynamically added stars
         */
        document.addEventListener('click', function (e) {
            // Check if clicked element is a star
            if (e.target && e.target.classList && e.target.classList.contains('star-rating')) {
                const star = e.target;
                const group = star.closest('.rating-stars');

                if (group) {
                    // Find modal ID (for multiple modals support)
                    const modalId = group.closest('.modal')?.id || 'addReviewModal';
                    const ratingInput = getElement(modalId, 'ratingInput', modalId + 'Rating');

                    if (!ratingInput) {
                        return;
                    }

                    // Get rating value from star's data attribute
                    const rating = parseInt(star.getAttribute('data-rating'));

                    // Set rating and validate
                    setRating(rating, ratingInput);
                    validateRating(ratingInput);
                }
            }
        });

        /**
         * Enable keyboard navigation for star buttons (radio-style interaction)
         */
        document.addEventListener('keydown', function (e) {
            if (!e.target || !e.target.classList || !e.target.classList.contains('star-rating')) {
                return;
            }
            const star = e.target;
            const group = star.closest('.rating-stars');
            if (!group) {
                return;
            }

            const modalId = group.closest('.modal')?.id || 'addReviewModal';
            const ratingInput = getElement(modalId, 'ratingInput', modalId + 'Rating');
            if (!ratingInput) {
                return;
            }

            const maxRating = 5;
            const minRating = 1;
            let currentRating = parseInt(ratingInput.value) || 0;
            let newRating = currentRating;
            let handled = false;

            switch (e.key) {
                case 'ArrowRight':
                case 'ArrowUp':
                    newRating =
                        currentRating > 0
                            ? Math.min(maxRating, currentRating + 1)
                            : parseInt(star.getAttribute('data-rating'));
                    handled = true;
                    break;
                case 'ArrowLeft':
                case 'ArrowDown':
                    if (currentRating > 0) {
                        newRating = Math.max(minRating, currentRating - 1);
                    } else {
                        newRating = parseInt(star.getAttribute('data-rating'));
                    }
                    handled = true;
                    break;
                case 'Home':
                    newRating = minRating;
                    handled = true;
                    break;
                case 'End':
                    newRating = maxRating;
                    handled = true;
                    break;
                case 'Delete':
                case 'Backspace':
                    newRating = 0;
                    handled = true;
                    break;
                default:
                    break;
            }

            if (handled) {
                e.preventDefault();
                const targetStar =
                    newRating > 0
                        ? group.querySelector(`[data-rating="${newRating}"]`)
                        : group.querySelector('[data-rating="1"]');
                setRating(newRating, ratingInput, { focusTarget: targetStar || star });
                validateRating(ratingInput);
            }
        });

        /**
         * Handle star hover (preview rating)
         * Shows preview of rating when user hovers over stars
         */
        document.addEventListener(
            'mouseenter',
            function (e) {
                if (e.target && e.target.classList && e.target.classList.contains('star-rating')) {
                    const star = e.target;
                    const group = star.closest('.rating-stars');

                    if (group) {
                        const stars = group.querySelectorAll('.star-rating');
                        const rating = parseInt(star.getAttribute('data-rating'));

                        // Highlight stars up to hovered star
                        highlightStars(rating, stars);
                    }
                }
            },
            true
        ); // Use capture phase for better performance

        /**
         * Handle star group mouse leave (reset to selected rating)
         * When user leaves star group, reset to currently selected rating
         */
        document.addEventListener(
            'mouseleave',
            function (e) {
                if (e.target && e.target.classList && e.target.classList.contains('rating-stars')) {
                    const group = e.target;
                    const modalId = group.closest('.modal')?.id || 'addReviewModal';
                    const ratingInput = getElement(modalId, 'ratingInput', modalId + 'Rating');
                    const stars = group.querySelectorAll('.star-rating');

                    if (!ratingInput) return;

                    // Reset to currently selected rating
                    const currentRating = parseInt(ratingInput.value);
                    highlightStars(currentRating, stars, { updateAria: true });
                }
            },
            true
        ); // Use capture phase for better performance
    }

    /**
     * Set up star rating for a specific modal
     *
     * This function is called when a modal is shown. It ensures the rating
     * display is synchronized with the current value. Event delegation in
     * setupStarRating handles all interactions, so we only need to sync state.
     *
     * @param {string} modalId - The ID of the modal containing the rating stars
     */
    function setupModalStarRating(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        const ratingInput = getElement(modalId, 'ratingInput', modalId + 'Rating');
        if (!ratingInput) return;

        // Sync star display with current rating value
        const currentRatingValue = parseInt(ratingInput.value) || 0;
        setRating(currentRatingValue, ratingInput);
    }

    /**
     * Set rating value and update star display
     *
     * @param {number} rating - The rating value (1-5)
     * @param {HTMLElement} ratingInput - The hidden input element storing the rating
     */
    function setRating(rating, ratingInput, options = {}) {
        const { focusTarget = null } = options;
        // Set rating value in hidden input
        ratingInput.value = rating;

        const modalElement = ratingInput.closest('.modal');
        if (!modalElement) {
            return;
        }

        // Find stars in the same modal and highlight them
        const stars = modalElement.querySelectorAll('.star-rating');
        if (!stars || stars.length === 0) {
            return;
        }
        highlightStars(rating, stars, { updateAria: true });

        stars.forEach((star, index) => {
            const starValue = index + 1;
            const shouldBeTabbable = rating > 0 ? starValue === rating : starValue === 1;
            star.setAttribute('tabindex', shouldBeTabbable ? '0' : '-1');
        });

        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus();
        }
    }

    /**
     * Highlight stars based on rating value
     *
     * This function visually updates stars to show selected rating:
     * - Filled stars (bi-star-fill) for selected rating
     * - Empty stars (bi-star) for unselected rating
     *
     * @param {number} rating - The rating value (0-5)
     * @param {NodeList} stars - The star elements to update
     */
    function highlightStars(rating, stars, options = {}) {
        const { updateAria = false } = options;
        stars.forEach((star, index) => {
            // Index is 0-based, rating is 1-based
            if (index < rating) {
                // Star is selected - show filled star
                star.classList.remove('bi-star');
                star.classList.add('bi-star-fill');
            } else {
                // Star is not selected - show empty star
                star.classList.remove('bi-star-fill');
                star.classList.add('bi-star');
            }

            if (updateAria) {
                const isChecked = index < rating;
                star.setAttribute('aria-checked', isChecked ? 'true' : 'false');
            }
        });
    }

    // ============================================================================
    // MODAL CLEANUP
    // ============================================================================

    /**
     * Clean up modal styles after modal is hidden
     *
     * Bootstrap sometimes leaves modal styles (backdrop, body classes, etc.)
     * after modal is closed. This function manually removes them to prevent
     * styling issues like locked scroll or remaining backdrop.
     */
    function cleanupModalStyles() {
        // Remove backdrop element if it exists
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }

        /**
         * Clean up body styles
         * Bootstrap adds these styles when modal is open
         */
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        document.body.style.paddingLeft = '';
        document.body.style.marginRight = '';
        document.body.style.marginLeft = '';

        /**
         * Clean up HTML element styles
         * Bootstrap also adds classes to html element
         */
        document.documentElement.classList.remove('modal-open');
        document.documentElement.style.overflow = '';
        document.documentElement.style.paddingRight = '';
    }

    // ============================================================================
    // REAL-TIME VALIDATION SETUP
    // ============================================================================

    /**
     * Set up real-time validation for all review forms on the page
     *
     * This function finds all review forms (by ID pattern) and attaches
     * validation listeners to each field. Supports multiple forms (page reviews,
     * dish reviews, etc.).
     *
     * Real-time validation provides better UX:
     * - Users see errors immediately
     * - Don't have to wait until form submission
     * - Can fix errors before submitting
     */
    function setupRealTimeValidation() {
        // Find all review forms (forms with IDs ending in "Form")
        const reviewForms = document.querySelectorAll('form[id$="Form"]');

        reviewForms.forEach(function (form) {
            // Extract modal ID from form ID (e.g., "addReviewForm" -> "addReview")
            const modalId = form.id.replace('Form', '');

            // Get form fields using cache (cache will be created on first access)
            const elements = getFormElements(modalId);
            if (!elements) return;

            const nameInput = elements.nameInput;
            const emailInput = elements.emailInput;
            const textInput = elements.textInput;

            /**
             * Name validation
             * Validates on input (as user types) and blur (when field loses focus)
             */
            if (nameInput) {
                nameInput.addEventListener('input', () => validateName(nameInput, modalId));
                nameInput.addEventListener('blur', () => validateName(nameInput, modalId));
            }

            /**
             * Email validation
             * Validates on input and blur (email is optional)
             */
            if (emailInput) {
                emailInput.addEventListener('input', () => validateEmail(emailInput, modalId));
                emailInput.addEventListener('blur', () => validateEmail(emailInput, modalId));
            }

            /**
             * Comment validation
             * Validates on input and blur
             */
            if (textInput) {
                textInput.addEventListener('input', () => validateComment(textInput, modalId));
                textInput.addEventListener('blur', () => validateComment(textInput, modalId));
            }
        });
    }

    // ============================================================================
    // FORM SUBMISSION SETUP
    // ============================================================================

    /**
     * Set up form submission handlers for all review forms
     *
     * This function finds all submit buttons (by ID pattern) and attaches
     * click handlers that validate and submit forms via AJAX.
     */
    function setupFormSubmission() {
        // Find all submit buttons (buttons with IDs ending in "Submit")
        const submitButtons = document.querySelectorAll('button[id$="Submit"]');

        submitButtons.forEach(function (button) {
            button.addEventListener('click', function (e) {
                // Prevent default form submission (no page reload)
                e.preventDefault();

                // Extract modal ID from button ID (e.g., "addReviewSubmit" -> "addReview")
                const modalId = button.id.replace('Submit', '');
                const form = document.getElementById(modalId + 'Form');

                if (!form) return;

                /**
                 * Validate all fields before submission
                 * All validations must pass for form to be submitted
                 */
                const isNameValid = validateName(
                    getElement(modalId, 'nameInput', modalId + 'Name'),
                    modalId
                );
                const isEmailValid = validateEmail(
                    getElement(modalId, 'emailInput', modalId + 'Email'),
                    modalId
                );
                const isRatingValid = validateRating(
                    getElement(modalId, 'ratingInput', modalId + 'Rating')
                );
                const isCommentValid = validateComment(
                    getElement(modalId, 'textInput', modalId + 'Text'),
                    modalId
                );

                if (isNameValid && isEmailValid && isRatingValid && isCommentValid) {
                    // All validations passed - submit form
                    submitReview(form, modalId);
                } else {
                    /**
                     * Scroll to first invalid field
                     * This helps user see what needs to be fixed
                     */
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                }
            });
        });
    }

    // ============================================================================
    // VALIDATION FUNCTIONS
    // ============================================================================

    /**
     * Validate name field
     *
     * Checks:
     * 1. Field is not empty (required)
     * 2. Matches name pattern (letters, spaces, hyphens only)
     *
     * @param {HTMLElement} input - The name input element
     * @param {string} modalId - The modal ID for error element lookup
     * @returns {boolean} True if valid, false otherwise
     */
    function validateName(input, modalId) {
        if (!input) return true; // No input element, consider valid

        const value = input.value.trim();
        const errorElement = getElement(modalId, 'nameError', modalId + 'NameError');

        const result = FV.validateName(value, 'Le nom');
        FV.applyFieldState(input, errorElement, result);
        return result.valid;
    }

    /**
     * Validate email field
     *
     * Checks:
     * 1. Field is empty OR valid (email is optional)
     * 2. Matches email pattern (if provided)
     *
     * @param {HTMLElement} input - The email input element
     * @param {string} modalId - The modal ID for error element lookup
     * @returns {boolean} True if valid, false otherwise
     */
    function validateEmail(input, modalId) {
        if (!input) return true; // No input element, consider valid

        const value = input.value.trim();
        const errorElement = getElement(modalId, 'emailError', modalId + 'EmailError');

        const result = FV.validateEmail(value, {
            label: "L'email",
            // email optional
        });
        if (value === '') {
            FV.clearFieldState(input, errorElement);
            return true;
        }
        FV.applyFieldState(input, errorElement, result);
        return result.valid;
    }

    /**
     * Validate rating field
     *
     * Checks:
     * 1. Rating is selected (not 0 or NaN)
     *
     * @param {HTMLElement} ratingInput - The rating input element
     * @returns {boolean} True if valid, false otherwise
     */
    function validateRating(ratingInput) {
        if (!ratingInput) return true; // No input element, consider valid

        const value = parseInt(ratingInput.value);
        const modalId = ratingInput.id.replace('Rating', '');
        const errorElement = getElement(modalId, 'ratingError', modalId + 'RatingError');

        const result =
            value === 0 || isNaN(value)
                ? { valid: false, message: 'Veuillez sélectionner une note' }
                : { valid: true };

        FV.applyFieldState(ratingInput, errorElement, result);
        return result.valid;
    }

    /**
     * Validate comment field
     *
     * Checks:
     * 1. Field is not empty (required)
     * 2. Length between 10 and 1000 characters
     * 3. Matches comment pattern (no HTML tags)
     *
     * @param {HTMLElement} input - The comment textarea element
     * @param {string} modalId - The modal ID for error element lookup
     * @returns {boolean} True if valid, false otherwise
     */
    function validateComment(input, modalId) {
        if (!input) return true; // No input element, consider valid

        const value = input.value.trim();
        const errorElement = getElement(modalId, 'textError', modalId + 'TextError');

        const result = FV.validateMessage(value, {
            label: "L'avis",
            required: true,
            min: 10,
            max: 1000,
        });
        FV.applyFieldState(input, errorElement, result);
        return result.valid;
    }

    // ============================================================================
    // VALIDATION HELPER FUNCTIONS
    // ============================================================================

    // ============================================================================
    // AJAX FORM SUBMISSION
    // ============================================================================

    /**
     * Submit review form via AJAX
     *
     * This function:
     * - Prevents page reload (AJAX submission)
     * - Shows loading state on submit button
     * - Collects form data
     * - Sends POST request to server (supports both API and form endpoints)
     * - Handles success/error responses
     * - Closes modal and resets form on success
     *
     * Why AJAX?
     * - Better UX: no page reload, instant feedback
     * - Preserves page state
     * - Can show inline notifications
     *
     * @param {HTMLElement} form - The form element to submit
     * @param {string} modalId - The modal ID for field lookup
     */
    function submitReview(form, modalId) {
        // Get cached form elements
        const elements = getFormElements(modalId);
        if (!elements || !elements.submitBtn) return;

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
         * Gather form field values (using cached elements)
         */
        const name = elements.nameInput.value.trim();
        const email = elements.emailInput.value.trim();
        const rating = elements.ratingInput.value;
        const comment = elements.textInput.value.trim();
        // Get CSRF token: first try form field, then fallback to global meta tag
        let csrfToken = form.querySelector('input[name="_token"]')?.value;
        if (!csrfToken && window.getCsrfToken) {
            csrfToken = window.getCsrfToken();
        }
        const dishIdInput = form.querySelector('input[name="dish_id"]');

        /**
         * Choose endpoint and payload format
         * Supports both API endpoints (JSON) and form endpoints (FormData)
         */
        const endpoint = window.REVIEWS_ENDPOINT || '/api/review';
        const isApiEndpoint = endpoint.startsWith('/api/');

        let payload;
        if (isApiEndpoint) {
            /**
             * API endpoint - use JSON payload
             * Modern API format with structured data
             */
            payload = {
                name: name,
                email: email || null, // Email is optional
                rating: parseInt(rating),
                comment: comment,
            };

            /**
             * Add dish_id if it's a dish review
             * This allows linking reviews to specific dishes
             */
            if (dishIdInput) {
                payload.dish_id = parseInt(dishIdInput.value);
            }
        } else {
            /**
             * Form endpoint - use FormData payload
             * Legacy form submission format
             */
            payload = new FormData();
            payload.append('name', name);
            payload.append('email', email);
            payload.append('rating', rating);
            payload.append('comment', comment);
            if (dishIdInput) {
                payload.append('dish_id', dishIdInput.value);
            }
            if (csrfToken) {
                payload.append('_token', csrfToken);
            }
        }

        /**
         * Submit form via AJAX
         * Uses fetch API for modern, promise-based HTTP requests
         * For API endpoints, use window.apiRequest() to automatically include CSRF token
         */
        const requestPromise = isApiEndpoint
            ? window.apiRequest(endpoint, {
                  method: 'POST',
                  body: JSON.stringify(payload),
              })
            : fetch(endpoint, {
                  method: 'POST',
                  headers: {
                      'X-Requested-With': 'XMLHttpRequest', // Identifies request as AJAX
                  },
                  body: payload,
              });
        
        requestPromise
            .then(response => response.json()) // Parse JSON response
            .then(data => {
                if (data.success) {
                    // Form submitted successfully
                    showSuccessMessage();

                    /**
                     * Close the modal gracefully
                     * Use Bootstrap Modal API to properly close modal
                     */
                    const openModalEl = document.getElementById(modalId);
                    if (openModalEl && window.bootstrap) {
                        const modalInstance =
                            window.bootstrap.Modal.getInstance(openModalEl) ||
                            new window.bootstrap.Modal(openModalEl);
                        modalInstance.hide();
                    }

                    /**
                     * Force remove backdrop if modal doesn't close properly
                     * Bootstrap sometimes leaves backdrop behind, so we clean it up
                     */
                    setTimeout(() => {
                        cleanupModalStyles();
                    }, 100);

                    // Reset form to allow new submission
                    resetForm(modalId);

                    /**
                     * Notify dish page (if any) to refresh its list without reloading
                     * This allows dynamic update of review lists after submission
                     */
                    document.dispatchEvent(new CustomEvent('review:submitted'));
                } else {
                    // Server returned error
                    showErrorMessage(
                        data.message || "Une erreur est survenue lors de l'envoi de votre avis."
                    );
                }
            })
            .catch(error => {
                // Network error or other exception
                console.error('Review submission error:', error);
                showErrorMessage("Une erreur est survenue lors de l'envoi de votre avis.");

                /**
                 * Force close modal on error too
                 * User shouldn't be stuck with open modal on error
                 */
                const openModalEl = document.getElementById(modalId);
                if (openModalEl && window.bootstrap) {
                    const modalInstance =
                        window.bootstrap.Modal.getInstance(openModalEl) ||
                        new window.bootstrap.Modal(openModalEl);
                    modalInstance.hide();
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
    // NOTIFICATION FUNCTIONS
    // ============================================================================

    /**
     * Auto-hide notification after delay with fade animation
     *
     * This helper function reduces code duplication and provides consistent
     * auto-hide behavior for all notifications.
     *
     * @param {HTMLElement} notification - The notification element
     * @param {number} delay - Delay in milliseconds (default: 5000)
     */
    function autoHideNotification(notification, delay = 5000) {
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.add('fade');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 500); // Wait for fade animation to complete
            }
        }, delay);
    }

    /**
     * Show notification message (success or error)
     *
     * Creates a temporary notification that auto-hides after 5 seconds.
     * Removes any existing notifications before showing new one.
     *
     * This consolidated function replaces showSuccessMessage and showErrorMessage
     * to reduce code duplication.
     *
     * @param {string} message - The message to display
     * @param {string} type - Notification type: 'success' or 'error' (default: 'success')
     */
    function showNotification(message, type = 'success') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());

        // Determine icon and class based on type
        const icon =
            type === 'success'
                ? '<i class="bi bi-check-circle me-2"></i>'
                : '<i class="bi bi-exclamation-triangle me-2"></i>';
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';

        /**
         * Create the notification element
         * Uses Bootstrap alert classes for styling
         */
        const notification = document.createElement('div');
        notification.className = `notification alert ${alertClass} alert-dismissible show alert-fixed-center`;

        notification.innerHTML = `
            <span class="notification-content">${icon}${message}</span>
            <button type="button" class="btn-close" onclick="this.parentElement.classList.add('fade'); setTimeout(() => this.parentElement.remove(), 500)"></button>
        `;

        document.body.appendChild(notification);

        // Auto-hide after 5 seconds
        autoHideNotification(notification, 5000);
    }

    /**
     * Show success notification message
     *
     * @param {string} message - Optional custom message (defaults to standard success message)
     */
    function showSuccessMessage(message) {
        showNotification(
            message || 'Votre avis a été envoyé et sera publié après modération.',
            'success'
        );
    }

    /**
     * Show error notification message
     *
     * @param {string} message - The error message to display
     */
    function showErrorMessage(message) {
        showNotification(message, 'error');
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
     * - Resets star rating to 0
     * - Clears all error messages
     *
     * This allows user to submit a new review without refreshing the page.
     *
     * @param {string} modalId - The modal ID for field lookup
     */
    function resetForm(modalId) {
        // Get cached form elements
        const elements = getFormElements(modalId);
        if (!elements) return;

        // Clear all form fields (using cached elements)
        elements.nameInput.value = '';
        elements.emailInput.value = '';
        elements.ratingInput.value = '0';
        elements.textInput.value = '';

        /**
         * Reset validation states
         * Remove both valid and invalid classes from all inputs
         */
        const form = elements.form;
        const inputs = form.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.classList.remove('is-valid', 'is-invalid');
        });

        /**
         * Reset stars
         * Set all stars to empty state (rating = 0)
         */
        setRating(0, elements.ratingInput);

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

    // ============================================================================
    // REVIEW LIST LOADING
    // ============================================================================

    /**
     * Current page for pagination
     * Tracks which page of reviews is currently loaded
     */
    let currentPage = 1;

    /**
     * Initialize "Load More" button functionality
     *
     * Sets up click handler for load more button that fetches and displays
     * additional reviews from the API.
     */
    function initLoadMoreExistingReviews() {
        const loadMoreBtn = document.getElementById('loadMoreReviews');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', async function () {
                try {
                    /**
                     * Show loading state
                     * Disable button and change text during loading
                     */
                    this.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Chargement...';
                    this.disabled = true;

                    /**
                     * Load next page of reviews
                     * Increment page number and fetch from API
                     */
                    currentPage++;
                    const response = await fetch(
                        `${window.REVIEWS_LIST_ENDPOINT}?page=${currentPage}&limit=6`
                    );
                    const data = await response.json();
                    const payload = data?.data || data;
                    const reviews = payload?.reviews || [];
                    const pagination = payload?.pagination;

                    if (data.success && Array.isArray(reviews)) {
                        // Append new reviews to existing ones
                        appendReviews(reviews);

                        /**
                         * Hide button if no more reviews
                         * If pagination indicates no more reviews, hide button
                         */
                        if (!pagination || !pagination.has_more) {
                            this.style.display = 'none';
                        } else {
                            // Reset button state for next load
                            this.innerHTML =
                                '<i class="bi bi-arrow-down me-2"></i>Charger plus d\'avis';
                            this.disabled = false;
                        }
                    } else {
                        throw new Error('Failed to load reviews');
                    }
                } catch (error) {
                    console.error('Error loading more reviews:', error);
                    // Reset button state on error
                    this.innerHTML = '<i class="bi bi-arrow-down me-2"></i>Charger plus d\'avis';
                    this.disabled = false;
                }
            });
        }
    }

    /**
     * Fetch and render reviews from API
     *
     * Fetches initial page of reviews and renders them in the container.
     * Manages load more button visibility based on pagination.
     *
     * @param {string} endpoint - The API endpoint to fetch reviews from
     * @returns {Promise} Promise that resolves when reviews are loaded
     */
    async function fetchAndRenderReviews(endpoint) {
        try {
            // Fetch first page of reviews
            const response = await fetch(`${endpoint}?page=1&limit=6`);
            const data = await response.json();
            const payload = data?.data || data;
            const reviews = payload?.reviews || [];
            const pagination = payload?.pagination;

            if (data.success && Array.isArray(reviews)) {
                // Render reviews in container
                renderReviews(reviews);

                /**
                 * Manage load more button visibility
                 * Hide button if no more reviews, show if more available
                 */
                const loadMoreBtn = document.getElementById('loadMoreReviews');
                if (loadMoreBtn) {
                    if (pagination && !pagination.has_more) {
                        loadMoreBtn.style.display = 'none';
                    } else {
                        loadMoreBtn.style.display = 'inline-block';
                    }
                }
            }
        } catch (error) {
            console.error('Error fetching reviews:', error);
        }
    }

    /**
     * Render reviews in container (clears existing content)
     *
     * This function clears the container and renders reviews. Used for
     * initial load or refresh.
     *
     * @param {Array} reviews - Array of review objects to render
     */
    function renderReviews(reviews) {
        const container = document.getElementById('reviewsContainer');
        // Clear existing content when rendering initial reviews
        container.innerHTML = '';

        if (reviews.length === 0) {
            /**
             * Show empty state
             * Display friendly message when no reviews are available
             */
            container.innerHTML = `
                <div class="col-12 text-center">
                    <div class="py-5">
                        <i class="bi bi-chat-dots icon-large"></i>
                        <h4 class="mt-3">Aucun avis trouvé</h4>
                        <p class="text-muted">Aucun avis disponible pour le moment.</p>
                    </div>
                </div>
            `;
        } else {
            // Render reviews using append function
            appendReviews(reviews);
        }
    }

    /**
     * Append reviews to container (doesn't clear existing content)
     *
     * This function adds new reviews to the container without clearing
     * existing ones. Used for pagination (load more).
     *
     * @param {Array} reviews - Array of review objects to append
     */
    function appendReviews(reviews) {
        const container = document.getElementById('reviewsContainer');
        if (!container) return;

        // Append new reviews to existing ones (don't clear container)
        reviews.forEach(review => {
            const reviewElement = createReviewElement(review);
            container.appendChild(reviewElement);
        });
    }

    /**
     * Create DOM element for a single review
     *
     * Generates HTML structure for a review card with:
     * - Reviewer name and avatar
     * - Rating stars
     * - Review text
     * - Date
     *
     * @param {Object} review - Review object with name, rating, comment, createdAt
     * @returns {HTMLElement} The created review element
     */
    function createReviewElement(review) {
        const div = document.createElement('div');
        div.className = 'col-lg-6 col-md-6 mb-4';

        /**
         * Generate Bootstrap stars
         * Create filled stars for rating, empty stars for remainder
         * Uses array methods for cleaner, more functional code style
         */
        const starsHTML = Array.from({ length: 5 }, (_, i) => {
            const starNumber = i + 1;
            const isFilled = starNumber <= review.rating;
            return `<i class="bi ${isFilled ? 'bi-star-fill' : 'bi-star'} text-warning"></i>`;
        }).join('');

        /**
         * Build review HTML structure
         * Includes reviewer info, rating, stars, and comment
         */
        div.innerHTML = `
            <div class="review-item" data-rating="${review.rating}">
                <div class="review-header">
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">${review.name.charAt(0).toUpperCase()}</div>
                        <div class="reviewer-details">
                            <h5>${review.name}</h5>
                            <small>${new Date(review.createdAt).toLocaleDateString('fr-FR')}</small>
                        </div>
                    </div>
                    <div class="review-rating">
                        <span class="rating-number">${review.rating}/5</span>
                    </div>
                </div>
                <div class="review-stars">
                    ${starsHTML}
                </div>
                <p class="review-text">"${review.comment}"</p>
            </div>
        `;

        return div;
    }
})();
