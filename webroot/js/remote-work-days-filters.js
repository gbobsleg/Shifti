/**
 * Remote Work Days - Amélioration UX des filtres et sélection en masse
 */

$(document).ready(function() {
    // Auto-submit quand on change un select (agent ou type)
    $('#user-id, #range-type').on('change', function() {
        $(this).closest('form').submit();
    });

    // Gestion de la sélection en masse (comme ranges-filters)
    function updateSelectedCount() {
        const checked = $('.range-checkbox:checked').length;
        $('#selectedCount').text(checked + ' jour(s) sélectionné(s)');
        $('#bulkDeleteBtn').prop('disabled', checked === 0);
    }

    function updateSelectAllState() {
        const totalCheckboxes = $('.range-checkbox').length;
        const checkedCheckboxes = $('.range-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes);

        if (checkedCheckboxes > 0) {
            $('#selectAllBtn').hide();
            $('#deselectAllBtn').show();
        } else {
            $('#selectAllBtn').show();
            $('#deselectAllBtn').hide();
        }
    }

    $('#selectAll').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.range-checkbox').prop('checked', isChecked);
        updateSelectedCount();
        updateSelectAllState();
    });

    $('.range-checkbox').on('change', function() {
        updateSelectedCount();
        updateSelectAllState();
    });

    $('#selectAllBtn').on('click', function() {
        $('.range-checkbox').prop('checked', true);
        $('#selectAll').prop('checked', true);
        updateSelectedCount();
        updateSelectAllState();
    });

    $('#deselectAllBtn').on('click', function() {
        $('.range-checkbox').prop('checked', false);
        $('#selectAll').prop('checked', false);
        updateSelectedCount();
        updateSelectAllState();
    });

    $('#bulkActionsForm').on('submit', function(e) {
        const checked = $('.range-checkbox:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert('Aucun jour sélectionné.');
            return false;
        }
        if (!confirm('Êtes-vous sûr de vouloir supprimer ' + checked + ' jour(s) de télétravail ?')) {
            e.preventDefault();
            return false;
        }
    });

    if ($('#bulkActionsForm').length) {
        updateSelectedCount();
        updateSelectAllState();
    }
    
    // Activation des tooltips Bootstrap
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});
