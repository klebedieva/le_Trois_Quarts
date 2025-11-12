// Main JavaScript file for Le Trois Quarts website
// Version 2 - No global error notifications for reservation form
//
// This file contains all the core JavaScript functionality for the website:
// - CSRF token handling for secure API requests
// - Navbar behavior (scroll effects, mobile menu)
// - Gallery lightbox functionality
// - Smooth scrolling for anchor links
// - Scroll-triggered animations
// - Notification system (alerts and confirmations)

// ============================================================================
// CSRF TOKEN HELPERS
// ============================================================================

/**
 * Read CSRF token from <meta name="csrf-token" content="...">
 * CSRF (Cross-Site Request Forgery) tokens protect against malicious requests.
 * This token is embedded in the HTML by Symfony and must be included in API requests.
 *
 * @returns {string|null} The CSRF token string, or null if not found
 */
window.getCsrfToken = function () {
    // Look for the meta tag that contains the CSRF token
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    // Return the token content if found, otherwise return null
    return metaTag ? metaTag.getAttribute('content') : null;
};

/**
 * Lightweight fetch wrapper that always sends the CSRF header.
 * This function wraps the native fetch() API to automatically include security headers.
 *
 * Why this exists: All API requests need CSRF protection, so this ensures we never forget it.
 *
 * @param {string} url - The API endpoint URL to call
 * @param {RequestInit} [options] - Optional fetch options (method, body, headers, etc.)
 * @returns {Promise<Response>} - A Promise that resolves to the Response object
 * @throws {Error} - If CSRF token is not found
 *
 * @example
 * // Simple GET request
 * apiRequest('/api/data').then(response => response.json())
 *
 * // POST request with body
 * apiRequest('/api/submit', {
 *   method: 'POST',
 *   body: JSON.stringify({ name: 'John' })
 * })
 */
window.apiRequest = function (url, options = {}) {
    // Get the CSRF token from the page
    const csrfToken = window.getCsrfToken();

    // If no token found, reject immediately (security requirement)
    if (!csrfToken) {
        return Promise.reject(new Error('CSRF token not found'));
    }

    // Default headers that should always be included
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json', // Tell server we're sending JSON
            'X-CSRF-Token': csrfToken, // Security token
        },
    };

    // Merge headers: user-provided headers override defaults
    // This allows calling code to add custom headers if needed
    const finalHeaders = {
        ...defaultOptions.headers, // Start with defaults
        ...(options.headers || {}), // Override with user headers (if any)
    };

    // Call fetch with merged options
    return fetch(url, {
        ...defaultOptions, // Spread default options
        ...options, // Override with user options
        headers: finalHeaders, // Use merged headers
    });
};

// ============================================================================
// INITIALIZATION
// ============================================================================

/**
 * Wait for the DOM to be fully loaded before initializing components.
 * This ensures all HTML elements exist before we try to access them.
 */
document.addEventListener('DOMContentLoaded', function () {
    // Initialize navbar scroll behavior and mobile menu
    initNavbar();

    // Only initialize homepage gallery if:
    // 1. Gallery items exist on the page (.gallery-item elements)
    // 2. We're NOT on the dedicated gallery page (which uses .gallery-card)
    // This prevents conflicts between homepage gallery and gallery page
    if (
        document.querySelectorAll('.gallery-item').length > 0 &&
        !document.querySelector('.gallery-card')
    ) {
        initGallery();
    }

    // Enable smooth scrolling for anchor links (e.g., #about, #menu)
    initSmoothScrolling();

    // Set up scroll-triggered fade-in animations
    initAnimations();
});

// ============================================================================
// NAVBAR FUNCTIONALITY
// ============================================================================

/**
 * Set up navbar behavior:
 * - Change logo and background color when scrolling past 100px
 * - Auto-close mobile menu when clicking navigation links
 * - Auto-close mobile menu when clicking outside the navbar
 */
function initNavbar() {
    // Get references to navbar elements
    const navbar = document.getElementById('mainNav');
    // Find the logo image inside the navbar (if it exists)
    const logoImg = navbar ? navbar.querySelector('.navbar-brand img') : null;

    /**
     * Scroll event handler: Change navbar appearance when scrolling
     * Uses passive: true for better scroll performance (browser can optimize scrolling)
     */
    window.addEventListener(
        'scroll',
        function () {
            // Check if user has scrolled more than 100 pixels from top
            if (window.scrollY > 100) {
                // Add 'scrolled' class to navbar (CSS uses this to change styles)
                navbar.classList.add('scrolled');
                // Switch to smaller logo for scrolled state
                if (logoImg) {
                    logoImg.src = '/logo2.png';
                }
            } else {
                // Remove 'scrolled' class (back to original state)
                navbar.classList.remove('scrolled');
                // Switch back to original logo
                if (logoImg) {
                    logoImg.src = '/logo-footer1.png';
                }
            }
        },
        { passive: true }
    ); // passive: true = browser can scroll immediately without waiting for JS

    // Get all navigation links
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    // Get the collapsible menu container (Bootstrap collapse component)
    const navbarCollapse = document.querySelector('.navbar-collapse');

    /**
     * Close mobile menu when clicking on any navigation link
     * This provides better UX - menu doesn't stay open after navigation
     */
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            // Check if menu is currently open
            if (navbarCollapse && navbarCollapse.classList.contains('show')) {
                // Use Bootstrap's Collapse API to close the menu smoothly
                const bsCollapse = new window.bootstrap.Collapse(navbarCollapse);
                bsCollapse.hide();
            }
        });
    });

    /**
     * Close mobile menu when clicking outside the navbar
     * This is a common UX pattern - clicking outside closes the menu
     */
    document.addEventListener('click', e => {
        // Only act if menu is open
        if (navbarCollapse && navbarCollapse.classList.contains('show')) {
            // Check if the click happened outside the navbar element
            if (!navbar.contains(e.target)) {
                // Close the menu
                const bsCollapse = new window.bootstrap.Collapse(navbarCollapse);
                bsCollapse.hide();
            }
        }
    });
}

// ============================================================================
// GALLERY LIGHTBOX FUNCTIONALITY
// ============================================================================

/**
 * Simple lightbox for elements with class .gallery-item.
 *
 * Features:
 * - Click any gallery image to open it in a modal
 * - Navigate with prev/next buttons
 * - Navigate with keyboard arrows
 * - Auto-refresh from API (with caching to avoid excessive requests)
 * - Shows image counter (e.g., "3 of 12")
 *
 * How it works:
 * 1. Collects all gallery images from the page
 * 2. On click, opens Bootstrap modal with the selected image
 * 3. Allows navigation between images
 * 4. Optionally refreshes from API to get latest images
 */
function initGallery() {
    // Get all gallery item elements from the page
    const galleryItems = document.querySelectorAll('.gallery-item');
    // Get the modal image element (where we'll display the selected image)
    const modalImage = document.getElementById('modalImage');
    // Get the modal container
    const modal = document.getElementById('galleryModal');

    // Support both ID variants used across different pages
    // Some pages use 'galleryPrev', others use 'prevBtn'
    const prevBtn = document.getElementById('galleryPrev') || document.getElementById('prevBtn');
    const nextBtn = document.getElementById('galleryNext') || document.getElementById('nextBtn');

    // Get counter elements (show "1 of 12" type display)
    const currentIndexSpan = document.getElementById('currentImageIndex');
    const totalImagesSpan = document.getElementById('totalImages');

    // Simple in-memory cache to avoid refetching on every navigation
    // Cache stores the last fetched gallery images and timestamp
    let galleryCache = null; // Cached image data
    let cacheTimestamp = 0; // When cache was created (milliseconds)
    const CACHE_DURATION = 90 * 1000; // Cache is valid for 90 seconds

    // Track modal image request IDs to avoid race conditions when switching fast
    let modalImageRequestId = 0;

    // Guard: ensure required elements exist before proceeding
    // If no gallery items or no modal image, exit early (nothing to do)
    if (galleryItems.length === 0 || !modalImage) {
        return;
    }

    /**
     * Collect all gallery images from the DOM, including fallback data
     */
    const collectDomGalleryData = () =>
        Array.from(document.querySelectorAll('.gallery-item')).map(item => {
            const imgEl = item.querySelector('img');
            const src = item.getAttribute('data-image') || imgEl?.getAttribute('src') || '';
            const fallback = item.getAttribute('data-fallback-image') || imgEl?.getAttribute('src') || src;
            const title = item.getAttribute('data-title') || imgEl?.getAttribute('alt') || '';
            const description = item.getAttribute('data-description') || '';
            const alt = imgEl?.getAttribute('alt') || title;

            return {
                src,
                fallback,
                title,
                description,
                alt,
            };
        });

    let galleryImages = collectDomGalleryData();

    // Track which image is currently being displayed
    let currentImageIndex = 0;

    /**
     * Refresh gallery images from API (with caching)
     *
     * This function:
     * 1. Checks if cache is still fresh (< 90 seconds old)
     * 2. If fresh, uses cached data (fast, no API call)
     * 3. If stale, fetches from API
     * 4. Merges API data with DOM structure
     * 5. Updates cache for next time
     *
     * Why cache? Avoids making API requests every time user navigates between images.
     */
    async function refreshGalleryImages() {
        const now = Date.now();
        if (galleryCache && now - cacheTimestamp < CACHE_DURATION) {
            galleryImages = galleryCache;
            updateCounter();
            return;
        }

        const domData = collectDomGalleryData();

        try {
            const response = await fetch('/api/gallery?limit=20');
            const result = await response.json();

            if (result.success) {
                const apiImages = result.data.map(item => ({
                    src: item.imageUrl,
                    fallback: item.originalUrl || item.imageUrl,
                    title: item.title || '',
                    description: item.description || '',
                    alt: item.title || '',
                }));

                const apiMap = new Map(apiImages.map(image => [image.src, image]));

                galleryImages = domData.map(domImage => {
                    const apiImage = apiMap.get(domImage.src);
                    return {
                        src: apiImage?.src || domImage.src,
                        fallback: domImage.fallback || apiImage?.fallback || domImage.src,
                        title: domImage.title || apiImage?.title || '',
                        description: domImage.description || apiImage?.description || '',
                        alt: domImage.alt || apiImage?.alt || '',
                    };
                });

                galleryCache = galleryImages;
                cacheTimestamp = now;
                updateCounter();
                return;
            }
        } catch (error) {
            console.warn('Failed to refresh gallery images, falling back to DOM data:', error);
        }

        galleryImages = domData;
        galleryCache = galleryImages;
        cacheTimestamp = now;
        updateCounter();
    }

    /**
     * Update the image counter display (e.g., "3 of 12")
     * Shows current position and total number of images
     */
    function updateCounter() {
        if (currentIndexSpan) {
            // Display 1-based index (human-friendly: "1" instead of "0")
            currentIndexSpan.textContent = currentImageIndex + 1;
        }
        if (totalImagesSpan) {
            // Display total count
            totalImagesSpan.textContent = galleryImages.length;
        }
    }

    /**
     * Show the image at a specific index in the modal
     * Handles wrapping: going past last image goes to first, and vice versa
     *
     * @param {number} index - The index of the image to show
     */
    async function showImage(index) {
        // Refresh gallery images to get latest data (uses cache if fresh)
        await refreshGalleryImages();

        // Safety check: if no images, do nothing
        if (galleryImages.length === 0) {
            return;
        }

        // Handle wrapping: if index is out of bounds, wrap around
        if (index < 0) {
            // Negative index: go to last image
            currentImageIndex = galleryImages.length - 1;
        } else if (index >= galleryImages.length) {
            // Index too large: go to first image (wrap around)
            currentImageIndex = 0;
        } else {
            // Valid index: use it
            currentImageIndex = index;
        }

        const currentImage = galleryImages[currentImageIndex] || {};
        const fallbackSrc = currentImage.fallback || currentImage.src || '';
        const optimizedSrc = currentImage.src || fallbackSrc;

        // Track request to avoid race conditions
        const requestId = ++modalImageRequestId;
        modalImage.dataset.requestId = String(requestId);

        // Apply loading state
        modalImage.style.opacity = '0.5';

        // Reset previous handlers
        modalImage.onload = null;
        modalImage.onerror = null;

        modalImage.onload = function () {
            if (this.dataset.requestId !== String(requestId)) {
                return;
            }
            this.style.opacity = '1';
        };

        modalImage.onerror = function () {
            if (this.dataset.requestId !== String(requestId)) {
                return;
            }
            this.style.opacity = '1';
            if (fallbackSrc && this.src !== fallbackSrc) {
                this.src = fallbackSrc;
            }
        };

        if (fallbackSrc) {
            modalImage.src = fallbackSrc;
        } else {
            modalImage.removeAttribute('src');
        }

        if (optimizedSrc && optimizedSrc !== fallbackSrc) {
            const preloadImage = new Image();
            preloadImage.onload = function () {
                if (modalImage.dataset.requestId === String(requestId)) {
                    modalImage.src = optimizedSrc;
                }
            };
            preloadImage.src = optimizedSrc;
        }

        modalImage.alt = currentImage.alt || currentImage.title || '';

        const titleEl = document.getElementById('modalImageTitle');
        const descEl = document.getElementById('modalImageDescription');
        if (titleEl) {
            titleEl.textContent = currentImage.title || '';
        }
        if (descEl) {
            descEl.textContent = currentImage.description || '';
        }

        // Update counter display
        updateCounter();

        /**
         * Show/hide navigation buttons based on image count
         * If only one image, hide buttons (no need to navigate)
         */
        if (prevBtn) {
            prevBtn.style.display = galleryImages.length > 1 ? 'flex' : 'none';
        }
        if (nextBtn) {
            nextBtn.style.display = galleryImages.length > 1 ? 'flex' : 'none';
        }
    }

    /**
     * Bind click events to gallery items
     * When user clicks an image, open it in the modal
     */
    galleryItems.forEach((item, index) => {
        item.addEventListener('click', async function () {
            // Set current index to the clicked item
            currentImageIndex = index;
            // Show the image in modal
            await showImage(currentImageIndex);
        });
    });

    /**
     * Bind navigation button events
     * Previous button: go to previous image (with wrapping)
     */
    if (prevBtn) {
        prevBtn.addEventListener('click', async function () {
            await showImage(currentImageIndex - 1);
        });
    }

    /**
     * Next button: go to next image (with wrapping)
     */
    if (nextBtn) {
        nextBtn.addEventListener('click', async function () {
            await showImage(currentImageIndex + 1);
        });
    }

    /**
     * Add keyboard navigation for better UX
     * Arrow keys: navigate between images
     * Escape: close the modal
     */
    document.addEventListener('keydown', async function (e) {
        // Only handle keyboard if modal is currently open
        if (modal && modal.classList.contains('show')) {
            if (e.key === 'ArrowLeft') {
                // Left arrow: previous image
                await showImage(currentImageIndex - 1);
            } else if (e.key === 'ArrowRight') {
                // Right arrow: next image
                await showImage(currentImageIndex + 1);
            } else if (e.key === 'Escape') {
                // Escape key: close modal
                const modalInstance = window.bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }
    });

    // Initialize the counter on page load
    updateCounter();
}

// ============================================================================
// SMOOTH SCROLLING
// ============================================================================

/**
 * Enable smooth scroll for on-page anchor links (e.g., #about, #menu)
 * Also handles the hero scroll indicator button
 *
 * How it works:
 * 1. Finds all links that start with "#" (anchor links)
 * 2. Intercepts clicks and prevents default behavior
 * 3. Calculates target element position
 * 4. Scrolls smoothly to that position with offset for fixed navbar
 */
function initSmoothScrolling() {
    // Find all anchor links (links that point to sections on same page)
    const links = document.querySelectorAll('a[href^="#"]');

    /**
     * Add click handler to each anchor link
     */
    links.forEach(link => {
        link.addEventListener('click', function (e) {
            // Get the target ID from href attribute (e.g., "#about" -> "#about")
            const targetId = this.getAttribute('href');

            // Skip if targetId is just '#' (invalid selector, would match nothing)
            if (targetId === '#') {
                return;
            }

            // Find the target element on the page
            const targetElement = document.querySelector(targetId);

            /**
             * Only prevent default if we have a valid target to scroll to
             * This allows broken links to behave normally (better for debugging)
             */
            if (targetElement) {
                // Prevent default anchor jump behavior
                e.preventDefault();

                // Calculate scroll position with offset for fixed navbar
                // navbar is ~80px tall, so we subtract that to scroll to correct position
                const offsetTop = targetElement.offsetTop - 80;

                // Scroll smoothly to the target position
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth', // Smooth animation instead of instant jump
                });
            }
        });
    });

    /**
     * Scroll indicator click behavior
     * The hero section has a "scroll down" indicator button
     * Clicking it scrolls to the "about" section
     */
    const scrollIndicator = document.querySelector('.scroll-indicator');
    if (scrollIndicator) {
        scrollIndicator.addEventListener('click', function () {
            const aboutSection = document.getElementById('about');
            if (aboutSection) {
                // Use scrollIntoView for simple, smooth scrolling
                aboutSection.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }
}

// ============================================================================
// SCROLL-TRIGGERED ANIMATIONS
// ============================================================================

/**
 * Add a 'loading' class when elements enter the viewport.
 * This class triggers CSS animations (fade-in effects).
 *
 * Uses IntersectionObserver API for efficient scroll detection.
 * IntersectionObserver is better than scroll events because:
 * - Only fires when elements enter/leave viewport (not on every scroll)
 * - More performant (browser-optimized)
 * - Automatically handles complex cases
 */
function initAnimations() {
    /**
     * IntersectionObserver configuration
     * - threshold: 0.1 = trigger when 10% of element is visible
     * - rootMargin: -50px bottom = trigger 50px before element fully enters viewport
     */
    const observerOptions = {
        threshold: 0.1, // Trigger when 10% visible
        rootMargin: '0px 0px -50px 0px', // Trigger 50px early for smoother effect
    };

    /**
     * Create IntersectionObserver
     * This watches for elements entering the viewport
     */
    const observer = new IntersectionObserver(function (entries) {
        // Process each element that changed visibility
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Element entered viewport: add 'loading' class to trigger animation
                entry.target.classList.add('loading');
                // Stop observing this element (animation only happens once)
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    /**
     * Start observing elements for animation
     * These CSS classes indicate elements that should animate on scroll
     */
    const animatedElements = document.querySelectorAll(
        '.menu-category-card, .gallery-item, .contact-info, .feature-item'
    );
    animatedElements.forEach(el => {
        observer.observe(el);
    });
}

// ============================================================================
// CONFIRMATION DIALOG
// ============================================================================

/**
 * Show a Bootstrap confirmation dialog with a callback on confirm.
 *
 * This creates a modal asking the user to confirm an action.
 * The modal is temporary (removed after use) and uses Bootstrap's modal component.
 *
 * @param {string} title - The title shown in the modal header
 * @param {string} message - The message shown in the modal body
 * @param {() => void} onConfirm - Callback function called when user clicks "Confirm"
 *
 * @example
 * showConfirmDialog(
 *   'Delete Item',
 *   'Are you sure you want to delete this item?',
 *   () => { console.log('User confirmed!'); }
 * );
 */
function showConfirmDialog(title, message, onConfirm) {
    // Remove existing modal if present (prevent duplicates)
    const existingModal = document.getElementById('confirmModal');
    if (existingModal) {
        existingModal.remove();
    }

    /**
     * Build modal HTML using template literal
     * This creates a Bootstrap modal structure with title, message, and buttons
     */
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

    // Append modal HTML to the page body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Get references to modal elements
    const modal = document.getElementById('confirmModal');
    const confirmBtn = document.getElementById('confirmBtn');

    // Create Bootstrap modal instance and show it
    const bootstrapModal = new window.bootstrap.Modal(modal);
    bootstrapModal.show();

    /**
     * Handle confirm button click
     * When user clicks "Confirm", hide modal and call callback
     */
    confirmBtn.addEventListener('click', function () {
        // Hide the modal
        bootstrapModal.hide();
        // Call the callback function if provided
        if (onConfirm) {
            onConfirm();
        }
    });

    /**
     * Cleanup when the modal is hidden
     * Remove the modal from DOM after animation completes
     */
    modal.addEventListener('hidden.bs.modal', function () {
        modal.remove();
    });
}

// Expose confirm dialog globally so other scripts can use it
window.showConfirmDialog = showConfirmDialog;

// ============================================================================
// NOTIFICATION HELPERS
// ============================================================================

/**
 * Common helper to pick a Bootstrap Icons class based on notification type.
 * This centralizes icon selection logic (DRY principle).
 *
 * @param {string} type - Notification type: 'success', 'error', 'warning', or 'info'
 * @returns {string} Bootstrap Icons class name
 */
function getIconClass(type) {
    if (type === 'success') return 'bi-check-circle';
    if (type === 'error') return 'bi-exclamation-triangle';
    if (type === 'warning') return 'bi-exclamation-triangle';
    return 'bi-info-circle'; // Default for 'info' or unknown types
}

/**
 * Helper to auto-hide notification after delay.
 * Provides smooth fade-out animation before removing from DOM.
 *
 * @param {HTMLElement} notification - The notification element to hide
 * @param {number} delay - Delay in milliseconds before starting fade-out
 */
function autoHideNotification(notification, delay) {
    setTimeout(() => {
        // Check if notification still exists in DOM (might have been manually closed)
        if (notification.parentNode) {
            // Add fade-out transition
            notification.style.transition = 'opacity 0.5s ease-out';
            notification.style.opacity = '0';

            // Remove from DOM after fade animation completes
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 500); // Wait for 0.5s fade animation
        }
    }, delay);
}

// ============================================================================
// NOTIFICATION FUNCTIONS
// ============================================================================

/**
 * Show a centered notification (auto hides after 5 seconds).
 *
 * This is used for form submissions and general user feedback.
 * The notification appears at the top center of the screen.
 *
 * @param {string} message - The message to display
 * @param {'info'|'success'|'warning'|'error'} [type='info'] - Notification type (affects color and icon)
 *
 * @example
 * showNotification('Form submitted successfully!', 'success');
 * showNotification('An error occurred', 'error');
 */
window.showNotification = function (message, type = 'info') {
    // Remove existing notifications to prevent stacking
    document.querySelectorAll('.notification').forEach(n => n.remove());

    /**
     * Create the notification element
     * Uses Bootstrap alert classes for styling
     */
    const notification = document.createElement('div');
    // Build className: handle 'error' -> 'danger' (Bootstrap uses 'danger' not 'error')
    notification.className = `notification alert alert-${type === 'error' ? 'danger' : type} alert-dismissible show position-fixed`;
    // Position at top center with high z-index so it appears above everything
    notification.style.cssText =
        'top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px; text-align: center;';

    // Get appropriate icon for notification type
    const icon = getIconClass(type);

    // Build notification HTML with icon, message, and close button
    notification.innerHTML = `
        <i class="bi ${icon} me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    // Add notification to page
    document.body.appendChild(notification);

    // Auto-hide after 5 seconds
    autoHideNotification(notification, 5000);
};

/**
 * Show a cart notification (top-right, auto hides after 3 seconds).
 *
 * This is used for cart-related actions (add to cart, remove from cart, etc.).
 * The notification appears at the top right, and hides faster than regular notifications.
 *
 * @param {string} message - The message to display
 * @param {'info'|'success'|'warning'|'error'} [type='info'] - Notification type
 *
 * @example
 * showCartNotification('Item added to cart!', 'success');
 */
window.showCartNotification = function (message, type = 'info') {
    // Remove existing cart notifications to prevent stacking
    document.querySelectorAll('.cart-notification').forEach(n => n.remove());

    /**
     * Create the notification element
     * Similar to showNotification, but positioned top-right
     */
    const notification = document.createElement('div');
    notification.className = `cart-notification alert alert-${type === 'error' ? 'danger' : type} alert-dismissible show position-fixed`;
    // Position at top right
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';

    // Get appropriate icon
    const icon = getIconClass(type);

    // Build notification HTML
    notification.innerHTML = `
        <i class="bi ${icon} me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    // Add notification to page
    document.body.appendChild(notification);

    // Auto-hide after 3 seconds (shorter than regular notifications for cart actions)
    autoHideNotification(notification, 3000);
};
