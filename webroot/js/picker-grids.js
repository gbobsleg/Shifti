// Fichier: webroot/js/picker-grids.js
$(function() { // Utiliser $(function() { ... }); est une syntaxe plus courte pour $(document).ready()

    var today = moment().format('DD/MM/YYYY');
    var localeOptions = { // Isoler les options de locale
        "format": "DD/MM/YYYY",
        "applyLabel": "Valider",
        "cancelLabel": "Annuler",
        "fromLabel": "De",
        "toLabel": "A",
        "customRangeLabel": "Personnaliser",
        "weekLabel": "W",
        "daysOfWeek": ["Di", "Lu", "Ma", "Me", "Je", "Ve", "Sa"],
        "monthNames": ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"],
        "firstDay": 1
    };
    var predefinedRanges = { // Isoler les ranges
        'Aujourd\'hui': [moment(), moment()],
        'Demain': [moment().add(1, 'days'), moment().add(1, 'days')],
        'Cette semaine': [moment().startOf('isoWeek'), moment().endOf('isoWeek')],
        'La semaine prochaine': [moment().add(1, 'week').startOf('isoWeek'), moment().add(1, 'week').endOf('isoWeek')], // Corrigé 'isoWeek' ici aussi
        'Ce mois-ci': [moment().startOf('month'), moment().endOf('month')],
        'Le mois prochain': [moment().add(1, 'month').startOf('month'), moment().add(1, 'month').endOf('month')]
    };

    // Cibler UNIQUEMENT #date-start
    $('#date-start').daterangepicker({
        // Utiliser les dates existantes ou aujourd'hui par défaut
        "startDate": moment($('#date-start').val(), localeOptions.format).isValid() ? moment($('#date-start').val(), localeOptions.format) : moment(),
        "endDate": moment($('#date-end').val(), localeOptions.format).isValid() ? moment($('#date-end').val(), localeOptions.format) : moment(),
        "showWeekNumbers": true,
        "timePicker": false,
        "autoApply": true, // Garder true
        "autoUpdateInput": false, // Garder false
        "showDropdowns": true,
        "minYear": 2021,
        "maxYear": 2030,
        "showISOWeekNumbers": true,
        "showCustomRangeLabel": true,
        "alwaysShowCalendars": true, // Mettre false est plus standard
        "ranges": predefinedRanges,
        "locale": localeOptions,
        "drops": "down"
    }, function(start, end, label) { // Le callback est appelé par autoApply après clic date fin OU clic range OU clic Valider
        console.log('Callback déclenché: ' + start.format('DD/MM/YYYY') + ' à ' + end.format('DD/MM/YYYY'));

        var selectedStartDate = start.format('DD/MM/YYYY');
        var selectedEndDate = end.format('DD/MM/YYYY');

        var $checkinInput = $('#date-start'); // Récupère $(this) implicitement ici
        var $checkoutInput = $('#date-end');

        // Mettre à jour les champs
        $checkinInput.val(selectedStartDate);
        $checkoutInput.val(selectedEndDate);
        console.log('Champs mis à jour:', selectedStartDate + ' ' + selectedEndDate);

        // --- CORRECTION : Fermer avec un léger délai ---
        // Utiliser setTimeout avec 0ms pousse l'exécution de hide() après
        // la fin du cycle d'événements actuel du navigateur/plugin.
        setTimeout(function() {
            // S'assurer que l'instance existe toujours avant de cacher
            if ($checkinInput.data('daterangepicker')) {
                $checkinInput.data('daterangepicker').hide();
                console.log('Picker caché via setTimeout');
            }
        }, 0);

        // Les lignes ci-dessous sont incorrectes car le picker n'est PAS attaché à #date-end
        // var checkOutPicker = $checkoutInput.data('daterangepicker');
        // if (checkOutPicker) { checkOutPicker.setStartDate(selectedStartDate); checkOutPicker.setEndDate(selectedEndDate); }
        // var checkInPicker = $checkinInput.data('daterangepicker');
        // if (checkInPicker) { checkInPicker.setStartDate(selectedStartDate); checkInPicker.setEndDate(selectedEndDate); }

    }); // Fin du callback et de l'initialisation

    // *** Assurer que le champ #date-end est bien readonly dans le HTML ***
    // (Vérification côté HTML dans templates/element/ranges-search-form.php)

}); // Fin $(function() { ... });
