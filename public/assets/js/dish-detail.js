// Dish Detail Page JavaScript - Cart functionality only
document.addEventListener('DOMContentLoaded', function() {
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
    } else {
        console.log('Dish not found in data, trying to initialize with dishData');
        // If we have dishData but it wasn't found by findDishById, use it directly
        if (window.dishData && window.dishData.id == dishId) {
            console.log('Using dishData directly:', window.dishData);
            initQuantityControls(window.dishData);
            addCartUpdateListener(window.dishData);
        }
    }
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
        
        decreaseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Decrease button clicked');
            if (!this.disabled) {
                const currentQty = getItemQuantity(dish.id);
                if (currentQty > 0) {
                    removeFromCart(dish.id);
                    updateQuantityDisplay(dish.id);
                    
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
        
        increaseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Increase button clicked');
            const currentQty = getItemQuantity(dish.id);
            addToCart(dish.id);
            updateQuantityDisplay(dish.id);
            
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

function updateQuantityDisplay(itemId) {
    const quantityDisplay = document.getElementById('quantityDisplay');
    const decreaseBtn = document.getElementById('decreaseQty');
    
    if (quantityDisplay) {
        const quantity = getItemQuantity(itemId);
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
    }
}

function getItemQuantity(itemId) {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const item = cart.find(item => item.id === itemId);
    return item ? item.quantity : 0;
}

function addToCart(itemId) {
    console.log('Adding to cart, itemId:', itemId);
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const existingItem = cart.find(item => item.id == itemId);
    
    if (existingItem) {
        console.log('Item exists in cart, increasing quantity');
        existingItem.quantity += 1;
    } else {
        // Find item in menu data
        const menuItem = findItemById(itemId);
        console.log('Item not in cart, looking for menu item:', menuItem);
        if (menuItem) {
            const newItem = {
                id: menuItem.id,
                name: menuItem.name,
                price: menuItem.price,
                quantity: 1
            };
            console.log('Adding new item to cart:', newItem);
            cart.push(newItem);
        } else {
            console.log('Menu item not found, cannot add to cart');
            return;
        }
    }
    
    console.log('Updated cart:', cart);
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // Update cart navigation and sidebar
    if (window.updateCartNavigation) {
        window.updateCartNavigation();
    }
    if (window.updateCartSidebar) {
        window.updateCartSidebar();
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

function removeFromCart(itemId) {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const index = cart.findIndex(item => item.id === itemId);
    
    if (index !== -1) {
        const item = cart[index];
        item.quantity--;
        
        if (item.quantity <= 0) {
            cart.splice(index, 1);
            showNotification(`${item.name} supprimé du panier`, 'info');
        } else {
            showNotification('Quantité diminuée', 'success');
        }
        
        localStorage.setItem('cart', JSON.stringify(cart));
        
        // Update cart navigation and sidebar
        if (window.updateCartNavigation) {
            window.updateCartNavigation();
        }
        if (window.updateCartSidebar) {
            window.updateCartSidebar();
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