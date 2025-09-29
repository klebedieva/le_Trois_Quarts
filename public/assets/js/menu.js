// Menu features for Le Trois Quarts

// State
let currentCategory = 'all';
let searchTerm = '';
let priceFilter = '';
let dietaryFilters = {
    vegetarian: false,
    vegan: false,
    glutenFree: false
};

// DOM elements
let menuGrid;
let noResults;

// Initialize menu features
function initMenu() {
    menuGrid = document.getElementById('menuGrid');
    noResults = document.getElementById('noResults');
    
    if (!menuGrid) return; // Exit if not on the menu page
    
    // Align with gallery: compute --nav-offset for sticky top
    setupNavOffset();
    // Observe sticky state for subtle shadow and ensure parity with Restaurant
    observeStickyState();
    // Enable robust sticky fallback like on Gallery page
    initMenuStickyFallback();

    renderMenu();
    setupMenuEventListeners();
    updateCartDisplay();
    
    // Also update the cart sidebar on init
    if (window.updateCartSidebar) {
        window.updateCartSidebar();
    }

    // Re-render when the cart is changed elsewhere (sidebar/buttons)
    window.addEventListener('cartUpdated', function() {
        renderMenu();
        updateCartDisplay();
    });

    // Re-render when localStorage changes in another tab (safety)
    window.addEventListener('storage', function(e) {
        if (e.key === 'cart') {
            renderMenu();
            updateCartDisplay();
        }
    });
}

// Menu event listeners
function setupMenuEventListeners() {
    // Category filters
    document.querySelectorAll('.filter-category').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-category').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.dataset.category;
            renderMenu();
        });
    });

    // Search
    const menuSearch = document.getElementById('menuSearch');
    if (menuSearch) {
        menuSearch.addEventListener('input', function() {
            searchTerm = this.value.toLowerCase();
            renderMenu();
        });
    }

    // Price filter
    const priceFilterSelect = document.getElementById('priceFilter');
    if (priceFilterSelect) {
        priceFilterSelect.addEventListener('change', function() {
            priceFilter = this.value;
            renderMenu();
        });
    }

    // Dietary filters
    document.querySelectorAll('.dietary-filter').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            dietaryFilters[this.id] = this.checked;
            renderMenu();
        });
    });
}

// Render menu
function renderMenu() {
    if (!menuGrid) return;
    
    const filteredItems = filterItems();
    
    // If 'boissons' (drinks) is selected, show only drinks section
    if (currentCategory === 'boissons') {
        menuGrid.style.display = 'block';
        noResults.style.display = 'none';
        menuGrid.innerHTML = renderDrinksSection();
        return;
    }

    if (filteredItems.length === 0) {
        menuGrid.style.display = 'none';
        noResults.style.display = 'block';
        return;
    }

    menuGrid.style.display = 'block';
    noResults.style.display = 'none';

    // Group items by category
    const groupedItems = groupItemsByCategory(filteredItems);
    let html = '';
    // Render each category
    Object.entries(groupedItems).forEach(([category, items]) => {
        if (items.length > 0) {
            html += renderCategorySection(category, items);
        }
    });
    // Append drinks section when showing all
    if (currentCategory === 'all') {
        html += renderDrinksSection();
    }
    menuGrid.innerHTML = html;
    // Attach listeners to newly rendered elements
    addMenuItemEventListeners();
    
    // Also update cart display after rendering
    updateCartDisplay();
}

// Compute and set CSS var for sticky top so the filters sit below the navbar with small gap
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

// Same approach as gallery page: expose --nav-offset = navbar height + small gap
function setupNavOffset() {
    const navbar = document.getElementById('mainNav');
    const root = document.documentElement;
    const compute = () => {
        const navH = navbar ? navbar.getBoundingClientRect().height : 64;
        const gap = 0; // no gap between navbar and filters
        root.style.setProperty('--nav-offset', (navH + gap) + 'px');
        // keep legacy var too, in case CSS still reads it
        root.style.setProperty('--menu-sticky-top', (navH + gap) + 'px');
    };
    compute();
    window.addEventListener('resize', compute);
    // Also recompute on scroll because navbar height can change with .scrolled class
    window.addEventListener('scroll', compute, { passive: true });
}

// Add a class while sticky for subtle shadow change
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
        } else {
            section.classList.remove('is-sticky');
        }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}

// Sticky fallback for the menu filters: mirrors gallery behavior
function initMenuStickyFallback() {
    const section = document.querySelector('.menu-filters-section');
    if (!section) return;

    // Spacer to avoid layout jump when switching to fixed
    const spacer = document.createElement('div');
    spacer.style.display = 'none';
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

// Filter items
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

// Group items by category
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

// Render a category section
function renderCategorySection(category, items) {
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

    items.forEach(item => {
        html += renderMenuItem(item);
    });

    html += `
            </div>
        </div>
    `;

    return html;
}

// Render a single menu item
function renderMenuItem(item) {
    const quantity = getItemQuantity(item.id);
    const badges = item.badges.map(badge => {
        let badgeClass = '';
        switch (badge) {
            case 'Spécialité':
                badgeClass = 'specialty';
                break;
            case 'Végétarien':
                badgeClass = 'vegetarian';
                break;
            case 'Fait maison':
                badgeClass = 'homemade';
                break;
                case 'Saison':
                    badgeClass = 'seasonal';
                    break;
                case 'Sans Gluten':
                    badgeClass = 'glutenfree';
                    break;
            default:
                badgeClass = '';
        }
        return `<span class="menu-badge ${badgeClass}">${badge}</span>`;
    }).join('');

    const dietaryIcons = item.tags.map(tag => {
        switch (tag) {
            case 'vegetarian':
                return '<span class="dietary-icon">🌱</span>';
            case 'vegan':
                return '<span class="dietary-icon">🌿</span>';
            case 'glutenFree':
                return '<span class="dietary-icon">🌾</span>';
            default:
                return '';
        }
    }).join('');



    return `
        <div class="col-lg-4 col-md-6">
            <div class="menu-card">
                <div class="menu-card-image">
                    <img src="${item.image}" alt="${item.name}">
                    <div class="menu-card-overlay">
                        <a href="/dish/${item.id}" class="quick-view-btn">
                            <i class="bi bi-eye me-2"></i>Voir détails
                        </a>
                    </div>
                    ${badges ? `<div class="menu-card-badges">${badges}</div>` : ''}
                    ${dietaryIcons ? `<div class="dietary-icons">${dietaryIcons}</div>` : ''}
                </div>
                <div class="menu-card-content">
                    <h3 class="menu-card-title">${item.name}</h3>
                    <p class="menu-card-description">${item.description}</p>
                    <div class="menu-card-footer d-flex align-items-center justify-content-between">
                        <div class="menu-card-price">${item.price}€</div>
                        <div class="menu-card-actions">
                            ${quantity > 0 ? `
                                <div class="quantity-controls">
                                    <button class="add-to-cart-btn" onclick="removeFromCart('${item.id}')">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <span class="quantity-display">${quantity}</span>
                                </div>
                            ` : ''}
                            <button class="add-to-cart-btn" onclick="addToCart('${item.id}')">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Render the drinks section
function renderDrinksSection() {
    if (!window.drinksData) return '';
    
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
                            <span class="drink-price">${drink.price}</span>
                        </div>
                    `).join('')}
                </div>
                <div class="drinks-category">
                    <h4><i class="bi bi-cup-hot me-2"></i>Boissons chaudes</h4>
                    ${window.drinksData.chaudes.map(drink => `
                        <div class="drink-item">
                            <span class="drink-name">${drink.name}</span>
                            <span class="drink-price">${drink.price}</span>
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
                            <span class="drink-price">${drink.price}</span>
                        </div>
                    `).join('')}
                </div>
                <div class="drinks-category">
                    <h4><i class="bi bi-droplet me-2"></i>Boissons fraîches</h4>
                    ${window.drinksData.fraiches.map(drink => `
                        <div class="drink-item">
                            <span class="drink-name">${drink.name}</span>
                            <span class="drink-price">${drink.price}</span>
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

// Add event listeners to menu items
function addMenuItemEventListeners() {
    // Quick view buttons - now redirect to the dish detail page
    document.querySelectorAll('.quick-view-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            // Get the dish ID from the href attribute
            const href = this.getAttribute('href');
            if (href && href.startsWith('/dish/')) {
                // Let default link behavior handle navigation
                // No need to prevent default or show an alert
            }
        });
    });
}

// Menu-specific cart functions
function addToCart(itemId) {
    const key = String(itemId);
    const item = window.menuItems.find(i => String(i.id) === key);
    if (item) {
        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const existingItem = cart.find(cartItem => String(cartItem.id) === key);
        if (existingItem) {
            existingItem.quantity++;
        } else {
            cart.push({ ...item, id: key, quantity: 1 });
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartDisplay();
        renderMenu();
        
        // Also update cart navigation from main.js
        if (window.updateCartNavigation) {
            window.updateCartNavigation();
        }
        
        // Keep the cart open when adding from the menu
        window.cartIsActive = true;
        if (window.resetCartActiveState) {
            window.resetCartActiveState();
        }
        
        // Show a notification
        if (window.showNotification) {
            window.showNotification(`${item.name} ajouté au panier`, 'success');
        }
        
        // Dispatch a custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    }
}

function removeFromCart(itemId) {
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const key = String(itemId);
    const index = cart.findIndex(cartItem => String(cartItem.id) === key);
    if (index !== -1) {
        const item = cart[index];
        cart[index].quantity--;
        if (cart[index].quantity <= 0) {
            cart.splice(index, 1);
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartDisplay();
        renderMenu();
        
        // Also update cart navigation from main.js
        if (window.updateCartNavigation) {
            window.updateCartNavigation();
        }
        
        // Keep the cart open when modifying quantities
        window.cartIsActive = true;
        if (window.resetCartActiveState) {
            window.resetCartActiveState();
        }
        
        // Show a notification
        if (window.showNotification) {
            if (cart[index] && cart[index].quantity > 0) {
                window.showNotification('Quantité diminuée', 'success');
            } else {
                window.showNotification(`${item.name} supprimé du panier`, 'info');
            }
        }
        
        // Dispatch a custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    }
}

function getItemQuantity(itemId) {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const targetId = String(itemId);
    const item = cart.find(cartItem => String(cartItem.id) === targetId);
    return item ? item.quantity : 0;
}

function updateCartDisplay() {
    const cartNavCount = document.getElementById('cartNavCount');
    if (cartNavCount) {
        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
        cartNavCount.textContent = totalItems;
        // Always show the counter
        cartNavCount.classList.remove('hidden');
    }
    
    // Also update cart sidebar if it exists
    if (window.updateCartSidebar) {
        window.updateCartSidebar();
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