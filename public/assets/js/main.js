// Main JavaScript file for Le Trois Quarts website
// Version 2 - No global error notifications for reservation form

// CSRF Token helper
window.getCsrfToken = function() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : null;
};

// Helper function for API requests with CSRF protection
window.apiRequest = function(url, options = {}) {
    const csrfToken = window.getCsrfToken();
    if (!csrfToken) {
        return Promise.reject(new Error('CSRF token not found'));
    }

    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        }
    };

    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    };

    return fetch(url, mergedOptions);
};

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initNavbar();
    
    // Only initialize homepage gallery if gallery items exist AND we are not on the gallery page (which uses .gallery-card)
    if (document.querySelectorAll('.gallery-item').length > 0 && !document.querySelector('.gallery-card')) {
        initGallery();
    }
    
    
    initSmoothScrolling();
    initAnimations();
});

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
    // Close the mobile menu when clicking a link
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
    // Support both ID variants used across pages
    const prevBtn = document.getElementById('galleryPrev') || document.getElementById('prevBtn');
    const nextBtn = document.getElementById('galleryNext') || document.getElementById('nextBtn');
    const currentIndexSpan = document.getElementById('currentImageIndex');
    const totalImagesSpan = document.getElementById('totalImages');

    // Simple in-memory cache to avoid refetching on every navigation
    let galleryCache = null;
    let cacheTimestamp = 0;
    const CACHE_DURATION = 90 * 1000; // 90 seconds
    
    // Guard: ensure required elements exist
    if (galleryItems.length === 0 || !modalImage) {
        return;
    }
    
    // Collect all gallery images
    let galleryImages = Array.from(galleryItems).map(item => ({
        src: item.getAttribute('data-image'),
        alt: item.querySelector('img').getAttribute('alt')
    }));
    
    let currentImageIndex = 0;
    
    // Function to refresh gallery images from API
    async function refreshGalleryImages() {
        // Use cache if fresh
        const now = Date.now();
        if (galleryCache && (now - cacheTimestamp) < CACHE_DURATION) {
            galleryImages = galleryCache;
            updateCounter();
            return;
        }
        
        try {
            const response = await fetch('/api/gallery?limit=20');
            const result = await response.json();
            
            if (result.success) {
                // Build DOM list (6 images actually rendered on homepage)
                const domItems = Array.from(document.querySelectorAll('.gallery-item'));
                const domSourcesInOrder = domItems.map(item => item.getAttribute('data-image'));
                const domSet = new Set(domSourcesInOrder);
                
                // Map API -> simple objects
                const apiImages = result.data.map(item => ({
                    src: item.imageUrl,
                    alt: item.title
                }));
                
                // Intersect by src and keep DOM order
                const intersected = domSourcesInOrder
                    .filter(src => domSet.has(src))
                    .map(src => apiImages.find(ai => ai.src === src) || { src, alt: '' });
                
                // Fallback if intersection empty (e.g., different base URLs)
                galleryImages = intersected.length > 0 ? intersected : domItems.map(item => ({
                    src: item.getAttribute('data-image'),
                    alt: item.querySelector('img').getAttribute('alt')
                }));
                
                // Save to cache
                galleryCache = galleryImages;
                cacheTimestamp = now;
                
                updateCounter();
            }
        } catch (error) {
            console.warn('Failed to fetch gallery images from API, falling back to DOM:', error);
            // Fallback to DOM method
            const newGalleryItems = document.querySelectorAll('.gallery-item');
            galleryImages = Array.from(newGalleryItems).map(item => ({
                src: item.getAttribute('data-image'),
                alt: item.querySelector('img').getAttribute('alt')
            }));
            updateCounter();
        }
    }
    
    // Update the counter
    function updateCounter() {
        if (currentIndexSpan) {
            currentIndexSpan.textContent = currentImageIndex + 1;
        }
        if (totalImagesSpan) {
            totalImagesSpan.textContent = galleryImages.length;
        }
    }
    
    // Show the image at a specific index
    async function showImage(index) {
        // Refresh gallery images to get latest data (but we still limit to DOM 6)
        await refreshGalleryImages();
        
        if (galleryImages.length === 0) {
            return;
        }
        
        if (index < 0) {
            currentImageIndex = galleryImages.length - 1;
        } else if (index >= galleryImages.length) {
            currentImageIndex = 0;
        } else {
            currentImageIndex = index;
        }
        
        const currentImage = galleryImages[currentImageIndex];
        modalImage.src = currentImage.src;
        modalImage.alt = currentImage.alt || '';
        
        // Populate title/description if available in DOM
        const domItem = document.querySelectorAll('.gallery-item')[currentImageIndex];
        const titleEl = document.getElementById('modalImageTitle');
        const descEl = document.getElementById('modalImageDescription');
        if (domItem) {
            const t = domItem.getAttribute('data-title') || '';
            const d = domItem.getAttribute('data-description') || '';
            if (titleEl) titleEl.textContent = t;
            if (descEl) descEl.textContent = d;
        }
        
        updateCounter();
        
        // Show/hide navigation buttons
        if (prevBtn) {
            prevBtn.style.display = galleryImages.length > 1 ? 'flex' : 'none';
        }
        if (nextBtn) {
            nextBtn.style.display = galleryImages.length > 1 ? 'flex' : 'none';
        }
    }
    
    // Bind click events to gallery items
    galleryItems.forEach((item, index) => {
        item.addEventListener('click', async function() {
            // Find the current item index in the current list
            const newIndex = Array.from(document.querySelectorAll('.gallery-item')).indexOf(this);
            currentImageIndex = newIndex >= 0 ? newIndex : index;
            await showImage(currentImageIndex);
        });
    });
    
    // Bind navigation button events
    if (prevBtn) {
        prevBtn.addEventListener('click', async function() {
            await showImage(currentImageIndex - 1);
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', async function() {
            await showImage(currentImageIndex + 1);
        });
    }
    
    // Add keyboard navigation
    document.addEventListener('keydown', async function(e) {
        if (modal && modal.classList.contains('show')) {
            if (e.key === 'ArrowLeft') {
                await showImage(currentImageIndex - 1);
            } else if (e.key === 'ArrowRight') {
                await showImage(currentImageIndex + 1);
            } else if (e.key === 'Escape') {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }
    });
    
    // Initialize the counter
    updateCounter();
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
    
    // Scroll indicator click behavior
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

// Initialize scroll-triggered animations
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


// Menu data comes from database via Twig template

// Drinks data comes from database via Twig template

// Bootstrap confirm dialog helper
function showConfirmDialog(title, message, onConfirm) {
    // Remove existing modal if present
    const existingModal = document.getElementById('confirmModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Build modal HTML
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
    
    // Append modal to the page
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Get modal elements
    const modal = document.getElementById('confirmModal');
    const confirmBtn = document.getElementById('confirmBtn');
    
    // Show the modal
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
    
    // Handle confirm button click
    function cleanupBackdrops() {
        // Force-remove any leftover bootstrap backdrops and classes
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = 'auto';
        document.body.style.paddingRight = '';
    }

    confirmBtn.addEventListener('click', function() {
        bootstrapModal.hide();
        // Defer cleanup to after hide animation
        setTimeout(cleanupBackdrops, 150);
        if (onConfirm) {
            onConfirm();
        }
    });
    
    // Cleanup when the modal is hidden
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
        cleanupBackdrops();
    });
}

// Expose confirm dialog globally
window.showConfirmDialog = showConfirmDialog;

// Global notification function for forms (centered)
window.showNotification = function(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create the notification element
    const notification = document.createElement('div');
    notification.className = `notification alert alert-${type === 'error' ? 'danger' : type} alert-dismissible show position-fixed`;
    notification.style.cssText = 'top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px; text-align: center;';
    
    const icon = type === 'success' ? 'bi-check-circle' : 
                 type === 'error' ? 'bi-exclamation-triangle' : 
                 type === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle';
    
    notification.innerHTML = `
        <i class="bi ${icon} me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-hide after 5 seconds
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
};

// Global notification function for menu and cart (right side)
window.showCartNotification = function(message, type = 'info') {
    // Remove existing cart notifications
    const existingNotifications = document.querySelectorAll('.cart-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create the notification element
    const notification = document.createElement('div');
    notification.className = `cart-notification alert alert-${type === 'error' ? 'danger' : type} alert-dismissible show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    
    const icon = type === 'success' ? 'bi-check-circle' : 
                 type === 'error' ? 'bi-exclamation-triangle' : 
                 type === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle';
    
    notification.innerHTML = `
        <i class="bi ${icon} me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-hide after 3 seconds (shorter for cart notifications)
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
    }, 3000);
};

