// Global cart count functionality
(function() {
    function unhideCartCount() {
        var el = document.getElementById('cartNavCount');
        if (el) { el.classList.remove('hidden'); }
    }
    document.addEventListener('DOMContentLoaded', unhideCartCount);
    window.addEventListener('load', unhideCartCount);
    // Run a few times after load to defeat late scripts
    var attempts = 0;
    var timer = setInterval(function() {
        unhideCartCount();
        attempts++;
        if (attempts > 10) clearInterval(timer);
    }, 150);
})();

// Global Bootstrap confirmation modal used by cart-api.js (vider panier)
window.showConfirmDialog = function(title, message, onConfirm) {
    try {
        var existing = document.getElementById('confirmModal');
        if (existing) { existing.remove(); }
        var html = '' +
            '<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">' +
            '  <div class="modal-dialog modal-dialog-centered">' +
            '    <div class="modal-content">' +
            '      <div class="modal-header">' +
            '        <h5 class="modal-title" id="confirmModalLabel">' + (title || 'Confirmation') + '</h5>' +
            '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '      </div>' +
            '      <div class="modal-body"><p>' + (message || '') + '</p></div>' +
            '      <div class="modal-footer">' +
            '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>' +
            '        <button type="button" class="btn btn-primary" id="confirmModalConfirmBtn">Confirmer</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        document.body.insertAdjacentHTML('beforeend', html);
        var modalEl = document.getElementById('confirmModal');
        var confirmBtn = document.getElementById('confirmModalConfirmBtn');
        var bsModal = window.bootstrap && window.bootstrap.Modal ? new window.bootstrap.Modal(modalEl) : null;
        if (bsModal) { bsModal.show(); }
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                try { if (bsModal) bsModal.hide(); } catch (e) {}
                if (typeof onConfirm === 'function') { onConfirm(); }
            }, { once: true });
        }
        modalEl.addEventListener('hidden.bs.modal', function() { modalEl.remove(); });
    } catch (e) {
        // Fallback to native confirm if Bootstrap/modal not available
        if (confirm(message || 'Confirmer ?')) {
            if (typeof onConfirm === 'function') { onConfirm(); }
        }
    }
};