// Order page JavaScript — single implementation modeled after Restaurant

let currentStep = 1;
let orderData = { items: [], delivery: {}, payment: {}, total: 0 };

// XSS detection patterns
const xssPatterns = [
    /<[^>]*>/gi,                    // HTML tags
    /javascript:/gi,                // JavaScript protocol
    /on\w+\s*=/gi,                  // Event handlers
    /vbscript:/gi,                  // VBScript protocol
    /data:text\/html/gi,            // Data URI with HTML
    /expression\s*\(/gi,            // CSS expressions
    /<script/gi,                    // Script tags
    /<iframe/gi,                    // Iframe tags
    /<object/gi,                    // Object tags
    /<embed/gi,                     // Embed tags
    /<form/gi,                      // Form tags
    /<link[^>]*href\s*=\s*["\']?javascript:/gi, // Link with JS
    /<meta[^>]*http-equiv\s*=\s*["\']?refresh/gi // Meta refresh
];

// XSS detection function
function containsXssAttempt(value) {
    for (let pattern of xssPatterns) {
        if (pattern.test(value)) {
            return true;
        }
    }
    return false;
}

// Sanitize input by removing dangerous content
function sanitizeInput(value) {
    return value
        .replace(/<[^>]*>/g, '')           // Remove HTML tags
        .replace(/javascript:/gi, '')      // Remove javascript: protocol
        .replace(/on\w+\s*=/gi, '')        // Remove event handlers
        .replace(/[<>'"]/g, '')            // Remove dangerous characters
        .trim();
}

// Helper function for safe notification display
function showOrderNotification(message, type = 'info') {
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    } else {
        alert(`${type.toUpperCase()}: ${message}`);
    }
}

// Simple Order API client using the new backend endpoints
window.orderAPI = {
    async createOrder(payload) {
        const res = await window.apiRequest('/api/order', {
            method: 'POST',
            credentials: 'include',
            body: JSON.stringify(payload || {})
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            const msg = data?.message || `Erreur ${res.status}`;
            throw new Error(msg);
        }
        return data; // { success, message?, order }
    },
    async getOrder(id) {
        const res = await fetch(`/api/order/${id}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include'
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            const msg = data?.message || `Erreur ${res.status}`;
            throw new Error(msg);
        }
        return data; // { success, order }
    }
};

    document.addEventListener('DOMContentLoaded', function() {
        initOrderPage();
});

async function initOrderPage() {
    await loadCartItems();
    updateOrderSummary();
    initDeliveryOptions();
    initPaymentOptions();
    initTimeValidation();
    initPhoneValidation();
    initNameEmailValidation();
    initZipCodeValidation();
    initAddressValidation();

    window.addEventListener('cartUpdated', async function() {
        await loadCartItems();
        updateOrderSummary();
    });
}

// Real-time validation for first/last name and email (same style as phone)
function initNameEmailValidation() {
    const firstNameInput = document.getElementById('clientFirstName');
    const lastNameInput = document.getElementById('clientLastName');
    const emailInput = document.getElementById('clientEmail');

    const nameRegex = /^[a-zA-ZÀ-ÿ\s\-']+$/;
    const emailRegex = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;

    function attachValidation(input, validator, messages) {
        if (!input) return;
        const onValidate = () => {
            const value = (input.value || '').trim();
            input.classList.remove('is-invalid');
            removeInlineError(input);
            if (value === '') {
                input.classList.add('is-invalid');
                showInlineError(input, messages.empty);
            } else if (!validator(value)) {
                input.classList.add('is-invalid');
                showInlineError(input, messages.invalid);
            } 
        };
        input.addEventListener('input', onValidate);
        input.addEventListener('blur', onValidate);
        input.addEventListener('focus', () => { input.classList.remove('is-invalid'); removeInlineError(input); });
    }

    attachValidation(firstNameInput, v => nameRegex.test(v), { empty: 'Le prénom est requis', invalid: 'Le prénom ne peut contenir que des lettres, espaces et tirets' });
    attachValidation(lastNameInput, v => nameRegex.test(v), { empty: 'Le nom est requis', invalid: 'Le nom ne peut contenir que des lettres, espaces et tirets' });
    attachValidation(emailInput, v => emailRegex.test(v), { empty: "L'email est requis", invalid: "L'email n'est pas valide" });
}

function showInlineError(input, message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback name-email-validation-error';
    errorDiv.textContent = message;
    input.parentNode.appendChild(errorDiv);
}

function removeInlineError(input) {
    const existing = input.parentNode?.querySelector('.name-email-validation-error');
    if (existing) existing.remove();
}

async function loadCartItems() {
    const container = document.getElementById('orderCartItems');
    if (!container) return;

    let cart = { items: [] };
    try { cart = await window.cartAPI.getCart(); } catch (_) { cart = { items: [] }; }
    const items = Array.isArray(cart.items) ? cart.items : [];
    orderData.items = items;

    if (items.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="mb-4"><i class="bi bi-basket display-1 text-muted"></i></div>
                <h4 class="mt-3 text-muted">Votre panier est vide</h4>
                <p class="text-muted mb-4">Ajoutez des plats depuis notre menu</p>
                <a href="${window.appMenuPath || '#'}" class="btn btn-primary"><i class="bi bi-arrow-left me-2"></i>Voir le menu</a>
            </div>`;
        return;
    }

    let html = '';
    items.forEach(it => {
        const itemTotal = Number(it.price) * Number(it.quantity);
        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <h5>${it.name}</h5>
                    <p>Quantité: ${it.quantity} × ${Number(it.price).toFixed(2)}€</p>
                </div>
                <div class="cart-item-controls">
                    <div class="quantity-controls">
                        <button type="button" class="btn btn-sm btn-outline-secondary quantity-btn" data-id="${it.id}" data-action="decrease" title="Diminuer"><i class="bi bi-dash"></i></button>
                        <span class="quantity-display">${it.quantity}</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary quantity-btn" data-id="${it.id}" data-action="increase" title="Augmenter"><i class="bi bi-plus"></i></button>
                    </div>
                    <div class="cart-item-price">${itemTotal.toFixed(2)}€</div>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-from-cart" data-id="${it.id}" title="Supprimer"><i class="bi bi-x"></i></button>
                </div>
            </div>`;
    });
    container.innerHTML = html;

    container.querySelectorAll('.quantity-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
        e.preventDefault();
        e.stopPropagation();
            const id = parseInt(this.getAttribute('data-id'));
            const action = this.getAttribute('data-action');
            const current = orderData.items.find(i => Number(i.id) === Number(id));
            if (!current) return;
            try {
                if (action === 'increase') {
                    await window.cartAPI.updateQuantity(id, current.quantity + 1);
                } else if (action === 'decrease') {
                    if (current.quantity > 1) {
                        await window.cartAPI.updateQuantity(id, current.quantity - 1);
                    } else {
                        await window.cartAPI.removeItem(id);
                    }
                }
            } catch (_) {}
                await loadCartItems();
                updateOrderSummary();
                if (window.updateCartSidebar) window.updateCartSidebar();
                if (window.updateCartNavigation) window.updateCartNavigation();
            window.dispatchEvent(new CustomEvent('cartUpdated'));
        });
    });

    container.querySelectorAll('.remove-from-cart').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const id = parseInt(this.getAttribute('data-id'));
            try { await window.cartAPI.removeItem(id); } catch (_) {}
            await loadCartItems();
    updateOrderSummary();
    if (window.updateCartSidebar) window.updateCartSidebar();
            if (window.updateCartNavigation) window.updateCartNavigation();
            window.dispatchEvent(new CustomEvent('cartUpdated'));
        });
    });
}

function initDeliveryOptions() {
    const options = document.querySelectorAll('input[name="deliveryMode"]');
    const details = document.getElementById('deliveryDetails');
    options.forEach(opt => {
        opt.addEventListener('change', function() {
            if (this.value === 'delivery') { if (details) details.style.display = 'block'; updateDeliveryFee(5); }
            else { if (details) details.style.display = 'none'; updateDeliveryFee(0); }
            updateOrderSummary();
        });
    });
    const checked = document.querySelector('input[name="deliveryMode"]:checked');
    if (checked) checked.dispatchEvent(new Event('change'));
}

function initPaymentOptions() {
    const options = document.querySelectorAll('input[name="paymentMode"]');
    const cardDetails = document.getElementById('cardDetails');
    options.forEach(opt => {
        opt.addEventListener('change', function() { if (cardDetails) cardDetails.style.display = this.value === 'card' ? 'block' : 'none'; });
    });
    const checked = document.querySelector('input[name="paymentMode"]:checked');
    if (checked) checked.dispatchEvent(new Event('change'));
}

function updateDeliveryFee(fee) {
    orderData.deliveryFee = fee;
    const el = document.getElementById('deliveryFee');
    if (el) el.textContent = fee + '€';
}

function updateOrderSummary() {
    const container = document.getElementById('summaryItems');
    if (!container) return;
    let subtotalWithTax = 0; let html = '';
    orderData.items.forEach(it => {
        const itemTotal = Number(it.price) * Number(it.quantity);
        subtotalWithTax += itemTotal;
        html += `<div class="summary-item"><div class="summary-item-info"><span class="summary-item-name">${it.name}</span><small class="text-muted">x${it.quantity}</small></div><span class="summary-item-price">${itemTotal.toFixed(2)}€</span></div>`;
    });
    container.innerHTML = html;

    // Menu prices already include taxes (TTC)
    // Calculate amount without taxes (HT) and tax separately
    const taxRate = 0.10; // 10% VAT - loaded from backend config
    const subtotalWithoutTax = subtotalWithTax / (1 + taxRate);
    const taxAmount = subtotalWithTax - subtotalWithoutTax;
    
    const deliveryFee = orderData.deliveryFee || 0;
    const total = subtotalWithTax + deliveryFee;
    
    const subEl = document.getElementById('subtotal');
    const taxEl = document.getElementById('taxAmount');
    const totalEl = document.getElementById('totalAmount');
    if (subEl) subEl.textContent = subtotalWithoutTax.toFixed(2) + '€';
    if (taxEl) taxEl.textContent = taxAmount.toFixed(2) + '€';
    if (totalEl) totalEl.textContent = total.toFixed(2) + '€';
    orderData.subtotal = subtotalWithoutTax; orderData.taxAmount = taxAmount; orderData.total = total;
}

async function nextStep(step) { 
    const isValid = await validateCurrentStep(); 
    if (isValid) { 
        showStep(step); 
    } 
}
function prevStep(step) { showStep(step); }

function showStep(step) {
    document.querySelectorAll('.order-step-content').forEach(c => c.classList.remove('active'));
    const target = document.getElementById(`step${step}`);
    if (target) target.classList.add('active');
    const steps = document.querySelectorAll('.step');
    steps.forEach((el, i) => { if (i + 1 <= step) el.classList.add('active'); else el.classList.remove('active'); });
    currentStep = step;
    if (step === 4) updateFinalSummary();
}

async function validateCurrentStep() {
    switch (currentStep) {
        case 1: return validateCartStep();
        case 2: return await validateDeliveryStep();
        case 3: return validatePaymentStep();
        default: return true;
    }
}

function validateCartStep() { if ((orderData.items || []).length === 0) { showOrderNotification('Votre panier est vide', 'error'); return false; } return true; }

async function validateDeliveryStep() {
    const mode = document.querySelector('input[name="deliveryMode"]:checked')?.value;
    const date = document.getElementById('deliveryDate')?.value;
    const time = document.getElementById('deliveryTime')?.value;
    
    if (!mode) { showOrderNotification('Veuillez choisir un mode de récupération', 'error'); return false; }
    if (!date) { showOrderNotification('Veuillez choisir une date', 'error'); return false; }
    if (!time) { showOrderNotification('Veuillez choisir un créneau horaire', 'error'); return false; }
    if (!validateSelectedTime()) return false;
    if (mode === 'delivery') {
        const address = document.getElementById('deliveryAddress')?.value;
        const zip = document.getElementById('deliveryZip')?.value;
        const instructions = document.getElementById('deliveryInstructions')?.value;
        
        // XSS check for address
        if (address && containsXssAttempt(address)) {
            showOrderNotification('L\'adresse contient des éléments non autorisés', 'error');
            return false;
        }
        
        // XSS check for delivery instructions
        if (instructions && containsXssAttempt(instructions)) {
            showOrderNotification('Les instructions de livraison contiennent des éléments non autorisés', 'error');
            return false;
        }
        
        if (!address || !zip) { showOrderNotification('Veuillez renseigner votre adresse de livraison', 'error'); return false; }
        
        // Validation du code postal pour la livraison
        if (!validateFrenchZipCode(zip)) {
            showOrderNotification('Format de code postal invalide', 'error');
            return false;
        }
        
        // Check if delivery is available for this address
        try {
            const addressValidation = await window.zipCodeAPI.validateAddress(address, zip);
            if (!addressValidation.valid) {
                showOrderNotification(addressValidation.error || 'Livraison non disponible pour cette adresse', 'error');
                return false;
            }
        } catch (error) {
            showOrderNotification('Erreur lors de la vérification de l\'adresse', 'error');
            return false;
        }
    }
    
    // Validation des informations client
    const firstName = document.getElementById('clientFirstName')?.value?.trim();
    const lastName = document.getElementById('clientLastName')?.value?.trim();
    const phone = document.getElementById('clientPhone')?.value?.trim();
    const email = document.getElementById('clientEmail')?.value?.trim();
    
    // XSS check for contact information
    if (firstName && containsXssAttempt(firstName)) {
        showOrderNotification('Le prénom contient des éléments non autorisés', 'error');
        return false;
    }
    if (lastName && containsXssAttempt(lastName)) {
        showOrderNotification('Le nom contient des éléments non autorisés', 'error');
        return false;
    }
    if (phone && containsXssAttempt(phone)) {
        showOrderNotification('Le numéro de téléphone contient des éléments non autorisés', 'error');
        return false;
    }
    if (email && containsXssAttempt(email)) {
        showOrderNotification('L\'email contient des éléments non autorisés', 'error');
        return false;
    }
    
    if (!firstName) { showOrderNotification('Veuillez renseigner votre prénom', 'error'); return false; }
    if (!lastName) { showOrderNotification('Veuillez renseigner votre nom', 'error'); return false; }
    if (!phone) { showOrderNotification('Veuillez renseigner votre numéro de téléphone', 'error'); return false; }
    if (!email) { showOrderNotification('Veuillez renseigner votre adresse email', 'error'); return false; }
    
    // French phone number validation
    if (!validateFrenchPhoneNumber(phone)) {
        showOrderNotification('Veuillez entrer un numéro de téléphone français valide', 'error');
        return false;
    }
    
    // Validation basique de l'email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) { showOrderNotification('Veuillez renseigner une adresse email valide', 'error'); return false; }
    
    orderData.delivery = { mode, date, time, address: document.getElementById('deliveryAddress')?.value, zip: document.getElementById('deliveryZip')?.value, instructions: document.getElementById('deliveryInstructions')?.value };
    orderData.client = { firstName, lastName, phone, email };
    
    return true;
}

function validatePaymentStep() {
    const mode = document.querySelector('input[name="paymentMode"]:checked')?.value;
    if (!mode) { showOrderNotification('Veuillez choisir un mode de paiement', 'error'); return false; }
    orderData.payment = { mode };
    return true;
}

function updateFinalSummary() {
    const itemsEl = document.getElementById('finalOrderItems');
    if (itemsEl) {
        let html = '';
        orderData.items.forEach(it => {
            const itemTotal = Number(it.price) * Number(it.quantity);
            html += `<div class="d-flex justify-content-between mb-2"><span>${it.name} x${it.quantity}</span><span>${itemTotal.toFixed(2)}€</span></div>`;
        });
        itemsEl.innerHTML = html;
    }
    
    const clientEl = document.getElementById('finalClientInfo');
    if (clientEl) {
        const c = orderData.client || {};
        let info = `<p><strong>${c.firstName || ''} ${c.lastName || ''}</strong></p>`;
        if (c.phone) info += `<p>Téléphone: ${c.phone}</p>`;
        if (c.email) info += `<p>Email: ${c.email}</p>`;
        clientEl.innerHTML = info;
    }
    
    const deliveryEl = document.getElementById('finalDeliveryInfo');
    if (deliveryEl) {
        const d = orderData.delivery || {};
        const modeText = d.mode === 'delivery' ? 'Livraison à domicile' : 'Retrait sur place';
        let info = `<p><strong>${modeText}</strong></p>`;
        info += `<p>Date: ${d.date} à ${d.time}</p>`;
        if (d.mode === 'delivery' && d.address) info += `<p>Adresse: ${d.address}, ${d.zip}</p>`;
        deliveryEl.innerHTML = info;
    }
    const paymentEl = document.getElementById('finalPaymentInfo');
    if (paymentEl) {
        const text = orderData.payment?.mode === 'card' ? 'Carte bancaire' : orderData.payment?.mode === 'cash' ? 'Paiement en espèces' : 'Tickets restaurant';
        paymentEl.innerHTML = `<p>${text}</p>`;
    }
}

async function confirmOrder() {
    const accept = document.getElementById('acceptTerms')?.checked;
    if (!accept) { showOrderNotification('Veuillez accepter les conditions générales', 'error'); return; }

    // Build payload expected by backend API with sanitized data
    const payload = {
        deliveryMode: orderData?.delivery?.mode || document.querySelector('input[name="deliveryMode"]:checked')?.value || 'delivery',
        deliveryAddress: sanitizeInput(document.getElementById('deliveryAddress')?.value || ''),
        deliveryZip: sanitizeInput(document.getElementById('deliveryZip')?.value || ''),
        deliveryInstructions: sanitizeInput(document.getElementById('deliveryInstructions')?.value || ''),
        deliveryFee: typeof orderData.deliveryFee === 'number' ? orderData.deliveryFee : (document.querySelector('input[name="deliveryMode"]:checked')?.value === 'pickup' ? 0 : 5),
        paymentMode: orderData?.payment?.mode || document.querySelector('input[name="paymentMode"]:checked')?.value || 'card',
        clientFirstName: sanitizeInput(document.getElementById('clientFirstName')?.value || ''),
        clientLastName: sanitizeInput(document.getElementById('clientLastName')?.value || ''),
        clientPhone: sanitizeInput(document.getElementById('clientPhone')?.value || ''),
        clientEmail: sanitizeInput(document.getElementById('clientEmail')?.value || '')
    };

    try {
        const result = await window.orderAPI.createOrder(payload);
        const created = result.order; // OrderResponse
        // Backend already clears cart, update UI
        try { if (window.updateCartSidebar) window.updateCartSidebar(); } catch (_) {}
        try { if (window.updateCartNavigation) window.updateCartNavigation(); } catch (_) {}
        showOrderConfirmation(created.no, created.id, created.total);
    } catch (e) {
        showOrderNotification(e.message || 'Erreur lors de la création de la commande', 'error');
    }
}

function sendConfirmationEmail(order) {
    const emailData = { to: 'client@example.com', subject: `Confirmation de commande ${order.id} - Le Trois Quarts`, body: `Votre commande ${order.id} a été confirmée. Total: ${order.total.toFixed(2)}€` };
    const emails = JSON.parse(localStorage.getItem('sentEmails') || '[]'); emails.push({ ...emailData, sentAt: new Date().toISOString(), orderId: order.id }); localStorage.setItem('sentEmails', JSON.stringify(emails));
    showOrderNotification('Email de confirmation envoyé !', 'success');
}

function showOrderConfirmation(orderNo, orderId, total) {
    document.querySelectorAll('.order-step-content').forEach(c => c.classList.remove('active'));
    const container = document.querySelector('.order-section .container');
    if (container) {
        container.innerHTML = `<div class="text-center py-5"><div class="mb-4"><i class="bi bi-check-circle-fill text-success icon-success-large"></i></div><h2 class="text-success mb-3">Commande confirmée !</h2><p class="lead mb-2">Numéro de commande: <strong>${orderNo || orderId}</strong></p><p class="lead mb-4">Montant total: <strong>${Number(total || 0).toFixed(2)}€</strong></p><div class="alert alert-info"><h5><i class="bi bi-info-circle me-2"></i>Prochaines étapes :</h5><ul class="list-unstyled mb-0"><li>• Vous recevrez un email de confirmation</li><li>• Votre commande sera préparée selon le créneau choisi</li></ul></div><div class="mt-4"><a href="${window.appMenuPath || '#'}" class="btn btn-primary"><i class="bi bi-arrow-left me-2"></i>Retour au menu</a></div></div>`;
    }
    showOrderNotification('Commande confirmée avec succès !', 'success');
}

// Use global showOrderNotification function from main.js

// French phone number validation
function validateFrenchPhoneNumber(phone) {
    if (!phone) return false;
    
    // Clean the number (remove spaces, dashes, dots)
    const cleanPhone = phone.replace(/[\s\-\.]/g, '');
    
    // First check length and general format
    // National format: 0X XXXX XXXX (10 digits total, starts with 0)
    // International format: +33 X XX XX XX XX (12 characters, starts with +33)
    
    if (cleanPhone.length === 10 && cleanPhone.startsWith('0')) {
        // French national format: 0X XXXX XXXX
        const nationalRegex = /^0[1-9]\d{8}$/;
        if (!nationalRegex.test(cleanPhone)) {
            return false;
        }
        
        // Check first digits for mobiles (06, 07) and landlines (01-05)
        const firstTwoDigits = cleanPhone.substring(0, 2);
        const validPrefixes = ['06', '07', '01', '02', '03', '04', '05'];
        return validPrefixes.includes(firstTwoDigits);
        
    } else if (cleanPhone.length === 12 && cleanPhone.startsWith('+33')) {
        // Format international: +33 X XX XX XX XX
        const internationalRegex = /^\+33[1-9]\d{8}$/;
        if (!internationalRegex.test(cleanPhone)) {
            return false;
        }
        
        // Extract number without country code (+33)
        const withoutCountryCode = cleanPhone.substring(3); // Remove '+33'
        
        // Check first digits for mobiles (06, 07) and landlines (01-05)
        const firstTwoDigits = withoutCountryCode.substring(0, 2);
        const validPrefixes = ['06', '07', '01', '02', '03', '04', '05'];
        return validPrefixes.includes(firstTwoDigits);
    }
    
    // If neither 10 digits with 0, nor 12 characters with +33, then invalid
    return false;
}

// Initialize real-time phone validation
function initPhoneValidation() {
    const phoneInput = document.getElementById('clientPhone');
    if (!phoneInput) return;
    
    // Real-time validation during input
    phoneInput.addEventListener('input', function() {
        const phone = this.value.trim();
        const isValid = phone === '' || validateFrenchPhoneNumber(phone);
        
        // Remove previous validation classes
        this.classList.remove('is-invalid');
        
        if (phone !== '' && !isValid) {
            this.classList.add('is-invalid');
            showPhoneError('Format de numéro de téléphone invalide');
        } else {
            removePhoneError();
        }
    });
    
    // Validation au blur (quand l'utilisateur quitte le champ)
    phoneInput.addEventListener('blur', function() {
        const phone = this.value.trim();
        if (phone !== '' && !validateFrenchPhoneNumber(phone)) {
            this.classList.add('is-invalid');
            showPhoneError('Numéro de téléphone invalide');
        }
    });
    
    // Clear errors when user starts typing
    phoneInput.addEventListener('focus', function() {
        this.classList.remove('is-invalid');
        removePhoneError();
    });
}

// Show phone validation error
function showPhoneError(message) {
    removePhoneError(); // Remove previous error if any
    
    const phoneInput = document.getElementById('clientPhone');
    if (!phoneInput) return;
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback phone-validation-error';
    errorDiv.textContent = message;
    
    phoneInput.parentNode.appendChild(errorDiv);
}

// Remove phone validation error
function removePhoneError() {
    const existingError = document.querySelector('.phone-validation-error');
    if (existingError) {
        existingError.remove();
    }
}

// API for postal code and address validation
window.zipCodeAPI = {
    async validateZipCode(zipCode) {
        const res = await fetch('/api/validate-zip-code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ zipCode })
        });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data?.error || `Erreur ${res.status}`);
        }
        return data;
    },
    
    async validateAddress(address, zipCode = null) {
        const res = await fetch('/api/validate-address', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ address, zipCode })
        });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data?.error || `Erreur ${res.status}`);
        }
        return data;
    }
};

// Postal code validation
function validateFrenchZipCode(zipCode) {
    if (!zipCode) return false;
    
    // Clean postal code
    const cleanZipCode = zipCode.replace(/[^0-9]/g, '');
    
    // Check French postal code format (5 digits)
    return /^[0-9]{5}$/.test(cleanZipCode);
}

// Initialize postal code validation
function initZipCodeValidation() {
    const zipInput = document.getElementById('deliveryZip');
    if (!zipInput) return;
    
    let validationTimeout;
    
    // Real-time validation
    zipInput.addEventListener('input', function() {
        clearTimeout(validationTimeout);
        const zipCode = this.value.trim();
        
        // Remove previous validation classes
        this.classList.remove('is-valid', 'is-invalid');
        removeZipCodeError();
        
        if (zipCode === '') {
            return;
        }
        
        // Basic format validation
        if (!validateFrenchZipCode(zipCode)) {
            this.classList.add('is-invalid');
            showZipCodeError('Format de code postal invalide');
            return;
        }
        
        // API validation with delay
        validationTimeout = setTimeout(async () => {
            try {
                const result = await window.zipCodeAPI.validateZipCode(zipCode);
                
                if (result.valid) {
                    this.classList.remove('is-invalid');
                    showZipCodeSuccess('Livraison disponible');
                } else {
                    this.classList.add('is-invalid');
                    showZipCodeError(result.error || 'Livraison non disponible pour ce code postal');
                }
            } catch (error) {
                this.classList.add('is-invalid');
                showZipCodeError('Erreur lors de la vérification du code postal');
            }
        }, 500); // 500ms delay after input ends
    });
    
    // Clear on focus
    zipInput.addEventListener('focus', function() {
        this.classList.remove('is-invalid');
        removeZipCodeError();
    });
}

// Show postal code validation error
function showZipCodeError(message) {
    removeZipCodeError();
    
    const zipInput = document.getElementById('deliveryZip');
    if (!zipInput) return;
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback zip-validation-error';
    errorDiv.textContent = message;
    
    zipInput.parentNode.appendChild(errorDiv);
}

// Show successful postal code validation
function showZipCodeSuccess(message) {
    removeZipCodeError();
    
    const zipInput = document.getElementById('deliveryZip');
    if (!zipInput) return;
    
    const successDiv = document.createElement('div');
    successDiv.className = 'valid-feedback zip-validation-success';
    successDiv.textContent = message;
    
    zipInput.parentNode.appendChild(successDiv);
}

// Remove postal code validation messages
function removeZipCodeError() {
    const existingError = document.querySelector('.zip-validation-error');
    const existingSuccess = document.querySelector('.zip-validation-success');
    
    if (existingError) existingError.remove();
    if (existingSuccess) existingSuccess.remove();
}

// Extract postal code from address
function extractZipCodeFromAddress(address) {
    if (!address) return null;
    
    // Search for 5-digit number in address
    const zipMatch = address.match(/\b(\d{5})\b/);
    if (zipMatch) {
        const zipCode = zipMatch[1];
        // Check that this is a French postal code
        if (/^[0-9]{5}$/.test(zipCode)) {
            return zipCode;
        }
    }
    return null;
}

// Extract only the street part from a full address that may contain ZIP and city
function extractStreetWithoutZipCity(address) {
    if (!address) return '';
    const text = String(address);
    const zipMatch = text.match(/\b(\d{5})\b/);
    if (zipMatch) {
        // Keep everything before the ZIP code
        const cutIndex = zipMatch.index || 0;
        let street = text.substring(0, cutIndex);
        // Remove trailing commas, spaces
        street = street.replace(/[\s,]+$/g, '').trim();
        return street;
    }
    // If no ZIP detected but a pattern like ", Marseille" exists, drop city after comma
    const commaIndex = text.lastIndexOf(',');
    if (commaIndex > -1) {
        return text.substring(0, commaIndex).trim();
    }
    return text.trim();
}

// Full address validation
function validateAddress(address, zipCode) {
    if (!address) return false;
    
    // Basic check - address should not be empty
    const cleanAddress = address.trim();
    if (cleanAddress.length < 5) return false;
    
    return true;
}

// Initialize address validation
function initAddressValidation() {
    const addressInput = document.getElementById('deliveryAddress');
    const zipInput = document.getElementById('deliveryZip');
    
    if (!addressInput) return;
    
    let validationTimeout;
    
    // Real-time validation
    addressInput.addEventListener('input', function() {
        clearTimeout(validationTimeout);
        const address = this.value.trim();
        
        // Automatic extraction and substitution of postal code
        const extractedZipCode = extractZipCodeFromAddress(address);
        if (extractedZipCode && zipInput) {
            zipInput.value = extractedZipCode;
            // Run postal code validation after substitution
            zipInput.dispatchEvent(new Event('input'));
        }

        // Keep only the street part in the address input (leave ZIP/City in their own fields)
        const streetOnly = extractStreetWithoutZipCity(address);
        if (streetOnly && streetOnly !== this.value.trim()) {
            this.value = streetOnly;
        }
        
        const zipCode = zipInput?.value?.trim() || extractedZipCode || null;
        
        // Remove previous validation classes
        this.classList.remove('is-valid', 'is-invalid');
        removeAddressError();
        
        if (address === '') {
            return;
        }
        
        // Basic address validation
        if (!validateAddress(address)) {
            this.classList.add('is-invalid');
            showAddressError('Adresse trop courte');
            return;
        }
        
        // API validation with delay (debounce)
        validationTimeout = setTimeout(async () => {
            try {
                const result = await window.zipCodeAPI.validateAddress(address, zipCode);
                
                if (result.valid) {
                    this.classList.remove('is-invalid');
                    showAddressSuccess(`Livraison disponible (${result.distance}km)`);
                } else {
                    this.classList.add('is-invalid');
                    showAddressError(result.error || 'Livraison non disponible pour cette adresse');
                }
            } catch (error) {
                this.classList.add('is-invalid');
                showAddressError('Erreur lors de la vérification de l\'adresse');
            }
        }, 800); // 800ms delay for address (longer than for postal code)
    });
    
    // Clear on focus
    addressInput.addEventListener('focus', function() {
        this.classList.remove('is-invalid');
        removeAddressError();
    });
}

// Show address validation error
function showAddressError(message) {
    removeAddressError();
    
    const addressInput = document.getElementById('deliveryAddress');
    if (!addressInput) return;
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback address-validation-error';
    errorDiv.textContent = message;
    
    addressInput.parentNode.appendChild(errorDiv);
}

// Show successful address validation
function showAddressSuccess(message) {
    removeAddressError();
    
    const addressInput = document.getElementById('deliveryAddress');
    if (!addressInput) return;
    
    const successDiv = document.createElement('div');
    successDiv.className = 'valid-feedback address-validation-success';
    successDiv.textContent = message;
    
    addressInput.parentNode.appendChild(successDiv);
}

// Remove address validation messages
function removeAddressError() {
    const existingError = document.querySelector('.address-validation-error');
    const existingSuccess = document.querySelector('.address-validation-success');
    
    if (existingError) existingError.remove();
    if (existingSuccess) existingSuccess.remove();
}

// Globals
window.nextStep = nextStep;
window.prevStep = prevStep;
window.confirmOrder = confirmOrder;

// Time validation
function initTimeValidation() {
    const dateInput = document.getElementById('deliveryDate');
    const timeSelect = document.getElementById('deliveryTime');
    if (dateInput && timeSelect) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today; dateInput.value = today;
        dateInput.addEventListener('change', updateTimeOptions);
        timeSelect.addEventListener('change', validateSelectedTime);
        updateTimeOptions();
    }
}

function updateTimeOptions() {
    const dateInput = document.getElementById('deliveryDate');
    const timeSelect = document.getElementById('deliveryTime');
    if (!dateInput || !timeSelect) return;
    const selectedDate = dateInput.value;
    const today = new Date().toISOString().split('T')[0];
    const currentTime = new Date();
    timeSelect.innerHTML = '<option value="">Choisir un créneau</option>';
    const timeSlots = [
        { value: '07:00', text: '07h00 - 07h30' },
        { value: '07:30', text: '07h30 - 08h00' },
        { value: '08:00', text: '08h00 - 08h30' },
        { value: '08:30', text: '08h30 - 09h00' },
        { value: '09:00', text: '09h00 - 09h30' },
        { value: '09:30', text: '09h30 - 10h00' },
        { value: '10:00', text: '10h00 - 10h30' },
        { value: '10:30', text: '10h30 - 11h00' },
        { value: '11:00', text: '11h00 - 11h30' },
        { value: '11:30', text: '11h30 - 12h00' },
        { value: '12:00', text: '12h00 - 12h30' },
        { value: '12:30', text: '12h30 - 13h00' },
        { value: '13:00', text: '13h00 - 13h30' },
        { value: '13:30', text: '13h30 - 14h00' },
        { value: '14:00', text: '14h00 - 14h30' },
        { value: '14:30', text: '14h30 - 15h00' },
        { value: '15:00', text: '15h00 - 15h30' },
        { value: '15:30', text: '15h30 - 16h00' },
        { value: '16:00', text: '16h00 - 16h30' },
        { value: '16:30', text: '16h30 - 17h00' },
        { value: '17:00', text: '17h00 - 17h30' },
        { value: '17:30', text: '17h30 - 18h00' },
        { value: '18:00', text: '18h00 - 18h30' },
        { value: '18:30', text: '18h30 - 19h00' },
        { value: '19:00', text: '19h00 - 19h30' },
        { value: '19:30', text: '19h30 - 20h00' },
        { value: '20:00', text: '20h00 - 20h30' },
        { value: '20:30', text: '20h30 - 21h00' },
        { value: '21:00', text: '21h00 - 21h30' },
        { value: '21:30', text: '21h30 - 22h00' },
        { value: '22:00', text: '22h00 - 22h30' },
        { value: '22:30', text: '22h30 - 23h00' }
    ];
    if (selectedDate === today) {
        // Add 1 hour to current time for minimum delay
        const minimumTime = new Date(currentTime.getTime() + 60 * 60 * 1000);
        timeSlots.forEach(slot => { 
            const t = new Date(`${selectedDate}T${slot.value}`); 
            if (t > minimumTime) { 
                const o = document.createElement('option'); 
                o.value = slot.value; 
                o.textContent = slot.text; 
                timeSelect.appendChild(o); 
            } 
        });
    } else {
        timeSlots.forEach(slot => { const o = document.createElement('option'); o.value = slot.value; o.textContent = slot.text; timeSelect.appendChild(o); });
    }
    if (selectedDate === today && timeSelect.options.length === 1) {
        const o = document.createElement('option'); o.value = ''; o.textContent = 'Aucun créneau disponible aujourd\'hui'; o.disabled = true; timeSelect.appendChild(o);
    }
}

function validateSelectedTime() {
    const dateInput = document.getElementById('deliveryDate');
    const timeSelect = document.getElementById('deliveryTime');
    if (!dateInput || !timeSelect) return true;
    const selectedDate = dateInput.value; 
    const selectedTime = timeSelect.value;
    const today = new Date().toISOString().split('T')[0]; 
    const currentTime = new Date();
    if (selectedDate === today && selectedTime) {
        const dt = new Date(`${selectedDate}T${selectedTime}`);
        // Add 1 hour to current time for minimum delay
        const minimumTime = new Date(currentTime.getTime() + 60 * 60 * 1000);
        if (dt <= minimumTime) { 
            showOrderNotification('Le créneau doit être au minimum 1 heure après l\'heure actuelle. Veuillez choisir un autre créneau.', 'error'); 
            timeSelect.value = ''; 
            return false; 
        }
    }
    return true;
}
