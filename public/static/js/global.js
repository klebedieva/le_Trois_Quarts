// ============================================================================
// GLOBAL.JS - Global Utility Functions
// ============================================================================
// This file contains utility functions that need to be available across the site:
// - Cart count display management
// - Confirmation dialog (fallback if main.js not loaded)
//
// Note: Some functions here may duplicate functionality in main.js to ensure
// they work even if main.js loads late or fails.

// ============================================================================
// CART COUNT DISPLAY
// ============================================================================

/**
 * Cart count visibility management
 * 
 * This IIFE (Immediately Invoked Function Expression) ensures the cart count
 * badge in the navbar becomes visible after the page loads.
 * 
 * Why this exists:
 * - Cart count starts hidden (CSS: .hidden) to prevent layout shift
 * - Needs to be shown after cart data loads from localStorage
 * - Should react to cart updates in real-time
 * 
 * How it works:
 * 1. Listens for 'cartUpdated' event (fires when cart changes)
 * 2. Shows cart count on DOMContentLoaded (fast fallback)
 * 3. Shows cart count on window.load (slower fallback)
 * 4. One-time check after 500ms (catches late-loading scripts)
 * 
 * This approach is more efficient than polling because:
 * - Only reacts when cart actually changes (event-driven)
 * - No unnecessary checks every 150ms
 * - Immediate response to cart updates
 */
(function() {
    /**
     * Remove 'hidden' class from cart count element to make it visible
     * This function is idempotent (safe to call multiple times)
     */
    function unhideCartCount() {
        // Find the cart count badge in the navbar
        var el = document.getElementById('cartNavCount');
        // If element exists, remove 'hidden' class to make it visible
        if (el) { el.classList.remove('hidden'); }
    }
    
    /**
     * Primary method: Listen for cart update events
     * When cart-api.js (or other scripts) updates the cart, they dispatch
     * a 'cartUpdated' event. This listener reacts immediately.
     * 
     * This is the most efficient approach - no polling, instant response.
     */
    window.addEventListener('cartUpdated', unhideCartCount);
    
    /**
     * Fallback 1: Try to show cart count as soon as DOM is ready
     * DOMContentLoaded fires when HTML is parsed (before images load)
     * This handles cases where cart count is already visible in HTML
     */
    document.addEventListener('DOMContentLoaded', unhideCartCount);
    
    /**
     * Fallback 2: Try again when all resources (images, CSS) are loaded
     * window.load fires after everything is loaded
     * More reliable but slower than DOMContentLoaded
     */
    window.addEventListener('load', unhideCartCount);
    
    /**
     * Fallback 3: One-time check after 500ms
     * Catches edge cases where:
     * - Late-loading scripts manipulate cart count
     * - cartUpdated event doesn't fire for some reason
     * - Page loads with existing cart data
     * 
     * This is a single check (not polling), so it's very lightweight.
     */
    setTimeout(unhideCartCount, 500);
})();

// ============================================================================
// CONFIRMATION DIALOG (FALLBACK)
// ============================================================================

/**
 * Global Bootstrap confirmation modal
 * 
 * This is a fallback implementation of showConfirmDialog that may be used
 * by cart-api.js and other scripts. If main.js loads first, this will be
 * overridden by the version in main.js.
 * 
 * Why this exists:
 * - Ensures confirmation dialogs work even if main.js loads late
 * - Provides fallback to native confirm() if Bootstrap is unavailable
 * - Used by cart-api.js for "vider panier" (empty cart) confirmation
 * 
 * @param {string} title - The title shown in the modal header
 * @param {string} message - The message shown in the modal body
 * @param {function} onConfirm - Callback function called when user confirms
 * 
 * @example
 * showConfirmDialog(
 *   'Vider le panier',
 *   'Êtes-vous sûr de vouloir vider votre panier ?',
 *   () => { console.log('Cart emptied'); }
 * );
 */
window.showConfirmDialog = function(title, message, onConfirm) {
    try {
        /**
         * Remove existing modal if present
         * Prevents duplicate modals if function is called multiple times
         */
        var existing = document.getElementById('confirmModal');
        if (existing) { existing.remove(); }
        
        /**
         * Build modal HTML using string concatenation
         * Note: This uses old-style string concatenation instead of template literals
         * for compatibility with older browsers (though template literals would be cleaner)
         * 
         * The modal structure:
         * - Header with title and close button
         * - Body with confirmation message
         * - Footer with Cancel and Confirm buttons
         */
        var html = '' +
            '<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">' +
            '  <div class="modal-dialog modal-dialog-centered">' +
            '    <div class="modal-content">' +
            '      <div class="modal-header">' +
            '        <h5 class="modal-title" id="confirmModalLabel">' + (title || 'Confirmation') + '</h5>' +
            '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '      </div>' +
            '      <div class="modal-body"><p>' + (message || '') + '</p></div>' +
            '      <div class="modal-footer">' +
            '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>' +
            '        <button type="button" class="btn btn-primary" id="confirmModalConfirmBtn">Confirmer</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        
        // Insert modal HTML at the end of body (before closing </body> tag)
        document.body.insertAdjacentHTML('beforeend', html);
        
        // Get references to modal elements
        var modalEl = document.getElementById('confirmModal');
        var confirmBtn = document.getElementById('confirmModalConfirmBtn');
        
        /**
         * Create Bootstrap modal instance
         * Check if Bootstrap is available (window.bootstrap might not exist)
         * If Bootstrap not available, bsModal will be null and we'll use fallback
         */
        var bsModal = window.bootstrap && window.bootstrap.Modal ? new window.bootstrap.Modal(modalEl) : null;
        
        // Show the modal if Bootstrap is available
        if (bsModal) { bsModal.show(); }
        
        /**
         * Handle confirm button click
         * Uses { once: true } so the event listener is automatically removed
         * after first use (prevents memory leaks and duplicate handlers)
         */
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                try { 
                    // Hide modal if Bootstrap is available
                    if (bsModal) bsModal.hide(); 
                } catch (e) {
                    // Silently handle errors (modal might already be hidden)
                }
                // Call the callback function if provided
                if (typeof onConfirm === 'function') { onConfirm(); }
            }, { once: true }); // Remove listener after first click
        }
        
        /**
         * Cleanup when modal is hidden
         * Remove modal from DOM after Bootstrap's hide animation completes
         * This prevents DOM clutter and memory leaks
         */
        modalEl.addEventListener('hidden.bs.modal', function() { 
            modalEl.remove(); 
        });
        
    } catch (e) {
        /**
         * Fallback to native confirm() if Bootstrap/modal not available
         * This ensures the function always works, even if:
         * - Bootstrap JavaScript is not loaded
         * - Modal creation fails
         * - Browser doesn't support modern features
         * 
         * Native confirm() is a browser-built-in dialog that always works
         * (though less pretty than Bootstrap modal)
         */
        if (confirm(message || 'Confirmer ?')) {
            // User clicked OK in native confirm dialog
            if (typeof onConfirm === 'function') { onConfirm(); }
        }
        // If user clicked Cancel, do nothing (onConfirm not called)
    }
};
