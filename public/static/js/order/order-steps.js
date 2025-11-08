// ============================================================================
// ORDER STEPS - Multi-Step Checkout Navigation and Management
// ============================================================================
// This module handles:
// - Step navigation (next, previous, show)
// - Step validation
// - Final summary rendering
//
// Usage: This module manages the checkout flow and step transitions.

'use strict';

/**
 * Current checkout step
 * 
 * Steps:
 * - 1: Cart review
 * - 2: Delivery information
 * - 3: Payment method
 * - 4: Confirmation
 */
let currentStep = 1;

/**
 * Navigate to next step in checkout process
 * 
 * Validates current step before advancing.
 * Only advances if validation passes.
 * 
 * @param {number} step - Step number to navigate to
 * @param {Object} orderData - Current order data state
 * @returns {Promise<void>}
 */
async function nextStep(step, orderData) {
    /**
     * Validate current step before advancing
     * Prevents invalid data from proceeding
     */
    const isValid = await validateCurrentStep(orderData);
    if (isValid) {
        showStep(step);
    }
}

/**
 * Navigate to previous step in checkout process
 * 
 * No validation required when going back.
 * 
 * @param {number} step - Step number to navigate to
 */
function prevStep(step) {
    showStep(step);
}

/**
 * Show a specific checkout step
 * 
 * This function:
 * - Hides all step content
 * - Shows target step content
 * - Updates step indicators (active states)
 * - Updates currentStep variable
 * - Updates final summary if on confirmation step
 * 
 * @param {number} step - Step number to show (1-4)
 */
function showStep(step) {
    /**
     * Hide all step content
     * Remove active class from all steps
     */
    document.querySelectorAll('.order-step-content').forEach(c => {
        c.classList.remove('active');
        c.setAttribute('aria-hidden', 'true');
    });
    
    /**
     * Show target step content
     * Add active class to target step
     */
    const target = document.getElementById(`step${step}`);
    if (target) {
        target.classList.add('active');
        target.setAttribute('aria-hidden', 'false');
    }
    
    /**
     * Update step indicators
     * Mark steps as active up to current step
     * Update aria-current for accessibility
     */
    const steps = document.querySelectorAll('.step');
    steps.forEach((el, i) => {
        if (i + 1 <= step) {
            el.classList.add('active');
            // Mark current step with aria-current
            if (i + 1 === step) {
                el.setAttribute('aria-current', 'step');
            } else {
                el.removeAttribute('aria-current');
            }
        } else {
            el.classList.remove('active');
            el.removeAttribute('aria-current');
        }
    });
    
    /**
     * Update current step variable
     * Used for validation logic
     */
    currentStep = step;
    
    /**
     * Update final summary if on confirmation step
     * Shows order review before submission
     * Get orderData from global scope
     */
    if (step === 4) {
        const orderData = window.orderData || {};
        updateFinalSummary(orderData);
    }
}

/**
 * Validate current checkout step
 * 
 * Calls appropriate validation function based on current step.
 * 
 * @param {Object} orderData - Current order data state
 * @returns {Promise<boolean>} True if validation passes, false otherwise
 */
async function validateCurrentStep(orderData) {
    if (!window.OrderValidation) {
        console.error('OrderValidation module not loaded');
        return false;
    }
    
    switch (currentStep) {
        case 1:
            /**
             * Validate cart step
             * Ensures cart is not empty
             */
            return window.OrderValidation.validateCartStep(orderData.items || []);
        case 2:
            /**
             * Validate delivery step
             * Validates delivery mode, address, date, time, and client info
             */
            return await window.OrderValidation.validateDeliveryStep(orderData);
        case 3:
            /**
             * Validate payment step
             * Ensures payment method is selected
             */
            return window.OrderValidation.validatePaymentStep(orderData);
        default:
            /**
             * No validation needed for other steps
             */
            return true;
    }
}

/**
 * Build the final confirmation view using current orderData
 * 
 * Uses DOM methods instead of innerHTML to prevent XSS attacks.
 * All user data is displayed using textContent which automatically escapes HTML.
 * 
 * @param {Object} orderData - Current order data state
 */
function updateFinalSummary(orderData) {
    if (!orderData) {
        console.error('orderData is required for updateFinalSummary');
        return;
    }
    
    /**
     * Render order items
     * Uses DOM methods for safe rendering
     */
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const itemsEl = getElement('finalOrderItems');
    if (itemsEl) {
        itemsEl.innerHTML = ''; // Clear first
        
        orderData.items.forEach(it => {
            const div = document.createElement('div');
            div.className = 'd-flex justify-content-between mb-2';
            
            const itemSpan = document.createElement('span');
            itemSpan.textContent = `${it.name} x${it.quantity}`;
            
            const priceSpan = document.createElement('span');
            const itemTotal = Number(it.price) * Number(it.quantity);
            priceSpan.textContent = `${itemTotal.toFixed(2)}€`;
            
            div.appendChild(itemSpan);
            div.appendChild(priceSpan);
            itemsEl.appendChild(div);
        });
    }
    
    /**
     * Render client information
     * Uses DOM methods for safe rendering
     */
    const clientEl = getElement('finalClientInfo');
    if (clientEl) {
        clientEl.innerHTML = ''; // Clear first
        
        const c = orderData.client || {};
        
        const nameP = document.createElement('p');
        const nameStrong = document.createElement('strong');
        nameStrong.textContent = `${c.firstName || ''} ${c.lastName || ''}`;
        nameP.appendChild(nameStrong);
        clientEl.appendChild(nameP);
        
        if (c.phone) {
            const phoneP = document.createElement('p');
            phoneP.textContent = `Téléphone: ${c.phone}`;
            clientEl.appendChild(phoneP);
        }
        
        if (c.email) {
            const emailP = document.createElement('p');
            emailP.textContent = `Email: ${c.email}`;
            clientEl.appendChild(emailP);
        }
    }
    
    /**
     * Render delivery information
     * Uses DOM methods for safe rendering
     */
    const deliveryEl = getElement('finalDeliveryInfo');
    if (deliveryEl) {
        deliveryEl.innerHTML = ''; // Clear first
        
        const d = orderData.delivery || {};
        const modeText = d.mode === 'delivery' ? 'Livraison à domicile' : 'Retrait sur place';
        
        const modeP = document.createElement('p');
        const modeStrong = document.createElement('strong');
        modeStrong.textContent = modeText;
        modeP.appendChild(modeStrong);
        deliveryEl.appendChild(modeP);
        
        const dateP = document.createElement('p');
        dateP.textContent = `Date: ${d.date} à ${d.time}`;
        deliveryEl.appendChild(dateP);
        
        if (d.mode === 'delivery' && d.address) {
            const addressP = document.createElement('p');
            addressP.textContent = `Adresse: ${d.address}, ${d.zip}`;
            deliveryEl.appendChild(addressP);
        }
    }
    
    /**
     * Render payment information
     * Uses DOM methods for safe rendering
     */
    const paymentEl = getElement('finalPaymentInfo');
    if (paymentEl) {
        paymentEl.innerHTML = ''; // Clear first
        
        const text = orderData.payment?.mode === 'card'
            ? 'Carte bancaire'
            : orderData.payment?.mode === 'cash'
                ? 'Paiement en espèces'
                : 'Tickets restaurant';
        
        const paymentP = document.createElement('p');
        paymentP.textContent = text;
        paymentEl.appendChild(paymentP);
    }
}

// Export step management functions to global scope
window.OrderSteps = {
    currentStep: () => currentStep,
    nextStep,
    prevStep,
    showStep,
    validateCurrentStep,
    updateFinalSummary,
    setCurrentStep: (step) => { currentStep = step; }
};

// Export functions to window for inline onclick handlers
// IMPORTANT: Use different names to avoid conflicts with local functions
window.nextStep = async (step) => {
    // Get orderData from global scope (set by main order.js)
    const orderData = window.orderData || {};
    // Call the local nextStep function (not window.nextStep to avoid recursion)
    const isValid = await validateCurrentStep(orderData);
    if (isValid) {
        showStep(step);
    }
};

window.prevStep = (step) => {
    showStep(step);
};

