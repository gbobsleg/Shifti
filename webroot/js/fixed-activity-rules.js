$(document).ready(function() {
    // Auto-submit sur changement des selects
    $('#offer-id, #site-mode, #active-status').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Active les tooltips Bootstrap
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});

