// ============================================================================
// ORDER CART - Cart Management and Order Summary
// ============================================================================
// This module handles:
// - Loading cart items from API
// - Rendering cart items in checkout
// - Cart quantity controls
// - Order summary calculations and display
// - Cart UI refresh
//
// Usage: This module manages cart display and calculations in the checkout process.

'use strict';

/**
 * Common cart update logic
 * 
 * Refreshes cart display and updates all related UI components.
 * This function consolidates cart update logic to prevent duplication.
 * 
 * @param {Object} orderData - Current order data state (passed by reference)
 * @returns {Promise<void>}
 */
async function refreshCartUI(orderData) {
    await loadCartItems(orderData);
    updateOrderSummary(orderData);
    if (window.updateCartSidebar) window.updateCartSidebar();
    if (window.updateCartNavigation) window.updateCartNavigation();
    window.dispatchEvent(new CustomEvent('cartUpdated'));
}

/**
 * Event handler for cart quantity controls
 * 
 * This handler is set up once and uses event delegation to handle
 * all quantity and remove button clicks. It's defined outside
 * loadCartItems() to prevent duplicate listeners.
 */
let cartItemsClickHandler = null;

/**
 * Load current cart and render the order summary items block
 * 
 * This function:
 * - Fetches cart data from API
 * - Updates orderData.items with current cart items
 * - Renders cart items in checkout UI
 * - Handles empty cart state
 * - Sets up event listeners for quantity controls and remove buttons (once)
 * 
 * @param {Object} orderData - Current order data state (passed by reference)
 * @returns {Promise<void>}
 */
async function loadCartItems(orderData) {
    /**
     * Get container element for cart items display
     * Exit early if element not found
     * Use cached getElement for better performance
     */
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const container = getElement('orderCartItems');
    if (!container) return;

    /**
     * Fetch cart data from API
     * Use empty cart as fallback if API call fails
     */
    let cart = { items: [] };
    try {
        cart = await window.cartAPI.getCart();
    } catch (error) {
        console.error('Error loading cart:', error);
        cart = { items: [] };
    }
    
    /**
     * Ensure items is an array
     * Safely handle unexpected data structures
     */
    const items = Array.isArray(cart.items) ? cart.items : [];
    orderData.items = items;

    /**
     * Handle empty cart state
     * Show message and link to menu
     */
    if (items.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="mb-4"><i class="bi bi-basket display-1 text-muted"></i></div>
                <h4 class="mt-3 text-muted">Votre panier est vide</h4>
                <p class="text-muted mb-4">Ajoutez des plats depuis notre menu</p>
                <a href="${window.appMenuPath || '#'}" class="btn btn-primary"><i class="bi bi-arrow-left me-2"></i>Voir le menu</a>
            </div>`;
        return;
    }

    /**
     * Get sanitize function
     */
    const sanitizeInput = window.OrderUtils?.sanitizeInput || ((v) => v.trim());

    /**
     * Render cart items HTML
     * Each item shows: name, quantity, price, and controls
     * Includes aria-attributes for accessibility
     */
    let html = '';
    items.forEach((it, index) => {
        const itemTotal = Number(it.price) * Number(it.quantity);
        html += `
            <div class="cart-item" role="listitem" aria-label="Article: ${sanitizeInput(it.name)}, quantité: ${it.quantity}, prix: ${itemTotal.toFixed(2)}€">
                <div class="cart-item-info">
                    <h5>${sanitizeInput(it.name)}</h5>
                    <p>Quantité: ${it.quantity} × ${Number(it.price).toFixed(2)}€</p>
                </div>
                <div class="cart-item-controls">
                    <div class="quantity-controls" role="group" aria-label="Contrôles de quantité pour ${sanitizeInput(it.name)}">
                        <button type="button" class="btn btn-sm btn-outline-secondary quantity-btn" data-id="${it.id}" data-action="decrease" aria-label="Diminuer la quantité de ${sanitizeInput(it.name)}" title="Diminuer">
                            <i class="bi bi-dash" aria-hidden="true"></i>
                        </button>
                        <span class="quantity-display" aria-label="Quantité actuelle: ${it.quantity}">${it.quantity}</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary quantity-btn" data-id="${it.id}" data-action="increase" aria-label="Augmenter la quantité de ${sanitizeInput(it.name)}" title="Augmenter">
                            <i class="bi bi-plus" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="cart-item-price" aria-label="Prix total pour cet article: ${itemTotal.toFixed(2)}€">${itemTotal.toFixed(2)}€</div>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-from-cart" data-id="${it.id}" aria-label="Supprimer ${sanitizeInput(it.name)} du panier" title="Supprimer">
                        <i class="bi bi-x" aria-hidden="true"></i>
                    </button>
                </div>
            </div>`;
    });
    container.innerHTML = html;

    /**
     * Set up event delegation for all cart controls (only once)
     * Single listener handles all quantity and remove buttons
     * Prevents memory leaks and improves performance
     */
    if (!cartItemsClickHandler) {
        cartItemsClickHandler = async function(e) {
            /**
             * Find clicked button (quantity or remove)
             */
            const btn = e.target.closest('.quantity-btn, .remove-from-cart');
            if (!btn) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            const id = parseInt(btn.getAttribute('data-id'));
            if (!id) return;
            
            try {
                /**
                 * Handle remove button
                 */
                if (btn.classList.contains('remove-from-cart')) {
                    await window.cartAPI.removeItem(id);
                }
                /**
                 * Handle quantity buttons
                 */
                else if (btn.classList.contains('quantity-btn')) {
                    const action = btn.getAttribute('data-action');
                    const current = orderData.items.find(i => Number(i.id) === Number(id));
                    
                    if (!current) {
                        console.warn('Item not found in cart:', id);
                        await refreshCartUI(orderData);
                        return;
                    }
                    
                    if (action === 'increase') {
                        /**
                         * Increase quantity by 1
                         */
                        await window.cartAPI.updateQuantity(id, current.quantity + 1);
                    } else if (action === 'decrease') {
                        /**
                         * Decrease quantity or remove item
                         * If quantity > 1: decrease by 1
                         * If quantity = 1: remove item completely
                         */
                        if (current.quantity > 1) {
                            await window.cartAPI.updateQuantity(id, current.quantity - 1);
                        } else {
                            await window.cartAPI.removeItem(id);
                        }
                    }
                }
                
                /**
                 * Refresh cart UI after successful change
                 * This updates orderData.items with fresh data from server
                 */
                await refreshCartUI(orderData);
            } catch (error) {
                /**
                 * Handle errors gracefully
                 * Show error notification to user
                 * Log error for debugging
                 */
                console.error('Error modifying cart quantity:', error);
                
                /**
                 * Show error notification to user
                 * Use showNotification if available, otherwise fallback to alert
                 */
                const errorMessage = 'Erreur lors de la modification de la quantité';
                if (typeof window.showNotification === 'function') {
                    window.showNotification(errorMessage, 'error');
                } else if (typeof window.showCartNotification === 'function') {
                    window.showCartNotification(errorMessage, 'error');
                } else {
                    alert(errorMessage);
                }
                
                /**
                 * Refresh cart UI even on error to ensure consistency
                 * This ensures UI reflects actual server state
                 */
                try {
                    await refreshCartUI(orderData);
                } catch (refreshError) {
                    console.error('Error refreshing cart UI:', refreshError);
                }
            }
        };
        
        container.addEventListener('click', cartItemsClickHandler);
    }
}

/**
 * Recompute and render the financial summary (HT/TVA/Total)
 * 
 * This function:
 * - Calculates subtotal with tax (TTC) from cart items
 * - Calculates subtotal without tax (HT) and tax amount
 * - Applies delivery fee and discount
 * - Updates UI with all financial totals
 * - Dynamically shows/hides discount line
 * 
 * Note: Menu prices already include taxes (TTC), so we calculate
 * backwards to get HT (without tax) amount.
 * 
 * @param {Object} orderData - Current order data state (passed by reference)
 */
function updateOrderSummary(orderData) {
    /**
     * Get container for summary items
     * Exit early if element not found
     * Use cached getElement for better performance
     */
    const getElement = window.OrderUtils?.getElement || (id => document.getElementById(id));
    const container = getElement('summaryItems');
    if (!container) return;
    
    /**
     * Get constants
     */
    const TAX_RATE = window.OrderConstants?.TAX_RATE || 0.10;
    
    /**
     * Calculate subtotal with tax (TTC)
     * Sum of all item prices × quantities
     */
    let subtotalWithTax = 0;
    let html = '';
    orderData.items.forEach(it => {
        const itemTotal = Number(it.price) * Number(it.quantity);
        subtotalWithTax += itemTotal;
        /**
         * Render summary item HTML
         * Shows item name, quantity, and total price
         */
        html += `<div class="summary-item"><div class="summary-item-info"><span class="summary-item-name">${it.name}</span><small class="text-muted">x${it.quantity}</small></div><span class="summary-item-price">${itemTotal.toFixed(2)}€</span></div>`;
    });
    container.innerHTML = html;

    /**
     * Calculate tax breakdown
     * Menu prices already include taxes (TTC)
     * Calculate amount without taxes (HT) and tax separately
     */
    const subtotalWithoutTax = subtotalWithTax / (1 + TAX_RATE);
    const taxAmount = subtotalWithTax - subtotalWithoutTax;
    
    /**
     * Calculate final total
     * Subtotal + delivery fee - discount
     */
    const deliveryFee = orderData.deliveryFee || 0;
    const discount = orderData.discount || 0;
    const total = subtotalWithTax + deliveryFee - discount;
    
    /**
     * Update subtotal and tax display
     * Use cached getElement for better performance
     */
    const subEl = getElement('subtotal');
    const taxEl = getElement('taxAmount');
    const totalEl = getElement('totalAmount');
    if (subEl) subEl.textContent = subtotalWithoutTax.toFixed(2) + '€';
    if (taxEl) taxEl.textContent = taxAmount.toFixed(2) + '€';
    
    /**
     * Update or add discount line dynamically
     * Shows discount only when coupon is applied
     */
    const totalsContainer = document.querySelector('.summary-totals');
    let discountLine = getElement('discountLine');
    
    if (discount > 0) {
        /**
         * Create discount line if it doesn't exist
         * Insert before total line
         */
        if (!discountLine) {
            discountLine = document.createElement('div');
            discountLine.id = 'discountLine';
            discountLine.className = 'summary-line text-success';
            // Insert before total line
            const totalLine = totalsContainer.querySelector('.summary-line.total');
            totalsContainer.insertBefore(discountLine, totalLine);
        }
        /**
         * Update discount line with coupon code and amount
         */
        discountLine.innerHTML = `<span>Réduction <small>(${orderData.coupon?.code || ''})</small></span><span>-${discount.toFixed(2)}€</span>`;
    } else if (discountLine) {
        /**
         * Remove discount line if no discount applied
         */
        discountLine.remove();
    }
    
    /**
     * Update total amount display
     */
    if (totalEl) totalEl.textContent = total.toFixed(2) + '€';
    
    /**
     * Update orderData with calculated values
     * Used for order submission
     */
    orderData.subtotal = subtotalWithoutTax;
    orderData.taxAmount = taxAmount;
    orderData.total = total;
}

// Export cart functions to global scope
window.OrderCart = {
    loadCartItems,
    updateOrderSummary,
    refreshCartUI
};

