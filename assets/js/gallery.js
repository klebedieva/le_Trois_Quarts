// Gallery page JavaScript

let currentImageIndex = 0;
let galleryImages = [];
let currentFilter = 'all';

document.addEventListener('DOMContentLoaded', function() {
    initGalleryPage();
});

function initGalleryPage() {
    initGalleryFilters();
    initGalleryModal();
    initLoadMore();
    collectGalleryImages();
    initImageErrorHandling();
}

function initImageErrorHandling() {
    // Handle image loading errors
    const images = document.querySelectorAll('.gallery-card img');
    images.forEach(img => {
        const card = img.closest('.gallery-card');
        
        // Set initial state - images are already loaded
        img.classList.add('loaded');
        if (card) {
            card.classList.add('loaded');
        }
        
        img.addEventListener('error', function() {
            // Replace broken image with a placeholder
            this.src = 'https://images.pexels.com/photos/1640777/pexels-photo-1640777.jpeg?auto=compress&cs=tinysrgb&w=400';
            this.alt = 'Image non disponible';
            this.classList.remove('loaded', 'loading');
            this.classList.add('error');
            if (card) {
                card.classList.remove('loaded', 'loading');
                card.classList.add('error');
            }
            
            // Update the modal data as well
            if (card) {
                card.setAttribute('data-image', 'https://images.pexels.com/photos/1640777/pexels-photo-1640777.jpeg?auto=compress&cs=tinysrgb&w=800');
            }
        });
        
        // Add loading state for new images only
        img.addEventListener('load', function() {
            this.classList.remove('loading', 'error');
            this.classList.add('loaded');
            if (card) {
                card.classList.remove('loading', 'error');
                card.classList.add('loaded');
            }
        });
    });
}

function initGalleryFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const galleryItems = document.querySelectorAll('.gallery-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            currentFilter = filter;
            
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filter gallery items with smooth animation
            galleryItems.forEach(item => {
                const category = item.getAttribute('data-category');
                
                if (filter === 'all' || category === filter) {
                    // Show item
                    item.style.display = 'block';
                    setTimeout(() => {
                        item.classList.remove('hidden');
                        item.style.opacity = '1';
                        item.style.transform = 'scale(1)';
                    }, 50);
                } else {
                    // Hide item
                    item.classList.add('hidden');
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.8)';
                    setTimeout(() => {
                        item.style.display = 'none';
                    }, 300);
                }
            });
            
            // Update gallery images array for modal navigation
            setTimeout(() => {
                collectGalleryImages();
            }, 350);
        });
    });
}

function initGalleryModal() {
    const galleryCards = document.querySelectorAll('.gallery-card');
    const modal = document.getElementById('galleryModal');
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalImageTitle');
    const modalDescription = document.getElementById('modalImageDescription');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    galleryCards.forEach((card, index) => {
        card.addEventListener('click', function(e) {
            e.preventDefault();
            const imageSrc = this.getAttribute('data-image');
            const imageTitle = this.getAttribute('data-title');
            const imageDescription = this.getAttribute('data-description');
            
            // Find the index in visible images
            const visibleCards = Array.from(document.querySelectorAll('.gallery-item:not(.hidden) .gallery-card'));
            currentImageIndex = visibleCards.indexOf(this);
            
            updateModalContent(imageSrc, imageTitle, imageDescription);
            
            // Show modal using Bootstrap's modal method
            if (modal) {
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            }
        });
    });
    
    // Navigation buttons
    if (prevBtn) {
        prevBtn.addEventListener('click', function(e) {
            e.preventDefault();
            navigateModal(-1);
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            e.preventDefault();
            navigateModal(1);
        });
    }
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (modal && modal.classList.contains('show')) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                navigateModal(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                navigateModal(1);
            } else if (e.key === 'Escape') {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }
    });
    
    // Touch/swipe support for mobile
    let touchStartX = 0;
    let touchEndX = 0;
    
    if (modal) {
        modal.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        });
        
        modal.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });
    }
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartX - touchEndX;
        
        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                // Swipe left - next
                navigateModal(1);
            } else {
                // Swipe right - previous
                navigateModal(-1);
            }
        }
    }
}

function collectGalleryImages() {
    const visibleCards = document.querySelectorAll('.gallery-item:not(.hidden) .gallery-card');
    galleryImages = Array.from(visibleCards).map(card => ({
        src: card.getAttribute('data-image'),
        title: card.getAttribute('data-title'),
        description: card.getAttribute('data-description')
    }));
}

function updateModalContent(imageSrc, imageTitle, imageDescription) {
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalImageTitle');
    const modalDescription = document.getElementById('modalImageDescription');
    
    if (modalImage) {
        // Add loading state
        modalImage.style.opacity = '0.5';
        modalImage.onload = function() {
            this.style.opacity = '1';
        };
        modalImage.onerror = function() {
            // Fallback image if modal image fails to load
            this.src = 'https://images.pexels.com/photos/1640777/pexels-photo-1640777.jpeg?auto=compress&cs=tinysrgb&w=800';
            this.style.opacity = '1';
        };
        modalImage.src = imageSrc;
        modalImage.alt = imageTitle;
    }
    
    if (modalTitle) {
        modalTitle.textContent = imageTitle;
    }
    
    if (modalDescription) {
        modalDescription.textContent = imageDescription;
    }
    
    // Update navigation buttons visibility
    updateNavigationButtons();
}

function navigateModal(direction) {
    if (galleryImages.length === 0) return;
    
    currentImageIndex += direction;
    
    // Wrap around
    if (currentImageIndex < 0) {
        currentImageIndex = galleryImages.length - 1;
    } else if (currentImageIndex >= galleryImages.length) {
        currentImageIndex = 0;
    }
    
    const currentImage = galleryImages[currentImageIndex];
    if (currentImage) {
        updateModalContent(currentImage.src, currentImage.title, currentImage.description);
    }
}

function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    if (prevBtn) {
        prevBtn.style.display = galleryImages.length > 1 ? 'flex' : 'none';
    }
    
    if (nextBtn) {
        nextBtn.style.display = galleryImages.length > 1 ? 'flex' : 'none';
    }
}

function initLoadMore() {
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    
    if (!loadMoreBtn) return;
    
    loadMoreBtn.addEventListener('click', function() {
        // Simulate loading more images
        this.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Chargement...';
        this.disabled = true;
        
        setTimeout(() => {
            // In a real application, you would load more images from the server
            addMoreImages();
            
            this.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Voir plus de photos';
            this.disabled = false;
        }, 1500);
    });
}

function addMoreImages() {
    const galleryContainer = document.getElementById('galleryContainer');
    
    // Additional images to add
    const newImages = [
        {
            category: 'ambiance',
            src: 'https://images.pexels.com/photos/2253643/pexels-photo-2253643.jpeg?auto=compress&cs=tinysrgb&w=400',
            largeSrc: 'https://images.pexels.com/photos/2253643/pexels-photo-2253643.jpeg?auto=compress&cs=tinysrgb&w=800',
            title: 'Soirée conviviale',
            description: 'Moments de partage entre amis'
        },
        {
            category: 'plats',
            src: 'https://images.pexels.com/photos/1449773/pexels-photo-1449773.jpeg?auto=compress&cs=tinysrgb&w=400',
            largeSrc: 'https://images.pexels.com/photos/1449773/pexels-photo-1449773.jpeg?auto=compress&cs=tinysrgb&w=800',
            title: 'Plateau de fromages',
            description: 'Sélection de fromages artisanaux'
        },
        {
            category: 'terrasse',
            src: 'https://images.pexels.com/photos/1395967/pexels-photo-1395967.jpeg?auto=compress&cs=tinysrgb&w=400',
            largeSrc: 'https://images.pexels.com/photos/1395967/pexels-photo-1395967.jpeg?auto=compress&cs=tinysrgb&w=800',
            title: 'Terrasse de jour',
            description: 'Profitez du soleil méditerranéen'
        }
    ];
    
    newImages.forEach(image => {
        const galleryItem = createGalleryItem(image);
        galleryContainer.appendChild(galleryItem);
    });
    
    // Update gallery images array
    collectGalleryImages();
    
    // Show notification
    if (window.LesTroisQuarts && window.LesTroisQuarts.showNotification) {
        window.LesTroisQuarts.showNotification('Nouvelles photos ajoutées !', 'success');
    }
}

function createGalleryItem(image) {
    const div = document.createElement('div');
    div.className = 'col-lg-4 col-md-6 gallery-item';
    div.setAttribute('data-category', image.category);
    
    div.innerHTML = `
        <div class="gallery-card" 
             data-image="${image.largeSrc}" data-title="${image.title}" data-description="${image.description}">
            <img src="${image.src}" alt="${image.title}" class="img-fluid">
            <div class="gallery-overlay">
                <div class="gallery-content">
                    <h5>${image.title}</h5>
                    <p>${image.description}</p>
                    <i class="bi bi-zoom-in"></i>
                </div>
            </div>
        </div>
    `;
    
    // Add click event to the new gallery card
    const galleryCard = div.querySelector('.gallery-card');
    galleryCard.addEventListener('click', function(e) {
        e.preventDefault();
        const imageSrc = this.getAttribute('data-image');
        const imageTitle = this.getAttribute('data-title');
        const imageDescription = this.getAttribute('data-description');
        
        // Find the index in visible images
        const visibleCards = Array.from(document.querySelectorAll('.gallery-item:not(.hidden) .gallery-card'));
        currentImageIndex = visibleCards.indexOf(this);
        
        updateModalContent(imageSrc, imageTitle, imageDescription);
        
        // Show modal using Bootstrap's modal method
        const modal = document.getElementById('galleryModal');
        if (modal) {
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }
    });
    
    // Add error handling for new images
    const img = div.querySelector('img');
    const card = div.querySelector('.gallery-card');
    
    // Set initial loading state for new images
    img.classList.add('loading');
    if (card) {
        card.classList.add('loading');
    }
    
    img.addEventListener('error', function() {
        this.src = 'https://images.pexels.com/photos/1640777/pexels-photo-1640777.jpeg?auto=compress&cs=tinysrgb&w=400';
        this.alt = 'Image non disponible';
        this.classList.remove('loaded', 'loading');
        this.classList.add('error');
        if (card) {
            card.classList.remove('loaded', 'loading');
            card.classList.add('error');
        }
    });
    
    img.addEventListener('load', function() {
        this.classList.remove('loading', 'error');
        this.classList.add('loaded');
        if (card) {
            card.classList.remove('loading', 'error');
            card.classList.add('loaded');
        }
    });
    
    return div;
}

// Add smooth scroll to gallery section when coming from other pages
window.addEventListener('load', function() {
    const hash = window.location.hash;
    if (hash === '#gallery') {
        const gallerySection = document.querySelector('.gallery-grid');
        if (gallerySection) {
            gallerySection.scrollIntoView({ behavior: 'smooth' });
        }
    }
});