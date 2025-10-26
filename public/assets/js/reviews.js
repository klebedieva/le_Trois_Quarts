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
        
        // Also setup star rating for modals when they are shown
        document.addEventListener('shown.bs.modal', function(e) {
            const modal = e.target;
            const modalId = modal.id;
            
            setupModalStarRating(modalId);
        });
        
        // Clean up modal styles when modal is hidden
        document.addEventListener('hidden.bs.modal', function(e) {
            
            cleanupModalStyles();
        });
        
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
        
        
        // Use event delegation to handle dynamically added elements
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList && e.target.classList.contains('star-rating')) {
                
                const star = e.target;
                const group = star.closest('.rating-stars');
                if (group) {
                    const modalId = group.closest('.modal')?.id || 'addReviewModal';
                    const ratingInput = document.getElementById(modalId + 'Rating');
                    
                    if (!ratingInput) { return; }
                    
                    const rating = parseInt(star.getAttribute('data-rating'));
                    setRating(rating, ratingInput);
                    validateRating(ratingInput);
                }
            }
        });
        
        document.addEventListener('mouseenter', function(e) {
            if (e.target && e.target.classList && e.target.classList.contains('star-rating')) {
                const star = e.target;
                const group = star.closest('.rating-stars');
                if (group) {
                    const stars = group.querySelectorAll('.star-rating');
                    const rating = parseInt(star.getAttribute('data-rating'));
                    highlightStars(rating, stars);
                }
            }
        }, true);
        
        document.addEventListener('mouseleave', function(e) {
            if (e.target && e.target.classList && e.target.classList.contains('rating-stars')) {
                const group = e.target;
                const modalId = group.closest('.modal')?.id || 'addReviewModal';
                const ratingInput = document.getElementById(modalId + 'Rating');
                const stars = group.querySelectorAll('.star-rating');
                
                if (!ratingInput) return;
                
                const currentRating = parseInt(ratingInput.value);
                highlightStars(currentRating, stars);
            }
        }, true);
    }

    function cleanupModalStyles() {
        // Remove backdrop
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
        
        // Clean up all modal-related styles
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        document.body.style.paddingLeft = '';
        document.body.style.marginRight = '';
        document.body.style.marginLeft = '';
        
        // Remove any remaining modal classes from html element
        document.documentElement.classList.remove('modal-open');
        document.documentElement.style.overflow = '';
        document.documentElement.style.paddingRight = '';
        
        
    }

    function setupModalStarRating(modalId) {
        
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        const stars = modal.querySelectorAll('.star-rating');
        const ratingInput = document.getElementById(modalId + 'Rating');
        
        if (!ratingInput) return;
        
        stars.forEach((star, index) => {
            // Remove existing listeners to avoid duplicates
            star.replaceWith(star.cloneNode(true));
        });
        
        // Re-query after cloning
        const newStars = modal.querySelectorAll('.star-rating');
        
        newStars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                
                setRating(rating, ratingInput);
                validateRating(ratingInput);
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                highlightStars(rating, newStars);
            });
        });
        
        const group = modal.querySelector('.rating-stars');
        if (group) {
            group.addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingInput.value);
                highlightStars(currentRating, newStars);
            });
        }
    }

    function setRating(rating, ratingInput) {
        ratingInput.value = rating;
        const stars = ratingInput.closest('.modal').querySelectorAll('.star-rating');
        highlightStars(rating, stars);
    }

    function highlightStars(rating, stars) {
        
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
        // Find all review forms on the page
        const reviewForms = document.querySelectorAll('form[id$="Form"]');
        
        reviewForms.forEach(function(form) {
            const modalId = form.id.replace('Form', '');
            const nameInput = document.getElementById(modalId + 'Name');
            const emailInput = document.getElementById(modalId + 'Email');
            const textInput = document.getElementById(modalId + 'Text');
            
        // Name validation
        if (nameInput) {
                nameInput.addEventListener('input', () => validateName(nameInput, modalId));
                nameInput.addEventListener('blur', () => validateName(nameInput, modalId));
        }
        
        // Email validation
        if (emailInput) {
                emailInput.addEventListener('input', () => validateEmail(emailInput, modalId));
                emailInput.addEventListener('blur', () => validateEmail(emailInput, modalId));
        }
        
        // Comment validation
            if (textInput) {
                textInput.addEventListener('input', () => validateComment(textInput, modalId));
                textInput.addEventListener('blur', () => validateComment(textInput, modalId));
            }
        });
    }

    function setupFormSubmission() {
        // Find all submit buttons for review forms
        const submitButtons = document.querySelectorAll('button[id$="Submit"]');
        
        submitButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const modalId = button.id.replace('Submit', '');
                const form = document.getElementById(modalId + 'Form');
                
                if (!form) return;
                
                // Validate all fields
                const isNameValid = validateName(document.getElementById(modalId + 'Name'), modalId);
                const isEmailValid = validateEmail(document.getElementById(modalId + 'Email'), modalId);
                const isRatingValid = validateRating(document.getElementById(modalId + 'Rating'));
                const isCommentValid = validateComment(document.getElementById(modalId + 'Text'), modalId);
                
                if (isNameValid && isEmailValid && isRatingValid && isCommentValid) {
                    submitReview(form, modalId);
                } else {
                    // Scroll to first invalid field
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                }
            });
        });
    }

    function validateName(input, modalId) {
        if (!input) return true;
        const value = input.value.trim();
        const errorElement = document.getElementById(modalId + 'NameError');
        
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
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            clearFieldError(errorElement);
            return true;
        }
    }

    function validateEmail(input, modalId) {
        if (!input) return true;
        const value = input.value.trim();
        const errorElement = document.getElementById(modalId + 'EmailError');
        
        if (value === '') {
            input.classList.remove('is-valid', 'is-invalid');
            clearFieldError(errorElement);
            return true; // Email is optional, so empty is valid
        } else if (!validationPatterns.email.test(value)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            showFieldError(errorElement, 'Veuillez saisir une adresse email valide');
            return false;
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            clearFieldError(errorElement);
            return true;
        }
    }

    function validateRating(ratingInput) {
        if (!ratingInput) return true;
        const value = parseInt(ratingInput.value);
        const modalId = ratingInput.id.replace('Rating', '');
        const errorElement = document.getElementById(modalId + 'RatingError');
        
        if (value === 0 || isNaN(value)) {
            ratingInput.classList.add('is-invalid');
            ratingInput.classList.remove('is-valid');
            showFieldError(errorElement, 'Veuillez sélectionner une note');
            return false;
        } else {
            ratingInput.classList.remove('is-invalid');
            ratingInput.classList.add('is-valid');
            clearFieldError(errorElement);
            return true;
        }
    }

    function validateComment(input, modalId) {
        if (!input) return true;
        const value = input.value.trim();
        const errorElement = document.getElementById(modalId + 'TextError');
        
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
            showFieldError(errorElement, 'L\'avis contient des caractères non autorisés');
            return false;
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
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

    function submitReview(form, modalId) {
        const submitBtn = document.getElementById(modalId + 'Submit');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Envoi en cours...';
        
        // Gather fields
        const name = document.getElementById(modalId + 'Name').value.trim();
        const email = document.getElementById(modalId + 'Email').value.trim();
        const rating = document.getElementById(modalId + 'Rating').value;
        const comment = document.getElementById(modalId + 'Text').value.trim();

        // Choose endpoint and payload format
        const endpoint = window.REVIEWS_ENDPOINT || '/api/review';
        const isApiEndpoint = endpoint.startsWith('/api/');

        let payload;
        if (isApiEndpoint) {
            payload = {
                name: name,
                email: email || null,
                rating: parseInt(rating),
                comment: comment
            };
            
            // Add dish_id if it's a dish review
            const dishIdInput = form.querySelector('input[name="dish_id"]');
            if (dishIdInput) {
                payload.dish_id = parseInt(dishIdInput.value);
            }
        } else {
            payload = new FormData();
            payload.append('name', name);
            payload.append('email', email);
            payload.append('rating', rating);
            payload.append('comment', comment);
        }

        fetch(endpoint, {
            method: 'POST',
            headers: isApiEndpoint ? {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            } : {},
            body: isApiEndpoint ? JSON.stringify(payload) : payload
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showSuccessMessage('Votre avis a été envoyé avec succès !');
                
                // Close the modal gracefully
                const openModalEl = document.getElementById(modalId);
                if (openModalEl) {
                    
                    const modalInstance = bootstrap.Modal.getInstance(openModalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                        
                    } else {
                        // If no instance, create one and hide
                        const newModalInstance = new bootstrap.Modal(openModalEl);
                        newModalInstance.hide();
                        
                    }
                } else {
                    
                }
                
                // Force remove backdrop if modal doesn't close properly
                setTimeout(() => {
                    cleanupModalStyles();
                }, 100);
                
                // Reset form
                resetForm(modalId);
                
                // Notify dish page (if any) to refresh its list without reloading
                document.dispatchEvent(new CustomEvent('review:submitted'));
            } else {
                // Show error message
                showErrorMessage(data.message || 'Une erreur est survenue lors de l\'envoi de votre avis.');
            }
        })
        .catch(error => {
            
            showErrorMessage('Une erreur est survenue lors de l\'envoi de votre avis.');
            
            // Force close modal on error too
            const openModalEl = document.getElementById(modalId);
            if (openModalEl) {
                const modalInstance = bootstrap.Modal.getInstance(openModalEl);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        })
        .finally(() => {
            // Restore button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    function showSuccessMessage(message) {
        
        
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        
        
        
        // Create the notification element
        const notification = document.createElement('div');
        notification.className = 'notification alert alert-success alert-dismissible show alert-fixed-center';
        
        notification.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>Votre avis a été envoyé et sera publié après modération.
            <button type="button" class="btn-close" onclick="this.parentElement.classList.add('fade'); setTimeout(() => this.parentElement.remove(), 500)"></button>
        `;
        
        document.body.appendChild(notification);
        
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.add('fade');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 500);
            }
        }, 5000);
    }

    function showErrorMessage(message) {
        
        
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        
        // Create the notification element
        const notification = document.createElement('div');
        notification.className = 'notification alert alert-danger alert-dismissible show alert-fixed-center';
        
        notification.innerHTML = `
            <i class="bi bi-exclamation-triangle me-2"></i>${message}
            <button type="button" class="btn-close" onclick="this.parentElement.classList.add('fade'); setTimeout(() => this.parentElement.remove(), 500)"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.add('fade');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 500);
            }
        }, 5000);
    }

    function resetForm(modalId) {
        document.getElementById(modalId + 'Name').value = '';
        document.getElementById(modalId + 'Email').value = '';
        document.getElementById(modalId + 'Rating').value = '0';
        document.getElementById(modalId + 'Text').value = '';
        
        // Reset validation states
        const form = document.getElementById(modalId + 'Form');
        const inputs = form.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.classList.remove('is-valid', 'is-invalid');
        });
        
        // Reset stars
        const stars = form.querySelectorAll('.star-rating');
        highlightStars(0, stars);
        
        // Clear error messages
        const errorElements = form.querySelectorAll('.invalid-feedback');
        errorElements.forEach(element => {
            element.textContent = '';
            element.classList.remove('show');
        });
    }

    // Load more reviews functionality
    let currentPage = 1;
    function initLoadMoreExistingReviews() {
        const loadMoreBtn = document.getElementById('loadMoreReviews');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', async function() {
                try {
                    // Show loading state
                    this.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Chargement...';
                    this.disabled = true;
                    
                    // Load next page
                    currentPage++;
                    const response = await fetch(`${window.REVIEWS_LIST_ENDPOINT}?page=${currentPage}&limit=6`);
                    const data = await response.json();
                    
                    if (data.success && data.reviews) {
                        // Append new reviews to existing ones
                        appendReviews(data.reviews);
                        
                        // Hide button if no more reviews
                        if (!data.pagination.has_more) {
                            this.style.display = 'none';
                        } else {
                            // Reset button state
                            this.innerHTML = '<i class="bi bi-arrow-down me-2"></i>Charger plus d\'avis';
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

    // Fetch and render reviews from API
    async function fetchAndRenderReviews(endpoint) {
        try {
            const response = await fetch(`${endpoint}?page=1&limit=6`);
            const data = await response.json();
            
            if (data.success && data.reviews) {
                renderReviews(data.reviews);
                
                // Manage load more button visibility
                const loadMoreBtn = document.getElementById('loadMoreReviews');
                if (loadMoreBtn) {
                    if (data.pagination && !data.pagination.has_more) {
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

    function renderReviews(reviews) {
        const container = document.getElementById('reviewsContainer');
        // Clear existing content when rendering initial reviews
        container.innerHTML = '';
        
        if (reviews.length === 0) {
            // Show empty state
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
            appendReviews(reviews);
        }
    }

    function appendReviews(reviews) {
        const container = document.getElementById('reviewsContainer');
        if (!container) return;
        
        // Append new reviews to existing ones (don't clear container)
        reviews.forEach(review => {
            const reviewElement = createReviewElement(review);
            container.appendChild(reviewElement);
        });
    }

    function createReviewElement(review) {
        const div = document.createElement('div');
        div.className = 'col-lg-6 col-md-6 mb-4';
        
        // Generate Bootstrap stars
        let starsHTML = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= review.rating) {
                starsHTML += '<i class="bi bi-star-fill text-warning"></i>';
            } else {
                starsHTML += '<i class="bi bi-star text-warning"></i>';
            }
        }
        
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