// Order page JavaScript — single implementation modeled after Restaurant

let currentStep = 1;
let orderData = { items: [], delivery: {}, payment: {}, total: 0 };

// Simple Order API client using the new backend endpoints
window.orderAPI = {
    async createOrder(payload) {
        const res = await fetch('/api/order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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

    window.addEventListener('cartUpdated', async function() {
        await loadCartItems();
        updateOrderSummary();
    });
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
            if (this.value === 'delivery') { if (details) details.style.display = 'block'; updateDeliveryFee(3); }
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
    let subtotal = 0; let html = '';
    orderData.items.forEach(it => {
        const itemTotal = Number(it.price) * Number(it.quantity);
        subtotal += itemTotal;
        html += `<div class="summary-item"><div class="summary-item-info"><span class="summary-item-name">${it.name}</span><small class="text-muted">x${it.quantity}</small></div><span class="summary-item-price">${itemTotal.toFixed(2)}€</span></div>`;
    });
    container.innerHTML = html;

    const deliveryFee = orderData.deliveryFee || 0;
    const tax = subtotal * 0.10;
    const total = subtotal + deliveryFee + tax;
    const subEl = document.getElementById('subtotal');
    const taxEl = document.getElementById('taxAmount');
    const totalEl = document.getElementById('totalAmount');
    if (subEl) subEl.textContent = subtotal.toFixed(2) + '€';
    if (taxEl) taxEl.textContent = tax.toFixed(2) + '€';
    if (totalEl) totalEl.textContent = total.toFixed(2) + '€';
    orderData.subtotal = subtotal; orderData.taxAmount = tax; orderData.total = total;
}

function nextStep(step) { if (validateCurrentStep()) { showStep(step); } }
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

function validateCurrentStep() {
    switch (currentStep) {
        case 1: return validateCartStep();
        case 2: return validateDeliveryStep();
        case 3: return validatePaymentStep();
        default: return true;
    }
}

function validateCartStep() { if ((orderData.items || []).length === 0) { showNotification('Votre panier est vide', 'error'); return false; } return true; }

function validateDeliveryStep() {
    const mode = document.querySelector('input[name="deliveryMode"]:checked')?.value;
    const date = document.getElementById('deliveryDate')?.value;
    const time = document.getElementById('deliveryTime')?.value;
    if (!mode) { showNotification('Veuillez choisir un mode de récupération', 'error'); return false; }
    if (!date) { showNotification('Veuillez choisir une date', 'error'); return false; }
    if (!time) { showNotification('Veuillez choisir un créneau horaire', 'error'); return false; }
    if (!validateSelectedTime()) return false;
    if (mode === 'delivery') {
        const address = document.getElementById('deliveryAddress')?.value;
        const zip = document.getElementById('deliveryZip')?.value;
        if (!address || !zip) { showNotification('Veuillez renseigner votre adresse de livraison', 'error'); return false; }
    }
    orderData.delivery = { mode, date, time, address: document.getElementById('deliveryAddress')?.value, zip: document.getElementById('deliveryZip')?.value, instructions: document.getElementById('deliveryInstructions')?.value };
    return true;
}

function validatePaymentStep() {
    const mode = document.querySelector('input[name="paymentMode"]:checked')?.value;
    if (!mode) { showNotification('Veuillez choisir un mode de paiement', 'error'); return false; }
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
    if (!accept) { showNotification('Veuillez accepter les conditions générales', 'error'); return; }

    // Build payload expected by backend API
    const payload = {
        deliveryMode: orderData?.delivery?.mode || document.querySelector('input[name="deliveryMode"]:checked')?.value || 'delivery',
        deliveryAddress: document.getElementById('deliveryAddress')?.value || null,
        deliveryZip: document.getElementById('deliveryZip')?.value || null,
        deliveryInstructions: document.getElementById('deliveryInstructions')?.value || null,
        deliveryFee: typeof orderData.deliveryFee === 'number' ? orderData.deliveryFee : (document.querySelector('input[name="deliveryMode"]:checked')?.value === 'pickup' ? 0 : 5),
        paymentMode: orderData?.payment?.mode || document.querySelector('input[name="paymentMode"]:checked')?.value || 'card'
    };

    try {
        const result = await window.orderAPI.createOrder(payload);
        const created = result.order; // OrderResponse
        // Backend уже очищает корзину, обновим UI
        try { if (window.updateCartSidebar) window.updateCartSidebar(); } catch (_) {}
        try { if (window.updateCartNavigation) window.updateCartNavigation(); } catch (_) {}
        showOrderConfirmation(created.no, created.id, created.total);
    } catch (e) {
        showNotification(e.message || 'Erreur lors de la création de la commande', 'error');
    }
}

function sendConfirmationEmail(order) {
    const emailData = { to: 'client@example.com', subject: `Confirmation de commande ${order.id} - Le Trois Quarts`, body: `Votre commande ${order.id} a été confirmée. Total: ${order.total.toFixed(2)}€` };
    const emails = JSON.parse(localStorage.getItem('sentEmails') || '[]'); emails.push({ ...emailData, sentAt: new Date().toISOString(), orderId: order.id }); localStorage.setItem('sentEmails', JSON.stringify(emails));
    showNotification('Email de confirmation envoyé !', 'success');
}

function showOrderConfirmation(orderNo, orderId, total) {
    document.querySelectorAll('.order-step-content').forEach(c => c.classList.remove('active'));
    const container = document.querySelector('.order-section .container');
    if (container) {
        container.innerHTML = `<div class="text-center py-5"><div class="mb-4"><i class="bi bi-check-circle-fill text-success icon-success-large"></i></div><h2 class="text-success mb-3">Commande confirmée !</h2><p class="lead mb-2">Numéro de commande: <strong>${orderNo || orderId}</strong></p><p class="lead mb-4">Montant total: <strong>${Number(total || 0).toFixed(2)}€</strong></p><div class="alert alert-info"><h5><i class="bi bi-info-circle me-2"></i>Prochaines étapes :</h5><ul class="list-unstyled mb-0"><li>• Vous recevrez un email de confirmation</li><li>• Votre commande sera préparée selon le créneau choisi</li></ul></div><div class="mt-4"><a href="${window.appMenuPath || '#'}" class="btn btn-primary"><i class="bi bi-arrow-left me-2"></i>Retour au menu</a></div></div>`;
    }
    showNotification('Commande confirmée avec succès !', 'success');
}

function showNotification(message, type = 'info') {
    document.querySelectorAll('.notification').forEach(n => n.remove());
    const n = document.createElement('div');
    n.className = `notification alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show`;
    n.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(n);
    setTimeout(() => { if (n.parentNode) n.remove(); }, 5000);
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
        { value: '12:00', text: '12h00 - 12h30' },
        { value: '12:30', text: '12h30 - 13h00' },
        { value: '13:00', text: '13h00 - 13h30' },
        { value: '19:00', text: '19h00 - 19h30' },
        { value: '19:30', text: '19h30 - 20h00' },
        { value: '20:00', text: '20h00 - 20h30' }
    ];
    if (selectedDate === today) {
        timeSlots.forEach(slot => { const t = new Date(`${selectedDate}T${slot.value}`); if (t > currentTime) { const o = document.createElement('option'); o.value = slot.value; o.textContent = slot.text; timeSelect.appendChild(o); } });
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
    const selectedDate = dateInput.value; const selectedTime = timeSelect.value;
    const today = new Date().toISOString().split('T')[0]; const currentTime = new Date();
    if (selectedDate === today && selectedTime) {
        const dt = new Date(`${selectedDate}T${selectedTime}`);
        if (dt <= currentTime) { showNotification('Ce créneau n\'est plus disponible. Veuillez choisir un autre créneau.', 'error'); timeSelect.value = ''; return false; }
    }
    return true;
}
