// ============================================================================
// CART-API.JS - Cart Management API
// ============================================================================
// This file handles all cart operations using API calls to the server.
// It replaces the old localStorage-based implementation for better reliability.
//
// Features:
// - API-based cart storage (server-side persistence)
// - Short-lived cache to prevent duplicate requests
// - Event-driven updates (dispatches 'cartUpdated' event)
// - Cart sidebar UI management
// - Navigation cart count updates

// ============================================================================
// CART API CLASS
// ============================================================================

/**
 * CartAPI class provides methods to interact with the server-side cart API.
 *
 * This class encapsulates all cart operations and handles:
 * - Caching to prevent duplicate API calls
 * - Request deduplication (multiple simultaneous calls reuse the same request)
 * - Error handling with fallbacks
 *
 * @class CartAPI
 */
class CartAPI {
    /**
     * Initialize the CartAPI instance
     * Sets up base URL and caching mechanism
     */
    constructor() {
        // Base URL for all cart API endpoints
        this.baseUrl = '/api/cart';

        /**
         * Short-lived cache to avoid duplicate concurrent calls
         *
         * Why cache?
         * - Multiple parts of the page may request cart data simultaneously
         * - During a single render cycle, we don't need multiple API calls
         * - Cache prevents race conditions and reduces server load
         *
         * Cache structure: { items: [], total: 0, itemCount: 0 }
         */
        this._cartCache = null; // Cached cart data
        this._cartCacheAt = 0; // Timestamp when cache was created (milliseconds)

        /**
         * In-flight request tracking
         * If multiple parts of code request cart simultaneously, they all
         * wait for the same request instead of making separate API calls
         */
        this._inflightCart = null; // Promise of ongoing request

        /**
         * Cache Time-To-Live (TTL) in milliseconds
         * 500ms is sufficient for a single render cycle
         * After this time, cache is considered stale and will be refreshed
         */
        this._CACHE_TTL_MS = 500;

        /**
         * Default error messages for cart operations
         */
        this._ERROR_MESSAGES = {
            add: "Erreur lors de l'ajout",
            remove: 'Erreur lors de la suppression',
            update: 'Erreur lors de la mise à jour',
            clear: 'Erreur lors du vidage',
        };
    }

    /**
     * Invalidate cart cache
     * Called after any cart modification operation
     */
    _invalidateCache() {
        this._cartCacheAt = 0;
    }

    /**
     * Handle HTTP error response
     * Tries to extract error message from response, falls back to default
     *
     * @param {Response} response - HTTP response object
     * @param {string} defaultMessage - Default error message if parsing fails
     * @returns {Promise<Error>} Error object with message
     */
    async _handleHttpError(response, defaultMessage) {
        let errorMessage = `Erreur ${response.status}: ${defaultMessage}`;
        try {
            const errorData = await response.json();
            errorMessage = errorData.message || errorData.error || errorMessage;
        } catch {
            // If JSON parsing fails, use default message
        }
        return new Error(errorMessage);
    }

    /**
     * Execute cart operation and handle response
     * Common logic for all cart modification operations
     *
     * @param {Response} response - HTTP response object
     * @param {string} operationName - Name of operation (for error messages)
     * @returns {Promise<Object>} Cart object from response
     * @throws {Error} If operation fails
     */
    async _handleCartResponse(response, operationName) {
        if (!response.ok) {
            throw await this._handleHttpError(response, this._ERROR_MESSAGES[operationName]);
        }

        this._invalidateCache();

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || this._ERROR_MESSAGES[operationName]);
        }

        return data.cart;
    }

    /**
     * Retrieve the cart from the server
     *
     * This method:
     * 1. Checks cache first (fast path if cache is fresh)
     * 2. Reuses in-flight request if one exists (prevents duplicate calls)
     * 3. Makes API call if needed
     * 4. Updates cache with fresh data
     *
     * @returns {Promise<Object>} Cart object with structure:
     *   { items: Array, total: number, itemCount: number }
     *
     * @example
     * const cart = await cartAPI.getCart();
     * console.log(cart.items); // Array of cart items
     */
    async getCart() {
        const now = Date.now();

        /**
         * Fast path: Return cached data if it's still fresh
         * Check if cache exists and is less than 500ms old
         */
        if (this._cartCache && now - this._cartCacheAt < this._CACHE_TTL_MS) {
            return this._cartCache;
        }

        /**
         * Request deduplication: Reuse in-flight request if one exists
         * If multiple parts of code call getCart() simultaneously, they
         * all wait for the same request instead of making separate calls
         */
        if (this._inflightCart) {
            return this._inflightCart;
        }

        /**
         * Create new API request
         * This is an async IIFE (Immediately Invoked Function Expression)
         * that makes the API call and handles caching
         */
        this._inflightCart = (async () => {
            try {
                // Make GET request to cart API endpoint
                const response = await fetch(this.baseUrl, {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' },
                });

                // Check if request was successful
                if (!response.ok) {
                    throw new Error('Erreur lors de la récupération du panier');
                }

                // Parse JSON response
                const data = await response.json();

                // Extract cart data (handle both success and error responses)
                const cart = data.success ? data.cart : { items: [], total: 0, itemCount: 0 };

                // Update cache with fresh data
                this._cartCache = cart;
                this._cartCacheAt = Date.now();

                return cart;
            } catch (error) {
                // Log error for debugging
                console.error('Error getting cart:', error);

                // Return empty cart on error (graceful degradation)
                return { items: [], total: 0, itemCount: 0 };
            } finally {
                // Clear in-flight request marker (allow new requests)
                this._inflightCart = null;
            }
        })();

        return this._inflightCart;
    }

    /**
     * Add an item to the cart
     *
     * @param {string|number} itemId - The ID of the menu item to add
     * @param {number} [quantity=1] - Quantity to add (default: 1)
     * @returns {Promise<Object>} Updated cart object
     * @throws {Error} If the API request fails
     *
     * @example
     * await cartAPI.addItem(123, 2); // Add 2 of item 123
     */
    async addItem(itemId, quantity = 1) {
        try {
            const response = await window.apiRequest(`${this.baseUrl}/add`, {
                method: 'POST',
                body: JSON.stringify({ itemId, quantity }),
            });

            return await this._handleCartResponse(response, 'add');
        } catch (error) {
            console.error('Error adding item:', error);
            throw error;
        }
    }

    /**
     * Remove an item from the cart
     *
     * @param {string|number} itemId - The ID of the item to remove
     * @returns {Promise<Object>} Updated cart object
     * @throws {Error} If the API request fails
     *
     * @example
     * await cartAPI.removeItem(123); // Remove item 123
     */
    async removeItem(itemId) {
        try {
            const response = await window.apiRequest(`${this.baseUrl}/remove/${itemId}`, {
                method: 'DELETE',
            });

            return await this._handleCartResponse(response, 'remove');
        } catch (error) {
            console.error('Error removing item:', error);
            throw error;
        }
    }

    /**
     * Update the quantity of an item in the cart
     *
     * @param {string|number} itemId - The ID of the item
     * @param {number} quantity - New quantity (must be > 0)
     * @returns {Promise<Object>} Updated cart object
     * @throws {Error} If the API request fails
     *
     * @example
     * await cartAPI.updateQuantity(123, 5); // Set quantity to 5
     */
    async updateQuantity(itemId, quantity) {
        try {
            const response = await window.apiRequest(`${this.baseUrl}/update/${itemId}`, {
                method: 'PUT',
                body: JSON.stringify({ quantity }),
            });

            return await this._handleCartResponse(response, 'update');
        } catch (error) {
            console.error('Error updating quantity:', error);
            throw error;
        }
    }

    /**
     * Clear the entire cart (remove all items)
     *
     * @returns {Promise<Object>} Empty cart object
     * @throws {Error} If the API request fails
     *
     * @example
     * await cartAPI.clearCart(); // Empty the cart
     */
    async clearCart() {
        try {
            const response = await window.apiRequest(`${this.baseUrl}/clear`, {
                method: 'POST',
            });

            return await this._handleCartResponse(response, 'clear');
        } catch (error) {
            console.error('Error clearing cart:', error);
            throw error;
        }
    }

    /**
     * Get the total number of items in the cart
     *
     * This method derives the count from the cached cart to avoid
     * making an extra API call. It reuses getCart() which uses cache.
     *
     * @returns {Promise<number>} Total item count
     *
     * @example
     * const count = await cartAPI.getCount(); // e.g., 5
     */
    async getCount() {
        try {
            /**
             * Get cart (uses cache if available)
             * Extract itemCount from cart data
             * This avoids making a separate API call just for count
             */
            const cart = await this.getCart();
            // Safety check: ensure cart exists and has itemCount property
            // This prevents errors if API returns unexpected structure
            if (cart && typeof cart.itemCount === 'number') {
                return cart.itemCount;
            }
            // Return 0 if cart is invalid (graceful degradation)
            return 0;
        } catch (error) {
            console.error('Error getting count:', error);
            return 0;
        }
    }
}

// ============================================================================
// GLOBAL INSTANCE
// ============================================================================

/**
 * Create global instance of CartAPI
 * This makes cartAPI available throughout the application via window.cartAPI
 */
window.cartAPI = new CartAPI();

// ============================================================================
// CART UI FUNCTIONS
// ============================================================================

/**
 * Toggle the cart sidebar open/closed
 *
 * This function:
 * - Opens/closes the cart sidebar
 * - Manages body scroll lock when sidebar is open
 * - Refreshes cart when opening
 *
 * @global
 */
window.toggleCart = function () {
    const cartSidebar = document.getElementById('cartSidebar');
    if (cartSidebar) {
        // Toggle 'open' class (CSS handles the animation)
        cartSidebar.classList.toggle('open');

        if (cartSidebar.classList.contains('open')) {
            // Sidebar is now open
            // Update aria attributes for accessibility
            cartSidebar.setAttribute('aria-hidden', 'false');
            // Lock body scroll to prevent background scrolling
            document.body.style.overflow = 'hidden';
            // Set flag to track cart state
            window.cartIsActive = true;
            // Refresh cart display when opening (show latest data)
            updateCartSidebar();
        } else {
            // Sidebar is now closed
            // Update aria attributes for accessibility
            cartSidebar.setAttribute('aria-hidden', 'true');
            // Restore body scroll
            document.body.style.overflow = 'auto';
            // Clear cart active flag
            window.cartIsActive = false;
        }
    }
};

/**
 * Reset cart active state after a delay
 *
 * This is used to prevent accidental cart closing when user is
 * actively interacting with cart controls (buttons, inputs).
 *
 * After 2 seconds of inactivity, the cart can be closed by clicking outside.
 *
 * @global
 */
window.resetCartActiveState = function () {
    setTimeout(() => {
        window.cartIsActive = false;
    }, 2000);
};

// ============================================================================
// CART NAVIGATION INITIALIZATION
// ============================================================================

/**
 * Initialize cart navigation functionality
 *
 * Sets up:
 * - Click handlers for cart link and close button
 * - Click-outside-to-close behavior
 * - Escape key to close
 * - Initial cart count display
 * - Cart sidebar initialization
 */
function initCartNavigation() {
    // Get DOM references
    const cartNavLink = document.getElementById('cartNavLink');
    const cartSidebar = document.getElementById('cartSidebar');
    const closeCart = document.getElementById('closeCart');

    /**
     * Handle cart link click (opens cart sidebar)
     */
    if (cartNavLink) {
        cartNavLink.addEventListener('click', function (e) {
            // Prevent default link behavior
            e.preventDefault();
            // Toggle cart sidebar
            window.toggleCart();
        });
    }

    /**
     * Handle close button click (closes cart sidebar)
     */
    if (closeCart) {
        closeCart.addEventListener('click', function () {
            // Clear active state
            window.cartIsActive = false;
            // Close sidebar
            window.toggleCart();
        });
    }

    /**
     * Close cart when clicking outside
     *
     * This is a common UX pattern - clicking outside a modal/sidebar closes it.
     * However, we need to be careful not to close when clicking on:
     * - Cart controls (quantity buttons, cart actions)
     * - Cart header (title, close button)
     * - Cart navigation link (to open cart)
     */
    document.addEventListener('click', function (e) {
        // Only act if cart is open
        if (cartSidebar && cartSidebar.classList.contains('open')) {
            /**
             * Check if click is on a cart control element
             * These elements should NOT close the cart when clicked
             */
            const isCartControl =
                e.target.closest('.cart-qty-btn') ||
                e.target.closest('.cart-item-controls') ||
                e.target.closest('.cart-actions') ||
                e.target.closest('.cart-header');

            /**
             * Close cart if:
             * - Click is outside the sidebar
             * - Click is NOT on the cart nav link (to open cart)
             * - Click is NOT on cart controls
             */
            if (
                !cartSidebar.contains(e.target) &&
                !(cartNavLink && cartNavLink.contains(e.target)) &&
                !isCartControl
            ) {
                window.toggleCart();
            }
        }
    });

    /**
     * Close cart with Escape key
     * Standard keyboard shortcut for closing modals/sidebars
     */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && cartSidebar && cartSidebar.classList.contains('open')) {
            window.cartIsActive = false;
            window.toggleCart();
        }
    });

    // Update cart count in navbar on initialization
    updateCartNavigation();

    // Initialize cart sidebar (clear button, order button, etc.)
    initCartSidebar();
}

// ============================================================================
// CART SIDEBAR INITIALIZATION
// ============================================================================

/**
 * Initialize cart sidebar functionality
 *
 * Sets up:
 * - Clear cart button with confirmation
 * - Order button (redirects to order page)
 * - Event delegation for quantity buttons
 * - Initial cart display
 */
function initCartSidebar() {
    const clearCartBtn = document.getElementById('clearCart');
    const cartItems = document.getElementById('cartItems');

    /**
     * Set up event delegation for quantity buttons
     *
     * Instead of attaching listeners to each button individually (which would
     * need to be re-attached every time cart updates), we attach one listener
     * to the parent container. This listener handles clicks on all buttons,
     * including dynamically added ones.
     *
     * Benefits:
     * - Only one listener in memory (not one per button)
     * - Works for dynamically added buttons (no need to re-attach)
     * - More efficient and performant
     */
    if (cartItems) {
        cartItems.addEventListener('click', async function (e) {
            // Check if click was on a quantity button
            const btn = e.target.closest('.cart-qty-btn');
            if (!btn) return; // Not a quantity button, ignore

            // Prevent default button behavior
            e.preventDefault();

            // Set cart as active (prevents accidental closing)
            window.cartIsActive = true;
            // Reset active state after delay
            if (window.resetCartActiveState) window.resetCartActiveState();

            // Get item ID and action from data attributes
            const id = parseInt(btn.getAttribute('data-id'));
            const action = btn.getAttribute('data-action');

            // Call appropriate function based on action
            if (action === 'decrease') {
                await window.removeFromCartSidebar(id);
            } else if (action === 'increase') {
                await window.addToCartSidebar(id);
            }
        });
    }

    // Update cart display immediately
    updateCartSidebar();

    /**
     * Clear cart button behavior
     * Shows confirmation dialog before clearing cart
     */
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', async function () {
            /**
             * Show confirmation dialog
             * Uses showConfirmDialog if available, falls back to native confirm()
             *
             * We wrap it in a Promise to use async/await syntax
             */
            const confirmed =
                typeof window.showConfirmDialog === 'function'
                    ? await new Promise(resolve => {
                          window.showConfirmDialog(
                              'Confirmation',
                              'Êtes-vous sûr de vouloir vider votre panier ?',
                              () => resolve(true)
                          );
                      })
                    : window.confirm('Êtes-vous sûr de vouloir vider votre panier ?');

            // Only proceed if user confirmed
            if (confirmed) {
                try {
                    // Clear cart via API
                    await window.cartAPI.clearCart();

                    // Update UI
                    await updateCartUI();

                    // Show success notification
                    if (window.showCartNotification) {
                        window.showCartNotification('Panier vidé avec succès', 'success');
                    }

                    // Trigger menu re-render if available
                    if (window.renderMenu && typeof window.renderMenu === 'function') {
                        await window.renderMenu();
                    }

                    /**
                     * Close cart sidebar after clearing
                     * Cart is empty, no need to keep sidebar open
                     */
                    const cartSidebarEl = document.getElementById('cartSidebar');
                    if (cartSidebarEl && cartSidebarEl.classList.contains('open')) {
                        cartSidebarEl.classList.remove('open');
                        document.body.style.overflow = 'auto';
                        window.cartIsActive = false;
                    }
                } catch (error) {
                    console.error('Error clearing cart:', error);
                    // Show error notification
                    if (window.showCartNotification) {
                        window.showCartNotification('Erreur lors du vidage du panier', 'error');
                    } else {
                        // Fallback to alert if notification system not available
                        window.alert('Erreur lors du vidage du panier');
                    }
                }
            }
        });
    }

    /**
     * Order button behavior
     * Redirects to order page if cart has items
     */
    const orderBtn = document.getElementById('orderBtn');
    if (orderBtn) {
        orderBtn.addEventListener('click', async function () {
            // Get current cart
            const cart = await window.cartAPI.getCart();

            // Only redirect if cart has items
            if (cart.items.length > 0) {
                window.location.href = '/order';
            } else {
                // Show warning if cart is empty
                if (window.showCartNotification) {
                    window.showCartNotification('Votre panier est vide', 'warning');
                } else {
                    window.alert('Votre panier est vide');
                }
            }
        });
    }
}

// ============================================================================
// CART SIDEBAR UPDATE
// ============================================================================

/**
 * Update the cart sidebar display
 *
 * This function:
 * - Fetches latest cart data from API
 * - Renders cart items in the sidebar
 * - Updates total price
 * - Handles empty cart state
 * - Attaches event listeners to quantity buttons
 *
 * @async
 */
async function updateCartSidebar() {
    // Get DOM references
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const clearCartBtn = document.getElementById('clearCart');

    // Exit early if required elements don't exist
    if (!cartItems || !cartTotal) return;

    try {
        // Fetch latest cart data
        const cart = await window.cartAPI.getCart();

        // Safety check: ensure cart exists and has correct structure
        // This prevents "Cannot read properties of undefined" errors
        if (!cart || !cart.items || !Array.isArray(cart.items)) {
            // Cart data is invalid or missing - show error message to user
            cartItems.innerHTML = `
                <div class="cart-empty">
                    <i class="bi bi-exclamation-triangle"></i>
                    <h4>Erreur de chargement</h4>
                    <p>Impossible de charger le panier</p>
                </div>
            `;
            cartTotal.textContent = '0€';
            if (clearCartBtn) {
                clearCartBtn.disabled = true;
            }
            return;
        }

        /**
         * Update clear cart button state
         * Disable button if cart is empty (nothing to clear)
         */
        if (clearCartBtn) {
            const isEmpty = cart.items.length === 0;
            clearCartBtn.disabled = isEmpty;
            clearCartBtn.classList.toggle('disabled', isEmpty);
            clearCartBtn.style.opacity = isEmpty ? '0.5' : '1';
            clearCartBtn.style.cursor = isEmpty ? 'not-allowed' : 'pointer';
        }

        /**
         * Handle empty cart state
         * Show friendly message instead of empty list
         */
        if (cart.items.length === 0) {
            cartItems.innerHTML = `
                <div class="cart-empty">
                    <i class="bi bi-basket"></i>
                    <h4>Votre panier est vide</h4>
                    <p>Ajoutez des plats depuis le menu</p>
                </div>
            `;
            cartTotal.textContent = '0€';
            return;
        }

        /**
         * Build HTML for cart items
         * Loop through items and create HTML structure for each
         */
        let itemsHTML = '';

        cart.items.forEach(item => {
            // Calculate total price for this item (price × quantity)
            const itemTotal = item.price * item.quantity;

            // Build HTML for single cart item
            // Includes aria-attributes for accessibility
            itemsHTML += `
                <div class="cart-item" role="listitem" aria-label="Article: ${item.name}, quantité: ${item.quantity}, prix: ${itemTotal.toFixed(2)}€">
                    <div class="cart-item-header">
                        <h5 class="cart-item-title">${item.name}</h5>
                        <span class="cart-item-price" aria-label="Prix unitaire: ${item.price}€">${item.price}€</span>
                    </div>
                    <div class="cart-item-controls">
                        <div class="cart-item-quantity" role="group" aria-label="Contrôles de quantité pour ${item.name}">
                            <button class="cart-qty-btn" data-action="decrease" data-id="${item.id}" aria-label="Diminuer la quantité de ${item.name}">-</button>
                            <span class="cart-item-total" aria-label="Quantité actuelle: ${item.quantity}">${item.quantity}</span>
                            <button class="cart-qty-btn" data-action="increase" data-id="${item.id}" aria-label="Augmenter la quantité de ${item.name}">+</button>
                        </div>
                        <span class="cart-item-total" aria-label="Prix total pour cet article: ${itemTotal.toFixed(2)}€">${itemTotal.toFixed(2)}€</span>
                    </div>
                </div>
            `;
        });

        // Update sidebar HTML
        cartItems.innerHTML = itemsHTML;
        // Update total price (format with 2 decimal places)
        cartTotal.textContent = cart.total.toFixed(2) + '€';

        /**
         * Note: Event listeners for quantity buttons are handled via
         * event delegation in initCartSidebar(). This means we don't need
         * to attach listeners here - they're already set up on the parent
         * container and will work for all buttons, including dynamically added ones.
         *
         * This is more efficient than attaching listeners to each button individually.
         */
    } catch (error) {
        console.error('Error updating cart sidebar:', error);
        // Show error state in sidebar
        cartItems.innerHTML = `
            <div class="cart-empty">
                <i class="bi bi-exclamation-triangle"></i>
                <h4>Erreur de chargement</h4>
                <p>Impossible de charger le panier</p>
            </div>
        `;
    }
}

// ============================================================================
// CART SIDEBAR HELPER FUNCTIONS
// ============================================================================

/**
 * Common UI update logic after cart operations
 *
 * @param {string|number} itemId - Optional item ID for menu page updates
 * @returns {Promise<void>}
 */
async function updateCartUI(itemId = null) {
    await updateCartSidebar();
    await updateCartNavigation();

    if (itemId && window.updateQuantityDisplay) {
        window.updateQuantityDisplay(itemId);
    }

    window.dispatchEvent(new CustomEvent('cartUpdated'));
}

/**
 * Remove item from cart (via sidebar controls)
 *
 * If quantity > 1, decreases quantity.
 * If quantity = 1, removes item entirely.
 *
 * @param {string|number} itemId - The ID of the item to remove/decrease
 * @global
 */
window.removeFromCartSidebar = async function (itemId) {
    try {
        const cart = await window.cartAPI.getCart();
        const item = cart.items.find(i => i.id === itemId);

        if (!item) return;

        if (item.quantity > 1) {
            await window.cartAPI.updateQuantity(itemId, item.quantity - 1);
            if (window.showCartNotification) {
                window.showCartNotification(`Quantité de ${item.name} diminuée`, 'info');
            }
        } else {
            await window.cartAPI.removeItem(itemId);
            if (window.showCartNotification) {
                window.showCartNotification(`${item.name} supprimé du panier`, 'info');
            }
        }

        await updateCartUI(itemId);
    } catch (error) {
        console.error('Error removing from cart sidebar:', error);
        if (window.showCartNotification) {
            window.showCartNotification('Erreur lors de la modification de la quantité', 'error');
        }
    }
};

/**
 * Add item to cart (via sidebar controls)
 * Increases quantity of existing item in cart.
 *
 * @param {string|number} itemId - The ID of the item to increase
 * @global
 */
window.addToCartSidebar = async function (itemId) {
    try {
        const cart = await window.cartAPI.getCart();
        const item = cart.items.find(i => i.id === itemId);

        if (!item) return;

        await window.cartAPI.updateQuantity(itemId, item.quantity + 1);

        if (window.showCartNotification) {
            window.showCartNotification(`Quantité de ${item.name} augmentée`, 'success');
        }

        await updateCartUI(itemId);
    } catch (error) {
        console.error('Error adding to cart sidebar:', error);
        if (window.showCartNotification) {
            window.showCartNotification('Erreur lors de la modification de la quantité', 'error');
        }
    }
};

// CART NAVIGATION UPDATE
// ============================================================================

/**
 * Update the cart count badge in the navigation bar
 *
 * This function:
 * - Fetches cart item count
 * - Updates the count badge text
 * - Makes the badge visible (removes 'hidden' class)
 *
 * @async
 */
async function updateCartNavigation() {
    const cartCount = document.getElementById('cartNavCount');
    if (cartCount) {
        try {
            // Get cart item count
            const count = await window.cartAPI.getCount();
            // Update badge text
            cartCount.textContent = count;
            // Make badge visible
            cartCount.classList.remove('hidden');
        } catch (error) {
            console.error('Error updating cart navigation:', error);
        }
    }
}

// ============================================================================
// INITIALIZATION
// ============================================================================

/**
 * Initialize cart functionality when DOM is ready
 *
 * This runs on page load and sets up:
 * - Cart count in navigation
 * - Cart navigation handlers
 */
document.addEventListener('DOMContentLoaded', function () {
    // Update cart count immediately
    updateCartNavigation();
    // Initialize cart navigation (click handlers, etc.)
    initCartNavigation();
});

/**
 * Support Turbo/Hotwire navigation if present
 *
 * Turbo (from Hotwire) is a framework for building fast web applications.
 * It intercepts link clicks and loads pages via AJAX, replacing the body.
 *
 * This listener ensures cart functionality works with Turbo navigation.
 * Without this, cart would only work on initial page load.
 */
window.addEventListener('turbo:load', function () {
    updateCartNavigation();
    initCartNavigation();
});

// ============================================================================
// GLOBAL EXPORTS
// ============================================================================

/**
 * Export functions globally for compatibility
 *
 * These functions are used by other scripts throughout the application.
 * Making them globally available ensures they can be called from anywhere.
 */
window.updateCartNavigation = updateCartNavigation;
window.updateCartSidebar = updateCartSidebar;
window.initCartNavigation = initCartNavigation;
window.initCartSidebar = initCartSidebar;
