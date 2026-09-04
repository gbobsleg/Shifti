/**
 * Ouvre le premier onglet Users qui contient une erreur de validation Cake.
 */
document.addEventListener('DOMContentLoaded', function () {
    var error = document.querySelector(
        '.tab-pane .error, .tab-pane .is-invalid, .tab-pane .form-error, .tab-pane .error-message'
    );
    if (!error) {
        return;
    }
    var pane = error.closest('.tab-pane');
    if (!pane || !pane.id) {
        return;
    }
    var btn = document.querySelector('[data-bs-target="#' + pane.id + '"]');
    if (!btn || typeof bootstrap === 'undefined' || !bootstrap.Tab) {
        return;
    }
    bootstrap.Tab.getOrCreateInstance(btn).show();
});
