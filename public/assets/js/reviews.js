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
        // If a list endpoint is provided, fetch and render reviews from API
        if (window.REVIEWS_LIST_ENDPOINT) {
            fetchAndRenderReviews(window.REVIEWS_LIST_ENDPOINT).then(() => {
                initLoadMoreExistingReviews();
            });
        } else {
            initLoadMoreExistingReviews();
        }
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
        
        // Gather fields
        const name = document.getElementById('reviewerName').value.trim();
        const email = document.getElementById('reviewEmail').value.trim();
        const rating = document.getElementById('ratingValue').value;
        const comment = document.getElementById('reviewText').value.trim();

        // Choose endpoint and payload format
        const endpoint = window.REVIEWS_ENDPOINT || '/api/review';
        const isApiEndpoint = endpoint.startsWith('/api/');

        let options = { method: 'POST' };
        if (isApiEndpoint) {
            // Send JSON for API endpoint
            options.headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            };
            options.body = JSON.stringify({
                name,
                email,
                rating: Number(rating),
                comment
            });
        } else {
            // Fallback to form submission for legacy endpoint
            const formData = new FormData();
            formData.append('name', name);
            formData.append('email', email);
            formData.append('rating', rating);
            formData.append('comment', comment);
            options.body = formData;
            options.headers = { 'X-Requested-With': 'XMLHttpRequest' };
        }

        // Submit via AJAX
        fetch(endpoint, options)
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

    async function fetchAndRenderReviews(endpoint) {
        const container = document.getElementById('reviewsContainer');
        if (!container) return;

        // Show loading state
        container.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border" role="status"></div><div class="mt-3 text-muted">Chargement des avis...</div></div>';

        try {
            const response = await fetch(endpoint, { headers: { 'Accept': 'application/json' } });
            const data = await response.json();

            if (!data.success) {
                container.innerHTML = '<div class="col-12 text-center py-5 text-danger">Impossible de charger les avis</div>';
                return;
            }

            const reviews = Array.isArray(data.reviews) ? data.reviews : [];
            if (reviews.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center">
                        <div class="py-5">
                            <i class="bi bi-chat-dots icon-large"></i>
                            <h4 class="mt-3">Aucun avis trouvé</h4>
                            <p class="text-muted">Aucun avis disponible pour le moment.</p>
                        </div>
                    </div>`;
                return;
            }

            // Build HTML similar to server-rendered cards
            const html = reviews.map(review => {
                const safeName = (review.name || '').toString();
                const safeDate = (review.createdAt || '').toString();
                const safeComment = (review.comment || '').toString();
                const rating = Number(review.rating || 0);

                const stars = Array.from({ length: 5 }).map((_, i) => {
                    if (i < rating) {
                        return '<i class="bi bi-star-fill text-warning"></i>';
                    }
                    return '<i class="bi bi-star text-warning"></i>';
                }).join('');

                return `
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="review-item" data-rating="${rating}">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <div class="reviewer-avatar">${(safeName.charAt(0) || 'U').toUpperCase()}</div>
                                <div class="reviewer-details">
                                    <h5>${escapeHtml(safeName)}</h5>
                                    <small>${escapeHtml(safeDate)}</small>
                                </div>
                            </div>
                            <div class="review-rating">
                                <span class="rating-number">${rating}/5</span>
                            </div>
                        </div>
                        <div class="review-stars">${stars}</div>
                        <p class="review-text">"${escapeHtml(safeComment)}"</p>
                    </div>
                </div>`;
            }).join('');

            container.innerHTML = html;
        } catch (e) {
            console.error(e);
            container.innerHTML = '<div class="col-12 text-center py-5 text-danger">Erreur lors du chargement des avis</div>';
        }
    }

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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
