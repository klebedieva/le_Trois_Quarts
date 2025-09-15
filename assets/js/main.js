// Main JavaScript file for Le Trois Quarts website

// Global cart toggle function
window.toggleCart = function() {
    const cartSidebar = document.getElementById('cartSidebar');
    if (cartSidebar) {
        cartSidebar.classList.toggle('open');
        if (cartSidebar.classList.contains('open')) {
            document.body.style.overflow = 'hidden'; // Prevent scrolling when cart is open
            // Set cart as active when opened
            window.cartIsActive = true;
        } else {
            document.body.style.overflow = 'auto';
            // Clear cart active state when closed
            window.cartIsActive = false;
        }
    }
};

// Function to reset cart active state after a delay
window.resetCartActiveState = function() {
    setTimeout(() => {
        window.cartIsActive = false;
    }, 2000); // Reset after 2 seconds of inactivity
};

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initNavbar();
    initCartNavigation();
    
    // Only initialize gallery if gallery items exist
    if (document.querySelectorAll('.gallery-item').length > 0) {
        initGallery();
    }
    
    // Only initialize reservation form if it exists
    if (document.getElementById('reservationForm')) {
        initReservationForm();
    }
    
    // Initialize menu functionality if on menu page
    if (document.getElementById('menuGrid')) {
        initMenu();
    }
    
    initSmoothScrolling();
    initAnimations();
});

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
    
    // Close cart when clicking outside (but not when cart is active from quantity changes)
    document.addEventListener('click', function(e) {
        if (cartSidebar && cartSidebar.classList.contains('open')) {
            // Don't close if clicking on cart controls or if cart was recently active
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
    
    // Update cart count in navigation
    updateCartNavigation();
    
    // Initialize cart sidebar
    initCartSidebar();
}

function initCartSidebar() {
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const orderBtn = document.getElementById('orderBtn');
    const clearCartBtn = document.getElementById('clearCart');
    
    if (cartItems && cartTotal) {
        updateCartSidebar();
    }
    
    if (orderBtn) {
        orderBtn.addEventListener('click', function() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            if (cart.length > 0) {
                window.location.href = 'order.html';
            } else {
                showNotification('Votre panier est vide', 'warning');
            }
        });
    }
    
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', function() {
            showConfirmDialog(
                'Vider le panier',
                'Êtes-vous sûr de vouloir vider complètement votre panier ?',
                function() {
                    localStorage.setItem('cart', JSON.stringify([]));
                    updateCartNavigation();
                    updateCartSidebar();
                    // Ensure page can scroll again if cart sidebar was open
                    const cartSidebarEl = document.getElementById('cartSidebar');
                    if (cartSidebarEl && cartSidebarEl.classList.contains('open')) {
                        cartSidebarEl.classList.remove('open');
                        document.body.style.overflow = 'auto';
                        window.cartIsActive = false;
                    }
                    
                    // Update menu display if on menu page
                    if (window.renderMenu) {
                        window.renderMenu();
                    }
                    
                    showNotification('Panier vidé avec succès', 'success');
                }
            );
        });
    }
}

function updateCartSidebar() {
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const clearCartBtn = document.getElementById('clearCart');
    
    if (!cartItems || !cartTotal) return;
    
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    
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
    
    let total = 0;
    let itemsHTML = '';
    
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
    
    cartItems.innerHTML = itemsHTML;
    cartTotal.textContent = total.toFixed(2) + '€';
}

// Global cart functions for sidebar
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
        updateCartNavigation();
        updateCartSidebar();
        
        // Update menu display if on menu page
        if (window.renderMenu) {
            window.renderMenu();
        }
        
        // Keep cart open when modifying quantities
        window.cartIsActive = true;
        window.resetCartActiveState();
        
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
        updateCartNavigation();
        updateCartSidebar();
        
        // Update menu display if on menu page
        if (window.renderMenu) {
            window.renderMenu();
        }
        
        // Keep cart open when modifying quantities
        window.cartIsActive = true;
        window.resetCartActiveState();
        
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
        
        showNotification('Quantité augmentée', 'success');
    }
};

// Special function for menu items
window.addMenuItemToCart = function(itemId, menuItems) {
    const item = menuItems.find(i => i.id === itemId);
    if (item) {
        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const existingItem = cart.find(cartItem => cartItem.id === itemId);
        if (existingItem) {
            existingItem.quantity++;
        } else {
            cart.push({ ...item, quantity: 1 });
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartNavigation();
        updateCartSidebar();
        showNotification(`${item.name} ajouté au panier`, 'success');
        return true;
    }
    return false;
};

window.removeMenuItemFromCart = function(itemId) {
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const index = cart.findIndex(cartItem => cartItem.id === itemId);
    if (index !== -1) {
        cart[index].quantity--;
        if (cart[index].quantity <= 0) {
            cart.splice(index, 1);
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartNavigation();
        updateCartSidebar();
        showNotification('Quantité diminuée', 'success');
        return true;
    }
    return false;
};

window.getMenuItemQuantity = function(itemId) {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const item = cart.find(cartItem => cartItem.id === itemId);
    return item ? item.quantity : 0;
};

function updateCartNavigation() {
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
    updateCartSidebar();
}

// Navbar functionality
function initNavbar() {
    const navbar = document.getElementById('mainNav');
    const logoImg = navbar ? navbar.querySelector('.navbar-brand img') : null;
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 100) {
            navbar.classList.add('scrolled');
            if (logoImg) {
                logoImg.src = '/logo2.png';
                logoImg.style.boxShadow = 'none';
            }
        } else {
            navbar.classList.remove('scrolled');
            if (logoImg) {
                logoImg.src = '/logo-footer1.png';
                logoImg.style.boxShadow = '';
            }
        }
    });
    if (logoImg) {
        logoImg.addEventListener('mouseenter', function() {
            this.style.boxShadow = '0 4px 16px rgba(212, 165, 116, 0.25)';
        });
        logoImg.addEventListener('mouseleave', function() {
            if (window.scrollY > 100) {
                this.style.boxShadow = 'none';
            } else {
                this.style.boxShadow = '';
        }
    });
    }
    // Close mobile menu when clicking on a link
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (navbarCollapse.classList.contains('show')) {
                const bsCollapse = new bootstrap.Collapse(navbarCollapse);
                bsCollapse.hide();
            }
        });
    });
}

// Gallery lightbox functionality
function initGallery() {
    const galleryItems = document.querySelectorAll('.gallery-item');
    const modalImage = document.getElementById('modalImage');
    const modal = document.getElementById('galleryModal');
    const prevBtn = document.getElementById('galleryPrev');
    const nextBtn = document.getElementById('galleryNext');
    const currentIndexSpan = document.getElementById('currentImageIndex');
    const totalImagesSpan = document.getElementById('totalImages');
    
    // Check if elements exist before proceeding
    if (galleryItems.length === 0 || !modalImage) {
        return;
    }
    
    // Collect all gallery images
    const galleryImages = Array.from(galleryItems).map(item => ({
        src: item.getAttribute('data-image'),
        alt: item.querySelector('img').getAttribute('alt')
    }));
    
    let currentImageIndex = 0;
    
    // Update counter display
    function updateCounter() {
        if (currentIndexSpan) {
            currentIndexSpan.textContent = currentImageIndex + 1;
        }
        if (totalImagesSpan) {
            totalImagesSpan.textContent = galleryImages.length;
        }
    }
    
    // Show image at specific index
    function showImage(index) {
        if (index < 0) {
            currentImageIndex = galleryImages.length - 1;
        } else if (index >= galleryImages.length) {
            currentImageIndex = 0;
        } else {
            currentImageIndex = index;
        }
        
        const currentImage = galleryImages[currentImageIndex];
        modalImage.src = currentImage.src;
        modalImage.alt = currentImage.alt;
        
        updateCounter();
        
        // Show/hide navigation buttons
        if (prevBtn) {
            prevBtn.style.display = galleryImages.length > 1 ? 'flex' : 'none';
        }
        if (nextBtn) {
            nextBtn.style.display = galleryImages.length > 1 ? 'flex' : 'none';
        }
    }
    
    // Add click events to gallery items
    galleryItems.forEach((item, index) => {
        item.addEventListener('click', function() {
            currentImageIndex = index;
            showImage(currentImageIndex);
        });
    });
    
    // Add navigation button events
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            showImage(currentImageIndex - 1);
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            showImage(currentImageIndex + 1);
        });
    }
    
    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (modal && modal.classList.contains('show')) {
            if (e.key === 'ArrowLeft') {
                showImage(currentImageIndex - 1);
            } else if (e.key === 'ArrowRight') {
                showImage(currentImageIndex + 1);
            } else if (e.key === 'Escape') {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }
    });
    
    // Initialize counter
    updateCounter();
}

// Reservation form functionality
function initReservationForm() {
    const form = document.getElementById('reservationForm');
    const dateInput = document.getElementById('date');
    
    // Check if elements exist before proceeding
    if (!form || !dateInput) {
        return;
    }
    
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    dateInput.setAttribute('min', today);
    
    // Add input event listeners to clear errors when user starts typing
    const formInputs = form.querySelectorAll('input, select, textarea');
    formInputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                const errorElement = this.parentNode.querySelector('.invalid-feedback');
                if (errorElement) {
                    errorElement.remove();
                }
            }
        });
    });
    
    // Initialize time validation for reservation form
    initReservationTimeValidation();
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data manually to ensure we get all values
        const data = {
            firstName: document.getElementById('firstName')?.value || '',
            lastName: document.getElementById('lastName')?.value || '',
            email: document.getElementById('email')?.value || '',
            phone: document.getElementById('phone')?.value || '',
            date: document.getElementById('date')?.value || '',
            time: document.getElementById('time')?.value || '',
            guests: document.getElementById('guests')?.value || '',
            message: document.getElementById('message')?.value || ''
        };
        
        // Clear previous error states
        clearFormErrors();
        
        // Validate form
        if (validateReservationForm(data)) {
            // Show success message
            showNotification('Votre réservation a été envoyée avec succès ! Nous vous confirmerons par email.', 'success');
            
            // Reset form
            form.reset();
            
            // In a real application, you would send this data to your server
            console.log('Reservation data:', data);
        }
    });
}

// Form validation
function validateReservationForm(data) {
    let isValid = true;
    const requiredFields = ['firstName', 'lastName', 'email', 'phone', 'date', 'time', 'guests'];
    
    // Check required fields
    for (let field of requiredFields) {
        if (!data[field] || data[field].trim() === '') {
            showFieldError(field, `Le champ ${getFieldLabel(field)} est requis.`);
            isValid = false;
        }
    }
    
    // Validate email
    if (data.email && data.email.trim() !== '') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(data.email)) {
            showFieldError('email', 'Veuillez entrer une adresse email valide.');
            isValid = false;
        }
    }
    
    // Validate phone
    if (data.phone && data.phone.trim() !== '') {
        const phoneRegex = /^[\d\s\-\+\(\)]{8,}$/;
        if (!phoneRegex.test(data.phone)) {
            showFieldError('phone', 'Veuillez entrer un numéro de téléphone valide (minimum 8 chiffres).');
            isValid = false;
        }
    }
    
    // Validate date (not in the past)
    if (data.date && data.date.trim() !== '') {
        const selectedDate = new Date(data.date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            showFieldError('date', 'La date de réservation ne peut pas être dans le passé.');
            isValid = false;
        }
    }
    
    // Validate time (not in the past for today's date)
    if (data.date && data.time && data.date.trim() !== '' && data.time.trim() !== '') {
        const selectedDate = new Date(data.date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // If it's today's date, check if the time has passed
        if (selectedDate.getTime() === today.getTime()) {
            const selectedDateTime = new Date(`${data.date}T${data.time}`);
            const currentTime = new Date();
            
            if (selectedDateTime <= currentTime) {
                showFieldError('time', 'Ce créneau n\'est plus disponible. Veuillez choisir un autre créneau.');
                isValid = false;
            }
        }
    }
    
    if (!isValid) {
        showNotification('Veuillez corriger les erreurs dans le formulaire.', 'error');
    }
    
    return isValid;
}

// Get field label for validation messages
function getFieldLabel(field) {
    const labels = {
        firstName: 'Prénom',
        lastName: 'Nom',
        email: 'Email',
        phone: 'Téléphone',
        date: 'Date',
        time: 'Heure',
        guests: 'Nombre de personnes'
    };
    return labels[field] || field;
}

// Clear form error states
function clearFormErrors() {
    const form = document.getElementById('reservationForm');
    if (form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.classList.remove('is-invalid');
            const errorElement = input.parentNode.querySelector('.invalid-feedback');
            if (errorElement) {
                errorElement.remove();
            }
        });
    }
}

// Show field error
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.classList.add('is-invalid');
        
        // Remove existing error message
        const existingError = field.parentNode.querySelector('.invalid-feedback');
        if (existingError) {
            existingError.remove();
        }
        
        // Add new error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }
}

// Initialize time validation for reservation form
function initReservationTimeValidation() {
    const dateInput = document.getElementById('date');
    const timeSelect = document.getElementById('time');
    
    if (dateInput && timeSelect) {
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;
        dateInput.value = today; // Set today's date as default
        
        // Add event listeners
        dateInput.addEventListener('change', updateReservationTimeOptions);
        timeSelect.addEventListener('change', validateReservationSelectedTime);
        
        // Initialize time options
        updateReservationTimeOptions();
    }
}

// Update available time slots for reservation
function updateReservationTimeOptions() {
    const dateInput = document.getElementById('date');
    const timeSelect = document.getElementById('time');
    
    if (!dateInput || !timeSelect) return;
    
    const selectedDate = dateInput.value;
    const today = new Date().toISOString().split('T')[0];
    const currentTime = new Date();
    
    // Clear current options
    timeSelect.innerHTML = '<option value="">Choisir...</option>';
    
    // Clear any existing error on time field
    if (timeSelect.classList.contains('is-invalid')) {
        timeSelect.classList.remove('is-invalid');
        const errorElement = timeSelect.parentNode.querySelector('.invalid-feedback');
        if (errorElement) {
            errorElement.remove();
        }
    }
    
    // Define time slots for restaurant reservations (08:00 to 22:30)
    const timeSlots = [
        { value: '08:00', text: '08:00' },
        { value: '08:30', text: '08:30' },
        { value: '09:00', text: '09:00' },
        { value: '09:30', text: '09:30' },
        { value: '10:00', text: '10:00' },
        { value: '10:30', text: '10:30' },
        { value: '11:00', text: '11:00' },
        { value: '11:30', text: '11:30' },
        { value: '12:00', text: '12:00' },
        { value: '12:30', text: '12:30' },
        { value: '13:00', text: '13:00' },
        { value: '13:30', text: '13:30' },
        { value: '14:00', text: '14:00' },
        { value: '14:30', text: '14:30' },
        { value: '15:00', text: '15:00' },
        { value: '15:30', text: '15:30' },
        { value: '16:00', text: '16:00' },
        { value: '16:30', text: '16:30' },
        { value: '17:00', text: '17:00' },
        { value: '17:30', text: '17:30' },
        { value: '18:00', text: '18:00' },
        { value: '18:30', text: '18:30' },
        { value: '19:00', text: '19:00' },
        { value: '19:30', text: '19:30' },
        { value: '20:00', text: '20:00' },
        { value: '20:30', text: '20:30' },
        { value: '21:00', text: '21:00' },
        { value: '21:30', text: '21:30' },
        { value: '22:00', text: '22:00' },
        { value: '22:30', text: '22:30' }
    ];
    
    // If today's date is selected, filter out past times
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
        // For future dates, show all slots
        timeSlots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.value;
            option.textContent = slot.text;
            timeSelect.appendChild(option);
        });
    }
    
    // If no slots available for today, show message
    if (selectedDate === today && timeSelect.options.length === 1) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Aucun créneau disponible aujourd\'hui';
        option.disabled = true;
        timeSelect.appendChild(option);
    }
}

// Validate selected reservation time
function validateReservationSelectedTime() {
    const dateInput = document.getElementById('date');
    const timeSelect = document.getElementById('time');
    
    if (!dateInput || !timeSelect) return true;
    
    const selectedDate = dateInput.value;
    const selectedTime = timeSelect.value;
    const today = new Date().toISOString().split('T')[0];
    const currentTime = new Date();
    
    if (selectedDate === today && selectedTime) {
        const selectedDateTime = new Date(`${selectedDate}T${selectedTime}`);
        
        if (selectedDateTime <= currentTime) {
            showFieldError('time', 'Ce créneau n\'est plus disponible. Veuillez choisir un autre créneau.');
            timeSelect.value = '';
            return false;
        }
    }
    
    return true;
}

// Smooth scrolling for anchor links
function initSmoothScrolling() {
    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            
            // Skip if targetId is just '#' (invalid selector)
            if (targetId === '#') {
                return;
            }
            
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const offsetTop = targetElement.offsetTop - 80; // Account for fixed navbar
                
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Scroll indicator functionality
    const scrollIndicator = document.querySelector('.scroll-indicator');
    if (scrollIndicator) {
        scrollIndicator.addEventListener('click', function() {
            const aboutSection = document.getElementById('about');
            if (aboutSection) {
                aboutSection.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }
}

// Initialize scroll animations
function initAnimations() {
    // Intersection Observer for fade-in animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('loading');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe elements for animation
    const animatedElements = document.querySelectorAll('.menu-category-card, .gallery-item, .contact-info, .feature-item');
    animatedElements.forEach(el => {
        observer.observe(el);
    });
}

// Notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show`;
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        opacity: 0;
        transition: opacity 0.3s ease-in;
    `;
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.style.opacity='0'; setTimeout(() => this.parentElement.remove(), 500)"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Trigger fade in animation
    setTimeout(() => {
        notification.style.opacity = '1';
    }, 10);
    
    // Auto-remove after 5 seconds with fade out animation
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.transition = 'opacity 0.5s ease-out';
            notification.style.opacity = '0';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 500);
        }
    }, 5000);
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Handle window resize
window.addEventListener('resize', debounce(function() {
    // Recalculate any layout-dependent elements if needed
}, 250));

// Handle page visibility change
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Page is hidden
        console.log('Page hidden');
    } else {
        // Page is visible
        console.log('Page visible');
    }
});

// Error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    // In production, you might want to send this to an error tracking service
});

// Performance monitoring
window.addEventListener('load', function() {
    // Log page load time
    const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
    console.log('Page load time:', loadTime + 'ms');
});



// Menu data and functions
window.menuItems = [

    {
        id: '15',
        name: 'Asperges Printemps à la Ricotta',
        description: 'Asperges vertes fraîches, crème de ricotta maison, oignons marinés et graines de moutarde toastées. Un contraste de textures et de saveurs végétariennes.',
        price: 14,
        image: 'assets/img/entrees/entree_1.png',
        category: 'entrees',
        tags: ['vegetarian'],
        badges: ['Végétarien', 'Fait maison']
    },
    {
        id: '17',
        name: 'Œuf Mollet au Safran et Petits Pois',
        description: 'Œuf mollet au safran, crème onctueuse de petits pois et tuiles noires au sésame. Un plat végétarien raffiné aux saveurs printanières.',
        price: 13,
        image: 'assets/img/entrees/entree_2.png',
        category: 'entrees',
        tags: ['vegetarian'],
        badges: ['Végétarien', 'Fait maison']
    },
    {
        id: '19',
        name: 'Seiches Sautées à la Chermoula',
        description: 'Seiches sautées, chermoula aux jeunes pousses d\'épinards, coulis de betteraves et fêta. Un plat méditerranéen aux saveurs marocaines.',
        ingredients: 'seiches, jeunes pousses d\'épinards, betteraves, fêta, ail, coriandre, citron, huile d\'olive, épices marocaines, sel, poivre',
        allergens: ['Poisson', 'Lactose'],
        price: 15,
        image: 'assets/img/entrees/entree_3.png',
        category: 'entrees',
        tags: [],
        badges: ['Méditerranéen', 'Fait maison']
    },
    {
        id: '2',
        name: 'Boulette d\'agneau',
        description: 'Boulettes d\'agneau parfumées aux herbes, carottes rôties au cumin et miel, yaourt grec à la citronnelle et miel, accompagné de riz basmati.',
        ingredients: 'agneau haché, oignon, ail, persil, cumin, paprika, carotte, miel, yaourt grec, riz basmati',
        allergens: ['Lactose', 'Gluten'],
        price: 22,
        image: 'assets/img/plats/plat_1.png',
        category: 'plats',
        tags: [],
        badges: ['Maison']
    },
    {
        id: '3',
        name: 'Galinette poêlée à l\'ajo blanco',
        description: 'Filet de galinette poêlé à la perfection, servi avec une soupe froide traditionnelle à l\'ail et amandes, poivre du Sichuan et huile parfumée à la ciboulette.',
        ingredients: 'galinette, ail, amandes, pain rassis, huile d\'olive, poivre du Sichuan, ciboulette, vinaigre de cidre, sel, beurre',
        allergens: ['Gluten', 'Fruits à coque', 'Poisson'],
        price: 24,
        image: 'assets/img/plats/plat_2.png',
        category: 'plats',
        tags: [],
        badges: ['Traditionnel', 'Spécialité']
    },

    
    // Plats
    {
        id: '5',
        name: 'Sashimi de ventrèche de thon fumé',
        description: 'Sashimi de ventrèche de thon fumé au charbon, crème fumée et herbes fraîches, servi avec une sauce soja et wasabi.',
        ingredients: 'ventrèche de thon, crème fumée, charbon actif, herbes fraîches, sauce soja, wasabi, gingembre, citron, huile de sésame, sel, poivre',
        allergens: ['Poisson', 'Soja'],
        price: 24,
        image: 'assets/img/plats/plat_9.png',
        category: 'plats',
        tags: [],
        badges: ['Fusion', 'Spécialité']
    },
    {
        id: '6',
        name: 'Magret de canard au fenouil confit',
        description: 'Magret de canard, fenouil confit au vin blanc, crème de betterave et herbes fraîches.',
        ingredients: 'magret de canard, fenouil, vin blanc, betterave, crème fraîche, herbes fraîches, beurre, sel, poivre',
        allergens: ['Lactose'],
        price: 28,
        image: 'assets/img/plats/plat_7.png',
        category: 'plats',
        tags: [],
        badges: ['Traditionnel', 'Spécialité']
    },
    {
        id: '7',
        name: 'Velouté de châtaignes aux pleurottes',
        description: 'Velouté crémeux de châtaignes, pleurottes sautées et coppa grillée, parfumé aux herbes de Provence.',
        ingredients: 'châtaignes, pleurottes, coppa, crème fraîche, oignon, ail, herbes de Provence, beurre, huile d\'olive, sel, poivre, bouillon de légumes',
        allergens: ['Lactose'],
        price: 16,
        image: 'assets/img/plats/plat_8.png',
        category: 'plats',
        tags: [],
        badges: ['Traditionnel', 'Saison']
    },
    {
        id: '8',
        name: 'Spaghettis à l\'ail noir et parmesan',
        description: 'Spaghettis al dente, sauce au jus de veau parfumé à l\'ail noir, citron confit et parmesan affiné.',
        ingredients: 'spaghettis, jus de veau, ail noir, citron confit, parmesan, beurre, huile d\'olive, sel, poivre, herbes fraîches',
        allergens: ['Gluten', 'Lactose'],
        price: 20,
        image: 'assets/img/plats/plat_3.png',
        category: 'plats',
        tags: [],
        badges: ['Traditionnel']
    },

    {
        id: '10',
        name: 'Loup de mer aux pois chiches',
        description: 'Loup de mer grillé, salade de pois chiches, tomates séchées, petits pois et olives de Kalamata.',
        ingredients: 'loup de mer, pois chiches, tomates séchées, petits pois, olives de Kalamata, huile d\'olive, citron, ail, herbes fraîches, sel, poivre',
        allergens: ['Poisson'],
        price: 26,
        image: 'assets/img/plats/plat_5.png',
        category: 'plats',
        tags: [],
        badges: ['Traditionnel', 'Méditerranéen']
    },

    {
        id: '16',
        name: 'Potimarron Rôti aux Saveurs d\'Asie',
        description: 'Potimarron rôti au four, mousseline de chou-fleur, roquette fraîche et jaune d\'œuf confit au soja, parsemé de nori. Un plat végétarien fusion.',
        ingredients: 'potimarron, chou-fleur, roquette, œufs, sauce soja, nori, beurre, crème fraîche, sel, poivre, huile d\'olive',
        allergens: ['Lactose', 'Œufs', 'Soja'],
        price: 18,
        image: 'assets/img/plats/plat_10.png',
        category: 'plats',
        tags: ['vegetarian'],
        badges: ['Végétarien', 'Fusion']
    },


    // Desserts

    {
        id: '20',
        name: 'Tartelette aux Marrons Suisses',
        description: 'Tartelette aux marrons suisses, meringuée. Un dessert traditionnel aux saveurs automnales.',
        ingredients: 'marrons suisses, pâte sablée, meringue italienne, crème pâtissière, sucre, beurre, œufs',
        allergens: ['Gluten', 'Lactose', 'Œufs'],
        price: 8,
        image: 'assets/img/desserts/dessert_1.png',
        category: 'desserts',
        tags: ['vegetarian'],
        badges: ['Fait maison', 'Saison']
    },
    {
        id: '21',
        name: 'Tartelette Ricotta au Miel et Fraises',
        description: 'Tartelette ricotta au miel, fraises fraîches et compotée de rhubarbe. Un dessert printanier raffiné.',
        ingredients: 'ricotta, miel, fraises, rhubarbe, pâte sablée, sucre, beurre, œufs, vanille',
        allergens: ['Gluten', 'Lactose', 'Œufs'],
        price: 9,
        image: 'assets/img/desserts/dessert_2.png',
        category: 'desserts',
        tags: ['vegetarian'],
        badges: ['Fait maison', 'Saison']
    },
    {
        id: '22',
        name: 'Crémeux Yuzu aux Fruits Rouges',
        description: 'Crémeux yuzu, fruits rouges frais, meringues et noisettes. Un dessert fusion aux saveurs japonaises.',
        ingredients: 'yuzu, fruits rouges, meringues, noisettes, crème fraîche, sucre, œufs, vanille',
        allergens: ['Lactose', 'Œufs', 'Fruits à coque'],
        price: 10,
        image: 'assets/img/desserts/dessert_3.png',
        category: 'desserts',
        tags: ['vegetarian'],
        badges: ['Fait maison', 'Fusion']
    },
    {
        id: '23',
        name: 'Gaspacho Tomates et Melon',
        description: 'Gaspacho tomates, melon, basilic et fêta. Un plat rafraîchissant sans gluten aux saveurs méditerranéennes.',
        ingredients: 'tomates, melon, basilic, fêta, huile d\'olive, vinaigre, ail, sel, poivre',
        allergens: ['Lactose'],
        price: 12,
        image: 'assets/img/plats/plat_12.png',
        category: 'plats',
        tags: ['vegetarian', 'glutenFree'],
        badges: ['Sans Gluten', 'Méditerranéen']
    }
];

// Drinks data
window.drinksData = {
    vins: [
        { name: 'Côtes du Rhône rouge', price: '5€ / 25€' },
        { name: 'Rosé de Provence', price: '4€ / 20€' },
        { name: 'Blanc de Cassis', price: '5€ / 24€' }
    ],
    bieres: [
        { name: 'Pression 25cl', price: '3€' },
        { name: 'Pression 50cl', price: '5€' },
        { name: 'Bière artisanale', price: '6€' }
    ],
    chaudes: [
        { name: 'Café expresso', price: '2€' },
        { name: 'Cappuccino', price: '3€' },
        { name: 'Thé / Infusion', price: '2.5€' }
    ],
    fraiches: [
        { name: 'Jus de fruits frais', price: '4€' },
        { name: 'Sodas', price: '3€' },
        { name: 'Eau minérale', price: '2€' }
    ]
};

// --- Export cart functions globally ---
window.LesTroisQuarts = window.LesTroisQuarts || {};
window.LesTroisQuarts.addToCart = addToCart;
window.LesTroisQuarts.removeCartItem = removeFromCart;
window.LesTroisQuarts.clearCart = function() {
    localStorage.setItem('cart', JSON.stringify([]));
    updateCartNavigation();
    updateCartSidebar();
    
    // Update menu display if on menu page
    if (window.renderMenu) {
        window.renderMenu();
    }
    
    showNotification('Panier vidé avec succès', 'success');
};
window.LesTroisQuarts.updateCartItemQuantity = function(itemId, action) {
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const item = cart.find(item => item.id === itemId);
    
    if (item) {
        if (action === 'increase') {
            item.quantity += 1;
            showNotification('Quantité augmentée', 'success');
        } else if (action === 'decrease') {
            item.quantity -= 1;
            if (item.quantity <= 0) {
                // Remove item if quantity is 0
                cart = cart.filter(cartItem => cartItem.id !== itemId);
                showNotification(`${item.name} supprimé du panier`, 'info');
            } else {
                showNotification('Quantité diminuée', 'success');
            }
        }
        
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartNavigation();
        updateCartSidebar();
    }
};

// Make functions available globally for menu.html
window.updateCartNavigation = updateCartNavigation;
window.updateCartSidebar = updateCartSidebar;

// Make cart functions available globally
window.removeFromCart = removeFromCart;
window.addToCart = addToCart;
window.addMenuItemToCart = addMenuItemToCart;
window.removeMenuItemFromCart = removeMenuItemFromCart;
window.getMenuItemQuantity = getMenuItemQuantity;

// Make menu data available globally
window.menuItems = menuItems;
window.drinksData = drinksData;

// Bootstrap confirm dialog function
function showConfirmDialog(title, message, onConfirm) {
    // Remove existing modal if any
    const existingModal = document.getElementById('confirmModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal HTML
    const modalHTML = `
        <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmModalLabel">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-primary" id="confirmBtn">Confirmer</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Get modal element
    const modal = document.getElementById('confirmModal');
    const confirmBtn = document.getElementById('confirmBtn');
    
    // Show modal
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
    
    // Handle confirm button click
    confirmBtn.addEventListener('click', function() {
        bootstrapModal.hide();
        if (onConfirm) {
            onConfirm();
        }
    });
    
    // Clean up modal when hidden
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
        document.body.style.overflow = 'auto';
    });
}

// Make confirm dialog available globally
window.showConfirmDialog = showConfirmDialog;

