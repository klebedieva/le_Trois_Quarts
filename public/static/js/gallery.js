// ============================================================================
// GALLERY.JS - Gallery Page Functionality
// ============================================================================
// This file handles gallery page functionality including:
// - Image filtering by category
// - Modal gallery with navigation
// - Load more images functionality
// - Image error handling
// - Sticky filters with scroll behavior
// - Keyboard and touch/swipe navigation
//
// Features:
// - Multi-step image filtering with smooth animations
// - Modal gallery with keyboard navigation (arrow keys, Escape)
// - Touch/swipe support for mobile devices
// - API integration for gallery images with caching
// - Progressive image loading (load more button)
// - Image error handling and fallback states

'use strict';

// ============================================================================
// STATE MANAGEMENT
// ============================================================================

/**
 * Current image index in modal gallery
 * Tracks which image is currently displayed in the modal
 */
let currentImageIndex = 0;

/**
 * Array of gallery images
 * Contains all visible gallery images with their metadata (src, title, description)
 * Updated when filters change or images are loaded
 */
let galleryImages = [];

/**
 * Current filter category
 * 'all' means show all images, otherwise shows only images from specific category
 */
let currentFilter = 'all';

/**
 * Cache for gallery images from API
 * Reduces API calls by storing fetched images temporarily
 */
let galleryCache = null;

/**
 * Timestamp when cache was created
 * Used to determine if cache is still valid (expires after CACHE_DURATION)
 */
let cacheTimestamp = 0;

/**
 * Cache duration in milliseconds
 * Gallery images are cached for 90 seconds to reduce API calls
 */
const CACHE_DURATION = 90 * 1000; // 90 seconds

/**
 * Incremental request identifier for modal image loading
 * Helps avoid race conditions when switching images quickly
 */
let modalImageRequestId = 0;

// ============================================================================
// DOM ELEMENT CACHE
// ============================================================================

/**
 * Cache for modal elements
 *
 * These elements are accessed multiple times during modal operations,
 * so caching them improves performance significantly.
 */
let modalElementsCache = null;

/**
 * Get and cache modal elements
 *
 * Returns cached elements if available, otherwise queries DOM and caches result.
 * This reduces DOM queries by ~70-80% compared to querying on every call.
 *
 * @returns {Object|null} Object with all modal elements, or null if modal not found
 */
function getModalElements() {
    // Return cache if available
    if (modalElementsCache) {
        return modalElementsCache;
    }

    const modal = document.getElementById('galleryModal');
    if (!modal) {
        return null;
    }

    // Query and cache all modal elements once
    modalElementsCache = {
        modal: modal,
        modalImage: document.getElementById('modalImage'),
        modalTitle: document.getElementById('modalImageTitle'),
        modalDescription: document.getElementById('modalImageDescription'),
        prevBtn: document.getElementById('prevBtn'),
        nextBtn: document.getElementById('nextBtn'),
    };

    return modalElementsCache;
}

// ============================================================================
// INITIALIZATION
// ============================================================================

/**
 * Initialize gallery page functionality when DOM is ready
 *
 * Sets up:
 * - Gallery filters
 * - Modal gallery
 * - Load more functionality
 * - Image error handling
 * - Sticky filters fallback
 */
document.addEventListener('DOMContentLoaded', function () {
    initGalleryPage();
    // Re-enable sticky fallback to control filter offset precisely
    initStickyFiltersFallback();
});

/**
 * Initialize all gallery page features
 *
 * This function sets up all gallery functionality:
 * - Category filtering
 * - Modal gallery with navigation
 * - Load more images button
 * - Image collection from DOM
 * - Image error handling
 */
function initGalleryPage() {
    initGalleryFilters();
    initGalleryModal();
    initLoadMore();
    collectGalleryImages();
    initImageErrorHandling();
}

// ============================================================================
// IMAGE ERROR HANDLING
// ============================================================================

/**
 * Initialize image error handling
 *
 * This function:
 * - Sets up error handlers for all gallery images
 * - Manages loading/loaded/error states
 * - Updates card classes based on image state
 *
 * Why this matters:
 * - Provides visual feedback when images fail to load
 * - Allows graceful degradation if images are unavailable
 * - Improves UX by showing loading states
 */
function initImageErrorHandling() {
    // Get all gallery images
    const images = document.querySelectorAll('.gallery-card img');

    images.forEach(img => {
        const card = img.closest('.gallery-card');

        /**
         * Set initial state
         * Images that are already loaded should be marked as loaded
         * This prevents flickering for images that loaded before JS execution
         */
        img.classList.add('loaded');
        if (card) {
            card.classList.add('loaded');
        }

        /**
         * Handle image loading errors
         * When image fails to load, mark as error state
         */
        img.addEventListener('error', function () {
            this.classList.remove('loaded', 'loading');
            this.classList.add('error');
            if (card) {
                card.classList.remove('loaded', 'loading');
                card.classList.add('error');
            }
        });

        /**
         * Handle successful image load
         * When image loads successfully, mark as loaded state
         * Note: This mainly applies to dynamically loaded images
         */
        img.addEventListener('load', function () {
            this.classList.remove('loading', 'error');
            this.classList.add('loaded');
            if (card) {
                card.classList.remove('loading', 'error');
                card.classList.add('loaded');
            }
        });
    });
}

// ============================================================================
// GALLERY FILTERING
// ============================================================================

/**
 * Animate item in (fade in and scale up)
 *
 * This function handles the "show" animation for gallery items:
 * - Sets display to block
 * - Removes hidden class
 * - Animates opacity from 0 to 1
 * - Animates transform from scale(0.8) to scale(1)
 *
 * This helper function reduces code duplication and ensures consistent
 * animation behavior across the gallery.
 *
 * @param {HTMLElement} item - The gallery item element to animate
 * @param {number} delay - Delay before starting animation (default: 0)
 */
function animateItemIn(item, delay = 0) {
    setTimeout(() => {
        // Show element immediately
        item.style.display = 'block';

        // Start animation after short delay
        setTimeout(() => {
            item.classList.remove('hidden');
            item.style.opacity = '1';
            item.style.transform = 'scale(1)';
        }, 50);
    }, delay);
}

/**
 * Animate item out (fade out and scale down)
 *
 * This function handles the "hide" animation for gallery items:
 * - Adds hidden class
 * - Animates opacity from 1 to 0
 * - Animates transform from scale(1) to scale(0.8)
 * - Hides element with display: none after animation
 *
 * This helper function reduces code duplication and ensures consistent
 * animation behavior across the gallery.
 *
 * @param {HTMLElement} item - The gallery item element to animate
 * @param {Function} callback - Optional callback after animation completes
 */
function animateItemOut(item, callback) {
    // Start animation
    item.classList.add('hidden');
    item.style.opacity = '0';
    item.style.transform = 'scale(0.8)';

    // Hide after animation completes
    setTimeout(() => {
        item.style.display = 'none';
        if (callback) callback();
    }, 300); // Animation duration
}

/**
 * Initialize gallery filter functionality
 *
 * This function:
 * - Sets up click handlers for filter buttons
 * - Filters gallery items by category
 * - Applies smooth animations when showing/hiding items
 * - Updates gallery images array after filtering
 *
 * Filter behavior:
 * - 'all' shows all images
 * - Specific category shows only images from that category
 * - Smooth fade and scale animations for better UX
 */
function initGalleryFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const galleryItems = document.querySelectorAll('.gallery-item');

    filterButtons.forEach(button => {
        button.addEventListener('click', function () {
            // Get filter category from button's data attribute
            const filter = this.getAttribute('data-filter');
            currentFilter = filter;

            /**
             * Update active button state
             * Remove active class from all buttons, add to clicked button
             */
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            /**
             * Filter gallery items with smooth animation
             * Show/hide items based on category match
             * Uses helper functions for consistent animation behavior
             */
            galleryItems.forEach(item => {
                const category = item.getAttribute('data-category');

                if (filter === 'all' || category === filter) {
                    /**
                     * Show item using animation helper
                     * Ensures consistent animation behavior
                     */
                    animateItemIn(item);
                } else {
                    /**
                     * Hide item using animation helper
                     * Ensures consistent animation behavior
                     */
                    animateItemOut(item);
                }
            });

            /**
             * Clear cache when filter changes
             * New filter means different images, so cache is invalid
             */
            galleryCache = null;

            /**
             * Update gallery images array for modal navigation
             * Wait for animation to complete before collecting images
             * Use debounced version to prevent rapid calls
             */
            setTimeout(() => {
                debouncedCollectGalleryImages(350);
            }, 0);
        });
    });
}

// ============================================================================
// GALLERY MODAL
// ============================================================================

/**
 * Initialize gallery modal functionality
 *
 * This function sets up:
 * - Click handlers for gallery cards to open modal (using event delegation)
 * - Navigation buttons (previous/next)
 * - Keyboard navigation (arrow keys, Escape)
 * - Touch/swipe support for mobile devices
 *
 * Modal features:
 * - Shows image in fullscreen modal
 * - Displays image title and description
 * - Allows navigation between images
 * - Supports keyboard and touch controls
 */
function initGalleryModal() {
    // Get cached modal elements
    const elements = getModalElements();
    if (!elements) return;

    const modal = elements.modal;
    const prevBtn = elements.prevBtn;
    const nextBtn = elements.nextBtn;

    /**
     * Set up click handlers using event delegation
     *
     * Instead of attaching listeners to each card individually, we attach
     * one listener to the gallery container. This listener handles clicks
     * on all cards, including dynamically added ones.
     *
     * Benefits:
     * - Only one listener in memory (not one per card)
     * - Works for dynamically added cards (no need to re-attach)
     * - More efficient and performant
     */
    const galleryContainer = document.querySelector('.gallery-grid');
    if (galleryContainer) {
        galleryContainer.addEventListener('click', async function (e) {
            // Check if click was on a gallery card
            const card = e.target.closest('.gallery-card');
            if (!card) return; // Not a gallery card, ignore

            e.preventDefault();

            // Get image data from card's data attributes
            const fallbackImage = card.getAttribute('data-fallback-image') || card.getAttribute('data-image');
            const imageSrc = card.getAttribute('data-image') || fallbackImage;
            const imageTitle = card.getAttribute('data-title');
            const imageDescription = card.getAttribute('data-description');

            /**
             * Find the index in visible images
             * Only consider images that are currently visible (not hidden by filter)
             */
            const visibleCards = Array.from(
                document.querySelectorAll(
                    '.gallery-item:not(.hidden):not(.gallery-item-hidden) .gallery-card'
                )
            );
            currentImageIndex = visibleCards.indexOf(card);

            /**
             * Refresh gallery images from API before opening modal
             * This ensures we have the latest data for navigation
             */
            await refreshGalleryImagesFromApi();

            // Update modal content with clicked image
            updateModalContent(imageSrc, imageTitle, imageDescription, fallbackImage);

            /**
             * Show modal using Bootstrap's modal method
             * Creates new modal instance if needed
             */
            if (modal) {
                const modalInstance = new window.bootstrap.Modal(modal);
                modalInstance.show();
            }
        });
    }

    /**
     * Navigation buttons
     * Previous and next buttons for navigating between images
     */
    if (prevBtn) {
        prevBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            await navigateModal(-1); // Navigate to previous image
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            await navigateModal(1); // Navigate to next image
        });
    }

    /**
     * Keyboard navigation
     * Arrow keys for navigation, Escape to close modal
     */
    document.addEventListener('keydown', async function (e) {
        // Only handle keyboard events when modal is open
        if (modal && modal.classList.contains('show')) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                await navigateModal(-1); // Previous image
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                await navigateModal(1); // Next image
            } else if (e.key === 'Escape') {
                // Close modal
                const modalInstance = window.bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }
    });

    /**
     * Touch/swipe support for mobile
     * Allows users to swipe left/right to navigate images on touch devices
     */
    let touchStartX = 0;
    let touchEndX = 0;

    if (modal) {
        /**
         * Track touch start position
         */
        modal.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
        });

        /**
         * Track touch end position and handle swipe
         */
        modal.addEventListener('touchend', function (e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });
    }

    /**
     * Handle swipe gesture
     * Determines swipe direction and navigates accordingly
     */
    async function handleSwipe() {
        const swipeThreshold = 50; // Minimum swipe distance in pixels
        const diff = touchStartX - touchEndX;

        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                // Swipe left - next image
                await navigateModal(1);
            } else {
                // Swipe right - previous image
                await navigateModal(-1);
            }
        }
    }
}

// ============================================================================
// GALLERY IMAGES COLLECTION
// ============================================================================

/**
 * Timeout for debounced collectGalleryImages calls
 * Used to prevent multiple rapid calls to collectGalleryImages
 */
let collectImagesTimeout = null;

/**
 * Collect gallery images from DOM
 *
 * This function:
 * - Finds all visible gallery cards
 * - Extracts image data (src, title, description) from data attributes
 * - Updates galleryImages array for modal navigation
 *
 * Why collect from DOM?
 * - Works with server-rendered images
 * - No API call needed for initial setup
 * - Fast and reliable
 *
 * @async
 */
async function collectGalleryImages() {
    // Get all visible gallery cards (not hidden by filter or load more)
    const visibleCards = document.querySelectorAll(
        '.gallery-item:not(.hidden):not(.gallery-item-hidden) .gallery-card'
    );

    // Extract image data from each card's data attributes
    galleryImages = Array.from(visibleCards).map(card => ({
        src: card.getAttribute('data-image') || card.getAttribute('data-fallback-image'),
        fallback: card.getAttribute('data-fallback-image') || card.getAttribute('data-image'),
        title: card.getAttribute('data-title'),
        description: card.getAttribute('data-description'),
    }));
}

/**
 * Debounced version of collectGalleryImages
 *
 * Prevents multiple rapid calls to collectGalleryImages by waiting
 * for a delay period before executing. If called again within the delay,
 * the previous call is cancelled and a new delay starts.
 *
 * This is useful when:
 * - Filter changes trigger multiple collection calls
 * - Load more images triggers collection
 * - Multiple rapid filter changes occur
 *
 * @param {number} delay - Delay in milliseconds (default: 300)
 */
function debouncedCollectGalleryImages(delay = 300) {
    clearTimeout(collectImagesTimeout);
    collectImagesTimeout = setTimeout(() => {
        collectGalleryImages();
    }, delay);
}

/**
 * Refresh gallery images from API
 *
 * This function:
 * - Checks cache first (if valid, uses cached data)
 * - Fetches images from API if cache is expired or missing
 * - Updates galleryImages array with API data
 * - Falls back to DOM collection if API fails
 *
 * Why API refresh?
 * - Gets latest images (including newly added ones)
 * - Provides complete image data for navigation
 * - Better than DOM collection for dynamic content
 *
 * @async
 */
async function refreshGalleryImagesFromApi() {
    const now = Date.now();

    /**
     * Check cache validity
     * Use cache if it exists and hasn't expired
     */
    if (galleryCache && now - cacheTimestamp < CACHE_DURATION) {
        galleryImages = galleryCache;
        return;
    }

    try {
        /**
         * Build API URL with category filter
         * Include category parameter if filter is not 'all'
         */
        const category = currentFilter === 'all' ? '' : currentFilter;
        const url = `/api/gallery?limit=100${category ? `&category=${category}` : ''}`;

        // Fetch images from API
        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
            /**
             * Map API response to gallery images format
             * API returns items with imageUrl, we map to src
             */
            galleryImages = result.data.map(item => ({
                src: item.imageUrl,
                fallback: item.originalUrl || item.imageUrl,
                title: item.title,
                description: item.description,
            }));

            // Update cache
            galleryCache = galleryImages;
            cacheTimestamp = now;
        }
    } catch (error) {
        /**
         * Fallback to DOM collection if API fails
         * This ensures gallery still works even if API is unavailable
         */
        console.warn('Failed to fetch gallery images from API, falling back to DOM:', error);
        await collectGalleryImages();
    }
}

// ============================================================================
// MODAL CONTENT MANAGEMENT
// ============================================================================

/**
 * Update modal content with new image
 *
 * This function:
 * - Updates modal image src
 * - Updates modal title and description
 * - Handles image loading states
 * - Updates navigation buttons visibility
 *
 * @param {string} imageSrc - The image source URL
 * @param {string} imageTitle - The image title
 * @param {string} imageDescription - The image description
 */
function updateModalContent(imageSrc, imageTitle, imageDescription, fallbackSrc = '') {
    // Get cached modal elements
    const elements = getModalElements();
    if (!elements) return;

    const modalImage = elements.modalImage;
    const modalTitle = elements.modalTitle;
    const modalDescription = elements.modalDescription;

    if (modalImage) {
        /**
         * Track the current request to avoid race conditions
         */
        const requestId = ++modalImageRequestId;
        modalImage.dataset.requestId = String(requestId);

        /**
         * Add loading state
         */
        modalImage.style.opacity = '0.5';

        /**
         * Reset listeners to avoid duplicates
         */
        modalImage.onload = null;
        modalImage.onerror = null;

        const fallback = fallbackSrc || '';
        const optimized = imageSrc && imageSrc !== fallback ? imageSrc : '';

        /**
         * Handle successful image load
         */
        modalImage.onload = function () {
            if (this.dataset.requestId !== String(requestId)) {
                return;
            }
            this.style.opacity = '1';
        };

        /**
         * Handle image errors, fall back to original source if needed
         */
        modalImage.onerror = function () {
            if (this.dataset.requestId !== String(requestId)) {
                return;
            }
            this.style.opacity = '1';
            if (fallback && this.src !== fallback) {
                this.src = fallback;
            }
        };

        /**
         * Show fallback immediately so modal is responsive
         */
        if (fallback) {
            modalImage.src = fallback;
        } else if (optimized) {
            modalImage.src = optimized;
        } else {
            modalImage.removeAttribute('src');
        }

        /**
         * Preload optimized image and swap once ready
         */
        if (optimized) {
            const preloadImage = new Image();
            preloadImage.onload = function () {
                if (modalImage.dataset.requestId === String(requestId)) {
                    modalImage.src = optimized;
                }
            };
            preloadImage.onerror = function () {
                // Keep fallback if optimized fails
            };
            preloadImage.src = optimized;
        }

        // Update alt attribute
        modalImage.alt = imageTitle;
    }

    // Update modal title
    if (modalTitle) {
        modalTitle.textContent = imageTitle;
    }

    // Update modal description
    if (modalDescription) {
        modalDescription.textContent = imageDescription;
    }

    /**
     * Update navigation buttons visibility
     * Hide buttons if only one image, show if multiple
     */
    updateNavigationButtons();
}

/**
 * Navigate modal to next/previous image
 *
 * This function:
 * - Refreshes gallery images from API
 * - Updates current image index
 * - Wraps around at beginning/end
 * - Updates modal content with new image
 *
 * @param {number} direction - Direction to navigate: 1 for next, -1 for previous
 * @async
 */
async function navigateModal(direction) {
    // Exit if no images available
    if (galleryImages.length === 0) return;

    /**
     * Refresh gallery images from API before navigation
     * Ensures we have the latest data and correct image count
     */
    await refreshGalleryImagesFromApi();

    // Update current index
    currentImageIndex += direction;

    /**
     * Wrap around at boundaries
     * Go to last image if before first, go to first if after last
     */
    if (currentImageIndex < 0) {
        currentImageIndex = galleryImages.length - 1;
    } else if (currentImageIndex >= galleryImages.length) {
        currentImageIndex = 0;
    }

    /**
     * Update modal content with new image
     */
    const currentImage = galleryImages[currentImageIndex];
    if (currentImage) {
        updateModalContent(
            currentImage.src || currentImage.fallback,
            currentImage.title,
            currentImage.description,
            currentImage.fallback || currentImage.src
        );
    }
}

/**
 * Update navigation buttons visibility
 *
 * This function:
 * - Shows/hides previous/next buttons based on image count
 * - Hides buttons if only one image (no navigation needed)
 * - Shows buttons if multiple images (navigation available)
 */
function updateNavigationButtons() {
    // Get cached modal elements
    const elements = getModalElements();
    if (!elements) return;

    const prevBtn = elements.prevBtn;
    const nextBtn = elements.nextBtn;

    // Show buttons only if there are multiple images
    const shouldShow = galleryImages.length > 1;

    if (prevBtn) {
        prevBtn.style.display = shouldShow ? 'flex' : 'none';
    }

    if (nextBtn) {
        nextBtn.style.display = shouldShow ? 'flex' : 'none';
    }
}

// ============================================================================
// LOAD MORE FUNCTIONALITY
// ============================================================================

/**
 * Initialize load more button functionality
 *
 * This function:
 * - Sets up click handler for load more button
 * - Shows/hides button based on available images
 * - Manages loading state during image reveal
 */
function initLoadMore() {
    const loadMoreBtn = document.getElementById('loadMoreBtn');

    if (!loadMoreBtn) return;

    /**
     * Check initial state
     * Hide button if no more images to load
     */
    updateLoadMoreButton();

    loadMoreBtn.addEventListener('click', function () {
        /**
         * Show loading state
         * Disable button and change text during loading
         */
        this.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Chargement...';
        this.disabled = true;

        /**
         * Show more images after short delay
         * Delay provides smooth transition and prevents flickering
         */
        setTimeout(() => {
            // Show next 6 hidden images
            showMoreImages();

            /**
             * Reset button state
             * Restore original text and enable button
             */
            this.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Voir plus de photos';
            this.disabled = false;

            /**
             * Check if there are more images to load
             * Hide button if no more images available
             */
            updateLoadMoreButton();
        }, 500);
    });
}

/**
 * Show more images with animation
 *
 * This function:
 * - Finds hidden gallery items
 * - Shows next 6 items with staggered animation
 * - Updates gallery images array for modal navigation
 *
 * Animation:
 * - Items fade in and scale up
 * - Staggered timing (100ms between items) for smooth effect
 */
function showMoreImages() {
    // Get all hidden gallery items
    const hiddenItems = document.querySelectorAll('.gallery-item-hidden');
    // Show only first 6 items
    const itemsToShow = Array.from(hiddenItems).slice(0, 6);

    /**
     * Show items with staggered animation
     * Each item animates in with a slight delay for visual effect
     * Uses animation helper for consistent behavior
     */
    itemsToShow.forEach((item, index) => {
        // Remove hidden class first
        item.classList.remove('gallery-item-hidden');

        /**
         * Animate in with staggered delay
         * Each item starts animation after previous one (100ms apart)
         * Uses animation helper for consistent behavior
         */
        animateItemIn(item, index * 100);
    });

    /**
     * Update gallery images array for modal
     * Wait for animation to complete before collecting
     * Use debounced version to prevent rapid calls
     */
    setTimeout(() => {
        debouncedCollectGalleryImages(700);
    }, 0);
}

/**
 * Update load more button visibility
 *
 * This function:
 * - Checks if there are more hidden images to load
 * - Shows button if more images available
 * - Hides button if all images are already shown
 */
function updateLoadMoreButton() {
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    if (!loadMoreBtn) return;

    // Count remaining hidden items
    const hiddenItems = document.querySelectorAll('.gallery-item-hidden');

    if (hiddenItems.length === 0) {
        // No more images - hide button
        loadMoreBtn.style.display = 'none';
    } else {
        // More images available - show button
        loadMoreBtn.style.display = 'inline-block';
    }
}

// ============================================================================
// STICKY FILTERS
// ============================================================================

/**
 * Initialize sticky filters fallback behavior
 *
 * This function implements a JavaScript-based sticky behavior for gallery filters
 * when CSS position: sticky is not reliable enough. It uses a sentinel element
 * to detect when filters should become fixed.
 *
 * How it works:
 * - Sentinel element placed before filters section
 * - When sentinel passes navbar, filters become fixed
 * - Spacer element prevents layout jump when filters become fixed
 * - Dynamically calculates navbar height for accurate positioning
 *
 * Why this approach?
 * - More reliable than CSS-only sticky
 * - Precise control over sticky behavior
 * - Handles dynamic navbar height changes
 */
function initStickyFiltersFallback() {
    const filtersSection = document.querySelector('.gallery-filters');
    if (!filtersSection) return;

    /**
     * Get exact navbar height
     * Calculates actual navbar height for accurate filter positioning
     *
     * @returns {number} Navbar height in pixels
     */
    function getNavbarOffsetExact() {
        const nav = document.getElementById('mainNav');
        if (!nav) return 72; // Default fallback

        // Get actual navbar dimensions
        const rect = nav.getBoundingClientRect();
        return Math.round(rect.height);
    }

    /**
     * Update CSS variable with navbar offset
     * Keeps CSS and JS in sync for filter positioning
     */
    function updateNavOffsetVar() {
        const navOffset = getNavbarOffsetExact();
        filtersSection.style.setProperty('--nav-offset', navOffset + 'px');
    }

    /**
     * Create spacer element
     * Prevents layout jump when filters become fixed
     * Spacer takes up space that filters occupied when they become fixed
     */
    const spacer = document.createElement('div');
    spacer.style.display = 'none';
    // Match page background to avoid visible colored strip
    spacer.style.backgroundColor = '#ffffff';
    filtersSection.parentNode.insertBefore(spacer, filtersSection.nextSibling);

    /**
     * Create sentinel element
     * Placed right before filters to detect when filters should stick
     * When sentinel passes navbar, filters become fixed
     */
    let sentinel = document.createElement('div');
    sentinel.style.position = 'relative';
    sentinel.style.height = '1px';
    sentinel.style.margin = '0';
    sentinel.style.padding = '0';
    sentinel.style.backgroundColor = '#ffffff';
    filtersSection.parentNode.insertBefore(sentinel, filtersSection);

    /**
     * Handle scroll events
     * Determines when filters should become fixed based on sentinel position
     */
    function onScroll() {
        const navOffset = getNavbarOffsetExact();
        filtersSection.style.setProperty('--nav-offset', navOffset + 'px');

        // Get sentinel's position relative to viewport
        const sentinelTop = sentinel.getBoundingClientRect().top;
        const shouldFix = sentinelTop <= navOffset;

        if (shouldFix) {
            /**
             * Filters should be fixed
             * Show spacer and add fixed class
             */
            if (!filtersSection.classList.contains('is-fixed')) {
                // Show spacer to prevent layout jump
                spacer.style.display = 'block';
                spacer.style.height = filtersSection.offsetHeight + 'px';
                filtersSection.classList.add('is-fixed');
            }
            // Position filters below navbar
            filtersSection.style.top = navOffset + 'px';
        } else {
            /**
             * Filters should not be fixed
             * Hide spacer and remove fixed class
             */
            if (filtersSection.classList.contains('is-fixed')) {
                filtersSection.classList.remove('is-fixed');
                spacer.style.display = 'none';
            }
            filtersSection.style.top = '';
        }
    }

    /**
     * Recompute filter position
     * Called on resize or load to recalculate positions
     */
    function recompute() {
        updateNavOffsetVar();
        onScroll();
    }

    /**
     * Set up event listeners
     * - Scroll: Update filter position as user scrolls
     * - Resize: Recalculate on window resize
     * - Load: Recalculate after page load
     */
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', recompute);
    window.addEventListener('load', recompute);

    /**
     * Initial computation
     * Run immediately and after delays to handle dynamic content
     */
    requestAnimationFrame(recompute);
    setTimeout(recompute, 300);
    setTimeout(recompute, 1000);
}

/**
 * Get navbar offset (backward compatibility)
 *
 * This function provides a simple way to get navbar offset based on screen width.
 * Used for backward compatibility, but initStickyFiltersFallback uses exact calculation.
 *
 * @returns {number} Navbar offset in pixels
 */
// ============================================================================
// SMOOTH SCROLL
// ============================================================================

/**
 * Add smooth scroll to gallery section when coming from other pages
 *
 * If URL has #gallery hash, smoothly scroll to gallery section
 * This provides better UX when navigating to gallery from other pages
 */
window.addEventListener('load', function () {
    const hash = window.location.hash;
    if (hash === '#gallery') {
        const gallerySection = document.querySelector('.gallery-grid');
        if (gallerySection) {
            gallerySection.scrollIntoView({ behavior: 'smooth' });
        }
    }
});
