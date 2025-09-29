// Review form validation - based on contact.js logic
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
        setupStarRating();
        setupRealTimeValidation();
        setupFormSubmission();
        initLoadMoreExistingReviews();
    });

    function setupStarRating() {
        const stars = document.querySelectorAll('.star-rating');
        const ratingInput = document.getElementById('ratingValue');
        
        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                setRating(rating);
                validateRating();
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                highlightStars(rating);
            });
        });
        
        const ratingContainer = document.querySelector('.rating-stars');
        if (ratingContainer) {
            ratingContainer.addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingInput.value);
                highlightStars(currentRating);
            });
        }
    }

    function setRating(rating) {
        const ratingInput = document.getElementById('ratingValue');
        ratingInput.value = rating;
        highlightStars(rating);
    }

    function highlightStars(rating) {
        const stars = document.querySelectorAll('.star-rating');
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.remove('bi-star');
                star.classList.add('bi-star-fill');
            } else {
                star.classList.remove('bi-star-fill');
                star.classList.add('bi-star');
            }
        });
    }

    function setupRealTimeValidation() {
        // Name validation
        const nameInput = document.getElementById('reviewerName');
        if (nameInput) {
            nameInput.addEventListener('input', () => validateName());
            nameInput.addEventListener('blur', () => validateName());
        }
        
        // Email validation
        const emailInput = document.getElementById('reviewEmail');
        if (emailInput) {
            emailInput.addEventListener('input', () => validateEmail());
            emailInput.addEventListener('blur', () => validateEmail());
        }
        
        // Comment validation
        const commentInput = document.getElementById('reviewText');
        if (commentInput) {
            commentInput.addEventListener('input', () => validateComment());
            commentInput.addEventListener('blur', () => validateComment());
        }
    }

    function setupFormSubmission() {
        const submitBtn = document.getElementById('submitReview');
        
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Validate all fields
                const isNameValid = validateName();
                const isEmailValid = validateEmail();
                const isRatingValid = validateRating();
                const isCommentValid = validateComment();
                
                if (isNameValid && isEmailValid && isRatingValid && isCommentValid) {
                    submitReview();
                } else {
                    // Scroll to first invalid field
                    const firstInvalid = document.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                }
            });
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

    function validateRating() {
        const ratingInput = document.getElementById('ratingValue');
        const value = parseInt(ratingInput.value);
        const errorElement = document.getElementById('ratingError');
        
        if (value === 0 || isNaN(value)) {
            ratingInput.classList.add('is-invalid');
            ratingInput.classList.remove('is-valid');
            showFieldError(errorElement, 'Veuillez sélectionner une note');
            return false;
        } else {
            ratingInput.classList.add('is-valid');
            ratingInput.classList.remove('is-invalid');
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
        } else if (validationPatterns.comment.test(value)) {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            clearFieldError(errorElement);
            return true;
        } else {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'L\'avis ne peut pas contenir de balises HTML');
            return false;
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

    function submitReview() {
        const submitBtn = document.getElementById('submitReview');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Envoi en cours...';
        
        // Prepare form data
        const formData = new FormData();
        const name = document.getElementById('reviewerName').value.trim();
        const email = document.getElementById('reviewEmail').value.trim();
        const rating = document.getElementById('ratingValue').value;
        const comment = document.getElementById('reviewText').value.trim();
        
        formData.append('name', name);
        formData.append('email', email);
        formData.append('rating', rating);
        formData.append('comment', comment);
        
        
        // Submit via AJAX
        // Use page-specific override when present (dish page)
        const endpoint = window.REVIEWS_ENDPOINT || '/submit-review';
        fetch(endpoint, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showSuccessMessage('Votre avis a été envoyé avec succès ! Il sera publié après modération.');
                
                // Close the modal gracefully (supports both restaurant and dish modals)
                const openModalEl = submitBtn.closest('.modal')
                    || document.querySelector('.modal.show')
                    || document.getElementById('dishReviewModal')
                    || document.getElementById('addReviewModal');
                if (openModalEl) {
                    const modal = bootstrap.Modal.getInstance(openModalEl) || new bootstrap.Modal(openModalEl);
                    modal.hide();
                }
                
                // Reset form
                resetForm();
                
                // Notify dish page (if any) to refresh its list without reloading
                document.dispatchEvent(new CustomEvent('review:submitted'));
            } else {
                // Show error message
                showErrorMessage(data.message || 'Une erreur est survenue lors de l\'envoi de votre avis.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorMessage('Une erreur est survenue lors de l\'envoi de votre avis.');
        })
        .finally(() => {
            // Restore button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    // Client-side pagination for server-rendered reviews
    function initLoadMoreExistingReviews() {
        const container = document.getElementById('reviewsContainer');
        if (!container) return;

        const reviewCols = Array.from(container.querySelectorAll('.col-lg-6, .col-md-6, .col-lg-4, .col-md-4'));
        if (reviewCols.length === 0) return;

        const loadMoreBtn = document.getElementById('loadMoreReviews');
        const PAGE_SIZE = 6;
        let visibleCount = 0;

        function updateVisibility() {
            reviewCols.forEach((el, idx) => {
                if (idx < visibleCount) {
                    el.classList.remove('d-none');
                } else {
                    el.classList.add('d-none');
                }
            });

            if (loadMoreBtn) {
                if (visibleCount >= reviewCols.length) {
                    loadMoreBtn.style.display = 'none';
                } else {
                    loadMoreBtn.style.display = '';
                }
            }
        }

        // Show initial set
        visibleCount = Math.min(PAGE_SIZE, reviewCols.length);
        updateVisibility();

        // Wire button
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
                visibleCount = Math.min(visibleCount + PAGE_SIZE, reviewCols.length);
                updateVisibility();
            });
        }
    }

    function showSuccessMessage(message) {
        // Create success alert
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
        alert.style.cssText = 'top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px; text-align: center;';
        alert.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            }
        }, 5000);
    }

    function showErrorMessage(message) {
        // Create error alert
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
        alert.style.cssText = 'top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px; text-align: center;';
        alert.innerHTML = `
            <i class="bi bi-exclamation-triangle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            }
        }, 5000);
    }

    function resetForm() {
        document.getElementById('reviewerName').value = '';
        document.getElementById('reviewEmail').value = '';
        document.getElementById('ratingValue').value = '0';
        document.getElementById('reviewText').value = '';
        
        // Reset validation states
        const inputs = document.querySelectorAll('#reviewForm input, #reviewForm textarea');
        inputs.forEach(input => {
            input.classList.remove('is-valid', 'is-invalid');
        });
        
        // Reset stars
        highlightStars(0);
        
        // Clear error messages
        const errorElements = document.querySelectorAll('.invalid-feedback');
        errorElements.forEach(element => {
            element.textContent = '';
            element.classList.remove('show');
        });
    }
})();
