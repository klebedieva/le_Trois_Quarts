// Main JavaScript file for Le Trois Quarts website
// Version 2 - No global error notifications for reservation form

// Cart functionality moved to cart.js

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initNavbar();
    
    // Only initialize gallery if gallery items exist
    if (document.querySelectorAll('.gallery-item').length > 0) {
        initGallery();
    }
    
    // Reservation form is handled by the reservation page script; no legacy init here
    
    // Initialize menu functionality if on menu page
    if (document.getElementById('menuGrid')) {
        initMenu();
    }
    
    initSmoothScrolling();
    initAnimations();
});

// Cart navigation functionality moved to cart.js

// Cart functions moved to cart.js

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
    const prevBtn = document.getElementById('galleryPrev');
    const nextBtn = document.getElementById('galleryNext');
    const currentIndexSpan = document.getElementById('currentImageIndex');
    const totalImagesSpan = document.getElementById('totalImages');
    
    // Guard: ensure required elements exist
    if (galleryItems.length === 0 || !modalImage) {
        return;
    }
    
    // Collect all gallery images
    const galleryImages = Array.from(galleryItems).map(item => ({
        src: item.getAttribute('data-image'),
        alt: item.querySelector('img').getAttribute('alt')
    }));
    
    let currentImageIndex = 0;
    
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
    
    // Bind click events to gallery items
    galleryItems.forEach((item, index) => {
        item.addEventListener('click', function() {
            currentImageIndex = index;
            showImage(currentImageIndex);
        });
    });
    
    // Bind navigation button events
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
    
    // Initialize the counter
    updateCounter();
}

// Legacy reservation code removed - handled by reservation page script

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

// Notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create the notification element
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
    
    // Trigger fade-in
    setTimeout(() => {
        notification.style.opacity = '1';
    }, 10);
    
    // Auto-remove after 5 seconds with fade-out
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
    // Recalculate layout-dependent elements if needed
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
    // In production, send to an error tracking service
});

// Performance monitoring
window.addEventListener('load', function() {
    // Log page load time
    const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
    console.log('Page load time:', loadTime + 'ms');
});



// Menu data and helpers
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

    
    // Main courses
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

// Cart functions moved to cart.js

// Make menu data available globally
window.menuItems = menuItems;
window.drinksData = drinksData;

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

