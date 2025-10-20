// Gallery page JavaScript

let currentImageIndex = 0;
let galleryImages = [];
let currentFilter = 'all';
let galleryCache = null;
let cacheTimestamp = 0;
const CACHE_DURATION = 90 * 1000; // 90 seconds

document.addEventListener('DOMContentLoaded', function() {
    initGalleryPage();
    // Re-enable sticky fallback to control filter offset precisely
    initStickyFiltersFallback();
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
            this.classList.remove('loaded', 'loading');
            this.classList.add('error');
            if (card) {
                card.classList.remove('loaded', 'loading');
                card.classList.add('error');
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
            
            // Clear cache when filter changes
            galleryCache = null;
            
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
        card.addEventListener('click', async function(e) {
            e.preventDefault();
            const imageSrc = this.getAttribute('data-image');
            const imageTitle = this.getAttribute('data-title');
            const imageDescription = this.getAttribute('data-description');
            
            // Find the index in visible images
            const visibleCards = Array.from(document.querySelectorAll('.gallery-item:not(.hidden):not(.gallery-item-hidden) .gallery-card'));
            currentImageIndex = visibleCards.indexOf(this);
            
            // Refresh gallery images from API before opening modal
            await refreshGalleryImagesFromApi();
            
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
        prevBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            await navigateModal(-1);
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            await navigateModal(1);
        });
    }
    
    // Keyboard navigation
    document.addEventListener('keydown', async function(e) {
        if (modal && modal.classList.contains('show')) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                await navigateModal(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                await navigateModal(1);
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
    
    async function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartX - touchEndX;
        
        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                // Swipe left - next
                await navigateModal(1);
            } else {
                // Swipe right - previous
                await navigateModal(-1);
            }
        }
    }
}

async function collectGalleryImages() {
    const visibleCards = document.querySelectorAll('.gallery-item:not(.hidden):not(.gallery-item-hidden) .gallery-card');
    galleryImages = Array.from(visibleCards).map(card => ({
        src: card.getAttribute('data-image'),
        title: card.getAttribute('data-title'),
        description: card.getAttribute('data-description')
    }));
}

async function refreshGalleryImagesFromApi() {
    const now = Date.now();
    if (galleryCache && (now - cacheTimestamp) < CACHE_DURATION) {
        galleryImages = galleryCache;
        return;
    }

    try {
        const category = currentFilter === 'all' ? '' : currentFilter;
        const url = `/api/gallery?limit=100${category ? `&category=${category}` : ''}`;
        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
            galleryImages = result.data.map(item => ({
                src: item.imageUrl,
                title: item.title,
                description: item.description
            }));
            galleryCache = galleryImages;
            cacheTimestamp = now;
        }
    } catch (error) {
        console.warn('Failed to fetch gallery images from API, falling back to DOM:', error);
        await collectGalleryImages();
    }
}

function updateModalContent(imageSrc, imageTitle, imageDescription) {
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalImageTitle');
    const modalDescription = document.getElementById('modalImageDescription');
    
    if (modalImage) {
        // Add loading state
        modalImage.style.opacity = '0.5';
        
        // Remove any existing event listeners
        modalImage.onload = null;
        modalImage.onerror = null;
        
        modalImage.onload = function() {
            this.style.opacity = '1';
        };
        
        modalImage.onerror = function() {
            this.style.opacity = '1';
        };
        
        // Clear the src first to ensure clean state
        modalImage.src = '';
        modalImage.removeAttribute('src');
        
        // Small delay before setting new src to prevent null assignment
        setTimeout(() => {
            modalImage.src = imageSrc;
            modalImage.setAttribute('src', imageSrc);
        }, 10);
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

async function navigateModal(direction) {
    if (galleryImages.length === 0) return;
    
    // Refresh gallery images from API before navigation
    await refreshGalleryImagesFromApi();
    
    currentImageIndex += direction;
    
    // Wrap around
    if (currentImageIndex < 0) {
        currentImageIndex = galleryImages.length - 1;
    } else if (currentImageIndex >= galleryImages.length) {
        currentImageIndex = 0;
    }
    
    const currentImage = galleryImages[currentImageIndex];
    if (currentImage && currentImage.src) {
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
    
    // Check initial state
    updateLoadMoreButton();
    
    loadMoreBtn.addEventListener('click', function() {
        // Show loading state
        this.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Chargement...';
        this.disabled = true;
        
        setTimeout(() => {
            // Show next 6 hidden images
            showMoreImages();
            
            // Reset button state
            this.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Voir plus de photos';
            this.disabled = false;
            
            // Check if there are more images to load
            updateLoadMoreButton();
        }, 500);
    });
}

function showMoreImages() {
    const hiddenItems = document.querySelectorAll('.gallery-item-hidden');
    const itemsToShow = Array.from(hiddenItems).slice(0, 6);
    
    itemsToShow.forEach((item, index) => {
        setTimeout(() => {
            item.classList.remove('gallery-item-hidden');
            item.style.opacity = '0';
            item.style.transform = 'scale(0.8)';
            item.style.display = 'block';
            
            setTimeout(() => {
                item.style.opacity = '1';
                item.style.transform = 'scale(1)';
            }, 50);
        }, index * 100);
    });
    
    // Update gallery images array for modal
    setTimeout(() => {
        collectGalleryImages();
    }, 700);
}

function updateLoadMoreButton() {
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    if (!loadMoreBtn) return;
    
    const hiddenItems = document.querySelectorAll('.gallery-item-hidden');
    
    if (hiddenItems.length === 0) {
        loadMoreBtn.style.display = 'none';
    } else {
        loadMoreBtn.style.display = 'inline-block';
    }
}


// Sticky behavior for filters: use reliable JS (sentinel + spacer) and keep CSS var in sync
function initStickyFiltersFallback() {
    const filtersSection = document.querySelector('.gallery-filters');
    if (!filtersSection) return;

    // Helper: exact navbar height
    function getNavbarOffsetExact() {
        const nav = document.getElementById('mainNav');
        if (!nav) return 72;
        const rect = nav.getBoundingClientRect();
        return Math.round(rect.height);
    }

    function updateNavOffsetVar() {
        const navOffset = getNavbarOffsetExact();
        filtersSection.style.setProperty('--nav-offset', navOffset + 'px');
    }

    // Spacer to avoid layout jump when fixed
    const spacer = document.createElement('div');
    spacer.style.display = 'none';
    // Ensure spacer matches page background to avoid visible colored strip
    spacer.style.backgroundColor = '#ffffff';
    filtersSection.parentNode.insertBefore(spacer, filtersSection.nextSibling);

    // Sentinel placed right before filters to decide the exact sticking moment
    let sentinel = document.createElement('div');
    sentinel.style.position = 'relative';
    sentinel.style.height = '1px';
    sentinel.style.margin = '0';
    sentinel.style.padding = '0';
    sentinel.style.backgroundColor = '#ffffff';
    filtersSection.parentNode.insertBefore(sentinel, filtersSection);

    function onScroll() {
        const navOffset = getNavbarOffsetExact();
        filtersSection.style.setProperty('--nav-offset', navOffset + 'px');

        const sentinelTop = sentinel.getBoundingClientRect().top;
        const shouldFix = sentinelTop <= navOffset;

        if (shouldFix) {
            if (!filtersSection.classList.contains('is-fixed')) {
                spacer.style.display = 'block';
                spacer.style.height = filtersSection.offsetHeight + 'px';
                filtersSection.classList.add('is-fixed');
            }
            filtersSection.style.top = navOffset + 'px';
        } else {
            if (filtersSection.classList.contains('is-fixed')) {
                filtersSection.classList.remove('is-fixed');
                spacer.style.display = 'none';
            }
            filtersSection.style.top = '';
        }
    }

    function recompute() {
        updateNavOffsetVar();
        onScroll();
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', recompute);
    window.addEventListener('load', recompute);

    requestAnimationFrame(recompute);
    setTimeout(recompute, 300);
    setTimeout(recompute, 1000);
}

// Compute current navbar offset so filters sit just below it
function getNavbarOffset() {
    // Backward-compat (unused for sticky; kept for other callers if any)
    const width = window.innerWidth || document.documentElement.clientWidth || 1024;
    if (width < 576) return 64;
    if (width < 992) return 68;
    return 72;
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
        const visibleCards = Array.from(document.querySelectorAll('.gallery-item:not(.hidden):not(.gallery-item-hidden) .gallery-card'));
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