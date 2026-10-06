/**
 * Alerts — sélection en masse (filtres : crud-filters.js).
 */
$(document).ready(function () {
    const $form = $('#bulkActionsForm');
    const totalCount = parseInt($form.attr('data-total-count'), 10) || 0;
    const unfiltered = $form.attr('data-unfiltered') === '1';
    let deleteAllMatching = false;

    function checkboxCount() {
        return $('.alert-checkbox').length;
    }

    function checkedCount() {
        return $('.alert-checkbox:checked').length;
    }

    function clearMatchingFlag() {
        deleteAllMatching = false;
        $('#deleteAllMatching').val('0');
        $('#confirmPurgeAll').val('0');
    }

    function syncSelection() {
        const totalBoxes = checkboxCount();
        const checked = checkedCount();
        const allChecked = totalBoxes > 0 && checked === totalBoxes;
        $('#selectAll').prop('checked', allChecked);

        if (!allChecked) {
            clearMatchingFlag();
        }

        if (checked > 0) {
            $('#selectAllBtn').hide();
            $('#deselectAllBtn').show();
        } else {
            $('#selectAllBtn').show();
            $('#deselectAllBtn').hide();
        }

        const showAllResults = allChecked && !deleteAllMatching && totalCount > totalBoxes;
        $('#selectAllResultsBtn').prop('hidden', !showAllResults);

        if (deleteAllMatching) {
            $('#selectedCount').text(totalCount + ' alerte(s) sélectionnée(s)');
            $('#bulkDeleteBtn').prop('disabled', false);
        } else {
            $('#selectedCount').text(checked + ' alerte(s) sélectionnée(s)');
            $('#bulkDeleteBtn').prop('disabled', checked === 0);
        }
    }

    $('#selectAll').on('change', function () {
        $('.alert-checkbox').prop('checked', $(this).prop('checked'));
        syncSelection();
    });

    $('.alert-checkbox').on('change', function () {
        syncSelection();
    });

    $('#selectAllBtn').on('click', function () {
        $('.alert-checkbox').prop('checked', true);
        syncSelection();
    });

    $('#deselectAllBtn').on('click', function () {
        $('.alert-checkbox').prop('checked', false);
        clearMatchingFlag();
        syncSelection();
    });

    $('#selectAllResultsBtn').on('click', function () {
        if (!($('.alert-checkbox').length > 0 && checkedCount() === checkboxCount() && totalCount > checkboxCount())) {
            return;
        }
        deleteAllMatching = true;
        $('#deleteAllMatching').val('1');
        if (unfiltered) {
            $('#confirmPurgeAll').val('1');
        }
        syncSelection();
    });

    $form.on('submit', function (e) {
        if ($('#deleteAllMatching').val() === '1') {
            if (!confirm('Êtes-vous sûr de vouloir supprimer les ' + totalCount + ' résultats de cette recherche ?')) {
                e.preventDefault();
                return false;
            }
            return true;
        }
        const checked = checkedCount();
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

    if ($form.length) {
        syncSelection();
    }

    if (typeof window.initTooltips === 'function') {
        window.initTooltips();
    }
});
