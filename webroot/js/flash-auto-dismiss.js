/**
 * Auto-dismiss pour les messages flash
 * 
 * Détecte les alertes avec l'attribut data-auto-dismiss et les masque
 * automatiquement après le délai spécifié.
 * Gère aussi les messages ajoutés dynamiquement au DOM.
 */

// Fonction réutilisable pour initialiser l'auto-dismiss sur des alertes
function initFlashAutoDismiss($container) {
    // Vérifier que jQuery est disponible
    if (typeof jQuery === 'undefined') {
        return;
    }
    
    var $ = jQuery;
    
    // Si aucun conteneur n'est fourni, utiliser document
    $container = $container || $(document);
    
    // Sélectionner toutes les alertes avec l'attribut data-auto-dismiss qui n'ont pas déjà été traitées
    var $alerts = $container.find('.alert[data-auto-dismiss]:not(.auto-dismiss-initialized)');
    
    $alerts.each(function() {
        var $alert = $(this);
        var delay = parseInt($alert.attr('data-auto-dismiss'), 10);
        
        // Marquer comme initialisée pour éviter les doubles traitements
        $alert.addClass('auto-dismiss-initialized');
        
        // Vérifier que le délai est valide
        if (delay > 0 && !isNaN(delay)) {
            // Masquer l'alerte après le délai spécifié
            setTimeout(function() {
                // Utiliser la méthode Bootstrap pour masquer avec animation fade
                $alert.fadeOut('slow', function() {
                    // Retirer l'élément du DOM après l'animation
                    $(this).remove();
                });
            }, delay);
        }
    });
}

// S'assurer que jQuery est disponible avant d'exécuter le code
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function($) {
        // Initialiser pour les messages déjà présents dans le DOM
        // Chercher dans tout le document pour être sûr de trouver les messages
        initFlashAutoDismiss();
        
        // Observer les changements dans le conteneur flash pour détecter les messages ajoutés dynamiquement
        var flashContainer = document.querySelector('.flash-container');
        if (flashContainer && typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        // Vérifier si des alertes ont été ajoutées
                        var $addedNodes = $(mutation.addedNodes);
                        if ($addedNodes.find('.alert[data-auto-dismiss]').length > 0 || $addedNodes.is('.alert[data-auto-dismiss]')) {
                            initFlashAutoDismiss($(flashContainer));
                        }
                    }
                });
            });
            
            observer.observe(flashContainer, {
                childList: true,
                subtree: true
            });
        }
        
        // Fallback : réinitialiser après un court délai au cas où les messages seraient ajoutés après le ready
        setTimeout(function() {
            initFlashAutoDismiss();
        }, 500);
    });
} else {
    // Si jQuery n'est pas encore disponible, attendre un peu et réessayer
    setTimeout(function() {
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function($) {
                initFlashAutoDismiss();
                setTimeout(function() {
                    initFlashAutoDismiss();
                }, 500);
            });
        }
    }, 100);
}

// Exposer la fonction globalement pour qu'elle puisse être appelée manuellement si nécessaire
window.initFlashAutoDismiss = initFlashAutoDismiss;

