/**
 * Users - Amélioration UX des filtres
 */

$(document).ready(function() {
    // Auto-submit quand on change un select (rôle ou site)
    $('select[name="role_id"], select[name="site_id"]').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Active les tooltips Bootstrap
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});

