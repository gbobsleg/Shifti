/**
 * Alerts — sélection en masse (filtres : crud-filters.js).
 */
$(document).ready(function () {
    function updateSelectedCount() {
        const checked = $('.alert-checkbox:checked').length;
        $('#selectedCount').text(checked + ' alerte(s) sélectionnée(s)');
        $('#bulkDeleteBtn').prop('disabled', checked === 0);
    }

    function updateSelectAllState() {
        const totalCheckboxes = $('.alert-checkbox').length;
        const checkedCheckboxes = $('.alert-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes);

        if (checkedCheckboxes > 0) {
            $('#selectAllBtn').hide();
            $('#deselectAllBtn').show();
        } else {
            $('#selectAllBtn').show();
            $('#deselectAllBtn').hide();
        }
    }

    $('#selectAll').on('change', function () {
        $('.alert-checkbox').prop('checked', $(this).prop('checked'));
        updateSelectedCount();
        updateSelectAllState();
    });

    $('.alert-checkbox').on('change', function () {
        updateSelectedCount();
        updateSelectAllState();
    });

    $('#selectAllBtn').on('click', function () {
        $('.alert-checkbox').prop('checked', true);
        $('#selectAll').prop('checked', true);
        updateSelectedCount();
        updateSelectAllState();
    });

    $('#deselectAllBtn').on('click', function () {
        $('.alert-checkbox').prop('checked', false);
        $('#selectAll').prop('checked', false);
        updateSelectedCount();
        updateSelectAllState();
    });

    $('#bulkActionsForm').on('submit', function (e) {
        const checked = $('.alert-checkbox:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert('Aucune alerte sélectionnée.');
            return false;
        }
        if (!confirm('Êtes-vous sûr de vouloir supprimer ' + checked + ' alerte(s) ?')) {
            e.preventDefault();
            return false;
        }
    });

    if ($('#bulkActionsForm').length) {
        updateSelectedCount();
        updateSelectAllState();
    }

    if (typeof window.initTooltips === 'function') {
        window.initTooltips();
    }
});
