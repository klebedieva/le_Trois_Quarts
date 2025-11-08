(function (global) {
    'use strict';

    /**
     * Shared Form Validation Utility
     * --------------------------------
     * This module centralises the most common validation helpers used by our
     * front-end forms (contact, reservation, reviews, etc.). By exposing a single
     * `FormValidation` object on the `window`, we:
     *   - Apply the DRY principle (no repeated regex or helper functions);
     *   - Guarantee that error messages stay identical across forms;
     *   - Make it easier for beginners to locate and update validation logic
     *     in one place instead of hunting through multiple files.
     *
     * The IIFE ensures we do not leak variables to the global scope. We also check
     * for an existing `FormValidation` instance so that the script can be included
     * multiple times without overwriting previous configuration (helpful for Turbolinks
     * or other partial page reload solutions).
     */

    if (global.FormValidation) {
        // Another script already initialised the helper – avoid creating duplicates.
        return;
    }

    /**
     * Common regular expressions we reuse across forms.
     * Keeping them in a single object makes maintenance easier and guarantees
     * that every form validates the same way (especially useful for translations).
     */
    const patterns = {
        // Allows letters (including accents), spaces and hyphens for names.
        name: /^[a-zA-ZÀ-ÿ\s\-]+$/,
        // Basic email pattern. Server-side validation still required.
        email: /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/,
        // French phone numbers (country code optional, accepts separators).
        phone: /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.\-]*\d{2}){4}$/
    };

    /**
     * Quick-and-dirty XSS detection
     * -----------------------------
     * Client-side validation is never enough to protect against XSS, but we still
     * provide a first line of defence to show immediate feedback to the user.
     * These patterns catch typical script injection attempts. Each regex is reset
     * before testing (by setting `lastIndex = 0`) so repeated calls are safe.
     */
    const xssPatterns = [
        /<[^>]*>/gi,
        /javascript:/gi,
        /on\w+\s*=/gi,
        /vbscript:/gi,
        /data:text\/html/gi,
        /expression\s*\(/gi,
        /<script/gi,
        /<iframe/gi,
        /<object/gi,
        /<embed/gi,
        /<form/gi,
        /<link[^>]*href\s*=\s*["']?javascript:/gi,
        /<meta[^>]*http-equiv\s*=\s*["']?refresh/gi
    ];

    /**
     * Centralised error messages in French.
     * The helpers accept labels (field names) so every form displays natural,
     * context-aware phrases without repeating string templates everywhere.
     */
    const messages = {
        required: (label) => `${label} est requis`,
        minLength: (label, min) => `${label} doit contenir au moins ${min} caractères`,
        nameFormat: (label) => `${label} ne peut contenir que des lettres, espaces et tirets`,
        emailFormat: () => `L'email n'est pas valide`,
        phoneFormat: () => `Le numéro de téléphone n'est pas valide`,
        numericMin: (label, min) => `${label} doit être au moins ${min}`,
        xss: (label) => `${label} contient des éléments non autorisés`,
        maxLength: (label, max) => `${label} ne peut pas dépasser ${max} caractères`
    };

    /**
     * Lightweight helper that scans a value for suspicious substrings.
     * We keep it separate so it can be reused by any validation routine.
     *
     * @param {string} value - User input
     * @returns {boolean} True when a forbidden pattern is detected
     */
    function containsXss(value) {
        if (!value) {
            return false;
        }
        for (const pattern of xssPatterns) {
            pattern.lastIndex = 0;
            if (pattern.test(value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Tiny factory function to produce a consistent validation response.
     * Every validator returns the same shape: `{ valid, message, value }`.
     * This makes it easy for consuming code to apply UI feedback.
     */
    function result(valid, message, value) {
        return { valid, message: message || '', value };
    }

    /**
     * Validate first/last names.
     * - Trims whitespace so " John " still counts as "John";
     * - Checks for empty values and potential XSS;
     * - Ensures length and allowed characters.
     *
     * @param {string} value - Raw input value
     * @param {string} label - Human friendly field label (used in messages)
     * @param {Object} options - Optional settings (e.g. different min length)
     * @returns {{valid: boolean, message: string, value: string}}
     */
    function validateName(value, label = 'Ce champ', options = {}) {
        const { minLength = 2 } = options;
        const trimmed = value.trim();

        if (!trimmed) {
            return result(false, messages.required(label));
        }
        if (containsXss(trimmed)) {
            return result(false, messages.xss(label));
        }
        if (trimmed.length < minLength) {
            return result(false, messages.minLength(label, minLength));
        }
        if (!patterns.name.test(trimmed)) {
            return result(false, messages.nameFormat(label));
        }

        return result(true, '', trimmed);
    }

    /**
     * Validate email addresses.
     * Emails are required by default, but the `options` object allows callers
     * to override the label or other behaviour if needed later.
     */
    function validateEmail(value, options = {}) {
        const { label = `L'email` } = options;
        const trimmed = value.trim();

        if (!trimmed) {
            return result(false, messages.required(label));
        }
        if (containsXss(trimmed)) {
            return result(false, messages.xss(label));
        }
        if (!patterns.email.test(trimmed)) {
            return result(false, messages.emailFormat());
        }

        return result(true, '', trimmed);
    }

    /**
     * Validate phone numbers with an optional requirement flag.
     * If `required` is false the function accepts empty strings but still checks
     * the format when a value is provided.
     */
    function validatePhone(value, options = {}) {
        const { label = 'Le numéro de téléphone', required = false } = options;
        const trimmed = value.trim();

        if (!trimmed) {
            return required ? result(false, messages.required(label)) : result(true, '', trimmed);
        }
        if (containsXss(trimmed)) {
            return result(false, messages.xss(label));
        }
        if (!patterns.phone.test(trimmed)) {
            return result(false, messages.phoneFormat());
        }

        return result(true, '', trimmed);
    }

    /**
     * Validate longer free-text fields (messages, comments, etc.).
     * This helper is flexible: callers decide if the field is required and which
     * min/max lengths are acceptable.
     */
    function validateMessage(value, options = {}) {
        const {
            label = 'Le message',
            required = false,
            min = 0,
            max = 1000
        } = options;

        const trimmed = value.trim();

        if (!trimmed) {
            return required ? result(false, messages.required(label)) : result(true, '', trimmed);
        }
        if (containsXss(trimmed)) {
            return result(false, `${label} contient des éléments non autorisés (balises HTML, JavaScript, etc.)`);
        }
        if (trimmed.length < min) {
            return result(false, messages.minLength(label, min));
        }
        if (trimmed.length > max) {
            return result(false, messages.maxLength(label, max));
        }

        return result(true, '', trimmed);
    }

    /**
     * Apply validation state to form controls.
     * Consuming code passes either an element reference or an element ID for the
     * error container. This keeps DOM manipulation consistent across forms.
     *
     * @param {HTMLElement} field - Input/textarea/select to update
     * @param {HTMLElement|string} errorTarget - Element or element ID for messages
     * @param {{valid:boolean,message:string}} validationResult - Result produced by a validator
     */
    function applyFieldState(field, errorTarget, validationResult) {
        const errorElement = typeof errorTarget === 'string'
            ? document.getElementById(errorTarget)
            : errorTarget;

        if (!field) {
            return;
        }

        if (validationResult && validationResult.valid) {
            field.classList.add('is-valid');
            field.classList.remove('is-invalid');
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.classList.remove('show');
            }
        } else {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            if (errorElement) {
                errorElement.textContent = validationResult ? validationResult.message || '' : '';
                if (validationResult && validationResult.message) {
                    errorElement.classList.add('show');
                } else {
                    errorElement.classList.remove('show');
                }
            }
        }
    }

    /**
     * Reset visual feedback on a field.
     * Useful when optional fields become empty or when we want to clear the UI
     * before running a fresh validation pass.
     */
    function clearFieldState(field, errorTarget) {
        const errorElement = typeof errorTarget === 'string'
            ? document.getElementById(errorTarget)
            : errorTarget;

        if (field) {
            field.classList.remove('is-valid', 'is-invalid');
        }
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.classList.remove('show');
        }
    }

    /**
     * Finally expose the helper so other scripts can reuse it.
     * Example usage:
     *   const result = window.FormValidation.validateEmail(value);
     *   window.FormValidation.applyFieldState(input, 'emailError', result);
     */
    global.FormValidation = {
        patterns,
        containsXss,
        validateName,
        validateEmail,
        validatePhone,
        validateMessage,
        applyFieldState,
        clearFieldState
    };
})(window);

