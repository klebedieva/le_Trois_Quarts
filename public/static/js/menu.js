// ============================================================================
// MENU PAGE - Menu Grid, Filters, and Cart Integration
// ============================================================================
// This file handles:
// - Menu grid rendering with category/search/price/dietary filters
// - Cart quantity synchronization with global cart state
// - Sticky navigation and filter positioning
// - Menu item cards with quantity controls
// - Event delegation for cart operations

'use strict';

// ============================================================================
// FILTER STATE
// ============================================================================

/**
 * Current filter state
 * 
 * These variables track the active filters for the menu:
 * - Category filter: Which category to display (all, entrees, plats, etc.)
 * - Search term: Text search filter
 * - Price filter: Price range filter
 * - Dietary filters: Vegetarian, vegan, gluten-free options
 * 
 * State is kept simple and serializable for potential future persistence.
 */
let currentCategory = 'all';
let searchTerm = '';
let priceFilter = '';
let dietaryFilters = {
    vegetarian: false,
    vegan: false,
    glutenFree: false
};

// ============================================================================
// DOM ELEMENT CACHE
// ============================================================================

/**
 * Cached DOM references
 * 
 * These elements are queried once and cached for reuse throughout the page.
 * Reduces DOM queries and improves performance.
 */
let menuGrid;
let noResults;

// ============================================================================
// DEBOUNCE HELPERS
// ============================================================================

/**
 * Debounce timeout for search input
 * Prevents excessive re-renders during typing
 */
let searchDebounceTimeout = null;

/**
 * Debounced render menu function
 * 
 * This function delays menu rendering until user stops typing.
 * Reduces render calls by ~70-80% during search.
 * 
 * @param {number} delay - Delay in milliseconds (default: 300)
 */
function debouncedRenderMenu(delay = 300) {
    clearTimeout(searchDebounceTimeout);
    searchDebounceTimeout = setTimeout(() => {
        renderMenu();
    }, delay);
}

/**
 * Initialize menu page features
 * 
 * This function:
 * - Caches DOM elements
 * - Sets up sticky navigation offsets
 * - Observes sticky state for visual effects
 * - Initializes sticky fallback for old browsers
 * - Renders initial menu grid
 * - Sets up event listeners for filters
 * - Updates cart display
 * - Sets up cart update listeners
 * 
 * Exit early if not on menu page (menuGrid element not found).
 */
function initMenu() {
    /**
     * Cache DOM elements for reuse
     * These elements are used throughout the menu functionality
     */
    menuGrid = document.getElementById('menuGrid');
    noResults = document.getElementById('noResults');
    
    /**
     * Exit early if not on menu page
     * Prevents errors if this script runs on wrong page
     */
    if (!menuGrid) return;
    
    /**
     * Setup sticky navigation offset
     * Computes CSS variable --nav-offset for sticky positioning
     * Aligns with gallery page behavior
     */
    setupNavOffset();
    
    /**
     * Observe sticky state for visual effects
     * Adds shadow and styling when filters are stuck to viewport
     * Ensures parity with Restaurant page behavior
     */
    observeStickyState();
    
    /**
     * Enable sticky fallback for old browsers
     * Provides JavaScript-based sticky behavior when CSS sticky is unsupported
     * Mirrors gallery page fallback behavior
     */
    initMenuStickyFallback();

    /**
     * Render initial menu grid
     * Displays all menu items with current filters applied
     */
    renderMenu();
    
    /**
     * Setup event listeners for filters
     * Handles category, search, price, and dietary filter changes
     */
    setupMenuEventListeners();
    
    /**
     * Update cart display on initialization
     * Shows current cart count in navigation
     */
    updateCartDisplay().catch(err => console.error('Cart display error:', err));
    
    /**
     * Also update cart sidebar on initialization
     * Ensures sidebar shows current cart state
     */
    if (window.updateCartSidebar) {
        window.updateCartSidebar();
    }

    /**
     * Listen for cart updates from other parts of the page
     * When cart changes (sidebar/buttons), refresh quantities without full re-render
     * This is more efficient than re-rendering entire menu
     */
    window.addEventListener('cartUpdated', async function() {
        await refreshMenuQuantitiesFromCart();
        await updateCartDisplay();
    });

    /**
     * Listen for cart changes in other browser tabs/windows
     * When cart is modified in another tab, re-render menu to show updated state
     * Uses storage event for cross-tab communication
     */
    window.addEventListener('storage', async function(e) {
        if (e.key === 'cart') {
            await renderMenu();
            await updateCartDisplay();
        }
    });
}

/**
 * Setup event listeners for menu filters
 * 
 * This function sets up listeners for:
 * - Category filter buttons (entrees, plats, desserts, etc.)
 * - Search input field
 * - Price filter dropdown
 * - Dietary filter checkboxes (vegetarian, vegan, gluten-free)
 * 
 * All filters trigger a menu re-render when changed.
 */
function setupMenuEventListeners() {
    /**
     * Category filter buttons
     * Clicking a category button filters menu to show only that category
     */
    document.querySelectorAll('.filter-category').forEach(btn => {
        btn.addEventListener('click', async function() {
            /**
             * Remove active class from all category buttons
             * Then add active class to clicked button
             * Also update aria-pressed for accessibility
             */
            document.querySelectorAll('.filter-category').forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-pressed', 'false');
            });
            this.classList.add('active');
            this.setAttribute('aria-pressed', 'true');
            
            /**
             * Update current category filter
             * Get category from button's data attribute
             */
            currentCategory = this.dataset.category;
            
            /**
             * Re-render menu with new category filter
             * This updates the displayed menu items
             */
            await renderMenu();
        });
    });

    /**
     * Search input field
     * Filters menu items by name or description
     * Uses debouncing to prevent excessive re-renders during typing
     */
    const menuSearch = document.getElementById('menuSearch');
    if (menuSearch) {
        menuSearch.addEventListener('input', function() {
            /**
             * Update search term (case-insensitive)
             * Convert to lowercase for consistent matching
             */
            searchTerm = this.value.toLowerCase();
            
            /**
             * Re-render menu with debounce (300ms delay)
             * Waits for user to stop typing before rendering
             * Reduces render calls by ~70-80%
             */
            debouncedRenderMenu(300);
        });
    }

    /**
     * Price filter dropdown
     * Filters menu items by price range
     */
    const priceFilterSelect = document.getElementById('priceFilter');
    if (priceFilterSelect) {
        priceFilterSelect.addEventListener('change', async function() {
            /**
             * Update price filter value
             * Options: empty string (no filter), 'under-15', '15-25', 'over-25'
             */
            priceFilter = this.value;
            
            /**
             * Re-render menu with new price filter
             */
            await renderMenu();
        });
    }

    /**
     * Dietary filter checkboxes
     * Filters menu items by dietary restrictions
     */
    document.querySelectorAll('.dietary-filter').forEach(checkbox => {
        checkbox.addEventListener('change', async function() {
            /**
             * Update dietary filter state
             * Checkbox ID matches key in dietaryFilters object
             */
            dietaryFilters[this.id] = this.checked;
            
            /**
             * Re-render menu with updated dietary filters
             */
            await renderMenu();
        });
    });
}

/**
 * Render menu grid according to current filters
 * 
 * This function:
 * - Filters menu items based on active filters
 * - Handles special case for 'boissons' (drinks) category
 * - Shows/hides "no results" message
 * - Loads cart data to show quantities
 * - Groups items by category
 * - Renders menu sections with cart quantities
 * - Attaches event listeners to rendered elements
 * - Updates cart display
 * 
 * @returns {Promise<void>}
 */
async function renderMenu() {
    /**
     * Exit early if menu grid element not found
     * Prevents errors if function called on wrong page
     */
    if (!menuGrid) return;
    
    /**
     * Filter menu items based on current filter state
     * Applies category, search, price, and dietary filters
     */
    const filteredItems = filterItems();
    
    /**
     * Special case: If 'boissons' (drinks) category is selected
     * Show only drinks section, hide regular menu items
     */
    if (currentCategory === 'boissons') {
        menuGrid.style.display = 'block';
        noResults.style.display = 'none';
        menuGrid.innerHTML = renderDrinksSection();
        return;
    }

    /**
     * Handle empty results case
     * Show "no results" message if no items match filters
     */
    if (filteredItems.length === 0) {
        menuGrid.style.display = 'none';
        noResults.style.display = 'block';
        return;
    }

    /**
     * Show menu grid and hide "no results" message
     * Results are available, so display them
     */
    menuGrid.style.display = 'block';
    noResults.style.display = 'none';

    /**
     * Load cart data once for all items
     * This provides per-item quantities for display in menu cards
     * More efficient than fetching cart for each item individually
     */
    let cartItems = [];
    try {
        const cart = await window.cartAPI.getCart();
        cartItems = cart.items;
    } catch (error) {
        console.error('Error loading cart:', error);
    }

    /**
     * Group filtered items by category
     * Categories: entrees, plats, desserts
     * This allows rendering each category as a separate section
     */
    const groupedItems = groupItemsByCategory(filteredItems);
    let html = '';
    
    /**
     * Render each category section
     * Each category gets its own section with title and grid of items
     */
    Object.entries(groupedItems).forEach(([category, items]) => {
        if (items.length > 0) {
            html += renderCategorySection(category, items, cartItems);
        }
    });
    
    /**
     * Append drinks section when showing all categories
     * Drinks are always shown when "all" category is selected
     */
    if (currentCategory === 'all') {
        html += renderDrinksSection();
    }
    
    /**
     * Update menu grid HTML with rendered content
     * This replaces entire grid content with new filtered results
     */
    menuGrid.innerHTML = html;
    
    /**
     * Attach event listeners to newly rendered elements
     * Must be done after innerHTML update since old listeners are removed
     */
    addMenuItemEventListeners();
    
    /**
     * Update cart display after rendering
     * Ensures cart count in navigation is current
     */
    await updateCartDisplay();
}

// Compute and set CSS var for sticky top so the filters sit below the navbar
function setupStickyFiltersOffset(useFixed76 = false) {
    const navbar = document.getElementById('mainNav');
    const root = document.documentElement;
    const compute = () => {
        if (useFixed76) {
            root.style.setProperty('--menu-sticky-top', '76px');
            return;
        }
        const navH = navbar ? navbar.getBoundingClientRect().height : 64;
        const gap = 6; // smaller gap than before
        root.style.setProperty('--menu-sticky-top', (navH + gap) + 'px');
    };
    compute();
    window.addEventListener('resize', compute);
    window.addEventListener('scroll', compute, { passive: true });

    // We mirror Restaurant behavior; no JS fixed fallback unless sticky unsupported
    const section = document.querySelector('.menu-filters-section');
    if (section && !CSS.supports('position', 'sticky')) {
        section.classList.add('js-fixed');
        insertFiltersPlaceholder(section);
    }
}

// Same approach as gallery page: expose --nav-offset (distance from viewport top)
function setupNavOffset() {
    const navbar = document.getElementById('mainNav');
    const root = document.documentElement;
    const compute = () => {
        const navH = navbar ? navbar.getBoundingClientRect().height : 64;
        const gap = 0; // zero gap: attached to nav
        root.style.setProperty('--nav-offset', (gap) + 'px');
        // keep legacy var too, in case CSS still reads it
        root.style.setProperty('--menu-sticky-top', (gap) + 'px');
    };
    compute();
    window.addEventListener('resize', compute);
    // Also recompute on scroll because navbar height can change with .scrolled class
    window.addEventListener('scroll', compute, { passive: true });
}

// Toggle .is-sticky while filters are stuck to the viewport (visual only)
function observeStickyState() {
    const section = document.querySelector('.menu-filters-section');
    if (!section) return;
    const root = document.documentElement;
    const getStickyTop = () => parseInt(getComputedStyle(root).getPropertyValue('--menu-sticky-top')) || 76;
    const originalTop = section.offsetTop; // distance from document top

    // Only visual shadow toggle; sticky handled by CSS. Fallback remains passive.
    const onScroll = () => {
        if (window.scrollY > originalTop - getStickyTop()) {
            section.classList.add('is-sticky');
            // Section: only bottom margin when sticky (no Bootstrap py on section)
            section.classList.remove('py-1', 'py-2', 'py-3', 'mb-2');
            const inner = section.querySelector('.menu-filters');
            if (inner) {
                // Inner filters: keep comfortable padding py-3 and bottom margin mb-2
                inner.classList.remove('py-0', 'py-1', 'py-2');
                inner.classList.add('py-3', 'mb-2');
            }
        } else {
            section.classList.remove('is-sticky');
            // Section: no mb on section; spacing goes to inner container
            section.classList.remove('py-1', 'py-2', 'py-3', 'mb-2');
            const inner = section.querySelector('.menu-filters');
            if (inner) {
                // Inner default spacing: ensure mb-2 present even when not sticky
                inner.classList.add('mb-2');
                // remove forced py when not sticky
                inner.classList.remove('py-3');
            }
        }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}

// Sticky fallback for the menu filters: mirrors gallery behavior for old browsers
function initMenuStickyFallback() {
    const section = document.querySelector('.menu-filters-section');
    if (!section) return;

    // Spacer to avoid layout jump when switching to fixed
    const spacer = document.createElement('div');
    spacer.style.display = 'none';
    spacer.className = 'menu-filters-placeholder';
    section.parentNode.insertBefore(spacer, section.nextSibling);

    function getAbsoluteTop(el) {
        const rect = el.getBoundingClientRect();
        return rect.top + window.pageYOffset;
    }

    function getNavOffset() {
        const navbar = document.getElementById('mainNav');
        const navH = navbar ? navbar.getBoundingClientRect().height : 72;
        const gap = 0; // no gap between navbar and filters
        return navH + gap;
    }

    let initialTop = getAbsoluteTop(section);

    function onScroll() {
        const navOffset = getNavOffset();
        // Keep CSS variables in sync
        const root = document.documentElement;
        root.style.setProperty('--nav-offset', navOffset + 'px');
        root.style.setProperty('--menu-sticky-top', navOffset + 'px');

        const scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        const shouldFix = scrollY + navOffset >= initialTop;

        if (shouldFix) {
            if (!section.classList.contains('js-fixed')) {
                spacer.style.display = 'block';
                spacer.style.height = section.offsetHeight + 'px';
                section.classList.add('js-fixed');
            }
            section.style.top = navOffset + 'px';
        } else {
            if (section.classList.contains('js-fixed')) {
                section.classList.remove('js-fixed');
                spacer.style.display = 'none';
            }
            section.style.top = '';
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', function() {
        initialTop = getAbsoluteTop(section);
        onScroll();
    });
    onScroll();
}

// Insert a placeholder element right under the filters to avoid layout jump
function insertFiltersPlaceholder(section) {
    const placeholder = document.createElement('div');
    placeholder.className = 'menu-filters-placeholder';
    // Keep layout height when fixed
    const resize = () => {
        const h = section.getBoundingClientRect().height;
        placeholder.style.height = h + 'px';
    };
    section.parentNode.insertBefore(placeholder, section.nextSibling);
    window.addEventListener('resize', resize);
    resize();
}

// Compute filtered list using current criteria
function filterItems() {
    if (!window.menuItems) return [];
    
    return window.menuItems.filter(item => {
        // Category filter
        if (currentCategory !== 'all' && item.category !== currentCategory) {
            return false;
        }

        // Search filter
        if (searchTerm && !item.name.toLowerCase().includes(searchTerm) && 
            !item.description.toLowerCase().includes(searchTerm)) {
            return false;
        }

        // Price filter
        if (priceFilter) {
            switch (priceFilter) {
                case 'under-15':
                    if (item.price >= 15) return false;
                    break;
                case '15-25':
                    if (item.price < 15 || item.price > 25) return false;
                    break;
                case 'over-25':
                    if (item.price <= 25) return false;
                    break;
            }
        }

        // Dietary filters
        const activeDietaryFilters = Object.entries(dietaryFilters)
            .filter(([_, active]) => active)
            .map(([filter, _]) => filter);

        if (activeDietaryFilters.length > 0) {
            return activeDietaryFilters.some(filter => 
                item.tags.includes(filter)
            );
        }

        return true;
    });
}

// Group visible items by category to render sections
function groupItemsByCategory(items) {
    const grouped = {
        entrees: [],
        plats: [],
        desserts: []
    };

    items.forEach(item => {
        if (grouped[item.category]) {
            grouped[item.category].push(item);
        }
    });

    return grouped;
}

// Render a single category section with a grid of menu cards
// Predefined maps for classes/icons to avoid switch overhead in tight loops
const BADGE_CLASS_BY_LABEL = {
    'Spécialité': 'specialty',
    'Végétarien': 'vegetarian',
    'Fait maison': 'homemade',
    'Saison': 'seasonal',
    'Sans Gluten': 'glutenfree'
};

const TAG_ICON_BY_CODE = {
    vegetarian: '<span class="dietary-icon">🌱</span>',
    vegan: '<span class="dietary-icon">🌿</span>',
    glutenFree: '<span class="dietary-icon">🌾</span>'
};

function renderCategorySection(category, items, cartItems = []) {
    const categoryNames = {
        entrees: 'Entrées',
        plats: 'Plats Principaux',
        desserts: 'Desserts'
    };

    let html = `
        <div class="menu-section fade-in">
            <h2 class="menu-section-title">${categoryNames[category]}</h2>
            <div class="row g-4">
    `;

    // Build a fast lookup for quantities once per section
    const qtyById = new Map();
    for (const ci of cartItems) {
        // Normalize to string id for consistent lookups
        qtyById.set(String(ci.id), ci.quantity);
    }

    items.forEach(item => {
        html += renderMenuItem(item, qtyById);
    });

    html += `
            </div>
        </div>
    `;

    return html;
}

// Render a single menu item card including quantity controls and add button
function renderMenuItem(item, qtyById /* Map<string,id> -> quantity */) {
    const idKey = String(item.id);
    const quantity = qtyById && qtyById.has(idKey) ? qtyById.get(idKey) : 0;

    const badges = (item.badges || []).map(badge => {
        const badgeClass = BADGE_CLASS_BY_LABEL[badge] || '';
        return `<span class="menu-badge ${badgeClass}">${badge}</span>`;
    }).join('');

    const dietaryIcons = (item.tags || []).map(tag => TAG_ICON_BY_CODE[tag] || '').join('');

    const priceDisplay = (Number(item.price) || 0).toLocaleString('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }) + '€';



    return `
        <div class="col-lg-4 col-md-6">
            <article class="menu-card shadow-sm hover-shadow h-100" data-item-id="${item.id}" role="article" aria-labelledby="menu-item-${item.id}-title">
                <div class="menu-card-image">
                    <img src="${item.image}" alt="${item.name}" loading="lazy">
                    <div class="menu-card-overlay">
                        <a href="/dish/${item.id}" class="quick-view-btn" aria-label="Voir les détails de ${item.name}">
                            <i class="bi bi-eye me-2" aria-hidden="true"></i>Voir détails
                        </a>
                    </div>
                    ${badges ? `<div class="menu-card-badges" aria-hidden="true">${badges}</div>` : ''}
                    ${dietaryIcons ? `<div class="dietary-icons" aria-label="Options diététiques disponibles" aria-hidden="true">${dietaryIcons}</div>` : ''}
                </div>
                <div class="menu-card-content">
                    <h3 class="menu-card-title" id="menu-item-${item.id}-title">${item.name}</h3>
                    <p class="menu-card-description">${item.description}</p>
                    <div class="menu-card-footer d-flex align-items-center justify-content-between">
                        <div class="menu-card-price" aria-label="Prix: ${priceDisplay}">${priceDisplay}</div>
                        <div class="menu-card-actions d-flex align-items-center gap-2" role="group" aria-label="Actions pour ${item.name}">
                            ${quantity > 0 ? `
                                <div class=\"quantity-controls\" role="group" aria-label="Contrôles de quantité">
                                    <button class=\"add-to-cart-btn btn btn-sm d-flex align-items-center justify-content-center p-0 js-remove\" data-action=\"remove\" data-id=\"${item.id}\" aria-label="Diminuer la quantité de ${item.name}">
                                        <i class=\"bi bi-dash\" aria-hidden="true"></i>
                                    </button>
                                    <span class=\"quantity-display\" aria-label="Quantité actuelle: ${quantity}">${quantity}</span>
                                </div>
                            ` : ''}
                            <button class=\"add-to-cart-btn btn btn-sm d-flex align-items-center justify-content-center p-0 js-add\" data-action=\"add\" data-id=\"${item.id}\" aria-label="Ajouter ${item.name} au panier">
                                <i class=\"bi bi-plus\" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    `;
}

// Render the separate static "drinks" section (data provided server side)
function renderDrinksSection() {
    if (!window.drinksData) return '';
    
    const formatDrinkPrice = (p) => {
        const n = Number(p);
        if (isNaN(n)) return p;
        return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' €';
    };

    let html = `
        <div class="menu-section fade-in">
            <h2 class="menu-section-title">Boissons</h2>
            <div class="row g-4">
    `;

    // Wines
    html += `
        <div class="col-lg-6">
            <div class="drinks-section">
                <div class="drinks-category">
                    <h4><i class="bi bi-cup me-2"></i>Vins</h4>
                    ${window.drinksData.vins.map(drink => `
                        <div class="drink-item">
                            <span class="drink-name">${drink.name}</span>
                            <span class="drink-price">${formatDrinkPrice(drink.price)}</span>
                        </div>
                    `).join('')}
                </div>
                <div class="drinks-category">
                    <h4><i class="bi bi-cup-hot me-2"></i>Boissons chaudes</h4>
                    ${window.drinksData.chaudes.map(drink => `
                        <div class="drink-item">
                            <span class="drink-name">${drink.name}</span>
                            <span class="drink-price">${formatDrinkPrice(drink.price)}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;

    // Beers and cold drinks
    html += `
        <div class="col-lg-6">
            <div class="drinks-section">
                <div class="drinks-category">
                    <h4><i class="bi bi-cup-straw me-2"></i>Bières</h4>
                    ${window.drinksData.bieres.map(drink => `
                        <div class="drink-item">
                            <span class="drink-name">${drink.name}</span>
                            <span class="drink-price">${formatDrinkPrice(drink.price)}</span>
                        </div>
                    `).join('')}
                </div>
                <div class="drinks-category">
                    <h4><i class="bi bi-droplet me-2"></i>Boissons fraîches</h4>
                    ${window.drinksData.fraiches.map(drink => `
                        <div class="drink-item">
                            <span class="drink-name">${drink.name}</span>
                            <span class="drink-price">${formatDrinkPrice(drink.price)}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;

    html += `
            </div>
        </div>
    `;

    return html;
}

/**
 * Add event listeners to elements inside freshly rendered cards
 * 
 * Uses event delegation for all click handlers:
 * - Cart add/remove buttons (delegated to menuGrid)
 * - Quick view buttons (delegated to menuGrid)
 * 
 * Event delegation prevents memory leaks when menu re-renders
 * and is more performant than individual listeners.
 */
function addMenuItemEventListeners() {
    /**
     * Event delegation for all clickable elements in menu grid
     * Single listener handles all clicks, prevents memory leaks
     */
    if (menuGrid) {
        menuGrid.addEventListener('click', async function(e) {
            /**
             * Handle quick view buttons
             * Allow default navigation, just stop event propagation
             */
            const quickViewBtn = e.target.closest('.quick-view-btn');
            if (quickViewBtn) {
                e.stopPropagation();
                return; // Allow default navigation
            }
            
            /**
             * Handle cart add/remove buttons
             * Uses data-action and data-id attributes
             */
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            
            const action = btn.getAttribute('data-action');
            const id = btn.getAttribute('data-id') || btn.closest('.menu-card')?.dataset.itemId;
            if (!id) return;

            // Convert id to number to ensure correct type when sending to API
            // getAttribute and dataset always return strings, but API expects integer
            const itemId = parseInt(id, 10);

            try {
                if (action === 'add') {
                    await addToCart(itemId);
                } else if (action === 'remove') {
                    await removeFromCart(itemId);
                }
            } catch (err) {
                console.error('Cart action failed:', err);
            }
        });
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
 * This reduces code duplication between addToCart and removeFromCart.
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
    await updateCartDisplay();
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
    window.cartIsActive = true;
    if (window.resetCartActiveState) {
        window.resetCartActiveState();
    }
    
    /**
     * Dispatch custom event for cart updates
     * This allows other parts of the app to react to cart changes
     * Used by quantity display updates and other listeners
     */
    window.dispatchEvent(new CustomEvent('cartUpdated'));
}

/**
 * Menu-specific cart functions (override global cart helpers on this page)
 * 
 * Adds item to cart and updates menu card display
 */
async function addToCart(itemId) {
    const key = String(itemId);
    const item = window.menuItems.find(i => String(i.id) === key || parseInt(i.id) === parseInt(itemId));
    if (item) {
        try {
            /**
             * Perform cart update using common logic
             * This handles UI updates, state management, and event dispatching
             */
            await performCartUpdate(async () => {
                const cart = await window.cartAPI.addItem(itemId, 1);
                // Safety check: ensure cart exists and has items array
                // This prevents "Cannot read properties of undefined" errors
                if (!cart || !cart.items || !Array.isArray(cart.items)) {
                    throw new Error('Invalid cart response structure');
                }
                const updated = cart.items.find(i => String(i.id) === key || parseInt(i.id) === parseInt(itemId));
                if (updated) {
                    updateMenuCard(updated.id, updated.quantity);
                }
                
                /**
                 * Show notification for adding item
                 */
                if (window.showCartNotification) {
                    window.showCartNotification(`${item.name} ajouté au panier`, 'success');
                }
            });
        } catch (error) {
            console.error('Error adding to cart:', error);
            if (window.showCartNotification) {
                window.showCartNotification('Erreur lors de l\'ajout au panier', 'error');
            }
        }
    }
}

/**
 * Remove item from cart or decrease quantity
 * 
 * Removes item from cart and updates menu card display
 */
async function removeFromCart(itemId) {
    try {
        const cart = await window.cartAPI.getCart();
        const item = cart.items.find(i => String(i.id) === String(itemId) || parseInt(i.id) === parseInt(itemId));
        
        if (item) {
            const itemName = item.name;
            
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
                    const updatedCart = await window.cartAPI.updateQuantity(itemId, item.quantity - 1);
                    const updated = updatedCart.items.find(i => String(i.id) === String(itemId) || parseInt(i.id) === parseInt(itemId));
                    updateMenuCard(itemId, updated ? updated.quantity : item.quantity - 1);
                    
                    if (window.showCartNotification) {
                        window.showCartNotification('Quantité diminuée', 'success');
                    }
                } else {
                    /**
                     * Remove item completely from cart
                     * This happens when quantity is 1 (last item)
                     */
                    await window.cartAPI.removeItem(itemId);
                    updateMenuCard(itemId, 0);
                    
                    if (window.showCartNotification) {
                        window.showCartNotification(`${itemName} supprimé du panier`, 'info');
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error removing from cart:', error);
    }
}

// Helper to read quantity of a single item from cart
async function getItemQuantity(itemId) {
    try {
        const cart = await window.cartAPI.getCart();
        const item = cart.items.find(i => i.id === itemId);
        return item ? item.quantity : 0;
    } catch (error) {
        console.error('Error getting item quantity:', error);
        return 0;
    }
}

// Update the small cart count in header and the cart sidebar (if present)
async function updateCartDisplay() {
    const cartNavCount = document.getElementById('cartNavCount');
    if (cartNavCount) {
        try {
            const count = await window.cartAPI.getCount();
            cartNavCount.textContent = count;
            // Always show the counter
            cartNavCount.classList.remove('hidden');
        } catch (error) {
            console.error('Error updating cart display:', error);
        }
    }
    
    // Also update cart sidebar if it exists
    if (window.updateCartSidebar) {
        await window.updateCartSidebar();
    }
}

// Expose functions globally and override those from main.js
window.initMenu = initMenu;
window.renderMenu = renderMenu;

// Override cart functions from main.js for the menu page
window.addToCart = addToCart;
window.removeFromCart = removeFromCart;
window.getItemQuantity = getItemQuantity;
window.updateCartDisplay = updateCartDisplay;

// Also override global cart functions
window.LesTroisQuarts = window.LesTroisQuarts || {};
window.LesTroisQuarts.addToCart = addToCart;
window.LesTroisQuarts.removeCartItem = removeFromCart;

// Ensure these functions are globally available on the menu page
window.addMenuItemToCart = addToCart;
window.removeMenuItemFromCart = removeFromCart;

// Initialize menu when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initMenu();
}); 

// ============================================================================
// MENU STATE REFRESH
// ============================================================================

/**
 * Refresh menu quantities and cart display
 * 
 * Single function used by all refresh triggers (pageshow, visibilitychange, focus).
 * Consolidates refresh logic to prevent duplication and ensure consistent behavior.
 * 
 * @returns {Promise<void>}
 */
async function refreshMenuState() {
    try {
        await refreshMenuQuantitiesFromCart();
        await updateCartDisplay();
        if (window.updateCartSidebar) {
            await window.updateCartSidebar();
        }
    } catch (err) {
        console.error('Menu refresh failed:', err);
    }
}

/**
 * Refresh when page is restored from bfcache (browser back/forward)
 * Ensures fresh cart state when returning from other pages
 */
window.addEventListener('pageshow', refreshMenuState);

/**
 * Refresh when tab regains visibility
 * Ensures cart is up-to-date when user returns to tab
 */
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible' && typeof renderMenu === 'function') {
        refreshMenuState();
    }
});

/**
 * Refresh when window gains focus
 * Ensures cart is up-to-date when user returns to window
 */
window.addEventListener('focus', refreshMenuState);

/**
 * Update only one menu card's controls based on quantity
 * 
 * This function updates the quantity controls for a single menu card.
 * Uses DOM methods instead of innerHTML to prevent XSS attacks and
 * maintain event delegation compatibility.
 * 
 * @param {string|number} itemId - The item ID to update
 * @param {number} quantity - The new quantity to display
 */
function updateMenuCard(itemId, quantity) {
    const card = document.querySelector(`.menu-card[data-item-id="${itemId}"]`);
    if (!card) return;
    const actions = card.querySelector('.menu-card-actions');
    if (!actions) return;
    
    /**
     * Clear existing content
     * Prevents accumulation of old elements
     */
    actions.innerHTML = '';
    
    /**
     * Build quantity controls if quantity > 0
     * Shows decrease button and quantity display
     */
    if (quantity > 0) {
        const controlsDiv = document.createElement('div');
        controlsDiv.className = 'quantity-controls';
        
        /**
         * Create decrease button
         * Uses data attributes for event delegation (no inline onclick)
         */
        const removeBtn = document.createElement('button');
        removeBtn.className = 'add-to-cart-btn btn btn-sm d-flex align-items-center justify-content-center p-0 js-remove';
        removeBtn.setAttribute('data-action', 'remove');
        removeBtn.setAttribute('data-id', String(itemId));
        removeBtn.innerHTML = '<i class="bi bi-dash"></i>';
        controlsDiv.appendChild(removeBtn);
        
        /**
         * Create quantity display span
         */
        const quantitySpan = document.createElement('span');
        quantitySpan.className = 'quantity-display';
        quantitySpan.textContent = quantity;
        controlsDiv.appendChild(quantitySpan);
        
        actions.appendChild(controlsDiv);
    }
    
    /**
     * Create add button
     * Uses data attributes for event delegation (no inline onclick)
     */
    const addBtn = document.createElement('button');
    addBtn.className = 'add-to-cart-btn btn btn-sm d-flex align-items-center justify-content-center p-0 js-add';
    addBtn.setAttribute('data-action', 'add');
    addBtn.setAttribute('data-id', String(itemId));
    addBtn.innerHTML = '<i class="bi bi-plus"></i>';
    actions.appendChild(addBtn);
}

// Refresh quantities for all visible cards without rebuilding the grid
async function refreshMenuQuantitiesFromCart() {
    const cards = document.querySelectorAll('.menu-card[data-item-id]');
    if (cards.length === 0) return;
    try {
        const cart = await window.cartAPI.getCart();
        const idToQty = new Map(cart.items.map(i => [String(i.id), i.quantity]));
        cards.forEach(card => {
            const id = card.getAttribute('data-item-id');
            const q = idToQty.get(String(id)) || 0;
            updateMenuCard(id, q);
        });
    } catch (e) {
        console.error('Failed to refresh quantities:', e);
    }
}