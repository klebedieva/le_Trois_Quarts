// ============================================================================
// ADMIN DROPDOWN SCROLL HANDLING
// ============================================================================
// This module handles dropdown overflow scrolling for EasyAdmin forms.
// Prevents dropdowns (Tom Select, Select2) from being cut off at bottom of viewport
// by dynamically adding spacer elements and scrolling page when needed.

(function() {
  /**
   * Spacer element used to extend page height for scrolling
   * Created once and reused for all dropdown adjustments
   */
  let spacerEl = null;

  /**
   * Get scroll container for EasyAdmin
   * 
   * EasyAdmin uses window scroll by default.
   * Falls back to nearest scrollable ancestor if needed.
   * 
   * @returns {Window} Window object for scrolling
   */
  function getScrollContainer() {
    return window;
  }

  /**
   * Ensure dropdown is fully visible by adding spacer and scrolling
   * 
   * Calculates if dropdown overflows viewport bottom and adds
   * invisible spacer element to allow page scrolling.
   * 
   * @param {HTMLElement} dropdown - Dropdown element to check
   */
  function ensureSpaceAndScroll(dropdown) {
    if (!dropdown) return;
    
    /**
     * Calculate dropdown position and viewport overflow
     * Add 16px padding for visual spacing
     */
    const rect = dropdown.getBoundingClientRect();
    const overflow = rect.bottom - window.innerHeight + 16;
    
    if (overflow > 0) {
      /**
       * Create spacer element if it doesn't exist
       * Spacer is invisible and positioned at end of body
       */
      if (!spacerEl) {
        spacerEl = document.createElement('div');
        spacerEl.id = 'ea-dropdown-spacer';
        spacerEl.style.cssText = 'width:1px;height:0;visibility:hidden;';
        document.body.appendChild(spacerEl);
      }
      
      /**
       * Set spacer height to match overflow amount
       * This extends page height, allowing scroll
       */
      spacerEl.style.height = overflow + 'px';
      
      /**
       * Scroll page to reveal dropdown
       * Uses smooth scrolling for better UX
       */
      getScrollContainer().scrollTo({ 
        top: window.scrollY + overflow, 
        behavior: 'smooth' 
      });
    }
  }

  /**
   * Clear added spacer height
   * 
   * Resets spacer to 0px when dropdown closes.
   * Keeps spacer element in DOM for reuse.
   */
  function clearAddedSpace() {
    if (spacerEl) {
      spacerEl.style.height = '0px';
    }
  }

  /**
   * Watch Tom Select dropdowns for overflow
   * 
   * Tom Select is used by EasyAdmin for AssociationField/ChoiceField with tags.
   * Monitors clicks and checks all visible dropdowns for viewport overflow.
   */
  function watchTomSelect() {
    /**
     * Listen for clicks to detect dropdown opening
     * Uses requestAnimationFrame to wait for dropdown rendering
     */
    document.addEventListener('click', function() {
      requestAnimationFrame(function() {
        /**
         * Find all Tom Select dropdowns
         * Check each visible dropdown for overflow
         */
        const dropdowns = document.querySelectorAll('.ts-dropdown');
        dropdowns.forEach(function(dd) {
          /**
           * Only process visible dropdowns
           * Check both display style and offsetParent
           */
          if (dd.style.display !== 'none' && dd.offsetParent !== null) {
            ensureSpaceAndScroll(dd);
          }
        });
      });
    });

    /**
     * Clean up spacer when dropdown closes
     * Triggered when clicking outside dropdown or control
     */
    document.addEventListener('mousedown', function(e) {
      if (!e.target.closest('.ts-dropdown') && !e.target.closest('.ts-control')) {
        clearAddedSpace();
      }
    });
  }

  /**
   * Watch Select2 dropdowns for overflow (fallback support)
   * 
   * Select2 may be used in some legacy forms.
   * Uses MutationObserver to detect when Select2 dropdown opens.
   */
  function watchSelect2() {
    const observer = new MutationObserver(function() {
      const open = document.querySelector('.select2-container--open .select2-dropdown');
      if (open) {
        ensureSpaceAndScroll(open);
      } else {
        clearAddedSpace();
      }
    });
    
    /**
     * Observe document body for changes
     * Watches for attribute changes and DOM additions
     */
    observer.observe(document.body, { 
      attributes: true, 
      childList: true, 
      subtree: true 
    });
  }

  /**
   * Initialize dropdown scroll handling
   * 
   * Sets up watchers for Tom Select and Select2.
   * Also creates generic MutationObserver to catch late DOM changes.
   */
  document.addEventListener('DOMContentLoaded', function() {
    watchTomSelect();
    watchSelect2();

    /**
     * Generic observer to catch late DOM changes
     * 
     * TomSelect renders dropdowns dynamically after initial page load.
     * This observer catches newly added dropdowns and attribute changes.
     */
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(m) {
        /**
         * Check for newly added dropdown nodes
         * Process only element nodes with ts-dropdown class
         */
        if (m.addedNodes) {
          m.addedNodes.forEach(function(n) {
            if (n.nodeType === 1 && n.classList && n.classList.contains('ts-dropdown')) {
              ensureSpaceAndScroll(n);
            }
          });
        }
        
        /**
         * Check for attribute changes on dropdown elements
         * Handles visibility/display style changes
         */
        if (m.target && m.target.classList && m.target.classList.contains('ts-dropdown')) {
          ensureSpaceAndScroll(m.target);
        }
      });
    });
    
    /**
     * Observe document body for changes
     * Watches child list, subtree, and style/class attributes
     */
    observer.observe(document.body, { 
      childList: true, 
      subtree: true, 
      attributes: true, 
      attributeFilter: ['style', 'class'] 
    });
  });
})();

// ============================================================================
// ADMIN REPLY TEMPLATE FUNCTIONALITY
// ============================================================================
// This module provides quick reply templates for admin message responses.
// Allows admins to insert pre-written response templates into message textarea.
// Supports EasyAdmin Turbo/Stimulus navigation for dynamic page loads.

(function() {
    /**
     * Get client first name from global AdminConfig
     * 
     * Injected from template via window.AdminConfig for personalization.
     * Falls back to empty string if not available.
     * 
     * @type {string}
     */
    const clientFirstName = (window.AdminConfig && window.AdminConfig.clientFirstName) 
        ? window.AdminConfig.clientFirstName 
        : '';
    
    /**
     * Insert template text into message textarea
     * 
     * Sets textarea value to template and focuses the field.
     * 
     * @param {string} template - Template text to insert
     */
    function insertTemplate(template) {
        const messageTextarea = document.getElementById('message');
        if (messageTextarea) {
            messageTextarea.value = template;
            messageTextarea.focus();
        }
    }
    
    /**
     * Reply templates for different message types
     * 
     * Templates are used via data-template attribute on buttons.
     * Internal aliases provided for backward compatibility.
     * 
     * @type {Object<string, string>}
     */
    const templates = {
        // Used in UI (data-template attribute)
        reservation: `Nous avons bien reçu votre demande de réservation. Nous allons vérifier nos disponibilités et vous confirmer dans les plus brefs délais.

En attendant, vous pouvez également nous appeler au 04 91 92 96 16 pour une réservation immédiate.`,

        commande: `Merci pour votre commande. Elle est en cours de préparation/validation. Nous vous recontactons très vite avec les détails (montant, délai, retrait/livraison).`,

        evenement_prive: `Merci pour votre intérêt pour l'organisation d'un évènement privé au Trois Quarts. Afin de vous proposer une offre adaptée, pouvez‑vous nous préciser la date souhaitée, le nombre de personnes et vos besoins (menu, boissons, budget) ?

Nous reviendrons vers vous rapidement avec une proposition personnalisée.`,

        reclamation: `Nous sommes désolés d'apprendre votre insatisfaction et vous remercions de nous l'avoir signalée. Afin de comprendre et corriger le problème, pouvez‑vous nous donner quelques précisions ?

Nous ferons le nécessaire pour vous apporter une solution rapidement.`,

        suggestion: `Merci pour votre suggestion ! Nous apprécions vos retours qui nous aident à nous améliorer. Votre message a été transmis à l'équipe concernée.`,

        general: `Merci pour votre message. Nous revenons vers vous très prochainement avec plus d'informations.`,

        // Internal aliases (for backward compatibility)
        confirmation: `Merci pour votre message. Nous avons bien reçu votre demande et nous vous répondrons dans les plus brefs délais.`,

        information: `Pour répondre à votre demande, voici les informations que vous recherchez :

[Insérez ici les informations spécifiques]

N'hésitez pas à nous contacter si vous avez d'autres questions.`
    };
    
    /**
     * Set up template button event delegation
     * 
     * Uses event delegation on document for better performance.
     * Handles dynamically added buttons (e.g., after Turbo navigation).
     * Only set up once to prevent duplicate listeners.
     */
    document.addEventListener('click', function(e) {
        const button = e.target.closest('.template-btn');
        if (!button) return;
        
        const templateType = button.dataset.template;
        if (templates[templateType]) {
            insertTemplate(templates[templateType]);
        }
    });
    
    /**
     * Expose clearMessage function globally
     * 
     * Allows inline buttons to clear message textarea.
     * Used by buttons outside template button group.
     * Re-exposed on each page load to ensure it's available.
     */
    function bindTemplateButtons() {
        window.clearMessage = function() {
            const messageTextarea = document.getElementById('message');
            if (messageTextarea) {
                messageTextarea.value = '';
                messageTextarea.focus();
            }
        };
    }

    /**
     * Initialize template functionality
     * 
     * Binds on DOM ready and after EasyAdmin Turbo navigation.
     * Handles both initial page load and dynamic page changes.
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindTemplateButtons);
    } else {
        bindTemplateButtons();
    }
    
    /**
     * Re-bind clearMessage after Turbo navigation
     * 
     * EasyAdmin uses Turbo for navigation, which replaces page content.
     * This listener ensures clearMessage is available after page changes.
     */
    window.addEventListener('turbo:load', bindTemplateButtons);
})();
