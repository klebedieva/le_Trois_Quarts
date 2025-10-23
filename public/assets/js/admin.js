(function() {
  let spacerEl = null;

  function getScrollContainer() {
    // EasyAdmin uses window scroll; fallback to nearest scrollable ancestor if any
    return window;
  }

  function ensureSpaceAndScroll(dropdown) {
    if (!dropdown) return;
    var rect = dropdown.getBoundingClientRect();
    var overflow = rect.bottom - window.innerHeight + 16; // small padding
    if (overflow > 0) {
      // Create (or resize) a spacer at the end of body so the page can scroll
      if (!spacerEl) {
        spacerEl = document.createElement('div');
        spacerEl.id = 'ea-dropdown-spacer';
        spacerEl.style.cssText = 'width:1px;height:0;visibility:hidden;';
        document.body.appendChild(spacerEl);
      }
      spacerEl.style.height = overflow + 'px';
      getScrollContainer().scrollTo({ top: window.scrollY + overflow, behavior: 'smooth' });
    }
  }

  function clearAddedSpace() {
    if (spacerEl) spacerEl.style.height = '0px';
  }

  // Tom Select (used by EasyAdmin for AssociationField/ChoiceField with tags)
  function watchTomSelect() {
    document.addEventListener('click', function() {
      // wait next frame for dropdown to render
      requestAnimationFrame(function() {
        var dropdowns = document.querySelectorAll('.ts-dropdown');
        dropdowns.forEach(function(dd) {
          if (dd.style.display !== 'none' && dd.offsetParent !== null) {
            ensureSpaceAndScroll(dd);
          }
        });
      });
    });

    // When an option is selected or dropdown closes, clean up padding
    document.addEventListener('mousedown', function(e) {
      if (!e.target.closest('.ts-dropdown') && !e.target.closest('.ts-control')) {
        clearAddedSpace();
      }
    });
  }

  // Select2 fallback (if present somewhere)
  function watchSelect2() {
    var observer = new MutationObserver(function() {
      var open = document.querySelector('.select2-container--open .select2-dropdown');
      if (open) ensureSpaceAndScroll(open); else clearAddedSpace();
    });
    observer.observe(document.body, { attributes: true, childList: true, subtree: true });
  }

  document.addEventListener('DOMContentLoaded', function() {
    watchTomSelect();
    watchSelect2();

    // Generic observer to catch late DOM changes (TomSelect renders dynamically)
    var observer = new MutationObserver(function(mutations){
      mutations.forEach(function(m){
        // New dropdowns added
        m.addedNodes && m.addedNodes.forEach(function(n){
          if (n.nodeType === 1 && n.classList && n.classList.contains('ts-dropdown')) {
            ensureSpaceAndScroll(n);
          }
        });
        // Attribute changes (visibility/display)
        if (m.target && m.target.classList && m.target.classList.contains('ts-dropdown')) {
          ensureSpaceAndScroll(m.target);
        }
      });
    });
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style','class'] });
  });
})();

// Admin reply functionality
// Ensure script runs after EasyAdmin loads the page content
(function() {
    // Get client first name injected from template (via window.AdminConfig)
    const clientFirstName = (window.AdminConfig && window.AdminConfig.clientFirstName) ? window.AdminConfig.clientFirstName : '';
    
    // Function to insert a reply template
    function insertTemplate(template) {
        const messageTextarea = document.getElementById('message');
        if (messageTextarea) {
            messageTextarea.value = template;
            messageTextarea.focus();
        }
    }
    
    // Reply templates
    const templates = {
        // Used in UI (data-template)
        reservation: `Nous avons bien reçu votre demande de réservation. Nous allons vérifier nos disponibilités et vous confirmer dans les plus brefs délais.

En attendant, vous pouvez également nous appeler au 04 91 92 96 16 pour une réservation immédiate.`,

        commande: `Merci pour votre commande. Elle est en cours de préparation/validation. Nous vous recontactons très vite avec les détails (montant, délai, retrait/livraison).`,

        evenement_prive: `Merci pour votre intérêt pour l'organisation d'un évènement privé au Trois Quarts. Afin de vous proposer une offre adaptée, pouvez‑vous nous préciser la date souhaitée, le nombre de personnes et vos besoins (menu, boissons, budget) ?

Nous reviendrons vers vous rapidement avec une proposition personnalisée.`,

        reclamation: `Nous sommes désolés d'apprendre votre insatisfaction et vous remercions de nous l'avoir signalée. Afin de comprendre et corriger le problème, pouvez‑vous nous donner quelques précisions ?

Nous ferons le nécessaire pour vous apporter une solution rapidement.`,

        suggestion: `Merci pour votre suggestion ! Nous apprécions vos retours qui nous aident à nous améliorer. Votre message a été transmis à l'équipe concernée.`,

        general: `Merci pour votre message. Nous revenons vers vous très prochainement avec plus d'informations.`,

        // Internal aliases (for eventual compatibility)
        confirmation: `Merci pour votre message. Nous avons bien reçu votre demande et nous vous répondrons dans les plus brefs délais.`,

        information: `Pour répondre à votre demande, voici les informations que vous recherchez :

[Insérez ici les informations spécifiques]

N'hésitez pas à nous contacter si vous avez d'autres questions.`
    };
    
    // Événements pour les boutons de templates
    function bindTemplateButtons() {
        const templateButtons = document.querySelectorAll('.template-btn');
        templateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const templateType = this.dataset.template;
                if (templates[templateType]) {
                    insertTemplate(templates[templateType]);
                }
            });
        });
        // Exposer clearMessage pour le bouton inline
        window.clearMessage = function() {
            const messageTextarea = document.getElementById('message');
            if (messageTextarea) {
                messageTextarea.value = '';
                messageTextarea.focus();
            }
        };
    }

    // Bind on DOM ready and after EA redraws (Turbo/Stimulus)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindTemplateButtons);
    } else {
        bindTemplateButtons();
    }
    window.addEventListener('turbo:load', bindTemplateButtons);
})();
