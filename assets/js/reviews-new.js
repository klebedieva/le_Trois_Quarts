// Reviews functionality with validation
(function() {
    'use strict';

    // Regex patterns for validation
    const validationPatterns = {
        name: /^[a-zA-ZÀ-ÿ\s\-]+$/,
        email: /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/,
        comment: /^(?!.*<.*?>)[\s\S]{10,1000}$/
    };

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        setupRatingStars();
        setupRealTimeValidation();
        setupFormSubmission();
        setupModalReset();
    });

    function setupRatingStars() {
        const stars = document.querySelectorAll('.star-rating');
        const ratingInput = document.getElementById('ratingValue');
        
        if (!stars.length || !ratingInput) {
            setTimeout(setupRatingStars, 100);
            return;
        }
        
        stars.forEach((star, index) => {
            star.addEventListener('click', handleStarClick);
            star.addEventListener('mouseenter', handleStarHover);
            star.addEventListener('mouseleave', handleStarLeave);
        });
    }
    
    function handleStarClick(e) {
        e.preventDefault();
        e.stopPropagation();
        const rating = parseInt(this.dataset.rating);
        
        const ratingInput = document.getElementById('ratingValue');
        if (ratingInput) {
            ratingInput.value = rating;
        }
        
        // Update stars display
        const stars = document.querySelectorAll('.star-rating');
        stars.forEach((s, starIndex) => {
            if (starIndex < rating) {
                s.classList.remove('bi-star');
                s.classList.add('bi-star-fill', 'filled');
            } else {
                s.classList.remove('bi-star-fill', 'filled');
                s.classList.add('bi-star');
            }
        });
        
        // Validate rating when clicked
        validateRating();
    }
    
    function handleStarHover() {
        const rating = parseInt(this.dataset.rating);
        const stars = document.querySelectorAll('.star-rating');
        stars.forEach((s, starIndex) => {
            if (starIndex < rating) {
                s.classList.add('active');
            }
        });
    }
    
    function handleStarLeave() {
        const stars = document.querySelectorAll('.star-rating');
        stars.forEach(s => s.classList.remove('active'));
    }

    function setupRealTimeValidation() {
        // Name validation
        const nameInput = document.getElementById('reviewerName');
        if (nameInput) {
            nameInput.addEventListener('input', () => validateName());
            nameInput.addEventListener('blur', () => validateName());
        }
        
        // Comment validation
        const commentInput = document.getElementById('reviewText');
        if (commentInput) {
            commentInput.addEventListener('input', () => validateComment());
            commentInput.addEventListener('blur', () => validateComment());
        }
        
        // Email validation
        const emailInput = document.getElementById('reviewEmail');
        if (emailInput) {
            emailInput.addEventListener('input', () => validateEmail());
            emailInput.addEventListener('blur', () => validateEmail());
        }
    }

    function validateName() {
        const input = document.getElementById('reviewerName');
        const value = input.value.trim();
        const errorElement = document.getElementById('reviewerNameError');
        
        if (value === '') {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Le nom est requis');
            return false;
        } else if (!validationPatterns.name.test(value)) {
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

    function validateRating() {
        const ratingInput = document.getElementById('ratingValue');
        const rating = parseInt(ratingInput.value);
        const errorElement = document.getElementById('ratingError');
        
        if (rating === 0) {
            showFieldError(errorElement, 'Veuillez sélectionner une note');
            return false;
        } else {
            clearFieldError(errorElement);
            return true;
        }
    }

    function validateComment() {
        const input = document.getElementById('reviewText');
        const value = input.value.trim();
        const errorElement = document.getElementById('reviewTextError');
        
        if (value === '') {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'L\'avis est requis');
            return false;
        } else if (value.length < 10) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'L\'avis doit contenir au moins 10 caractères');
            return false;
        } else if (value.length > 1000) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'L\'avis ne peut pas dépasser 1000 caractères');
            return false;
        } else if (!validationPatterns.comment.test(value)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'L\'avis ne peut pas contenir de balises HTML');
            return false;
        } else {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            clearFieldError(errorElement);
            return true;
        }
    }

    function validateEmail() {
        const input = document.getElementById('reviewEmail');
        const value = input.value.trim();
        const errorElement = document.getElementById('reviewEmailError');
        
        if (value === '') {
            input.classList.remove('is-valid', 'is-invalid');
            clearFieldError(errorElement);
            return true; // Email is optional, so empty is valid
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

    function setupFormSubmission() {
        const form = document.querySelector('#addReviewModal form');
        const submitBtn = document.getElementById('submitReview');
        
        if (form && submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Validate all fields
                const isNameValid = validateName();
                const isRatingValid = validateRating();
                const isCommentValid = validateComment();
                const isEmailValid = validateEmail();
                
                if (isNameValid && isRatingValid && isCommentValid && isEmailValid) {
                    // Get form data
                    const name = document.getElementById('reviewerName').value.trim();
                    const rating = parseInt(document.getElementById('ratingValue').value);
                    const text = document.getElementById('reviewText').value.trim();
                    const email = document.getElementById('reviewEmail').value.trim();
                    
                    // Create hidden form for submission
                    const hiddenForm = document.createElement('form');
                    hiddenForm.method = 'POST';
                    hiddenForm.action = '/submit-review';
                    hiddenForm.style.display = 'none';
                    
                    // Add form fields
                    const nameInput = document.createElement('input');
                    nameInput.type = 'hidden';
                    nameInput.name = 'review[name]';
                    nameInput.value = name;
                    
                    const ratingInput = document.createElement('input');
                    ratingInput.type = 'hidden';
                    ratingInput.name = 'review[rating]';
                    ratingInput.value = rating;
                    
                    const commentInput = document.createElement('input');
                    commentInput.type = 'hidden';
                    commentInput.name = 'review[comment]';
                    commentInput.value = text;
                    
                    const emailInput = document.createElement('input');
                    emailInput.type = 'hidden';
                    emailInput.name = 'review[email]';
                    emailInput.value = email;
                    
                    hiddenForm.appendChild(nameInput);
                    hiddenForm.appendChild(ratingInput);
                    hiddenForm.appendChild(commentInput);
                    hiddenForm.appendChild(emailInput);
                    
                    // Submit form
                    document.body.appendChild(hiddenForm);
                    hiddenForm.submit();
                }
            });
        }
    }

    function setupModalReset() {
        const modal = document.getElementById('addReviewModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function() {
                resetReviewForm();
            });
        }
    }

    function resetReviewForm() {
        const form = document.querySelector('#addReviewModal form');
        if (form) {
            form.reset();
        }
        
        const ratingInput = document.getElementById('ratingValue');
        if (ratingInput) {
            ratingInput.value = '0';
        }
        
        const stars = document.querySelectorAll('.star-rating');
        stars.forEach(star => {
            star.classList.remove('bi-star-fill', 'filled');
            star.classList.add('bi-star');
        });
        
        // Clear validation states
        const inputs = form.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.classList.remove('is-valid', 'is-invalid');
        });
        
        // Clear error messages
        const errorElements = form.querySelectorAll('.invalid-feedback');
        errorElements.forEach(errorElement => {
            clearFieldError(errorElement);
        });
    }
})();