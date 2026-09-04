/**
 * Alerts - Amélioration UX des filtres
 */

$(document).ready(function() {
    // Auto-submit quand on change la priorité
    $('select[name="priority"]').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Active les tooltips Bootstrap
    if (typeof window.initTooltips === 'function') {
        window.initTooltips();
    }
});
