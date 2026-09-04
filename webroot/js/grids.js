$(document).ready(function() {
    var $submitButton = $('#submitRanges');
    var $flashContainer = $('.flash-container');

    function flashToastHtml(kind, message, autoDismiss) {
        var autoAttr = (autoDismiss > 0) ? ' data-auto-dismiss="' + autoDismiss + '"' : '';
        return '<div class="flash-toast is-' + kind + '" role="alert"' + autoAttr + '>' +
            message +
            '<button type="button" class="btn-close" data-flash-dismiss aria-label="Fermer"></button></div>';
    }

    // Soumission AJAX du formulaire de planning
    $("#rangesForm").on("submit", function(e) {
        e.preventDefault(); // Empêche la soumission classique

        // --- Logique de préparation JSON (Déplacée depuis dragselect.js) ---
        const jsonField = document.getElementById('planning-data-json');
        if (!jsonField) {
            console.error("Erreur: Champ caché #planning-data-json manquant.");
            alert("Erreur technique: Impossible de préparer les données pour la sauvegarde.");
            return;
        }
        const rangesToSave = [];
        const modifiedCells = document.querySelectorAll('.td_quarter.is-modified'); // Utiliser un sélecteur plus précis

        if (modifiedCells.length === 0) {
            jsonField.value = "[]";
            // Optionnel : Afficher info et ne pas soumettre
            // $flashContainer.empty().prepend('<div role="alert" class="alert alert-info alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>Aucune modification détectée.</div>');
            // return;
        } else {
            modifiedCells.forEach(cell => {
                const offerId = cell.dataset.offerId;
                const rangeId = cell.dataset.rangeId || null;
                if (!cell.dataset.userId || !cell.dataset.start || !cell.dataset.end) {
                    console.warn("Cellule modifiée ignorée (data-attributes manquants):", cell);
                    return;
                }
                rangesToSave.push({
                    id: rangeId,
                    user_id: cell.dataset.userId,
                    date_start: cell.dataset.start,
                    date_end: cell.dataset.end,
                    offer_id: offerId === '0' ? '0' : (offerId || '0'),
                });
            });
            jsonField.value = JSON.stringify(rangesToSave);
        }
        // --- Fin Préparation JSON ---

        var form = $(this);
        var url = form.attr('action');

        // Feedback visuel + Nettoyage
        $submitButton.prop('disabled', true).val('Enregistrement...');
        $flashContainer.empty(); // Vider les anciens messages

        $.ajax({
            beforeSend: function (xhr) {
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            },
            type: 'post',
            url: url,
            data: form.serialize(),
            dataType: 'json', // **Ajouté : Indiquer explicitement le type attendu**
            headers: {
                'X-CSRF-Token': $('[name="_csrfToken"]').val()
            }
        }).done(function(response, textStatus, xhr) {
            var messages = response && response._message ? response._message : [];

            if (messages.length > 0) {
                var flashMessagesHTML = '';
                $.each(messages, function(index, message) {
                    var kind = 'info';
                    if (message.element === 'flash/error') kind = 'error';
                    if (message.element === 'flash/success') kind = 'success';
                    if (message.element === 'flash/warning') kind = 'warning';
                    var autoDismiss = (kind === 'success' || kind === 'info') ? 5000 : 0;
                    flashMessagesHTML += flashToastHtml(kind, message.message, autoDismiss);
                });
                $flashContainer.prepend(flashMessagesHTML);
                
                // Initialiser l'auto-dismiss pour les messages AJAX ajoutés dynamiquement
                if (typeof window.initFlashAutoDismiss === 'function') {
                    window.initFlashAutoDismiss($flashContainer);
                }

                // *** CORRECTION CRUCIALE : Nettoyer .is-modified si succès ***
                if (response.status === 'success') {
                    $('.td_quarter.is-modified').removeClass('is-modified');
                    // Optionnel: Vider jsonField si on ne veut pas resoumettre par erreur
                    // if (jsonField) jsonField.value = "[]";
                    // TODO: Mettre à jour data-range-id si le serveur renvoie les ID créés
                }
            } else {
                $flashContainer.prepend(flashToastHtml('warning', 'Réponse inattendue du serveur.', 0));
            }

        }).fail(function(xhr, textStatus, errorThrown) {
            console.error("Erreur AJAX:", textStatus, errorThrown, xhr.responseText);
            var errorDetail = xhr.statusText || textStatus;
            try {
                var jsonError = JSON.parse(xhr.responseText);
                if(jsonError && jsonError.message) errorDetail = jsonError.message;
            } catch(e) {}
            $flashContainer.prepend(flashToastHtml('error', 'Erreur de communication (' + errorDetail + '). Vérifiez la console.', 0));

        }).always(function() {
            // *** CORRECTION CRUCIALE : Réactiver le bouton ***
            $submitButton.prop('disabled', false).val('Enregistrer le planning');
        });
    });

    // Gestion des créneaux d'indisponibilité (zones grisées)
    // Permettre la sélection forcée avec confirmation
    $(document).on('click', '.td_unavailable', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $cell = $(this);
        
        // Récupérer l'offre sélectionnée depuis dragselect.js (variables globales)
        const selectedColor = window.selectedColor || "white";
        const selectedId = window.selectedId || "0";
        
        // Si aucune offre n'est sélectionnée, ne rien faire (pas d'alerte, pas de changement)
        if (selectedColor === "white" || selectedId === "0") {
            return false;
        }
        
        // Si déjà sélectionné forcément, permettre la sélection normale
        if ($cell.hasClass('force-selected')) {
            $cell.removeClass('td_unavailable force-selected');
            $cell.css('background', ''); // Retire le style inline hachuré
            
            // Une offre est sélectionnée : appliquer sa couleur
            $cell.css("background-color", selectedColor);
            $cell.attr('data-offer-id', selectedId);
            $cell.addClass('is-modified');
            return true;
        }
        
        // Sinon, demander confirmation
        if (confirm('Cet agent n\'est pas disponible sur ce créneau selon son contrat. Voulez-vous forcer l\'affectation ?')) {
            // Si accepté, débloquer et appliquer couleur
            $cell.removeClass('td_unavailable');
            $cell.css('background', ''); // Retire le style inline hachuré
            
            // Une offre est sélectionnée : appliquer sa couleur
            $cell.css("background-color", selectedColor);
            $cell.attr('data-offer-id', selectedId);
            $cell.addClass('is-modified');
            return true;
        }
        
        return false;
    });
}); // Fin $(document).ready
