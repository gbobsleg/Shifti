$( document ).ready(function() {
    let selectedColor = "white";
    let selectedId = "0";
    let offerColor = "white"; // Couleur appliquée lors du drag
    let offerId = "0";      // ID appliqué lors du drag
    let userId = "";        // ID de l'utilisateur sur la ligne en cours de drag
    let isDragging = false; // Indicateur pour savoir si on est en train de glisser
    
    // Exposer les variables globalement pour grids.js
    window.selectedColor = selectedColor;
    window.selectedId = selectedId;

    // Mémorisation de l'offre sélectionnée par l'utilisateur
    $('.offerColor').click(function(e) {
        selectedColor = $(this).attr('data-color') || "white"; // Fallback
        selectedId = $(this).attr('data-id') || "0";        // Fallback
        // Mettre à jour les variables globales
        window.selectedColor = selectedColor;
        window.selectedId = selectedId;
        // Optionnel : Indiquer visuellement l'offre sélectionnée
        $('.offerColor').removeClass('selected-offer');
        $(this).addClass('selected-offer');
    });

    // Désactiver le menu contextuel par défaut sur les cellules
    $('.td_quarter').bind("contextmenu",function(e){
        e.preventDefault();
        return false;
    });

    // Mousedown sur une cellule (début potentiel du drag)
    $('.td_quarter').mousedown(function(e) {
        const $cell = $(this);
        
        // Si la case est indisponible, vérifier qu'une offre est sélectionnée et demander confirmation
        if ($cell.hasClass('td_unavailable')) {
            // Si aucune offre n'est sélectionnée, bloquer le drag
            if (selectedColor === "white" || selectedId === "0") {
                e.preventDefault();
                return false;
            }
            
            // Si déjà débloquée précédemment, continuer normalement
            if ($cell.hasClass('force-selected')) {
                // Retirer la classe et le style inline avant le drag (comme pour les cellules normales)
                $cell.removeClass('td_unavailable force-selected');
                $cell.css('background', ''); // Retire le style inline hachuré
            } else {
                // Demander confirmation
                if (!confirm('Cet agent n\'est pas disponible sur ce créneau selon son contrat. Voulez-vous forcer l\'affectation ?')) {
                    e.preventDefault();
                    return false; // Bloquer le drag si refusé
                }
                // Si accepté, retirer la classe et le style inline avant le drag
                $cell.removeClass('td_unavailable');
                $cell.css('background', ''); // Retire le style inline hachuré
            }
        }
        
        isDragging = true;
        userId = $cell.closest("tr").attr('data_user');

        if (e.buttons === 1) { // Clic gauche
            offerColor = selectedColor; // Utilise la couleur sélectionnée
            offerId = selectedId;     // Utilise l'ID sélectionné
            applyChange(this);
        } else if (e.buttons === 2) { // Clic droit (Suppression)
            offerColor = "white";
            offerId = "0";
            applyChange(this);
        }
        e.preventDefault(); // Empêche la sélection de texte
    });

    // Mouseover sur une cellule (pendant le drag)
    $('.td_quarter').mouseover(function(e) {
        if (!isDragging) return;
        const $cell = $(this);
        var newUserId = $cell.closest("tr").attr('data_user');

        if (userId === newUserId) {
            // Si la case est indisponible, retirer la classe et le style inline avant d'appliquer la couleur
            if ($cell.hasClass('td_unavailable')) {
                $cell.removeClass('td_unavailable force-selected');
                $cell.css('background', ''); // Retire le style inline hachuré
            }
            // La couleur/id a été définie dans mousedown
            applyChange(this);
        }
    });

    // Mouseup n'importe où sur la page (fin du drag)
    $(document).mouseup(function(e) {
        if (isDragging) {
            isDragging = false;
            userId = "";
        }
    });

    // Fonction pour appliquer la couleur, marquer comme modifié et mettre à jour les data-*
    function applyChange(cellElement) {
        const $cell = $(cellElement);
        $cell.css("background-color", offerColor);
        $cell.addClass('is-modified'); // Marque pour la sauvegarde
        $cell.attr('data-offer-id', offerId); // Met à jour l'ID de l'offre pour la sauvegarde
    }

});
