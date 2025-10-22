// Reservation form validation and AJAX submission
(function() {
    'use strict';

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        setupReservationForm();
    });

    function setupReservationForm() {
        const form = document.getElementById('reservationForm');
        if (!form) {
            return;
        }


        // Set minimum date to today
        const dateInput = form.querySelector('input[name="reservation[date]"]');
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
            if (!dateInput.value) {
                dateInput.value = today;
            }
            
            // Update time options when date changes
            dateInput.addEventListener('change', function() {
                updateTimeOptions(this.value);
            });
            
            // Initialize time options
            updateTimeOptions(dateInput.value);
        }

        // Setup form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
        const isValid = validateForm();
        
        if (isValid) {
            submitReservation();
        }
        });

        // Setup real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            });
            
            // Re-validate time when date changes
            if (input.name.includes('[date]')) {
                input.addEventListener('change', function() {
                    const timeInput = form.querySelector('select[name="reservation[time]"]');
                    if (timeInput && timeInput.value) {
                        validateField(timeInput);
                    }
                });
            }
        });
    }

    function updateTimeOptions(selectedDate) {
        const timeSelect = document.querySelector('select[name="reservation[time]"]');
        if (!timeSelect) return;
        
        const today = new Date().toISOString().split('T')[0];
        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();
        
        // Clear existing options except placeholder
        timeSelect.innerHTML = '<option value="">Choisir...</option>';
        
        // Generate time slots from 14:00 to 22:30 in 30-minute steps
        for (let hour = 14; hour <= 22; hour++) {
            for (let minute = 0; minute < 60; minute += 30) {
                if (hour === 22 && minute > 30) {
                    break; // Stop at 22:30
                }
                
                const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                
                // If it's today, skip past times
                if (selectedDate === today) {
                    if (hour < currentHour || (hour === currentHour && minute <= currentMinute)) {
                        continue; // Skip this time slot
                    }
                }
                
                const option = document.createElement('option');
                option.value = timeString;
                option.textContent = timeString;
                
                timeSelect.appendChild(option);
            }
        }
        
        // Clear selection if no valid options available
        if (timeSelect.options.length === 1) {
            timeSelect.value = '';
        }
    }

    function validateForm() {
        const form = document.getElementById('reservationForm');
        const inputs = form.querySelectorAll('input[name^="reservation["], select[name^="reservation["], textarea[name^="reservation["]');
        let isValid = true;

        inputs.forEach(input => {
            // Skip validation for CSRF token and optional message field
            if (!input.name.includes('[message]') && !input.name.includes('[_token]') && !validateField(input)) {
                isValid = false;
            }
        });

        if (!isValid) {
            const form = document.getElementById('reservationForm');
            const firstInvalid = form ? form.querySelector('.is-invalid') : null;
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }
        }

        return isValid;
    }

    function validateField(field) {
        const value = field.value.trim();
        const fieldName = field.name;
        let isValid = false;
        let errorMessage = '';

        // Validation rules
        if (fieldName.includes('[firstName]')) {
            if (value === '') {
                errorMessage = 'Le prénom est requis';
            } else if (value.length < 2) {
                errorMessage = 'Le prénom doit contenir au moins 2 caractères';
            } else if (!/^[a-zA-ZÀ-ÿ\s\-]+$/.test(value)) {
                errorMessage = 'Le prénom ne peut contenir que des lettres, espaces et tirets';
            } else {
                isValid = true;
            }
        } else if (fieldName.includes('[lastName]')) {
            if (value === '') {
                errorMessage = 'Le nom est requis';
            } else if (value.length < 2) {
                errorMessage = 'Le nom doit contenir au moins 2 caractères';
            } else if (!/^[a-zA-ZÀ-ÿ\s\-]+$/.test(value)) {
                errorMessage = 'Le nom ne peut contenir que des lettres, espaces et tirets';
            } else {
                isValid = true;
            }
        } else if (fieldName.includes('[email]')) {
            if (value === '') {
                errorMessage = 'L\'email est requis';
            } else if (!/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(value)) {
                errorMessage = 'L\'email n\'est pas valide';
            } else {
                isValid = true;
            }
        } else if (fieldName.includes('[phone]')) {
            if (value === '') {
                errorMessage = 'Le numéro de téléphone est requis';
            } else if (value.length < 10) {
                errorMessage = 'Le numéro de téléphone doit contenir au moins 10 caractères';
            } else {
                isValid = true;
            }
        } else if (fieldName.includes('[date]')) {
            if (value === '') {
                errorMessage = 'La date est requise';
            } else {
                const selectedDate = new Date(value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate < today) {
                    errorMessage = 'La date ne peut pas être dans le passé';
                } else {
                    isValid = true;
                }
            }
        } else if (fieldName.includes('[time]')) {
            if (value === '') {
                errorMessage = 'L\'heure est requise';
            } else {
                // Check if time is not in the past for today
                const form = document.getElementById('reservationForm');
                const dateInput = form ? form.querySelector('input[name="reservation[date]"]') : null;
                const selectedDate = dateInput ? dateInput.value : '';
                const today = new Date().toISOString().split('T')[0];
                
                if (selectedDate === today) {
                    const now = new Date();
                    const [hours, minutes] = value.split(':').map(Number);
                    const selectedDateTime = new Date();
                    selectedDateTime.setHours(hours, minutes, 0, 0);
                    
                    if (selectedDateTime <= now) {
                        errorMessage = 'L\'heure ne peut pas être dans le passé';
                    } else {
                        isValid = true;
                    }
                } else {
                    isValid = true;
                }
            }
        } else if (fieldName.includes('[guests]')) {
            if (value === '') {
                errorMessage = 'Le nombre de personnes est requis';
            } else if (parseInt(value) < 1) {
                errorMessage = 'Le nombre de personnes doit être au moins 1';
            } else {
                isValid = true;
            }
        } else if (fieldName.includes('[message]')) {
            // Message is optional, so always valid
            isValid = true;
        }

        // Update field appearance
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
        }

        // Update error message
        const errorElement = document.getElementById(fieldName.replace(/\[/g, '_').replace(/\]/g, '') + 'Error');
        if (errorElement) {
            if (isValid) {
                errorElement.textContent = '';
                errorElement.classList.remove('show');
            } else {
                errorElement.textContent = errorMessage;
                errorElement.classList.add('show');
            }
        }

        return isValid;
    }

    function submitReservation() {
        const submitBtn = document.querySelector('#reservationForm button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Envoi en cours...';
        
        // Prepare form data
        const formData = new FormData();
        const form = document.getElementById('reservationForm');
        
        formData.append('firstName', form.querySelector('input[name="reservation[firstName]"]').value.trim());
        formData.append('lastName', form.querySelector('input[name="reservation[lastName]"]').value.trim());
        formData.append('email', form.querySelector('input[name="reservation[email]"]').value.trim());
        formData.append('phone', form.querySelector('input[name="reservation[phone]"]').value.trim());
        formData.append('date', form.querySelector('input[name="reservation[date]"]').value);
        formData.append('time', form.querySelector('select[name="reservation[time]"]').value);
        formData.append('guests', form.querySelector('select[name="reservation[guests]"]').value);
        formData.append('message', form.querySelector('textarea[name="reservation[message]"]').value.trim());
        
        
        // Submit via AJAX
        fetch('/reservation-ajax', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (window.showNotification) {
                    window.showNotification(data.message, 'success');
                }
                resetForm();
            } else {
                if (window.showNotification) {
                    window.showNotification(data.message || 'Une erreur est survenue lors de l\'envoi de votre réservation.', 'error');
                }
            }
        })
        .catch(error => {
            if (window.showNotification) {
                window.showNotification('Une erreur est survenue lors de l\'envoi de votre réservation.', 'error');
            }
        })
        .finally(() => {
            // Restore button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    // Use global showNotification function from main.js

    function resetForm() {
        const form = document.getElementById('reservationForm');
        if (form) {
            form.reset();
            
            // Reset validation states
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.classList.remove('is-valid', 'is-invalid');
            });
            
            // Clear error messages
            const errorElements = form.querySelectorAll('.invalid-feedback');
            errorElements.forEach(element => {
                element.textContent = '';
                element.classList.remove('show');
            });
            
            // Reset date minimum
            const dateInput = form.querySelector('input[name="reservation[date]"]');
            if (dateInput) {
                const today = new Date().toISOString().split('T')[0];
                dateInput.min = today;
                dateInput.value = today;
            }
        }
    }
})();