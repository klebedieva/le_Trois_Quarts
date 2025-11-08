// ============================================================================
// ORDER SUBMISSION - Order Creation and Confirmation
// ============================================================================
// This module handles:
// - Order payload building
// - Order submission to backend
// - Order confirmation display
// - Coupon application after order creation
//
// Usage: This module manages the final order submission process.

'use strict';

/**
 * Collect and sanitize form data for order submission
 * 
 * Centralizes form data collection and sanitization.
 * Uses cached DOM elements for better performance.
 * 
 * @param {Object} orderData - Current order data state
 * @returns {Object} Sanitized form data payload
 */
function collectOrderFormData(orderData) {
    /**
     * Get form elements using cached getElement function
     */
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const getElements = window.OrderUtils?.getElements || ((ids) => {
        return ids.reduce((acc, id) => {
            acc[id] = getElement(id);
            return acc;
        }, {});
    });
    
    const elements = getElements([
        'deliveryAddress',
        'deliveryZip',
        'deliveryInstructions',
        'clientFirstName',
        'clientLastName',
        'clientPhone',
        'clientEmail'
    ]);
    
    /**
     * Get delivery and payment modes with fallbacks
     */
    const deliveryMode = document.querySelector('input[name="deliveryMode"]:checked')?.value
        || orderData?.delivery?.mode
        || 'delivery';
    
    const paymentMode = document.querySelector('input[name="paymentMode"]:checked')?.value
        || orderData?.payment?.mode
        || 'card';
    
    /**
     * Get constants
     */
    const DELIVERY_FEE = window.OrderConstants?.DELIVERY_FEE || 5;
    
    /**
     * Calculate delivery fee
     */
    const deliveryFee = typeof orderData.deliveryFee === 'number'
        ? orderData.deliveryFee
        : (deliveryMode === 'pickup' ? 0 : DELIVERY_FEE);
    
    /**
     * Get sanitize function
     */
    const sanitizeInput = window.OrderUtils?.sanitizeInput || ((v) => v.trim());
    
    /**
     * Build payload with sanitized data
     */
    return {
        deliveryMode,
        deliveryAddress: sanitizeInput(elements.deliveryAddress?.value || ''),
        deliveryZip: sanitizeInput(elements.deliveryZip?.value || ''),
        deliveryInstructions: sanitizeInput(elements.deliveryInstructions?.value || ''),
        deliveryFee,
        paymentMode,
        clientFirstName: sanitizeInput(elements.clientFirstName?.value || ''),
        clientLastName: sanitizeInput(elements.clientLastName?.value || ''),
        clientPhone: sanitizeInput(elements.clientPhone?.value || ''),
        clientEmail: sanitizeInput(elements.clientEmail?.value || '')
    };
}

/**
 * Build the order payload and call the backend to create the order
 * 
 * This function:
 * - Validates terms acceptance
 * - Collects form data
 * - Submits order to backend
 * - Applies coupon usage increment
 * - Updates cart UI
 * - Shows confirmation screen
 * 
 * @param {Object} orderData - Current order data state (passed by reference)
 * @returns {Promise<void>}
 */
async function confirmOrder(orderData) {
    // Prevent double submission
    if (window.isSubmittingOrder) {
        return;
    }
    window.isSubmittingOrder = true;
    
    // Disable confirm button if present
    const confirmBtn = document.querySelector('#step4 .btn.btn-success');
    const oldText = confirmBtn ? confirmBtn.innerHTML : null;
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
    }
    
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const accept = getElement('acceptTerms')?.checked;
    if (!accept) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez accepter les conditions générales', 'error');
        }
        window.isSubmittingOrder = false;
        if (confirmBtn) {
            confirmBtn.disabled = false;
            if (oldText !== null) confirmBtn.innerHTML = oldText;
        }
        return;
    }

    // Build payload using centralized form data collection
    const payload = collectOrderFormData(orderData);
    
    // Add coupon data if applied
    if (orderData.coupon && orderData.coupon.couponId) {
        payload.couponId = orderData.coupon.couponId;
        payload.discountAmount = orderData.discount;
    }

    try {
        const result = await window.orderAPI.createOrder(payload);
        const created = result.order; // OrderResponse
        
        // Apply coupon usage increment if coupon was used
        if (orderData.coupon && orderData.coupon.couponId) {
            try {
                await window.couponAPI.applyCoupon(orderData.coupon.couponId);
            } catch (e) {
                console.error('Error incrementing coupon usage:', e);
            }
        }
        
        // Backend already clears cart, update UI
        try { if (window.updateCartSidebar) window.updateCartSidebar(); } catch (_) {}
        try { if (window.updateCartNavigation) window.updateCartNavigation(); } catch (_) {}
        showOrderConfirmation(created.no, created.id, created.total);
    } catch (e) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification(e.message || 'Erreur lors de la création de la commande', 'error');
        }
    } finally {
        // Re-enable button only if confirmation screen not rendered
        if (confirmBtn && document.body.contains(confirmBtn)) {
            confirmBtn.disabled = false;
            if (oldText !== null) confirmBtn.innerHTML = oldText;
        }
        window.isSubmittingOrder = false;
    }
}

/**
 * Replace main container with a success screen after order creation
 * 
 * Shows order confirmation with order number and total.
 * 
 * @param {string} orderNo - Order number
 * @param {string|number} orderId - Order ID
 * @param {number} total - Order total amount
 */
function showOrderConfirmation(orderNo, orderId, total) {
    document.querySelectorAll('.order-step-content').forEach(c => c.classList.remove('active'));
    const container = document.querySelector('.order-section .container');
    if (container) {
        container.innerHTML = `<div class="text-center py-5"><div class="mb-4"><i class="bi bi-check-circle-fill text-success icon-success-large"></i></div><h2 class="text-success mb-3">Commande confirmée !</h2><p class="lead mb-2">Numéro de commande: <strong>${orderNo || orderId}</strong></p><p class="lead mb-4">Montant total: <strong>${Number(total || 0).toFixed(2)}€</strong></p><div class="alert alert-info"><h5><i class="bi bi-info-circle me-2"></i>Prochaines étapes :</h5><ul class="list-unstyled mb-0"><li>• Vous recevrez un email de confirmation</li><li>• Votre commande sera préparée selon le créneau choisi</li></ul></div><div class="mt-4"><a href="${window.appMenuPath || '#'}" class="btn btn-primary"><i class="bi bi-arrow-left me-2"></i>Retour au menu</a></div></div>`;
    }
    if (window.OrderUtils) {
        window.OrderUtils.showOrderNotification('Commande confirmée avec succès !', 'success');
    }
}

// Export submission functions to global scope
window.OrderSubmission = {
    collectOrderFormData,
    confirmOrder,
    showOrderConfirmation
};

// Export to window for inline onclick handlers
// IMPORTANT: Use different implementation to avoid conflicts with local function
window.confirmOrder = async (orderData) => {
    // Get orderData from global scope if not provided
    const data = orderData || window.orderData || {};
    
    // Prevent double submission
    if (window.isSubmittingOrder) {
        return;
    }
    window.isSubmittingOrder = true;
    
    // Disable confirm button if present
    const confirmBtn = document.querySelector('#step4 .btn.btn-success');
    const oldText = confirmBtn ? confirmBtn.innerHTML : null;
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
    }
    
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const accept = getElement('acceptTerms')?.checked;
    if (!accept) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification('Veuillez accepter les conditions générales', 'error');
        }
        window.isSubmittingOrder = false;
        if (confirmBtn) {
            confirmBtn.disabled = false;
            if (oldText !== null) confirmBtn.innerHTML = oldText;
        }
        return;
    }

    // Build payload using centralized form data collection
    const payload = collectOrderFormData(data);
    
    // Add coupon data if applied
    if (data.coupon && data.coupon.couponId) {
        payload.couponId = data.coupon.couponId;
        payload.discountAmount = data.discount;
    }

    try {
        const result = await window.orderAPI.createOrder(payload);
        const created = result.order; // OrderResponse
        
        // Apply coupon usage increment if coupon was used
        if (data.coupon && data.coupon.couponId) {
            try {
                await window.couponAPI.applyCoupon(data.coupon.couponId);
            } catch (e) {
                console.error('Error incrementing coupon usage:', e);
            }
        }
        
        // Backend already clears cart, update UI
        try { if (window.updateCartSidebar) window.updateCartSidebar(); } catch (_) {}
        try { if (window.updateCartNavigation) window.updateCartNavigation(); } catch (_) {}
        showOrderConfirmation(created.no, created.id, created.total);
    } catch (e) {
        if (window.OrderUtils) {
            window.OrderUtils.showOrderNotification(e.message || 'Erreur lors de la création de la commande', 'error');
        }
    } finally {
        // Re-enable button only if confirmation screen not rendered
        if (confirmBtn && document.body.contains(confirmBtn)) {
            confirmBtn.disabled = false;
            if (oldText !== null) confirmBtn.innerHTML = oldText;
        }
        window.isSubmittingOrder = false;
    }
};

