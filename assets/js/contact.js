// Contact form validation
(function() {
    'use strict';

    // Regex patterns for validation
    const validationPatterns = {
        firstName: /^[a-zA-ZÀ-ÿ\s\-]+$/,
        lastName: /^[a-zA-ZÀ-ÿ\s\-]+$/,
        email: /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/,
        phone: /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.\-]*\d{2}){4}$/,
        message: /^(?!.*<.*?>)[\s\S]{10,1000}$/
    };

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        setupRealTimeValidation();
        setupFormSubmission();
        setupAutoHideSuccessMessage();
    });

    function setupRealTimeValidation() {
    // First name validation
        const firstNameInput = document.querySelector('input[name="contact_message[firstName]"]');
        if (firstNameInput) {
            firstNameInput.addEventListener('input', () => validateFirstName());
            firstNameInput.addEventListener('blur', () => validateFirstName());
    }
    
    // Last name validation
        const lastNameInput = document.querySelector('input[name="contact_message[lastName]"]');
        if (lastNameInput) {
            lastNameInput.addEventListener('input', () => validateLastName());
            lastNameInput.addEventListener('blur', () => validateLastName());
    }
    
    // Email validation
        const emailInput = document.querySelector('input[name="contact_message[email]"]');
        if (emailInput) {
            emailInput.addEventListener('input', () => validateEmail());
            emailInput.addEventListener('blur', () => validateEmail());
        }

        // Phone validation
        const phoneInput = document.querySelector('input[name="contact_message[phone]"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', () => validatePhone());
            phoneInput.addEventListener('blur', () => validatePhone());
    }
    
    // Subject validation
        const subjectInput = document.querySelector('select[name="contact_message[subject]"]');
        if (subjectInput) {
            subjectInput.addEventListener('change', () => validateSubject());
            subjectInput.addEventListener('blur', () => validateSubject());
    }
    
    // Message validation
        const messageInput = document.querySelector('textarea[name="contact_message[message]"]');
        if (messageInput) {
            messageInput.addEventListener('input', () => validateMessage());
            messageInput.addEventListener('blur', () => validateMessage());
        }

        // Consent validation
        const consentInput = document.querySelector('input[name="contact_message[consent]"]');
        if (consentInput) {
            consentInput.addEventListener('change', () => validateConsent());
        }
    }

    function setupFormSubmission() {
        const form = document.querySelector('.contact-form');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        if (form && submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Validate all fields
                const isFirstNameValid = validateFirstName();
                const isLastNameValid = validateLastName();
                const isEmailValid = validateEmail();
                const isPhoneValid = validatePhone();
                const isSubjectValid = validateSubject();
                const isMessageValid = validateMessage();
                const isConsentValid = validateConsent();
                
                if (isFirstNameValid && isLastNameValid && isEmailValid && isPhoneValid && isSubjectValid && isMessageValid && isConsentValid) {
                    form.submit();
                }
            });
        }
    }

    function validateFirstName() {
        const input = document.querySelector('input[name="contact_message[firstName]"]');
        const value = input.value.trim();
        const errorElement = document.getElementById('firstNameError');
        
        if (value === '') {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le prénom est requis');
            return false;
        } else if (!validationPatterns.firstName.test(value)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le prénom ne peut contenir que des lettres, espaces et tirets');
            return false;
        } else {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            clearFieldError(errorElement);
            return true;
        }
    }

    function validateLastName() {
        const input = document.querySelector('input[name="contact_message[lastName]"]');
        const value = input.value.trim();
        const errorElement = document.getElementById('lastNameError');
        
        if (value === '') {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le nom est requis');
            return false;
        } else if (!validationPatterns.lastName.test(value)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le nom ne peut contenir que des lettres, espaces et tirets');
            return false;
        } else {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            clearFieldError(errorElement);
            return true;
        }
    }

    function validateEmail() {
        const input = document.querySelector('input[name="contact_message[email]"]');
        const value = input.value.trim();
        const errorElement = document.getElementById('emailError');
        
        if (value === '') {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'L\'email est requis');
            return false;
        } else if (!validationPatterns.email.test(value)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'L\'email n\'est pas valide');
            return false;
        } else {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            clearFieldError(errorElement);
            return true;
        }
    }

    function validatePhone() {
        const input = document.querySelector('input[name="contact_message[phone]"]');
        const value = input.value.trim();
        const errorElement = document.getElementById('phoneError');
        
        if (value === '') {
            input.classList.remove('is-valid', 'is-invalid');
            clearFieldError(errorElement);
            return true; // Phone is optional, so empty is valid
        } else if (!validationPatterns.phone.test(value)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le numéro de téléphone n\'est pas valide');
            return false;
        } else {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            clearFieldError(errorElement);
            return true;
        }
    }

    function validateSubject() {
        const input = document.querySelector('select[name="contact_message[subject]"]');
        const value = input.value;
        const errorElement = document.getElementById('subjectError');
        
        if (value === '') {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le sujet est requis');
            return false;
        } else {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            clearFieldError(errorElement);
            return true;
        }
    }

    function validateMessage() {
        const input = document.querySelector('textarea[name="contact_message[message]"]');
        const value = input.value.trim();
        const errorElement = document.getElementById('messageError');
        
        if (value === '') {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le message est requis');
            return false;
        } else if (value.length < 10) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le message doit contenir au moins 10 caractères');
            return false;
        } else if (value.length > 1000) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le message ne peut pas dépasser 1000 caractères');
            return false;
        } else if (validationPatterns.message.test(value)) {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            clearFieldError(errorElement);
            return true;
        } else {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le message ne peut pas contenir de balises HTML');
            return false;
        }
    }

    function validateConsent() {
        const input = document.querySelector('input[name="contact_message[consent]"]');
        const errorElement = document.getElementById('consentError');
        
        if (!input.checked) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Vous devez accepter d\'être contacté');
            return false;
        } else {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            clearFieldError(errorElement);
            return true;
        }
    }

    function showFieldError(errorElement, message) {
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }
    }

    function clearFieldError(errorElement) {
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.classList.remove('show');
        }
    }

    function setupAutoHideSuccessMessage() {
        const successMessage = document.getElementById('successMessage');
        if (successMessage) {
            // Auto-hide after 5 seconds with smooth fade
            setTimeout(() => {
                if (successMessage && successMessage.parentNode) {
                    // Start fade out animation
                    successMessage.style.transition = 'opacity 0.5s ease-out';
                    successMessage.style.opacity = '0';
                    
                    // Remove element after animation completes
                    setTimeout(() => {
                        if (successMessage.parentNode) {
                            successMessage.remove();
                        }
                    }, 500); // Wait for fade animation to complete
                }
            }, 5000);
        }
    }
})();