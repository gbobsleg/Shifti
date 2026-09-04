/**
 * Skills - Amélioration UX des filtres
 */

$(document).ready(function() {
    // Auto-submit quand on change un select
    $('select[name="user_id"], select[name="offer_id"]').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Active les tooltips Bootstrap
    if (typeof window.initTooltips === 'function') {
        window.initTooltips();
    }
});
