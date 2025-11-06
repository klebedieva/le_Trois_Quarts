// ============================================================================
// ORDER COUPON - Coupon/Promo Code Functionality
// ============================================================================
// This module handles:
// - Promo code initialization
// - Coupon validation and application
// - Coupon removal
// - UI updates for coupon state
//
// Usage: This module manages coupon functionality in the checkout process.

'use strict';

/**
 * Initialize promo code functionality
 * 
 * Wire up promo code apply button and Enter key behavior.
 * Uses cached getElement for better performance.
 * 
 * @param {Object} orderData - Current order data state (passed by reference)
 */
function initPromoCode(orderData) {
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const promoInput = getElement('promoCode');
    const promoButton = document.querySelector('.promo-code button');
    
    if (!promoButton || !promoInput) return;
    
    promoButton.addEventListener('click', async function(e) {
        e.preventDefault();
        await applyPromoCode(orderData);
    });
    
    // Allow Enter key to apply promo code
    promoInput.addEventListener('keypress', async function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            await applyPromoCode(orderData);
        }
    });
}

/**
 * Apply promo code
 * 
 * Validate and apply a coupon; update order summary and UI.
 * Uses cached getElement and centralized order amount calculation.
 * 
 * @param {Object} orderData - Current order data state (passed by reference)
 * @returns {Promise<void>}
 */
async function applyPromoCode(orderData) {
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const promoInput = getElement('promoCode');
    const promoButton = document.querySelector('.promo-code button');
    
    if (!promoInput || !promoButton) return;
    
    const code = promoInput.value.trim().toUpperCase();
    
    if (!code) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez entrer un code promo', 'error');
        }
        return;
    }
    
    // Disable button during validation
    const originalText = promoButton.textContent;
    promoButton.disabled = true;
    promoButton.textContent = 'Vérification...';
    
    try {
        /**
         * Calculate order amount using centralized function
         * Uses cached calculation if available
         */
        const orderAmount = getOrderAmountForCoupon(orderData);
        
        // Validate coupon
        const result = await window.couponAPI.validateCoupon(code, orderAmount);
        
        if (result.success) {
            // Store coupon data
            orderData.coupon = result.data;
            orderData.discount = parseFloat(result.data.discountAmount);
            
            // Update summary
            if (window.OrderCart && window.OrderCart.updateOrderSummary) {
                window.OrderCart.updateOrderSummary(orderData);
            } else {
                console.warn('OrderCart.updateOrderSummary not available');
            }
            
            // Update UI
            promoInput.value = '';
            promoInput.disabled = true;
            promoButton.textContent = 'Appliqué ✓';
            promoButton.classList.remove('btn-outline-secondary');
            promoButton.classList.add('btn-success');
            
            // Add remove button
            addRemoveCouponButton(orderData);
            
            if (window.OrderUtils) {
                window.OrderUtils.showOrderNotification(`Code promo appliqué ! Vous économisez ${orderData.discount.toFixed(2)}€`, 'success');
            }
        }
    } catch (error) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification(error.message || 'Code promo invalide', 'error');
        }
    } finally {
        promoButton.disabled = false;
        if (promoButton.textContent === 'Vérification...') {
            promoButton.textContent = originalText;
        }
    }
}

/**
 * Add remove coupon button
 * 
 * Create a small button that lets the user remove the currently applied coupon.
 * 
 * @param {Object} orderData - Current order data state (passed by reference)
 */
function addRemoveCouponButton(orderData) {
    const promoCodeDiv = document.querySelector('.promo-code');
    if (!promoCodeDiv) return;
    
    // Check if button already exists
    if (document.getElementById('removeCouponBtn')) return;
    
    const removeBtn = document.createElement('button');
    removeBtn.id = 'removeCouponBtn';
    removeBtn.className = 'btn btn-sm mt-2 w-100';
    removeBtn.style.borderRadius = '50px'; // Pill-style rounded corners like "Continuer" button
    removeBtn.style.fontWeight = '600';
    removeBtn.style.letterSpacing = '0.5px';
    removeBtn.style.border = '2px solid #8b4513'; // Secondary color (dark brown)
    removeBtn.style.color = '#8b4513';
    removeBtn.style.background = 'white';
    removeBtn.style.transition = 'all 0.3s ease';
    removeBtn.innerHTML = '<i class="bi bi-x"></i> Retirer le code promo';
    removeBtn.onclick = () => removeCoupon(orderData);
    
    // Add hover effect
    removeBtn.addEventListener('mouseenter', function() {
        this.style.background = '#8b4513';
        this.style.color = 'white';
        this.style.transform = 'translateY(-1px)';
        this.style.boxShadow = '0 4px 12px rgba(139, 69, 19, 0.3)';
    });
    removeBtn.addEventListener('mouseleave', function() {
        this.style.background = 'white';
        this.style.color = '#8b4513';
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
    });
    
    promoCodeDiv.appendChild(removeBtn);
}

/**
 * Remove coupon
 * 
 * Remove an applied coupon and refresh totals/controls.
 * Uses cached getElement for better performance.
 * 
 * @param {Object} orderData - Current order data state (passed by reference)
 */
function removeCoupon(orderData) {
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const promoInput = getElement('promoCode');
    const promoButton = document.querySelector('.promo-code button');
    const removeBtn = getElement('removeCouponBtn');
    
    // Reset coupon data
    orderData.coupon = null;
    orderData.discount = 0;
    
    // Update summary
    if (window.OrderCart && window.OrderCart.updateOrderSummary) {
        window.OrderCart.updateOrderSummary(orderData);
    } else {
        console.warn('OrderCart.updateOrderSummary not available');
    }
    
    // Reset UI
    if (promoInput) {
        promoInput.disabled = false;
        promoInput.value = '';
    }
    
    if (promoButton) {
        promoButton.textContent = 'Appliquer';
        promoButton.classList.remove('btn-success');
        promoButton.classList.add('btn-outline-secondary');
    }
    
    if (removeBtn) {
        removeBtn.remove();
    }
    
    if (window.OrderUtils) {
        window.OrderUtils.showOrderNotification('Code promo retiré', 'info');
    }
}

/**
 * Get order amount for coupon validation
 * 
 * Uses orderData.total + discount if available, otherwise calculates.
 * 
 * @param {Object} orderData - Current order data state
 * @returns {number} Order amount before discount
 */
function getOrderAmountForCoupon(orderData) {
    // If order summary is up to date, use it
    if (orderData.total && orderData.discount !== undefined) {
        return orderData.total + orderData.discount; // total before discount
    }
    // Otherwise calculate
    return calculateOrderAmount(orderData);
}

/**
 * Calculate order total amount (before discount)
 * 
 * Extracts order amount calculation to a reusable function.
 * Used for coupon validation and order processing.
 * 
 * @param {Object} orderData - Current order data state
 * @returns {number} Order amount in euros
 */
function calculateOrderAmount(orderData) {
    let subtotalWithTax = 0;
    orderData.items.forEach(it => {
        subtotalWithTax += Number(it.price) * Number(it.quantity);
    });
    const deliveryFee = orderData.deliveryFee || 0;
    return subtotalWithTax + deliveryFee;
}

// Export coupon functions to global scope
window.OrderCoupon = {
    initPromoCode,
    applyPromoCode,
    removeCoupon,
    addRemoveCouponButton,
    getOrderAmountForCoupon,
    calculateOrderAmount
};

// Export to window for inline onclick handlers
// IMPORTANT: Use direct implementation to avoid potential conflicts
window.removeCoupon = (orderData) => {
    // Get orderData from global scope if not provided
    const data = orderData || window.orderData || {};
    
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const promoInput = getElement('promoCode');
    const promoButton = document.querySelector('.promo-code button');
    const removeBtn = getElement('removeCouponBtn');
    
    // Reset coupon data
    data.coupon = null;
    data.discount = 0;
    
    // Update summary
    if (window.OrderCart && window.OrderCart.updateOrderSummary) {
        window.OrderCart.updateOrderSummary(data);
    } else {
        console.warn('OrderCart.updateOrderSummary not available');
    }
    
    // Reset UI
    if (promoInput) {
        promoInput.disabled = false;
        promoInput.value = '';
    }
    
    if (promoButton) {
        promoButton.textContent = 'Appliquer';
        promoButton.classList.remove('btn-success');
        promoButton.classList.add('btn-outline-secondary');
    }
    
    if (removeBtn) {
        removeBtn.remove();
    }
    
    if (window.OrderUtils) {
        window.OrderUtils.showOrderNotification('Code promo retiré', 'info');
    }
};

