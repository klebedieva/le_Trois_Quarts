// Cart API functionality for Le Trois Quarts
// New API-based implementation replacing localStorage

class CartAPI {
    constructor() {
        this.baseUrl = '/api/cart';
    // Short-lived cache to avoid duplicate concurrent calls
    this._cartCache = null; // { items, total, itemCount }
    this._cartCacheAt = 0;  // timestamp ms
    this._inflightCart = null; // Promise
    this._CACHE_TTL_MS = 500; // small TTL sufficient for single render cycle
    }

    /**
     * Récupérer le panier depuis le serveur
     */
    async getCart() {
    const now = Date.now();
    // Serve from cache if fresh
    if (this._cartCache && now - this._cartCacheAt < this._CACHE_TTL_MS) {
      return this._cartCache;
    }
    // Reuse in-flight request to collapse bursts
    if (this._inflightCart) {
      return this._inflightCart;
    }
    this._inflightCart = (async () => {
      try {
        const response = await fetch(this.baseUrl, {
          method: 'GET',
          headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) {
          throw new Error('Erreur lors de la récupération du panier');
        }
        const data = await response.json();
        const cart = data.success ? data.cart : { items: [], total: 0, itemCount: 0 };
        // update cache
        this._cartCache = cart;
        this._cartCacheAt = Date.now();
        return cart;
      } catch (error) {
        console.error('Erreur getCart:', error);
        return { items: [], total: 0, itemCount: 0 };
      } finally {
        this._inflightCart = null;
      }
    })();
    return this._inflightCart;
    }

    /**
     * Ajouter un article au panier
     */
    async addItem(itemId, quantity = 1) {
        try {
            const response = await fetch(`${this.baseUrl}/add`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ itemId, quantity })
            });
      // invalidate cache
      this._cartCacheAt = 0;
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Erreur lors de l\'ajout');
            }
            
            return data.cart;
        } catch (error) {
            console.error('Erreur addItem:', error);
            throw error;
        }
    }

    /**
     * Retirer un article du panier
     */
    async removeItem(itemId) {
        try {
            const response = await fetch(`${this.baseUrl}/remove/${itemId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
      // invalidate cache
      this._cartCacheAt = 0;
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Erreur lors de la suppression');
            }
            
            return data.cart;
        } catch (error) {
            console.error('Erreur removeItem:', error);
            throw error;
        }
    }

    /**
     * Mettre à jour la quantité d'un article
     */
    async updateQuantity(itemId, quantity) {
        try {
            const response = await fetch(`${this.baseUrl}/update/${itemId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ quantity })
            });
      // invalidate cache
      this._cartCacheAt = 0;
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Erreur lors de la mise à jour');
            }
            
            return data.cart;
        } catch (error) {
            console.error('Erreur updateQuantity:', error);
            throw error;
        }
    }

    /**
     * Vider le panier
     */
    async clearCart() {
        try {
            const response = await fetch(`${this.baseUrl}/clear`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
      // invalidate cache
      this._cartCacheAt = 0;
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Erreur lors du vidage');
            }
            
            return data.cart;
        } catch (error) {
            console.error('Erreur clearCart:', error);
            throw error;
        }
    }

    /**
     * Obtenir le nombre d'articles
     */
    async getCount() {
    try {
      // derive count from (cached) cart to avoid extra endpoint hit
      const cart = await this.getCart();
      return cart.itemCount || 0;
    } catch (error) {
      console.error('Erreur getCount:', error);
      return 0;
    }
    }
}

// Instance globale
window.cartAPI = new CartAPI();

// Global cart toggle function
window.toggleCart = function() {
    const cartSidebar = document.getElementById('cartSidebar');
    if (cartSidebar) {
        cartSidebar.classList.toggle('open');
        if (cartSidebar.classList.contains('open')) {
            document.body.style.overflow = 'hidden';
            window.cartIsActive = true;
            // Refresh cart when opening
            updateCartSidebar();
        } else {
            document.body.style.overflow = 'auto';
            window.cartIsActive = false;
        }
    }
};

// Reset cart active state after a short delay
window.resetCartActiveState = function() {
    setTimeout(() => {
        window.cartIsActive = false;
    }, 2000);
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
            window.cartIsActive = false;
            toggleCart();
        });
    }

    // Close cart when clicking outside
    document.addEventListener('click', function(e) {
        if (cartSidebar && cartSidebar.classList.contains('open')) {
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
    const clearCartBtn = document.getElementById('clearCart');

    updateCartSidebar();

    // Clear cart button behavior
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', async function() {
            const confirmed = typeof showConfirmDialog === 'function'
                ? await new Promise(resolve => {
                    showConfirmDialog('Confirmation', 'Êtes-vous sûr de vouloir vider votre panier ?', () => resolve(true));
                })
                : confirm('Êtes-vous sûr de vouloir vider votre panier ?');

            if (confirmed) {
                try {
                    await window.cartAPI.clearCart();
                    await updateCartNavigation();
                    await updateCartSidebar();
                    
                    // Trigger global renderMenu if available
                    if (window.renderMenu && typeof window.renderMenu === 'function') {
                        await window.renderMenu();
                    }
                    
                    window.dispatchEvent(new CustomEvent('cartUpdated'));
                    
                    const cartSidebarEl = document.getElementById('cartSidebar');
                    if (cartSidebarEl && cartSidebarEl.classList.contains('open')) {
                        cartSidebarEl.classList.remove('open');
                        document.body.style.overflow = 'auto';
                        window.cartIsActive = false;
                    }
                } catch (error) {
                    console.error('Erreur lors du vidage du panier:', error);
                    alert('Erreur lors du vidage du panier');
                }
            }
        });
    }

    // Order button behavior
    const orderBtn = document.getElementById('orderBtn');
    if (orderBtn) {
        orderBtn.addEventListener('click', async function() {
            const cart = await window.cartAPI.getCart();
            if (cart.items.length > 0) {
                window.location.href = '/order';
            } else {
                if (typeof showNotification === 'function') {
                    showNotification('Votre panier est vide', 'warning');
                } else {
                    alert('Votre panier est vide');
                }
            }
        });
    }
}

async function updateCartSidebar() {
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const clearCartBtn = document.getElementById('clearCart');

    if (!cartItems || !cartTotal) return;

    try {
        const cart = await window.cartAPI.getCart();

        // Update clear-cart button state
        if (clearCartBtn) {
            if (cart.items.length === 0) {
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

        let itemsHTML = '';

        cart.items.forEach(item => {
            const itemTotal = item.price * item.quantity;

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
                        <span class="cart-item-total">${itemTotal.toFixed(2)}€</span>
                    </div>
                </div>
            `;
        });

        cartItems.innerHTML = itemsHTML;
        cartTotal.textContent = cart.total.toFixed(2) + '€';

        // Attach event listeners
        cartItems.querySelectorAll('.cart-qty-btn').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                window.cartIsActive = true;
                if (window.resetCartActiveState) window.resetCartActiveState();
                
                const id = parseInt(this.getAttribute('data-id'));
                const action = this.getAttribute('data-action');
                
                if (action === 'decrease') {
                    await window.removeFromCartSidebar(id);
                } else if (action === 'increase') {
                    await window.addToCartSidebar(id);
                }
            });
        });
    } catch (error) {
        console.error('Erreur updateCartSidebar:', error);
        cartItems.innerHTML = `
            <div class="cart-empty">
                <i class="bi bi-exclamation-triangle"></i>
                <h4>Erreur de chargement</h4>
                <p>Impossible de charger le panier</p>
            </div>
        `;
    }
}

// Global helpers for the sidebar cart
window.removeFromCartSidebar = async function(itemId) {
    try {
        const cart = await window.cartAPI.getCart();
        const item = cart.items.find(i => i.id === itemId);
        
        if (item) {
            if (item.quantity > 1) {
                await window.cartAPI.updateQuantity(itemId, item.quantity - 1);
            } else {
                await window.cartAPI.removeItem(itemId);
            }
            
            await updateCartSidebar();
            await updateCartNavigation();
            
            if (window.updateQuantityDisplay) {
                window.updateQuantityDisplay(itemId);
            }
            
            window.dispatchEvent(new CustomEvent('cartUpdated'));
        }
    } catch (error) {
        console.error('Erreur removeFromCartSidebar:', error);
    }
};

window.addToCartSidebar = async function(itemId) {
    try {
        const cart = await window.cartAPI.getCart();
        const item = cart.items.find(i => i.id === itemId);
        
        if (item) {
            await window.cartAPI.updateQuantity(itemId, item.quantity + 1);
            await updateCartSidebar();
            await updateCartNavigation();
            
            if (window.updateQuantityDisplay) {
                window.updateQuantityDisplay(itemId);
            }
            
            window.dispatchEvent(new CustomEvent('cartUpdated'));
        }
    } catch (error) {
        console.error('Erreur addToCartSidebar:', error);
    }
};

// Add a menu item to the cart (menu page)
window.addMenuItemToCart = async function(itemId) {
    try {
        await window.cartAPI.addItem(itemId, 1);
        await updateCartNavigation();
        await updateCartSidebar();
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    } catch (error) {
        console.error('Erreur addMenuItemToCart:', error);
        alert('Erreur lors de l\'ajout au panier');
    }
};

// Remove a menu item from the cart (menu page)
window.removeMenuItemFromCart = async function(itemId) {
    try {
        await window.cartAPI.removeItem(itemId);
        await updateCartNavigation();
        await updateCartSidebar();
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    } catch (error) {
        console.error('Erreur removeMenuItemFromCart:', error);
    }
};

async function updateCartNavigation() {
    const cartCount = document.getElementById('cartNavCount');
    if (cartCount) {
        try {
            const count = await window.cartAPI.getCount();
            cartCount.textContent = count;
            cartCount.classList.remove('hidden');
        } catch (error) {
            console.error('Erreur updateCartNavigation:', error);
        }
    }
}

// Initialize cart when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    updateCartNavigation();
    initCartNavigation();
});

// Support Turbo/Hotwire navigation if present
window.addEventListener('turbo:load', function() {
    updateCartNavigation();
    initCartNavigation();
});

// Export functions globally for compatibility
window.updateCartNavigation = updateCartNavigation;
window.updateCartSidebar = updateCartSidebar;
window.initCartNavigation = initCartNavigation;
window.initCartSidebar = initCartSidebar;

