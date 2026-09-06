/**
 * Planning Generation Jobs - Amélioration UX des filtres
 */

$(document).ready(function() {
    // Gestion de la sélection en masse
    function updateSelectedCount() {
        const checked = $('.job-checkbox:checked:not(:disabled)').length;
        $('#selectedCount').text(checked + ' job(s) sélectionné(s)');
        $('#bulkDeleteBtn').prop('disabled', checked === 0);
    }

    function updateSelectAllState() {
        const totalCheckboxes = $('.job-checkbox:not(:disabled)').length;
        const checkedCheckboxes = $('.job-checkbox:checked:not(:disabled)').length;
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
        $('.job-checkbox:not(:disabled)').prop('checked', isChecked);
        updateSelectedCount();
        updateSelectAllState();
    });

    $('.job-checkbox').on('change', function() {
        updateSelectedCount();
        updateSelectAllState();
    });

    $('#selectAllBtn').on('click', function() {
        $('.job-checkbox:not(:disabled)').prop('checked', true);
        $('#selectAll').prop('checked', true);
        updateSelectedCount();
        updateSelectAllState();
    });

    $('#deselectAllBtn').on('click', function() {
        $('.job-checkbox:not(:disabled)').prop('checked', false);
        $('#selectAll').prop('checked', false);
        updateSelectedCount();
        updateSelectAllState();
    });

    $('#bulkActionsForm').on('submit', function(e) {
        const checked = $('.job-checkbox:checked:not(:disabled)').length;
        if (checked === 0) {
            e.preventDefault();
            alert('Aucun job sélectionné.');
            return false;
        }
        
        if (!confirm('Êtes-vous sûr de vouloir supprimer ' + checked + ' job(s) ?\n\nLes brouillons et détails des jours seront supprimés automatiquement.')) {
            e.preventDefault();
            return false;
        }
    });

    updateSelectedCount();
    updateSelectAllState();
    
    // Bouton de rafraîchissement manuel
    $('#refreshBtn').on('click', function() {
        const $btn = $(this);
        const $icon = $btn.find('i');
        
        // Désactiver le bouton et animer l'icône
        $btn.prop('disabled', true);
        $icon.addClass('spinning');
        
        // Recharger la page en préservant les paramètres de requête
        setTimeout(function() {
            window.location.reload();
        }, 100);
    });
    
    // Active les tooltips Bootstrap
    if (typeof window.initTooltips === 'function') {
        window.initTooltips();
    }
});
