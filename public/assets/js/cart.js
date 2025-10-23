// Cart functionality for Le Trois Quarts website
// Extracted from main.js for better organization

// Global cart toggle function
window.toggleCart = function() {
    const cartSidebar = document.getElementById('cartSidebar');
    if (cartSidebar) {
        cartSidebar.classList.toggle('open');
        if (cartSidebar.classList.contains('open')) {
            document.body.style.overflow = 'hidden'; // Prevent scrolling when the cart is open
            // Mark cart as active when opened
            window.cartIsActive = true;
        } else {
            document.body.style.overflow = 'auto';
            // Clear cart active state when closed
            window.cartIsActive = false;
        }
    }
};

// Reset cart active state after a short delay
window.resetCartActiveState = function() {
    setTimeout(() => {
        window.cartIsActive = false;
    }, 2000); // Reset after 2 seconds of inactivity
};

// Cart navigation functionality
function initCartNavigation() {
    const cartNavLink = document.getElementById('cartNavLink');
    const cartSidebar = document.getElementById('cartSidebar');
    const closeCart = document.getElementById('closeCart');

    if (cartNavLink) {
        cartNavLink.addEventListener('click', function(e) {
            e.preventDefault();
            toggleCart();
        });
    }

    if (closeCart) {
        closeCart.addEventListener('click', function() {
            // Force close cart and clear active state
            window.cartIsActive = false;
            toggleCart();
        });
    }

    // Close cart when clicking outside (but not if cart was just used for quantity changes)
    document.addEventListener('click', function(e) {
        if (cartSidebar && cartSidebar.classList.contains('open')) {
            // Ignore clicks on cart controls or if the cart was recently active
            const isCartControl = e.target.closest('.cart-qty-btn') || 
                                 e.target.closest('.cart-item-controls') ||
                                 e.target.closest('.cart-actions') ||
                                 e.target.closest('.cart-header');

            if (!cartSidebar.contains(e.target) && !cartNavLink.contains(e.target) && !isCartControl) {
                toggleCart();
            }
        }
    });

    // Close cart with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && cartSidebar && cartSidebar.classList.contains('open')) {
            window.cartIsActive = false;
            toggleCart();
        }
    });

    // Update cart count in the navbar
    updateCartNavigation();

    // Initialize cart sidebar
    initCartSidebar();
}

function initCartSidebar() {
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');

    const clearCartBtn = document.getElementById('clearCart');

    if (cartItems && cartTotal) {
        updateCartSidebar();
    }

    // Clear cart button behavior
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', function() {
            if (typeof showConfirmDialog === 'function') {
                // UI Bootstrap confirm like in Restaurant
                showConfirmDialog('Confirmation', 'Êtes-vous sûr de vouloir vider votre panier ?', function() {
                    localStorage.setItem('cart', JSON.stringify([]));
                    updateCartNavigation();
                    updateCartSidebar();
                    
                    // Show notification for cart clearing
                    if (window.showCartNotification) {
                        window.showCartNotification('Panier vidé avec succès', 'success');
                    }
                    
                    // Inform other modules (menu page) to refresh quantities
                    window.dispatchEvent(new CustomEvent('cartUpdated'));
                    if (typeof window.renderMenu === 'function') {
                        window.renderMenu();
                    }
                    const cartSidebarEl = document.getElementById('cartSidebar');
                    if (cartSidebarEl && cartSidebarEl.classList.contains('open')) {
                        cartSidebarEl.classList.remove('open');
                        document.body.style.overflow = 'auto';
                        window.cartIsActive = false;
                    }
                });
            } else {
                // Fallback: confirm natif
                if (confirm('Êtes-vous sûr de vouloir vider votre panier ?')) {
                    localStorage.setItem('cart', JSON.stringify([]));
                    updateCartNavigation();
                    updateCartSidebar();
                    
                    // Show notification for cart clearing
                    if (window.showCartNotification) {
                        window.showCartNotification('Panier vidé avec succès', 'success');
                    }
                    
                    // Inform other modules (menu page) to refresh quantities
                    window.dispatchEvent(new CustomEvent('cartUpdated'));
                    if (typeof window.renderMenu === 'function') {
                        window.renderMenu();
                    }
                    const cartSidebarEl = document.getElementById('cartSidebar');
                    if (cartSidebarEl && cartSidebarEl.classList.contains('open')) {
                        cartSidebarEl.classList.remove('open');
                        document.body.style.overflow = 'auto';
                        window.cartIsActive = false;
                    }
                }
            }
        });
    }

    // Order button behavior
    const orderBtn = document.getElementById('orderBtn');
    if (orderBtn) {
        orderBtn.addEventListener('click', function() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            if (cart.length > 0) {
                // Redirect to order page
                window.location.href = '/order';
            } else {
                // Show non-blocking notification (aligned with the Restaurant project)
                if (window.showCartNotification) {
                    window.showCartNotification('Votre panier est vide', 'warning');
                } else {
                    alert('Votre panier est vide');
                }
            }
        });
    }
}

function updateCartSidebar() {
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const clearCartBtn = document.getElementById('clearCart');

    if (!cartItems || !cartTotal) return;

    const cart = JSON.parse(localStorage.getItem('cart') || '[]');

    // Update clear-cart button state
    if (clearCartBtn) {
        if (cart.length === 0) {
            clearCartBtn.disabled = true;
            clearCartBtn.classList.add('disabled');
            clearCartBtn.style.opacity = '0.5';
            clearCartBtn.style.cursor = 'not-allowed';
        } else {
            clearCartBtn.disabled = false;
            clearCartBtn.classList.remove('disabled');
            clearCartBtn.style.opacity = '1';
            clearCartBtn.style.cursor = 'pointer';
        }
    }

    if (cart.length === 0) {
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

    let itemsHTML = '';
    let total = 0;

    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;

        itemsHTML += `
            <div class="cart-item">
                <div class="cart-item-header">
                    <h5 class="cart-item-title">${item.name}</h5>
                    <span class="cart-item-price">${item.price}€</span>
                </div>
                <div class="cart-item-controls">
                    <div class="cart-item-quantity">
                        <button class="cart-qty-btn" data-action="decrease" data-id="${item.id}">-</button>
                        <span class="cart-item-total">${item.quantity}</span>
                        <button class="cart-qty-btn" data-action="increase" data-id="${item.id}">+</button>
                    </div>
                    <span class="cart-item-total">${itemTotal}€</span>
                </div>
            </div>
        `;
    });

    cartItems.innerHTML = itemsHTML;
    cartTotal.textContent = total.toFixed(2) + '€';

    // Attach event listeners via delegation for newly rendered buttons
    cartItems.querySelectorAll('.cart-qty-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            // keep cart open while interacting
            if (window.cartIsActive !== undefined) {
                window.cartIsActive = true;
                if (window.resetCartActiveState) { window.resetCartActiveState(); }
            }
            const id = this.getAttribute('data-id');
            const action = this.getAttribute('data-action');
            if (action === 'decrease') {
                window.removeFromCartSidebar(id);
            } else if (action === 'increase') {
                window.addToCartSidebar(id);
            }
        });
    });
}

// Global helpers for the sidebar cart
window.removeFromCartSidebar = function(itemId) {
    // Normalize to string for robust comparison
    const key = String(itemId);
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const index = cart.findIndex(item => String(item.id) === key);

    if (index !== -1) {
        const item = cart[index];
        if (item.quantity > 1) {
            item.quantity -= 1;
        } else {
            cart.splice(index, 1);
        }

        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartSidebar();
        updateCartNavigation();
        
        // Update quantity display on dish detail page
        if (window.updateQuantityDisplay) {
            window.updateQuantityDisplay(key);
        }
        
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    } else {
    }
};

window.addToCartSidebar = function(itemId) {
    // Normalize to string for robust comparison
    const key = String(itemId);
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const index = cart.findIndex(item => String(item.id) === key);

    if (index !== -1) {
        cart[index].quantity += 1;
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartSidebar();
        updateCartNavigation();
        
        // Update quantity display on dish detail page
        if (window.updateQuantityDisplay) {
            window.updateQuantityDisplay(key);
        }
        
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    } else {
    }
};

// Add a menu item to the cart (menu page)
window.addMenuItemToCart = function(itemId, menuItems) {
    const key = String(itemId);
    const item = menuItems.find(item => String(item.id) === key);
    if (!item) return;

    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const existingItem = cart.find(cartItem => String(cartItem.id) === key);

    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: key,
            name: item.name,
            price: item.price,
            quantity: 1
        });
    }

    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartNavigation();
    updateCartSidebar();
    
    // Dispatch custom event for cart updates
    window.dispatchEvent(new CustomEvent('cartUpdated'));
};

// Remove a menu item from the cart (menu page)
window.removeMenuItemFromCart = function(itemId) {
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    cart = cart.filter(item => item.id !== itemId);
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartNavigation();
    updateCartSidebar();
    
    // Dispatch custom event for cart updates
    window.dispatchEvent(new CustomEvent('cartUpdated'));
};

function ensureCartCounterVisible() {
    const el = document.getElementById('cartNavCount');
    if (!el) return;
    el.classList.remove('hidden');
    try {
        new MutationObserver(mutations => {
            for (const m of mutations) {
                if (m.type === 'attributes' && m.attributeName === 'class' && el.classList.contains('hidden')) {
                    el.classList.remove('hidden');
                }
            }
        }).observe(el, { attributes: true });
    } catch (_) {}
}

function updateCartNavigation() {
    const cartCount = document.getElementById('cartNavCount');
    if (cartCount) {
        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
        cartCount.textContent = totalItems;
        cartCount.classList.remove('hidden');
    }
}

// Initialize cart when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    updateCartNavigation();
    ensureCartCounterVisible();
    initCartNavigation();
});

// Support Turbo/Hotwire navigation if present
window.addEventListener('turbo:load', function() {
    updateCartNavigation();
    ensureCartCounterVisible();
    initCartNavigation();
});

// Export functions globally for compatibility
window.updateCartNavigation = updateCartNavigation;
window.updateCartSidebar = updateCartSidebar;
window.initCartNavigation = initCartNavigation;
window.initCartSidebar = initCartSidebar;
