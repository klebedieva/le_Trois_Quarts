// Order page JavaScript

let currentStep = 1;
let orderData = {
    items: [],
    delivery: {},
    payment: {},
    total: 0
};

document.addEventListener('DOMContentLoaded', function() {
    initOrderPage();
    // Auto-fill address and postal code when selecting saved address
    const savedAddresses = document.getElementById('savedAddresses');
    if (savedAddresses) {
        savedAddresses.addEventListener('change', function() {
            const value = this.value;
            if (value === 'home') {
                document.getElementById('deliveryAddress').value = '123 Rue de la République, 13001 Marseille';
                document.getElementById('deliveryZip').value = '13001';
            } else {
                document.getElementById('deliveryAddress').value = '';
                document.getElementById('deliveryZip').value = '';
            }
        });
    }
});

function initOrderPage() {
    loadCartItems();
    updateCartSidebar(); // Update sidebar cart on load
    initDeliveryOptions();
    initPaymentOptions();
    updateOrderSummary();
    initTimeValidation();
}

function loadCartItems() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const cartItemsContainer = document.getElementById('orderCartItems');
    
    if (!cartItemsContainer) return;
    
    if (cart.length === 0) {
        cartItemsContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-basket display-1 text-muted"></i>
                </div>
                <h4 class="mt-3 text-muted">Votre panier est vide</h4>
                <p class="text-muted mb-4">Ajoutez des plats depuis notre menu</p>
                <a href="menu.html" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>Voir le menu
                </a>
            </div>
        `;
        return;
    }
    
    orderData.items = cart;
    
    let itemsHTML = '';
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        itemsHTML += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <h5>${item.name}</h5>
                    <p>Quantité: ${item.quantity} × ${item.price.toFixed(2)}€</p>
                </div>
                <div class="cart-item-controls">
                    <div class="quantity-controls">
                        <button class="btn btn-sm btn-outline-secondary quantity-btn" data-id="${item.id}" data-action="decrease" title="Diminuer">
                            <i class="bi bi-dash"></i>
                        </button>
                        <span class="quantity-display">${item.quantity}</span>
                        <button class="btn btn-sm btn-outline-secondary quantity-btn" data-id="${item.id}" data-action="increase" title="Augmenter">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                    <div class="cart-item-price">${itemTotal.toFixed(2)}€</div>
                    <button class="btn btn-sm btn-outline-danger remove-from-cart" data-id="${item.id}" title="Supprimer">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartItemsContainer.innerHTML = itemsHTML;
    
            // Add event handlers for quantity change buttons
    document.querySelectorAll('.quantity-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            const action = this.getAttribute('data-action');
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const itemIndex = cart.findIndex(item => String(item.id) === String(itemId));
            
            if (itemIndex !== -1) {
                if (action === 'increase') {
                    cart[itemIndex].quantity += 1;
                } else if (action === 'decrease') {
                    if (cart[itemIndex].quantity > 1) {
                        cart[itemIndex].quantity -= 1;
                    } else {
                        // Remove item if quantity becomes 0
                        cart = cart.filter(item => String(item.id) !== String(itemId));
                    }
                }
                
                localStorage.setItem('cart', JSON.stringify(cart));
                loadCartItems();
                updateCartSidebar(); // Update sidebar cart
                if (window.updateCartNavigation) window.updateCartNavigation();
            }
        });
    });
    
    // Add event handlers for remove buttons
    document.querySelectorAll('.remove-from-cart').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            cart = cart.filter(item => String(item.id) !== String(itemId));
            localStorage.setItem('cart', JSON.stringify(cart));
            loadCartItems();
            updateOrderSummary();
            updateCartSidebar(); // Update sidebar cart
            if (window.updateCartNavigation) window.updateCartNavigation();
        });
    });
}

// Function to update sidebar cart
function updateCartSidebar() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const cartItemsContainer = document.getElementById('cartItems');
    const cartTotalElement = document.getElementById('cartTotal');
    const clearCartBtn = document.getElementById('clearCart');
    
    if (!cartItemsContainer) return;
    
    // Update clear cart button state
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
        cartItemsContainer.innerHTML = `
            <div class="cart-empty">
                <i class="bi bi-basket"></i>
                <h4>Votre panier est vide</h4>
                <p>Ajoutez des plats depuis le menu</p>
            </div>
        `;
        if (cartTotalElement) {
            cartTotalElement.textContent = '0€';
        }
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
                        <button class="cart-qty-btn" onclick="removeFromCartSidebar('${item.id}')">-</button>
                        <span class="cart-item-total">${item.quantity}</span>
                        <button class="cart-qty-btn" onclick="addToCartSidebar('${item.id}')">+</button>
                    </div>
                    <span class="cart-item-total">${itemTotal}€</span>
                </div>
            </div>
        `;
    });
    
    cartItemsContainer.innerHTML = itemsHTML;
    
    if (cartTotalElement) {
        cartTotalElement.textContent = total.toFixed(2) + '€';
    }
    
    // Update final summary after loading items
    updateOrderSummary();
}

// Global functions for sidebar cart (like in main.js)
window.removeFromCartSidebar = function(itemId) {
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const index = cart.findIndex(item => item.id === itemId);
    
    if (index !== -1) {
        const item = cart[index];
        item.quantity--;
        
        if (item.quantity <= 0) {
            // Remove item if quantity becomes 0 or less
            cart.splice(index, 1);
            showNotification(`${item.name} supprimé du panier`, 'info');
        } else {
            showNotification('Quantité diminuée', 'success');
        }
        
        localStorage.setItem('cart', JSON.stringify(cart));
        loadCartItems(); // Update main page
        updateCartSidebar();
        if (window.updateCartNavigation) window.updateCartNavigation();
        
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    }
};

window.addToCartSidebar = function(itemId) {
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const index = cart.findIndex(item => item.id === itemId);
    
    if (index !== -1) {
        const item = cart[index];
        item.quantity += 1;
        localStorage.setItem('cart', JSON.stringify(cart));
        loadCartItems(); // Update main page
        updateCartSidebar();
        if (window.updateCartNavigation) window.updateCartNavigation();
        
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
        
        showNotification('Quantité augmentée', 'success');
    }
};

// Function to clear cart
window.clearCart = function() {
    if (confirm('Êtes-vous sûr de vouloir vider votre panier ?')) {
        localStorage.removeItem('cart');
        loadCartItems(); // Update main page
        updateCartSidebar();
        if (window.updateCartNavigation) window.updateCartNavigation();
        
        showNotification('Panier vidé', 'info');
        
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    }
};

function initDeliveryOptions() {
    const deliveryOptions = document.querySelectorAll('input[name="deliveryMode"]');
    const deliveryDetails = document.getElementById('deliveryDetails');
    
    deliveryOptions.forEach(option => {
        option.addEventListener('change', function() {
            if (this.value === 'delivery') {
                deliveryDetails.style.display = 'block';
                updateDeliveryFee(3);
            } else {
                deliveryDetails.style.display = 'none';
                updateDeliveryFee(0);
            }
            updateOrderSummary();
        });
    });
    
    // Trigger initial state
    const checkedOption = document.querySelector('input[name="deliveryMode"]:checked');
    if (checkedOption) {
        checkedOption.dispatchEvent(new Event('change'));
    }
}

function initPaymentOptions() {
    const paymentOptions = document.querySelectorAll('input[name="paymentMode"]');
    const cardDetails = document.getElementById('cardDetails');
    
    paymentOptions.forEach(option => {
        option.addEventListener('change', function() {
            if (this.value === 'card') {
                cardDetails.style.display = 'block';
            } else {
                cardDetails.style.display = 'none';
            }
        });
    });
    
    // Format card number input
    const cardNumberInput = document.getElementById('cardNumber');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            this.value = formattedValue;
        });
    }
    
    // Format expiry date input
    const cardExpiryInput = document.getElementById('cardExpiry');
    if (cardExpiryInput) {
        cardExpiryInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            this.value = value;
        });
    }
    
    // Trigger initial state
    const checkedOption = document.querySelector('input[name="paymentMode"]:checked');
    if (checkedOption) {
        checkedOption.dispatchEvent(new Event('change'));
    }
}

function updateDeliveryFee(fee) {
    orderData.deliveryFee = fee;
    const deliveryFeeElement = document.getElementById('deliveryFee');
    if (deliveryFeeElement) {
        deliveryFeeElement.textContent = fee + '€';
    }
}

function updateOrderSummary() {
    const cart = orderData.items;
    const summaryItemsContainer = document.getElementById('summaryItems');
    const subtotalElement = document.getElementById('subtotal');
    const taxAmountElement = document.getElementById('taxAmount');
    const totalAmountElement = document.getElementById('totalAmount');
    
    if (!summaryItemsContainer) return;
    
    let subtotal = 0;
    let itemsHTML = '';
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        
        itemsHTML += `
            <div class="summary-item">
                <div class="summary-item-info">
                    <span class="summary-item-name">${item.name}</span>
                    <small class="text-muted">x${item.quantity}</small>
                </div>
                <span class="summary-item-price">${itemTotal.toFixed(2)}€</span>
            </div>
        `;
    });
    
    summaryItemsContainer.innerHTML = itemsHTML;
    
    const deliveryFee = orderData.deliveryFee || 0;
    const taxRate = 0.10; // 10% TVA
    const taxAmount = subtotal * taxRate;
    const total = subtotal + deliveryFee + taxAmount;
    
    if (subtotalElement) {
        subtotalElement.textContent = subtotal.toFixed(2) + '€';
    }
    
    if (taxAmountElement) {
        taxAmountElement.textContent = taxAmount.toFixed(2) + '€';
    }
    
    if (totalAmountElement) {
        totalAmountElement.textContent = total.toFixed(2) + '€';
    }
    
    orderData.subtotal = subtotal;
    orderData.taxAmount = taxAmount;
    orderData.total = total;
}

function nextStep(step) {
    if (validateCurrentStep()) {
        showStep(step);
    }
}

function prevStep(step) {
    showStep(step);
}

function showStep(step) {
    // Hide all step contents
    const stepContents = document.querySelectorAll('.order-step-content');
    stepContents.forEach(content => content.classList.remove('active'));
    
    // Show target step content
    const targetContent = document.getElementById(`step${step}`);
    if (targetContent) {
        targetContent.classList.add('active');
    }
    
    // Update step indicators
    const steps = document.querySelectorAll('.step');
    steps.forEach((stepEl, index) => {
        if (index + 1 <= step) {
            stepEl.classList.add('active');
        } else {
            stepEl.classList.remove('active');
        }
    });
    
    currentStep = step;
    
    // Update final summary if on confirmation step
    if (step === 4) {
        updateFinalSummary();
    }
}

function validateCurrentStep() {
    switch (currentStep) {
        case 1:
            return validateCartStep();
        case 2:
            return validateDeliveryStep();
        case 3:
            return validatePaymentStep();
        default:
            return true;
    }
}

function validateCartStep() {
    if (orderData.items.length === 0) {
        showNotification('Votre panier est vide', 'error');
        return false;
    }
    return true;
}

function validateDeliveryStep() {
    const deliveryMode = document.querySelector('input[name="deliveryMode"]:checked')?.value;
    const deliveryDate = document.getElementById('deliveryDate')?.value;
    const deliveryTime = document.getElementById('deliveryTime')?.value;
    
    if (!deliveryMode) {
        showNotification('Veuillez choisir un mode de récupération', 'error');
        return false;
    }
    
    if (!deliveryDate) {
        showNotification('Veuillez choisir une date', 'error');
        return false;
    }
    
    if (!deliveryTime) {
        showNotification('Veuillez choisir un créneau horaire', 'error');
        return false;
    }
    
            // Check that selected time hasn't passed
    if (!validateSelectedTime()) {
        return false;
    }
    
    if (deliveryMode === 'delivery') {
        const address = document.getElementById('deliveryAddress')?.value;
        const zip = document.getElementById('deliveryZip')?.value;
        
        if (!address || !zip) {
            showNotification('Veuillez renseigner votre adresse de livraison', 'error');
            return false;
        }
    }
    
    // Store delivery data
    orderData.delivery = {
        mode: deliveryMode,
        date: deliveryDate,
        time: deliveryTime,
        address: document.getElementById('deliveryAddress')?.value,
        zip: document.getElementById('deliveryZip')?.value,
        instructions: document.getElementById('deliveryInstructions')?.value
    };
    
    return true;
}

function validatePaymentStep() {
    const paymentMode = document.querySelector('input[name="paymentMode"]:checked')?.value;
    
    if (!paymentMode) {
        showNotification('Veuillez choisir un mode de paiement', 'error');
        return false;
    }
    
            // Don't require card data input, only method selection
    orderData.payment = {
        mode: paymentMode
    };
    
    return true;
}

function updateFinalSummary() {
    // Update final order items
    const finalOrderItems = document.getElementById('finalOrderItems');
    if (finalOrderItems) {
        let itemsHTML = '';
        orderData.items.forEach(item => {
            const itemTotal = item.price * item.quantity;
            itemsHTML += `
                <div class="d-flex justify-content-between mb-2">
                    <span>${item.name} x${item.quantity}</span>
                    <span>${itemTotal.toFixed(2)}€</span>
                </div>
            `;
        });
        finalOrderItems.innerHTML = itemsHTML;
    }
    
    // Update delivery info
    const finalDeliveryInfo = document.getElementById('finalDeliveryInfo');
    if (finalDeliveryInfo) {
        const delivery = orderData.delivery;
        const modeText = delivery.mode === 'delivery' ? 'Livraison à domicile' : 'Retrait sur place';
        let infoHTML = `<p><strong>${modeText}</strong></p>`;
        infoHTML += `<p>Date: ${delivery.date} à ${delivery.time}</p>`;
        
        if (delivery.mode === 'delivery' && delivery.address) {
            infoHTML += `<p>Adresse: ${delivery.address}, ${delivery.zip}</p>`;
        }
        
        finalDeliveryInfo.innerHTML = infoHTML;
    }
    
    // Update payment info
    const finalPaymentInfo = document.getElementById('finalPaymentInfo');
    if (finalPaymentInfo) {
        const payment = orderData.payment;
        let paymentText = '';
        
        switch (payment.mode) {
            case 'card':
                paymentText = 'Carte bancaire';
                break;
            case 'cash':
                paymentText = 'Paiement en espèces';
                break;
            case 'tickets':
                paymentText = 'Tickets restaurant';
                break;
        }
        
        finalPaymentInfo.innerHTML = `<p>${paymentText}</p>`;
    }
}

function confirmOrder() {
    const acceptTerms = document.getElementById('acceptTerms')?.checked;
    
    if (!acceptTerms) {
        showNotification('Veuillez accepter les conditions générales', 'error');
        return;
    }
    
    // Generate order ID
    const orderId = 'CMD' + Date.now();
    
    // Create final order object
    const finalOrder = {
        id: orderId,
        items: orderData.items,
        delivery: orderData.delivery,
        payment: orderData.payment,
        subtotal: orderData.subtotal,
        deliveryFee: orderData.deliveryFee || 0,
        taxAmount: orderData.taxAmount,
        total: orderData.total,
        status: 'confirmed',
        date: new Date().toISOString()
    };
    
    // Store order in localStorage (in real app, send to server)
    const orders = JSON.parse(localStorage.getItem('userOrders') || '[]');
    orders.push(finalOrder);
    localStorage.setItem('userOrders', JSON.stringify(orders));
    
    // Clear cart
    localStorage.removeItem('cart');
    
    // Send confirmation email simulation
    sendConfirmationEmail(finalOrder);
    
    // Show success message
    showOrderConfirmation(orderId);
}

function sendConfirmationEmail(order) {
    // Simulate email sending
    console.log('Sending confirmation email for order:', order.id);
    
    // In a real application, this would call an email service
    const emailData = {
        to: 'client@example.com', // Would be actual user email
        subject: `Confirmation de commande ${order.id} - Le Trois Quarts`,
        body: `
            Bonjour,
            
            Votre commande ${order.id} a été confirmée.
            
            Détails de la commande:
            - Articles: ${order.items.length}
            - Total: ${order.total.toFixed(2)}€
            - Mode: ${order.delivery.mode === 'delivery' ? 'Livraison' : 'Retrait'}
            - Date: ${order.delivery.date} à ${order.delivery.time}
            
            Merci de votre confiance !
            
            L'équipe du Trois Quarts
        `
    };
    
    // Store email in localStorage for demo purposes
    const emails = JSON.parse(localStorage.getItem('sentEmails') || '[]');
    emails.push({
        ...emailData,
        sentAt: new Date().toISOString(),
        orderId: order.id
    });
    localStorage.setItem('sentEmails', JSON.stringify(emails));
    
    showNotification('Email de confirmation envoyé !', 'success');
}
function showOrderConfirmation(orderId) {
    // Hide all steps
    const stepContents = document.querySelectorAll('.order-step-content');
    stepContents.forEach(content => content.classList.remove('active'));
    
    // Show confirmation message
    const container = document.querySelector('.order-section .container');
    if (container) {
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success icon-success-large"></i>
                </div>
                <h2 class="text-success mb-3">Commande confirmée !</h2>
                <p class="lead mb-4">Votre commande <strong>${orderId}</strong> a été enregistrée avec succès.</p>
                <div class="alert alert-info">
                    <h5><i class="bi bi-info-circle me-2"></i>Prochaines étapes :</h5>
                    <ul class="list-unstyled mb-0">
                        <li>• Vous recevrez un email de confirmation</li>
                        <li>• Votre commande sera préparée selon le créneau choisi</li>
                    </ul>
                </div>
                <div class="mt-4">
                    <a href="menu.html" class="btn btn-primary">
                        <i class="bi bi-arrow-left me-2"></i>Retour au menu
                    </a>
                </div>
            </div>
        `;
    }
    showNotification('Commande confirmée avec succès !', 'success');
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show`;
    notification.classList.add('notification');
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Make functions available globally
window.nextStep = nextStep;
window.prevStep = prevStep;
window.confirmOrder = confirmOrder;

// Function to initialize time validation
function initTimeValidation() {
    const dateInput = document.getElementById('deliveryDate');
    const timeSelect = document.getElementById('deliveryTime');
    
    if (dateInput && timeSelect) {
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;
        dateInput.value = today; // Set today's date as default
        
        // Add event handlers
        dateInput.addEventListener('change', updateTimeOptions);
        timeSelect.addEventListener('change', validateSelectedTime);
        
        // Initialize time options
        updateTimeOptions();
    }
}

// Function to update available time slots
function updateTimeOptions() {
    const dateInput = document.getElementById('deliveryDate');
    const timeSelect = document.getElementById('deliveryTime');
    
    if (!dateInput || !timeSelect) return;
    
    const selectedDate = dateInput.value;
    const today = new Date().toISOString().split('T')[0];
    const currentTime = new Date();
    
            // Clear current options
    timeSelect.innerHTML = '<option value="">Choisir un créneau</option>';
    
            // Define time slots
    const timeSlots = [
        { value: '12:00', text: '12h00 - 12h30' },
        { value: '12:30', text: '12h30 - 13h00' },
        { value: '13:00', text: '13h00 - 13h30' },
        { value: '19:00', text: '19h00 - 19h30' },
        { value: '19:30', text: '19h30 - 20h00' },
        { value: '20:00', text: '20h00 - 20h30' }
    ];
    
            // If today's date is selected, filter past time
    if (selectedDate === today) {
        timeSlots.forEach(slot => {
            const slotTime = new Date(`${selectedDate}T${slot.value}`);
            if (slotTime > currentTime) {
                const option = document.createElement('option');
                option.value = slot.value;
                option.textContent = slot.text;
                timeSelect.appendChild(option);
            }
        });
    } else {
        // For future dates show all slots
        timeSlots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.value;
            option.textContent = slot.text;
            timeSelect.appendChild(option);
        });
    }
    
            // If no available slots for today, show message
    if (selectedDate === today && timeSelect.options.length === 1) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Aucun créneau disponible aujourd\'hui';
        option.disabled = true;
        timeSelect.appendChild(option);
    }
}

// Function to validate selected time
function validateSelectedTime() {
    const dateInput = document.getElementById('deliveryDate');
    const timeSelect = document.getElementById('deliveryTime');
    
    if (!dateInput || !timeSelect) return;
    
    const selectedDate = dateInput.value;
    const selectedTime = timeSelect.value;
    const today = new Date().toISOString().split('T')[0];
    const currentTime = new Date();
    
    if (selectedDate === today && selectedTime) {
        const selectedDateTime = new Date(`${selectedDate}T${selectedTime}`);
        
        if (selectedDateTime <= currentTime) {
            showNotification('Ce créneau n\'est plus disponible. Veuillez choisir un autre créneau.', 'error');
            timeSelect.value = '';
            return false;
        }
    }
    
    return true;
}