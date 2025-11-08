// ============================================================================
// DISH DETAIL PAGE - Cart Functionality and Reviews
// ============================================================================
// This file handles:
// - Quantity controls for adding/removing items from cart
// - Cart update listeners (storage events and custom events)
// - Loading and displaying dish reviews
// - Cart integration with global cart API

// ============================================================================
// DOM ELEMENT CACHE
// ============================================================================

/**
 * Cache for DOM elements used throughout the page
 * 
 * This cache reduces DOM queries by storing elements after first access.
 * Elements are queried once and reused, improving performance.
 * 
 * Cache structure:
 * - decreaseBtn: Decrease quantity button
 * - increaseBtn: Increase quantity button
 * - quantityDisplay: Quantity display element
 * - reviewsList: Container for reviews list
 */
const elementsCache = {};

/**
 * Get cached DOM elements
 * 
 * This function queries DOM elements once and caches them for reuse.
 * Subsequent calls return cached elements, avoiding repeated DOM queries.
 * 
 * @returns {Object} Object with cached DOM elements
 */
function getElements() {
    /**
     * Only query elements if not already cached
     * This ensures we only query DOM once per page load
     */
    if (!elementsCache.decreaseBtn) {
        elementsCache.decreaseBtn = document.getElementById('decreaseQty');
        elementsCache.increaseBtn = document.getElementById('increaseQty');
        elementsCache.quantityDisplay = document.getElementById('quantityDisplay');
        elementsCache.reviewsList = document.getElementById('dishReviewsList');
    }
    return elementsCache;
}

// ============================================================================
// QUANTITY CACHE
// ============================================================================

/**
 * Cache for item quantity to reduce API calls
 * 
 * This cache stores the last fetched quantity and timestamp.
 * Short TTL (Time-To-Live) ensures data stays fresh while reducing
 * redundant API calls during rapid user interactions.
 */
let cachedQuantity = null;
let cachedQuantityAt = 0;
const CACHE_TTL = 1000; // 1 second cache lifetime

/**
 * Invalidate quantity cache
 * 
 * This function clears the cached quantity value.
 * Should be called after cart operations to ensure fresh data.
 */
function invalidateQuantityCache() {
    cachedQuantity = null;
    cachedQuantityAt = 0;
}

/**
 * Initialize dish detail page functionality
 * 
 * This function:
 * - Waits for cart.js to initialize (100ms delay)
 * - Extracts dish ID from URL path (/dish/{id})
 * - Finds dish data in menu/drinks arrays
 * - Initializes quantity controls and cart listeners
 * - Loads dish reviews
 * 
 * Timing:
 * - Small delay ensures cartAPI is available before use
 * - Prevents race conditions with cart initialization
 */
document.addEventListener('DOMContentLoaded', function() {
    /**
     * Wait for cart.js to initialize before using cart API
     * Small delay ensures window.cartAPI is available
     */
    setTimeout(function() {
        /**
         * Extract dish ID from Symfony route pattern: /dish/{id}
         * Uses regex to match numeric ID in URL path
         */
        const match = window.location.pathname.match(/\/dish\/(\d+)/);
        const dishId = match ? match[1] : null;
        
        /**
         * Exit early if no dish ID found in URL
         * This can happen if user navigates to unexpected page
         */
        if (!dishId) {
            return;
        }
        
        /**
         * Find dish data in available data sources
         * Priority: dishData (current page) > menuItems > drinksData
         */
        const dish = findItemById(dishId);
        
        if (dish) {
            /**
             * Initialize all dish detail features
             * - Quantity controls (add/decrease buttons)
             * - Cart update listeners (storage and custom events)
             * - Load and display dish reviews
             */
            initQuantityControls(dish);
            addCartUpdateListener(dish);
            loadDishReviews(dish.id);
        } else {
            /**
             * Fallback: If dish wasn't found by findDishById,
             * check if dishData exists and matches current dish ID
             * This handles edge cases where data structure differs
             */
            if (window.dishData && window.dishData.id == dishId) {
                initQuantityControls(window.dishData);
                addCartUpdateListener(window.dishData);
                loadDishReviews(window.dishData.id);
            }
        }
    }, 100);
});

/**
 * Find item by ID in available data sources (unified function)
 * 
 * This function replaces both findDishById and findItemById.
 * It searches for an item in multiple data sources:
 * 1. window.dishData (current page data - highest priority)
 * 2. window.menuItems (menu items array)
 * 3. window.drinksData (drinks array - fallback)
 * 
 * @param {string|number} itemId - The item ID to search for
 * @returns {Object|null} Item object if found, null otherwise
 * 
 * @example
 * const item = findItemById('123');
 * if (item) {
 *     console.log(item.name);
 * }
 */
function findItemById(itemId) {
    /**
     * Priority 1: Check current page data
     * dishData is typically provided by the server for the current page
     * This is the fastest and most reliable source
     */
    if (window.dishData && window.dishData.id == itemId) {
        return window.dishData;
    }
    
    /**
     * Priority 2: Search in menu items array
     * This is used when item data needs to be found from global menu data
     */
    if (window.menuItems) {
        return window.menuItems.find(item => item.id == itemId);
    }
    
    /**
     * Priority 3: Fallback to drinks data
     * Some items might be categorized as drinks
     */
    if (window.drinksData) {
        return window.drinksData.find(item => item.id == itemId);
    }
    
    return null;
}

/**
 * Initialize quantity controls for dish detail page
 * 
 * This function:
 * - Sets up decrease and increase quantity buttons
 * - Initializes quantity display with current cart quantity
 * - Shows all controls (buttons and display)
 * - Handles click events for adding/removing items
 * 
 * @param {Object} dish - The dish object with id and name properties
 * 
 * Button behavior:
 * - Decrease button: Removes item from cart, shows notification
 * - Increase button: Adds item to cart, shows notification
 * - Both buttons update quantity display after operation
 */
function initQuantityControls(dish) {
    /**
     * Get cached DOM elements for quantity controls
     * Uses cached elements to avoid repeated DOM queries
     */
    const elements = getElements();
    const decreaseBtn = elements.decreaseBtn;
    const increaseBtn = elements.increaseBtn;
    const quantityDisplay = elements.quantityDisplay;
    
    /**
     * Exit if required elements are missing
     * This prevents errors if page structure is different
     */
    if (decreaseBtn && increaseBtn && quantityDisplay) {
        /**
         * Initialize quantity display with current cart quantity
         * This ensures the display shows the correct value on page load
         */
        updateQuantityDisplay(dish.id);
        
        /**
         * Show all quantity controls
         * Ensures buttons and display are visible
         */
        showAllControls();
        
        /**
         * Decrease quantity button handler
         * Removes item from cart when clicked
         */
        decreaseBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            
            /**
             * Only process if button is not disabled
             * Prevents action when quantity is already 0
             */
            if (!this.disabled) {
                /**
                 * Get current quantity before removing
                 * Used to determine notification message
                 */
                const currentQty = await getItemQuantity(dish.id);
                
                /**
                 * Only remove if quantity is greater than 0
                 * Safety check to prevent negative quantities
                 */
                if (currentQty > 0) {
                    /**
                     * Remove item from cart and update display
                     * Both operations are async, so we await them
                     */
                    await removeFromCartDetail(dish.id);
                    await updateQuantityDisplay(dish.id);
                    
                    /**
                     * Show appropriate notification based on quantity
                     * - If quantity was 1: Item removed from cart
                     * - If quantity > 1: Quantity decreased
                     */
                    if (window.showCartNotification) {
                        if (currentQty === 1) {
                            window.showCartNotification(`${dish.name} supprimé du panier`, 'info');
                        } else {
                            window.showCartNotification('Quantité diminuée', 'success');
                        }
                    }
                }
            }
        });
        
        /**
         * Increase quantity button handler
         * Adds item to cart when clicked
         */
        increaseBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            
            /**
             * Get current quantity before adding
             * Used for notification logic in addToCartDetail
             */
            const currentQty = await getItemQuantity(dish.id);
            
            /**
             * Add item to cart and update display
             * Notification is handled inside addToCartDetail function
             */
            await addToCartDetail(dish.id);
            await updateQuantityDisplay(dish.id);
        });
    }
}

/**
 * Show all quantity controls on the page
 * 
 * This function makes the decrease button and quantity display visible.
 * Used to ensure controls are always shown on dish detail page.
 * 
 * Controls shown:
 * - Decrease button (display: flex)
 * - Quantity display (display: block)
 */
function showAllControls() {
    /**
     * Get cached DOM elements for controls
     * Uses cached elements to avoid repeated DOM queries
     */
    const elements = getElements();
    const decreaseBtn = elements.decreaseBtn;
    const quantityDisplay = elements.quantityDisplay;
    
    /**
     * Show controls if they exist
     * Uses inline styles to override any CSS that might hide them
     */
    if (decreaseBtn && quantityDisplay) {
        decreaseBtn.style.display = 'flex';
        quantityDisplay.style.display = 'block';
    }
}

/**
 * Update quantity display and button state
 * 
 * This function:
 * - Fetches current quantity from cart
 * - Updates quantity display text
 * - Enables/disables decrease button based on quantity
 * - Updates button visual state (opacity, cursor)
 * 
 * @param {string|number} itemId - The item ID to update quantity for
 * 
 * Button states:
 * - Quantity > 0: Button enabled, full opacity, pointer cursor
 * - Quantity = 0: Button disabled, reduced opacity, not-allowed cursor
 */
async function updateQuantityDisplay(itemId) {
    /**
     * Get cached DOM elements for quantity display and decrease button
     * Uses cached elements to avoid repeated DOM queries
     */
    const elements = getElements();
    const quantityDisplay = elements.quantityDisplay;
    const decreaseBtn = elements.decreaseBtn;
    
    /**
     * Update quantity display if element exists
     * Prevents errors if element is missing
     */
    if (quantityDisplay) {
        /**
         * Fetch current quantity from cart
         * This is an async operation, so we await it
         */
        const quantity = await getItemQuantity(itemId);
        
        /**
         * Update display text with current quantity
         * Shows user how many items are in cart
         */
        quantityDisplay.textContent = quantity;
        
        /**
         * Update decrease button state based on quantity
         * Button should be disabled when quantity is 0
         */
        if (decreaseBtn) {
            if (quantity > 0) {
                /**
                 * Enable button when quantity > 0
                 * Full opacity and pointer cursor indicate clickability
                 */
                decreaseBtn.disabled = false;
                decreaseBtn.style.opacity = '1';
                decreaseBtn.style.cursor = 'pointer';
            } else {
                /**
                 * Disable button when quantity is 0
                 * Reduced opacity and not-allowed cursor indicate disabled state
                 */
                decreaseBtn.disabled = true;
                decreaseBtn.style.opacity = '0.5';
                decreaseBtn.style.cursor = 'not-allowed';
            }
        }
    }
}

/**
 * Make updateQuantityDisplay globally available
 * This allows other scripts to update the quantity display
 * Used by cart update listeners and external cart operations
 */
window.updateQuantityDisplay = updateQuantityDisplay;

/**
 * Get current quantity of an item in the cart (with caching)
 * 
 * This function:
 * - Checks cache first (if fresh, returns cached value)
 * - Fetches cart data from API if cache is stale
 * - Searches for item by ID
 * - Returns quantity if found, 0 otherwise
 * - Updates cache with fresh data
 * 
 * @param {string|number} itemId - The item ID to get quantity for
 * @param {boolean} useCache - Whether to use cached value (default: true)
 * @returns {Promise<number>} Quantity of item in cart (0 if not found)
 * 
 * Error handling:
 * - Returns 0 on any error (safe fallback)
 * - Logs error to console for debugging
 * 
 * Cache:
 * - Cache TTL: 1 second (1000ms)
 * - Cache invalidated after cart operations
 */
async function getItemQuantity(itemId, useCache = true) {
    const now = Date.now();
    
    /**
     * Check cache first if enabled
     * Return cached value if it's still fresh (less than 1 second old)
     */
    if (useCache && cachedQuantity !== null && (now - cachedQuantityAt) < CACHE_TTL) {
        return cachedQuantity;
    }
    
    try {
        /**
         * Fetch cart data from API
         * cartAPI.getCart() returns object with items array
         */
        const cart = await window.cartAPI.getCart();
        
        /**
         * Find item in cart by ID
         * Uses strict equality (===) for comparison
         * Item structure: { id, name, price, quantity, ... }
         */
        const item = cart.items.find(i => i.id === itemId);
        
        /**
         * Get quantity (0 if item not found)
         * Safe fallback ensures function always returns a number
         */
        const quantity = item ? item.quantity : 0;
        
        /**
         * Update cache with fresh data
         * Cache timestamp is updated for next check
         */
        cachedQuantity = quantity;
        cachedQuantityAt = now;
        
        return quantity;
    } catch (error) {
        /**
         * Handle errors gracefully
         * Return 0 instead of throwing to prevent UI breakage
         */
        console.error('Error getting item quantity:', error);
        return 0;
    }
}

// ============================================================================
// CART UPDATE LOGIC
// ============================================================================

/**
 * Common cart update logic
 * 
 * This function handles shared operations after cart modifications:
 * - Updates cart navigation and sidebar UI
 * - Keeps cart sidebar open for user convenience
 * - Dispatches cart update event
 * 
 * This reduces code duplication between addToCartDetail and removeFromCartDetail.
 * 
 * @param {Function} cartOperation - Async function that performs the cart operation
 */
async function performCartUpdate(cartOperation) {
    /**
     * Execute the cart operation callback
     * This is the specific action (add, remove, update)
     */
    await cartOperation();
    
    /**
     * Update cart UI components
     * These functions refresh the cart display in navigation and sidebar
     */
    if (window.updateCartNavigation) {
        await window.updateCartNavigation();
    }
    if (window.updateCartSidebar) {
        await window.updateCartSidebar();
    }
    
    /**
     * Keep cart sidebar open when modifying quantities
     * This provides better UX - user can see changes immediately
     * resetCartActiveState prevents accidental closing
     */
    if (window.cartIsActive !== undefined) {
        window.cartIsActive = true;
        if (window.resetCartActiveState) {
            window.resetCartActiveState();
        }
    }
    
    /**
     * Dispatch custom event for cart updates
     * This allows other parts of the app to react to cart changes
     * Used by quantity display updates and other listeners
     */
    window.dispatchEvent(new CustomEvent('cartUpdated'));
    
    /**
     * Invalidate quantity cache after cart operation
     * Ensures fresh data on next quantity lookup
     */
    invalidateQuantityCache();
}

/**
 * Add item to cart from dish detail page
 * 
 * This function:
 * - Adds item to cart via API
 * - Updates cart navigation and sidebar UI
 * - Keeps cart open for user convenience
 * - Shows appropriate notification (first add vs. quantity increase)
 * - Dispatches cart update event
 * 
 * @param {string|number} itemId - The item ID to add to cart
 * 
 * Notification logic:
 * - First time adding: "Item name ajouté au panier"
 * - Increasing quantity: "Quantité augmentée"
 */
async function addToCartDetail(itemId) {
    try {
        /**
         * Perform cart update using common logic
         * This handles UI updates, state management, and event dispatching
         */
        await performCartUpdate(async () => {
            /**
             * Add item to cart via API
             * Adds quantity of 1 to the item
             */
            await window.cartAPI.addItem(itemId, 1);
            
            /**
             * Show notification based on whether this is first add or quantity increase
             * Different messages for better user feedback
             */
            if (window.showCartNotification) {
                /**
                 * Find item data to get name for notification
                 * Uses findItemById to search in menu/drinks data
                 */
                const item = findItemById(itemId);
                if (item) {
                    /**
                     * Check if this is the first time adding this item
                     * If quantity is 1 after adding, it's the first time
                     * Otherwise, it's a quantity increase
                     */
                    const cart = await window.cartAPI.getCart();
                    const cartItem = cart.items.find(i => i.id === itemId);
                    
                    if (cartItem && cartItem.quantity === 1) {
                        /**
                         * First time adding item
                         * Show message with item name
                         */
                        window.showCartNotification(`${item.name} ajouté au panier`, 'success');
                    } else {
                        /**
                         * Increasing quantity of existing item
                         * Show generic quantity increase message
                         */
                        window.showCartNotification('Quantité augmentée', 'success');
                    }
                }
            }
        });
    } catch (error) {
        /**
         * Handle errors gracefully
         * Log error but don't break UI
         */
        console.error('Error adding to cart:', error);
    }
}

/**
 * Remove item from cart or decrease quantity
 * 
 * This function:
 * - Checks current quantity in cart
 * - Decreases quantity if > 1, removes item if quantity is 1
 * - Updates cart navigation and sidebar UI
 * - Keeps cart open for user convenience
 * - Shows appropriate notification
 * - Dispatches cart update event
 * 
 * @param {string|number} itemId - The item ID to remove/decrease
 * 
 * Behavior:
 * - Quantity > 1: Decrease quantity by 1
 * - Quantity = 1: Remove item completely
 */
async function removeFromCartDetail(itemId) {
    try {
        /**
         * Get current cart state
         * Need to check quantity before deciding action
         */
        const cart = await window.cartAPI.getCart();
        const item = cart.items.find(i => i.id === itemId);
        
        /**
         * Only proceed if item exists in cart
         * Prevents errors if item was already removed
         */
        if (item) {
            /**
             * Perform cart update using common logic
             * This handles UI updates, state management, and event dispatching
             */
            await performCartUpdate(async () => {
                if (item.quantity > 1) {
                    /**
                     * Decrease quantity by 1
                     * Item remains in cart with reduced quantity
                     */
                    await window.cartAPI.updateQuantity(itemId, item.quantity - 1);
                    
                    /**
                     * Show notification for quantity decrease
                     * Success type indicates positive action
                     */
                    if (window.showCartNotification) { 
                        window.showCartNotification('Quantité diminuée', 'success'); 
                    }
                } else {
                    /**
                     * Remove item completely from cart
                     * This happens when quantity is 1 (last item)
                     */
                    await window.cartAPI.removeItem(itemId);
                    
                    /**
                     * Show notification for item removal
                     * Info type indicates item was removed
                     */
                    if (window.showCartNotification) { 
                        window.showCartNotification(`${item.name} supprimé du panier`, 'info'); 
                    }
                }
            });
        }
    } catch (error) {
        /**
         * Handle errors gracefully
         * Log error but don't break UI
         */
        console.error('Error removing from cart:', error);
    }
}


/**
 * Add event listeners for cart updates
 * 
 * This function sets up listeners to detect when cart changes:
 * - storage event: When cart is updated from other browser tabs/windows
 * - cartUpdated event: When cart is updated in current tab/window
 * 
 * When cart updates are detected, the quantity display is refreshed
 * to show the current quantity in cart.
 * 
 * @param {Object} dish - The dish object with id property
 * 
 * Note:
 * - Polling was removed to avoid excessive API calls
 * - Updates now rely on events only (more efficient)
 */
function addCartUpdateListener(dish) {
    /**
     * Listen for storage changes (cross-tab/window updates)
     * This fires when localStorage is modified in another tab/window
     * Only updates if the changed key is 'cart'
     */
    window.addEventListener('storage', function(e) {
        if (e.key === 'cart') {
            /**
             * Update quantity display when cart changes in another tab
             * This keeps the display in sync across browser tabs
             */
            updateQuantityDisplay(dish.id);
        }
    });
    
    /**
     * Listen for custom cart update events (same-tab updates)
     * This fires when cart is updated in the current tab/window
     * Dispatched by cart operations (add, remove, update)
     */
    window.addEventListener('cartUpdated', function() {
        /**
         * Update quantity display when cart changes in current tab
         * This keeps the display in sync with cart operations
         */
        updateQuantityDisplay(dish.id);
    });
}

// ============================================================================
// DISH REVIEWS
// ============================================================================

/**
 * Load and display approved reviews for a dish
 * 
 * This function:
 * - Fetches reviews from server API endpoint
 * - Shows loading state while fetching
 * - Renders reviews with name, rating, comment, and date
 * - Handles empty state (no reviews)
 * - Handles error state (API failure)
 * 
 * @param {string|number} dishId - The dish ID to load reviews for
 * 
 * API endpoint: /dish/{dishId}/reviews
 * Response format: { success: boolean, reviews: Array }
 */
function loadDishReviews(dishId) {
    /**
     * Get cached container element for reviews list
     * Uses cached element to avoid repeated DOM queries
     */
    const elements = getElements();
    const list = elements.reviewsList;
    if (!list) return;
    
    /**
     * Show loading state while fetching reviews
     * Provides user feedback that data is being loaded
     */
    list.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split me-2"></i>Chargement…</div>';

    /**
     * Fetch reviews from server
     * X-Requested-With header indicates AJAX request
     */
    fetch(`/dish/${dishId}/reviews`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
        .then(r => r.json())
        .then(data => {
            /**
             * Check if API response indicates success
             * Throw error if response format is unexpected
             */
            if (!data.success) throw new Error();
            
            /**
             * Handle empty reviews case
             * Show message if no reviews exist for this dish
             */
            if (!data.reviews || data.reviews.length === 0) {
                list.innerHTML = '<div class="text-muted">Aucun avis pour ce plat pour le moment.</div>';
                return;
            }
            
            /**
             * Render reviews list
             * Each review shows: name, star rating, comment, date
             * Uses escapeHtml to prevent XSS attacks
             */
            list.innerHTML = data.reviews.map(r => `
                <div class="review-item">
                  <div class="review-header">
                    <strong>${escapeHtml(r.name)}</strong>
                    <div class="review-stars">${renderStars(r.rating)}</div>
                  </div>
                  <p>${escapeHtml(r.comment)}</p>
                  <small class="text-muted">${escapeHtml(r.createdAt)}</small>
                </div>
            `).join('');
        })
        .catch(() => {
            /**
             * Handle errors gracefully
             * Show error message if API call fails
             */
            list.innerHTML = '<div class="text-danger">Erreur de chargement des avis.</div>';
        });
}

/**
 * Listen for review submission events
 * 
 * When a new review is submitted (from reviews.js modal),
 * refresh the reviews list to show the new review.
 * 
 * Note: New reviews may not appear immediately if they require moderation.
 */
document.addEventListener('review:submitted', function () {
    /**
     * Extract dish ID from current URL
     * Only refresh if we're on a dish detail page
     */
    const match = window.location.pathname.match(/\/dish\/(\d+)/);
    const dishId = match ? match[1] : null;
    
    /**
     * Reload reviews if dish ID is found
     * This ensures new review appears (if approved) or list refreshes
     */
    if (dishId) {
        loadDishReviews(dishId);
    }
});

/**
 * Render star rating HTML (optimized)
 * 
 * This function creates HTML for a 5-star rating display:
 * - Filled stars for rating value
 * - Empty stars for remaining stars
 * 
 * Uses Array.from for consistency with reviews.js and better readability.
 * 
 * @param {number|string} n - Rating value (0-5)
 * @returns {string} HTML string with star icons
 * 
 * Example:
 * renderStars(3) returns 3 filled stars + 2 empty stars
 */
function renderStars(n) {
    /**
     * Clamp rating value to valid range (0-5)
     * Prevents invalid ratings from breaking display
     */
    n = Math.max(0, Math.min(5, parseInt(n, 10) || 0));
    
    /**
     * Generate star HTML using Array.from
     * Creates array of 5 elements, each element is a star icon
     * - Filled stars for ratings <= star number
     * - Empty stars for ratings > star number
     * 
     * This approach is more readable and consistent with reviews.js
     */
    return Array.from({ length: 5 }, (_, i) => {
        const starNumber = i + 1;
        const isFilled = starNumber <= n;
        return `<i class="bi ${isFilled ? 'bi-star-fill' : 'bi-star'} text-warning"></i>`;
    }).join('');
}

/**
 * Escape HTML special characters to prevent XSS attacks
 * 
 * This function replaces dangerous characters with HTML entities:
 * - & becomes &amp;
 * - < becomes &lt;
 * - > becomes &gt;
 * - " becomes &quot;
 * - ' becomes &#39;
 * 
 * @param {string} s - String to escape
 * @returns {string} Escaped string safe for HTML insertion
 * 
 * Security:
 * - Prevents Cross-Site Scripting (XSS) attacks
 * - Always use this when inserting user-generated content
 */
function escapeHtml(s) {
    /**
     * Return empty string if input is null/undefined
     * Prevents errors when processing null values
     */
    return (s || '').replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[m]));
}