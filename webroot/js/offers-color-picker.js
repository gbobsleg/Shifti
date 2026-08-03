/**
 * Initialise Pickr sur le champ couleur des formulaires Offres (add/edit).
 * La couleur est appliquée directement sur le trigger (.offer-color-pickr-trigger).
 * Synchronise l'input #color à chaque save ; met à jour l'affichage à l'init, au save et au change.
 */
(function() {
    function initOfferColorPickr() {
        var input = document.getElementById('color');
        var trigger = document.querySelector('.offer-color-pickr-trigger');
        if (!input || !trigger || typeof Pickr === 'undefined') {
            return;
        }

        var defaultColor = (input.value && input.value.trim()) ? input.value.trim() : '#3498db';
        if (!/^#[0-9A-Fa-f]{6}$/.test(defaultColor) && !/^#[0-9A-Fa-f]{3}$/.test(defaultColor)) {
            defaultColor = '#3498db';
        }

        function toHex(color) {
            if (!color) return defaultColor;
            var hex = color.toHEXA().toString();
            return hex.length === 9 ? hex.substring(0, 7) : hex;
        }

        /** Applique la couleur directement sur le trigger (aucun enfant). */
        function applyColor(hex) {
            trigger.style.backgroundColor = hex;
            trigger.style.color = hex;
        }

        var pickr = Pickr.create({
            el: trigger,
            useAsButton: true,
            theme: 'classic',
            default: defaultColor,
            defaultRepresentation: 'HEX',
            lockOpacity: true,
            components: {
                preview: true,
                opacity: false,
                hue: true,
                interaction: {
                    hex: true,
                    input: true,
                    save: true,
                    cancel: true,
                    rgba: false,
                    hsla: false,
                    hsva: false,
                    cmyk: false,
                    clear: false
                }
            }
        });

        pickr.on('init', function() {
            pickr.setColor(defaultColor, true);
            pickr.applyColor(true);
            applyColor(defaultColor);
        });

        pickr.on('save', function(color) {
            if (color) {
                var hex = toHex(color);
                input.value = hex;
                applyColor(hex);
            }
        });

        pickr.on('change', function(color) {
            applyColor(toHex(color) || defaultColor);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOfferColorPickr);
    } else {
        initOfferColorPickr();
    }
})();
