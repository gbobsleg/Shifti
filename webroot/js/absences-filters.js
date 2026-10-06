/**
 * Absences - Amélioration UX des filtres et sélection en masse
 */

$(document).ready(function() {
    const $form = $('#bulkActionsForm');
    const totalCount = parseInt($form.attr('data-total-count'), 10) || 0;
    let deleteAllMatching = false;

    function checkboxCount() {
        return $('.range-checkbox').length;
    }

    function checkedCount() {
        return $('.range-checkbox:checked').length;
    }

    function clearMatchingFlag() {
        deleteAllMatching = false;
        $('#deleteAllMatching').val('0');
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
            $('#selectedCount').text(totalCount + ' absence(s) sélectionnée(s)');
            $('#bulkDeleteBtn').prop('disabled', false);
        } else {
            $('#selectedCount').text(checked + ' absence(s) sélectionnée(s)');
            $('#bulkDeleteBtn').prop('disabled', checked === 0);
        }
    }

    $('#selectAll').on('change', function() {
        $('.range-checkbox').prop('checked', $(this).prop('checked'));
        syncSelection();
    });

    $('.range-checkbox').on('change', function() {
        syncSelection();
    });

    $('#selectAllBtn').on('click', function() {
        $('.range-checkbox').prop('checked', true);
        syncSelection();
    });

    $('#deselectAllBtn').on('click', function() {
        $('.range-checkbox').prop('checked', false);
        clearMatchingFlag();
        syncSelection();
    });

    $('#selectAllResultsBtn').on('click', function() {
        if (!($('.range-checkbox').length > 0 && checkedCount() === checkboxCount() && totalCount > checkboxCount())) {
            return;
        }
        deleteAllMatching = true;
        $('#deleteAllMatching').val('1');
        syncSelection();
    });

    $form.on('submit', function(e) {
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
            alert('Aucune absence sélectionnée.');
            return false;
        }
        if (!confirm('Êtes-vous sûr de vouloir supprimer ' + checked + ' absence(s) ?')) {
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
