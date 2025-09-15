// Menu functionality for Le Trois Quarts website

// State
let currentCategory = 'all';
let searchTerm = '';
let priceFilter = '';
let dietaryFilters = {
    vegetarian: false,
    vegan: false,
    glutenFree: false
};

// DOM Elements
let menuGrid;
let noResults;

// Initialize menu functionality
function initMenu() {
    menuGrid = document.getElementById('menuGrid');
    noResults = document.getElementById('noResults');
    
    if (!menuGrid) return; // Exit if not on menu page
    
    renderMenu();
    setupMenuEventListeners();
    updateCartDisplay();
    
    // Also update cart sidebar on initialization
    if (window.updateCartSidebar) {
        window.updateCartSidebar();
    }
}

// Event Listeners for menu
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

// Render Menu
function renderMenu() {
    if (!menuGrid) return;
    
    const filteredItems = filterItems();
    
            // If 'boissons' filter is selected, show only drinks section
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
    // Add drinks section if showing all
    if (currentCategory === 'all') {
        html += renderDrinksSection();
    }
    menuGrid.innerHTML = html;
    // Add event listeners to new elements
    addMenuItemEventListeners();
    
    // Also update cart display after rendering menu
    updateCartDisplay();
}

// Filter Items
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

// Group Items by Category
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

// Render Category Section
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

// Render Menu Item
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
                        <a href="dish-detail.html?id=${item.id}" class="quick-view-btn">
                            <i class="bi bi-eye me-2"></i>Voir détails
                        </a>
                    </div>
                    ${badges ? `<div class="menu-card-badges">${badges}</div>` : ''}
                    ${dietaryIcons ? `<div class="dietary-icons">${dietaryIcons}</div>` : ''}
                </div>
                <div class="menu-card-content">
                    <h3 class="menu-card-title">${item.name}</h3>
                    <p class="menu-card-description">${item.description}</p>
                    <div class="menu-card-footer">
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

// Render Drinks Section
function renderDrinksSection() {
    if (!window.drinksData) return '';
    
    let html = `
        <div class="menu-section fade-in">
            <h2 class="menu-section-title">Boissons</h2>
            <div class="row g-4">
    `;

    // Vins
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

    // Bières et boissons fraîches
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

// Add Menu Item Event Listeners
function addMenuItemEventListeners() {
    // Quick view buttons - now redirect to dish detail page
    document.querySelectorAll('.quick-view-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            // Get the dish ID from the href attribute
            const href = this.getAttribute('href');
            if (href && href.includes('dish-detail.html?id=')) {
                // Let the default link behavior handle the navigation
                // No need to prevent default or show alert
            }
        });
    });
}

// Menu-specific cart functions
function addToCart(itemId) {
    const item = window.menuItems.find(i => i.id === itemId);
    if (item) {
        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const existingItem = cart.find(cartItem => cartItem.id === itemId);
        if (existingItem) {
            existingItem.quantity++;
        } else {
            cart.push({ ...item, quantity: 1 });
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartDisplay();
        renderMenu();
        
        // Also update cart navigation from main.js
        if (window.updateCartNavigation) {
            window.updateCartNavigation();
        }
        
        // Keep cart open when adding items from menu
        window.cartIsActive = true;
        if (window.resetCartActiveState) {
            window.resetCartActiveState();
        }
        
        // Show notification
        if (window.showNotification) {
            window.showNotification(`${item.name} ajouté au panier`, 'success');
        }
        
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    }
}

function removeFromCart(itemId) {
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const index = cart.findIndex(cartItem => cartItem.id === itemId);
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
        
        // Keep cart open when modifying quantities
        window.cartIsActive = true;
        if (window.resetCartActiveState) {
            window.resetCartActiveState();
        }
        
        // Show notification
        if (window.showNotification) {
            if (cart[index] && cart[index].quantity > 0) {
                window.showNotification('Quantité diminuée', 'success');
            } else {
                window.showNotification(`${item.name} supprimé du panier`, 'info');
            }
        }
        
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    }
}

function getItemQuantity(itemId) {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const item = cart.find(cartItem => cartItem.id === itemId);
    return item ? item.quantity : 0;
}

function updateCartDisplay() {
    const cartNavCount = document.getElementById('cartNavCount');
    if (cartNavCount) {
        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
        cartNavCount.textContent = totalItems;
        if (totalItems > 0) {
            cartNavCount.classList.remove('hidden');
        } else {
            cartNavCount.classList.add('hidden');
        }
    }
    
    // Also update cart sidebar if it exists
    if (window.updateCartSidebar) {
        window.updateCartSidebar();
    }
}

// Make functions available globally and override main.js functions
window.initMenu = initMenu;
window.renderMenu = renderMenu;

// Override cart functions from main.js for menu page
window.addToCart = addToCart;
window.removeFromCart = removeFromCart;
window.getItemQuantity = getItemQuantity;
window.updateCartDisplay = updateCartDisplay;

// Also override the global cart functions
window.LesTroisQuarts = window.LesTroisQuarts || {};
window.LesTroisQuarts.addToCart = addToCart;
window.LesTroisQuarts.removeCartItem = removeFromCart;

// Make sure these functions are available globally for menu page
window.addMenuItemToCart = addToCart;
window.removeMenuItemFromCart = removeFromCart; 