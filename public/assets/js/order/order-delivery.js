// ============================================================================
// ORDER DELIVERY - Delivery Options, Payment Options, and Time Management
// ============================================================================
// This module handles:
// - Delivery mode selection (delivery vs. pickup)
// - Payment method selection
// - Delivery fee updates
// - Time slot generation and validation
//
// Usage: This module manages delivery and payment options in the checkout process.

'use strict';

/**
 * Initialize delivery option toggles and auto fee updates
 * 
 * Sets up handlers for delivery mode selection (delivery vs. pickup).
 * When delivery is selected, shows address fields and applies delivery fee.
 * 
 * Behavior:
 * - 'delivery': Shows address fields, applies 5€ fee
 * - 'pickup': Hides address fields, no fee
 * 
 * @param {Object} orderData - Current order data state (passed by reference)
 */
function initDeliveryOptions(orderData) {
    /**
     * Get all delivery mode radio buttons
     */
    const options = document.querySelectorAll('input[name="deliveryMode"]');
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const details = getElement('deliveryDetails');
    
    /**
     * Get constants
     */
    const DELIVERY_FEE = window.OrderConstants?.DELIVERY_FEE || 5;
    
    /**
     * Set up change handlers for each option
     */
    options.forEach(opt => {
        opt.addEventListener('change', function() {
            if (this.value === 'delivery') {
                /**
                 * Delivery mode selected
                 * Show address fields and apply delivery fee
                 */
                if (details) details.style.display = 'block';
                updateDeliveryFee(DELIVERY_FEE, orderData);
            } else {
                /**
                 * Pickup mode selected
                 * Hide address fields and remove delivery fee
                 */
                if (details) details.style.display = 'none';
                updateDeliveryFee(0, orderData);
            }
            
            /**
             * Update order summary to reflect fee change
             */
            if (window.OrderCart && window.OrderCart.updateOrderSummary) {
                window.OrderCart.updateOrderSummary(orderData);
            }
        });
    });
    
    /**
     * Trigger change event for pre-selected option
     * Ensures UI is in sync with initial state
     */
    const checked = document.querySelector('input[name="deliveryMode"]:checked');
    if (checked) checked.dispatchEvent(new Event('change'));
}

/**
 * Initialize payment option toggles
 * 
 * Sets up handlers for payment method selection.
 * Shows card details fields when card payment is selected.
 * 
 * Behavior:
 * - 'card': Shows card details fields
 * - Other methods: Hides card details fields
 */
function initPaymentOptions() {
    /**
     * Get all payment mode radio buttons
     */
    const options = document.querySelectorAll('input[name="paymentMode"]');
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const cardDetails = getElement('cardDetails');
    
    /**
     * Set up change handlers for each option
     */
    options.forEach(opt => {
        opt.addEventListener('change', function() {
            /**
             * Show card details only for card payment
             * Hide for cash and other payment methods
             */
            if (cardDetails) {
                cardDetails.style.display = this.value === 'card' ? 'block' : 'none';
            }
        });
    });
    
    /**
     * Trigger change event for pre-selected option
     * Ensures UI is in sync with initial state
     */
    const checked = document.querySelector('input[name="paymentMode"]:checked');
    if (checked) checked.dispatchEvent(new Event('change'));
}

/**
 * Update delivery fee in state and UI
 * 
 * Updates both orderData state and UI display with new delivery fee.
 * 
 * @param {number} fee - Delivery fee amount (0 for pickup, 5 for delivery)
 * @param {Object} orderData - Current order data state (passed by reference)
 */
function updateDeliveryFee(fee, orderData) {
    /**
     * Update orderData state
     * Used for order summary calculations
     */
    orderData.deliveryFee = fee;
    
    /**
     * Update UI display
     * Shows fee amount in order summary
     */
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const el = getElement('deliveryFee');
    if (el) el.textContent = fee + '€';
}

/**
 * Initialize time validation
 * 
 * Sets up date and time selection with validation.
 * Uses cached getElement for better performance.
 */
function initTimeValidation() {
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const dateInput = getElement('deliveryDate');
    const timeSelect = getElement('deliveryTime');
    if (dateInput && timeSelect) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;
        dateInput.value = today;
        dateInput.addEventListener('change', updateTimeOptions);
        timeSelect.addEventListener('change', () => {
            if (window.OrderValidation && window.OrderValidation.validateSelectedTime) {
                window.OrderValidation.validateSelectedTime();
            }
        });
        updateTimeOptions();
    }
}

/**
 * Update time slot options based on selected date
 * 
 * Filters available time slots based on:
 * - Selected date (today vs. future)
 * - Minimum time delay requirement
 * 
 * Uses cached TIME_SLOTS array for better performance.
 */
function updateTimeOptions() {
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const dateInput = getElement('deliveryDate');
    const timeSelect = getElement('deliveryTime');
    if (!dateInput || !timeSelect) return;
    
    const selectedDate = dateInput.value;
    const today = new Date().toISOString().split('T')[0];
    const currentTime = new Date();
    
    /**
     * Get constants
     */
    const TIME_SLOTS = window.OrderConstants?.TIME_SLOTS || [];
    const MIN_TIME_DELAY_HOURS = window.OrderConstants?.MIN_TIME_DELAY_HOURS || 1;
    
    /**
     * Clear existing options
     */
    timeSelect.innerHTML = '<option value="">Choisir un créneau</option>';
    
    /**
     * Filter time slots based on selected date
     */
    if (selectedDate === today) {
        /**
         * For today, filter out past time slots
         * Minimum delay is MIN_TIME_DELAY_HOURS
         */
        const minimumTime = new Date(currentTime.getTime() + MIN_TIME_DELAY_HOURS * 60 * 60 * 1000);
        
        TIME_SLOTS.forEach(slot => {
            const slotTime = new Date(`${selectedDate}T${slot.value}`);
            if (slotTime > minimumTime) {
                const option = document.createElement('option');
                option.value = slot.value;
                option.textContent = slot.text;
                timeSelect.appendChild(option);
            }
        });
    } else {
        /**
         * For future dates, show all time slots
         */
        TIME_SLOTS.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.value;
            option.textContent = slot.text;
            timeSelect.appendChild(option);
        });
    }
    
    /**
     * Show message if no slots available for today
     */
    if (selectedDate === today && timeSelect.options.length === 1) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Aucun créneau disponible aujourd\'hui';
        option.disabled = true;
        timeSelect.appendChild(option);
    }
}

// Export delivery functions to global scope
window.OrderDelivery = {
    initDeliveryOptions,
    initPaymentOptions,
    updateDeliveryFee,
    initTimeValidation,
    updateTimeOptions
};

