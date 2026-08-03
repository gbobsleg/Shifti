// Fonction pour gérer le tri
function handleSortChange() {
    const sortSelect = document.getElementById('sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('sort_by', selectedValue);
            window.location.href = currentUrl.toString();
        });
    }
}

// Fonction pour gérer l'icône du collapse des alertes
function handleAlertCollapseIcon() {
    var alertsContainer = $('#alertsCollapseContainer');
    var alertToggleButton = $('a[href="#alertsCollapseContainer"][data-toggle="collapse"]');
    var alertToggleIcon = alertToggleButton.find('i.bi');

    if (alertsContainer.length && alertToggleButton.length && alertToggleIcon.length) {
        alertsContainer.on('show.bs.collapse', function () {
            alertToggleIcon.removeClass('bi-chevron-right').addClass('bi-chevron-down');
            alertToggleButton.attr('aria-expanded', 'true');
        });

        alertsContainer.on('hide.bs.collapse', function () {
            alertToggleIcon.removeClass('bi-chevron-down').addClass('bi-chevron-right');
            alertToggleButton.attr('aria-expanded', 'false');
        });

        // Vérification initiale au chargement
        if (!alertsContainer.hasClass('show')) {
            alertToggleIcon.removeClass('bi-chevron-down').addClass('bi-chevron-right');
        } else {
            alertToggleIcon.removeClass('bi-chevron-right').addClass('bi-chevron-down');
        }
    }
}

// Exécution quand le DOM est prêt (pour le tri Vanilla JS)
document.addEventListener('DOMContentLoaded', handleSortChange);

// Exécution quand jQuery est prêt
$(document).ready(function() {

    handleAlertCollapseIcon();

    var $submitButton = $('#submitRanges'); // Mettre en cache le bouton
    var $flashContainer = $('.flash-container'); // Mettre en cache le conteneur flash

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
            // $flashContainer.empty().prepend('<div role="alert" class="alert alert-info alert-dismissible fade show"><button type="button" class="close" data-dismiss="alert">&times;</button>Aucune modification détectée.</div>');
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
                    var closeButton = '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
                    var alertClass = 'alert-secondary';
                    if (message.element === 'flash/error') alertClass = 'alert-danger';
                    if (message.element === 'flash/success') alertClass = 'alert-success';
                    if (message.element === 'flash/info') alertClass = 'alert-info';
                    
                    // Ajouter data-auto-dismiss pour les messages non-error (géré par flash-auto-dismiss.js)
                    var autoDismissAttr = (message.element !== 'flash/error') ? ' data-auto-dismiss="5000"' : '';
                    flashMessagesHTML += `<div role="alert" class="alert ${alertClass} alert-dismissible fade show"${autoDismissAttr}>${closeButton}${message.message}</div>`;
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
                $flashContainer.prepend('<div role="alert" class="alert alert-warning alert-dismissible fade show"><button type="button" class="close" data-dismiss="alert">&times;</button>Réponse inattendue du serveur.</div>');
            }

        }).fail(function(xhr, textStatus, errorThrown) {
            console.error("Erreur AJAX:", textStatus, errorThrown, xhr.responseText);
            var errorDetail = xhr.statusText || textStatus;
            try {
                var jsonError = JSON.parse(xhr.responseText);
                if(jsonError && jsonError.message) errorDetail = jsonError.message;
            } catch(e) {}
            $flashContainer.prepend(`<div role="alert" class="alert alert-danger alert-dismissible fade show"><button type="button" class="close" data-dismiss="alert">&times;</button>Erreur de communication (${errorDetail}). Vérifiez la console.</div>`);

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
