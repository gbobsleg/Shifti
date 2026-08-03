/**
 * UserAvailabilities - Amélioration UX des filtres
 */

$(document).ready(function() {
    // Auto-submit quand on change un select
    $('#user-id, #day-of-week').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Activation des tooltips Bootstrap
    $('[data-toggle="tooltip"]').tooltip();
});

