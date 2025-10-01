// Dish Detail Page JavaScript - Cart functionality only
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit for cart.js to initialize
    setTimeout(function() {
        // Get dish ID from Symfony route /dish/{id}
        const match = window.location.pathname.match(/\/dish\/(\d+)/);
        const dishId = match ? match[1] : null;
        
        console.log('Dish Detail Page Loaded');
        console.log('Dish ID:', dishId);
        console.log('Dish Data:', window.dishData);
        console.log('Menu Items:', window.menuItems);
        console.log('Cart functions available:', {
            toggleCart: typeof window.toggleCart,
            updateCartNavigation: typeof window.updateCartNavigation,
            updateCartSidebar: typeof window.updateCartSidebar
        });
        
        // Check if cart elements exist
        const cartNavLink = document.getElementById('cartNavLink');
        const cartSidebar = document.getElementById('cartSidebar');
        console.log('Cart elements found:', {
            cartNavLink: !!cartNavLink,
            cartSidebar: !!cartSidebar
        });
        
        // Cart should be initialized by cart.js automatically
        console.log('Cart initialization status:', {
            toggleCart: typeof window.toggleCart,
            initCartNavigation: typeof window.initCartNavigation,
            initCartSidebar: typeof window.initCartSidebar
        });
    
        if (!dishId) {
            console.log('No dish ID found, exiting');
            return; // Don't redirect, just exit
        }
        
        // Find dish in menu data (optional - for cart functionality)
        const dish = findDishById(dishId);
        console.log('Found dish:', dish);
        
        if (dish) {
            console.log('Initializing quantity controls for dish:', dish.name);
            // Initialize quantity controls if dish found in JS data
            initQuantityControls(dish);
            // Add event listener for cart updates
            addCartUpdateListener(dish);
            // Load dish reviews
            loadDishReviews(dish.id);
        } else {
            console.log('Dish not found in data, trying to initialize with dishData');
            // If we have dishData but it wasn't found by findDishById, use it directly
            if (window.dishData && window.dishData.id == dishId) {
                console.log('Using dishData directly:', window.dishData);
                initQuantityControls(window.dishData);
                addCartUpdateListener(window.dishData);
                loadDishReviews(window.dishData.id);
            }
        }
    }, 100); // Wait 100ms for cart.js to initialize
});

function findDishById(dishId) {
    // First check if we have dishData from the current page
    if (window.dishData && window.dishData.id == dishId) {
        return window.dishData;
    }
    
    // Search in menuItems array
    if (window.menuItems) {
        return window.menuItems.find(item => item.id == dishId);
    }
    
    // Fallback: search in drinks data
    if (window.drinksData) {
        return window.drinksData.find(item => item.id == dishId);
    }
    
    return null;
}

function initQuantityControls(dish) {
    console.log('Initializing quantity controls for dish:', dish);
    const decreaseBtn = document.getElementById('decreaseQty');
    const increaseBtn = document.getElementById('increaseQty');
    const quantityDisplay = document.getElementById('quantityDisplay');
    
    console.log('Found elements:', {
        decreaseBtn: !!decreaseBtn,
        increaseBtn: !!increaseBtn,
        quantityDisplay: !!quantityDisplay
    });
    
    if (decreaseBtn && increaseBtn && quantityDisplay) {
        console.log('All elements found, setting up controls');
        // Initialize quantity display with current cart quantity
        updateQuantityDisplay(dish.id);
        
        // Always show all buttons and quantity display
        showAllControls();
        
        decreaseBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            console.log('Decrease button clicked');
            if (!this.disabled) {
                const currentQty = await getItemQuantity(dish.id);
                if (currentQty > 0) {
                    await removeFromCartDetail(dish.id);
                    await updateQuantityDisplay(dish.id);
                    
                    // Show notification
                    if (window.showNotification) {
                        if (currentQty === 1) {
                            window.showNotification(`${dish.name} supprimé du panier`, 'info');
                        } else {
                            window.showNotification('Quantité diminuée', 'success');
                        }
                    }
                }
            }
        });
        
        increaseBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            console.log('Increase button clicked');
            const currentQty = await getItemQuantity(dish.id);
            await addToCartDetail(dish.id);
            await updateQuantityDisplay(dish.id);
            
            // Show notification
            if (window.showNotification) {
                if (currentQty === 0) {
                    window.showNotification(`${dish.name} ajouté au panier`, 'success');
                } else {
                    window.showNotification('Quantité augmentée', 'success');
                }
            }
        });
        
        console.log('Event listeners attached successfully');
    } else {
        console.error('Could not find required elements for quantity controls');
    }
}

function showAllControls() {
    const decreaseBtn = document.getElementById('decreaseQty');
    const quantityDisplay = document.getElementById('quantityDisplay');
    
    if (decreaseBtn && quantityDisplay) {
        decreaseBtn.style.display = 'flex';
        quantityDisplay.style.display = 'block';
    }
}

async function updateQuantityDisplay(itemId) {
    console.log('updateQuantityDisplay called with itemId:', itemId);
    const quantityDisplay = document.getElementById('quantityDisplay');
    const decreaseBtn = document.getElementById('decreaseQty');
    
    console.log('Found elements:', {
        quantityDisplay: !!quantityDisplay,
        decreaseBtn: !!decreaseBtn
    });
    
    if (quantityDisplay) {
        const quantity = await getItemQuantity(itemId);
        console.log('Current quantity for item', itemId, ':', quantity);
        quantityDisplay.textContent = quantity;
        
        // Enable/disable decrease button based on quantity
        if (decreaseBtn) {
            if (quantity > 0) {
                decreaseBtn.disabled = false;
                decreaseBtn.style.opacity = '1';
                decreaseBtn.style.cursor = 'pointer';
            } else {
                decreaseBtn.disabled = true;
                decreaseBtn.style.opacity = '0.5';
                decreaseBtn.style.cursor = 'not-allowed';
            }
        }
    } else {
        console.log('quantityDisplay element not found');
    }
}

// Make updateQuantityDisplay globally available
window.updateQuantityDisplay = updateQuantityDisplay;

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

async function addToCartDetail(itemId) {
    console.log('Adding to cart, itemId:', itemId);
    
    try {
        await window.cartAPI.addItem(itemId, 1);
        
        // Update cart navigation and sidebar
        if (window.updateCartNavigation) {
            await window.updateCartNavigation();
        }
        if (window.updateCartSidebar) {
            await window.updateCartSidebar();
        }
        
        // Keep cart open when modifying quantities
        if (window.cartIsActive !== undefined) {
            window.cartIsActive = true;
            if (window.resetCartActiveState) {
                window.resetCartActiveState();
            }
        }
        
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
        
        console.log('Item added to cart successfully');
    } catch (error) {
        console.error('Error adding to cart:', error);
    }
}

async function removeFromCartDetail(itemId) {
    try {
        const cart = await window.cartAPI.getCart();
        const item = cart.items.find(i => i.id === itemId);
        
        if (item) {
            if (item.quantity > 1) {
                await window.cartAPI.updateQuantity(itemId, item.quantity - 1);
                if (window.showNotification) { 
                    showNotification('Quantité diminuée', 'success'); 
                }
            } else {
                await window.cartAPI.removeItem(itemId);
                if (window.showNotification) { 
                    showNotification(`${item.name} supprimé du panier`, 'info'); 
                }
            }
            
            // Update cart navigation and sidebar
            if (window.updateCartNavigation) {
                await window.updateCartNavigation();
            }
            if (window.updateCartSidebar) {
                await window.updateCartSidebar();
            }
            
            // Keep cart open when modifying quantities
            if (window.cartIsActive !== undefined) {
                window.cartIsActive = true;
                if (window.resetCartActiveState) {
                    window.resetCartActiveState();
                }
            }
            
            // Dispatch custom event for cart updates
            window.dispatchEvent(new CustomEvent('cartUpdated'));
        }
    } catch (error) {
        console.error('Error removing from cart:', error);
    }
}

function findItemById(itemId) {
    // First check if we have dishData from the current page
    if (window.dishData && window.dishData.id == itemId) {
        return window.dishData;
    }
    
    // Search in menuItems array
    if (window.menuItems) {
        return window.menuItems.find(item => item.id == itemId);
    }
    
    // Fallback: search in drinks data
    if (window.drinksData) {
        return window.drinksData.find(item => item.id == itemId);
    }
    
    return null;
}

function addCartUpdateListener(dish) {
    // Listen for storage changes (when cart is updated from other pages)
    window.addEventListener('storage', function(e) {
        if (e.key === 'cart') {
            updateQuantityDisplay(dish.id);
        }
    });
    
    // Listen for custom cart update events
    window.addEventListener('cartUpdated', function() {
        updateQuantityDisplay(dish.id);
    });
    
    // Set up interval to check for cart changes (fallback)
    setInterval(() => {
        updateQuantityDisplay(dish.id);
    }, 1000);
}

// ---------------- DISH REVIEWS ----------------
/**
 * Fetch and render approved reviews for the given dish.
 */
function loadDishReviews(dishId) {
    const list = document.getElementById('dishReviewsList');
    if (!list) return;
    list.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split me-2"></i>Chargement…</div>';

    fetch(`/dish/${dishId}/reviews`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error();
            if (!data.reviews || data.reviews.length === 0) {
                list.innerHTML = '<div class="text-muted">Aucun avis pour ce plat pour le moment.</div>';
                return;
            }
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
            list.innerHTML = '<div class="text-danger">Erreur de chargement des avis.</div>';
        });
}

// Listen for global submission event from reviews.js to refresh dish list
document.addEventListener('review:submitted', function () {
    const match = window.location.pathname.match(/\/dish\/(\d+)/);
    const dishId = match ? match[1] : null;
    if (dishId) {
        loadDishReviews(dishId);
    }
});

function renderStars(n) {
    n = Math.max(0, Math.min(5, parseInt(n, 10) || 0));
    const full = '<i class="bi bi-star-fill text-warning"></i>';
    const empty = '<i class="bi bi-star text-warning"></i>';
    return full.repeat(n) + empty.repeat(5 - n);
}

function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}