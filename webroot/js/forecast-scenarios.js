/**
 * Forecast Scenarios - Amélioration UX des filtres
 */

$(document).ready(function() {
    // Auto-submit quand on change le statut
    $('select[name="status"]').on('change', function() {
        $(this).closest('form').submit();
    });

    // Activer les tooltips Bootstrap pour les dates relatives
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});
