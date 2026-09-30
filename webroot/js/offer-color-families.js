/**
 * Déplace les offres entre colonnes de familles et réindexe le POST au submit.
 * Les champs name/position existent dès la création d'une colonne, même vide.
 */
(function () {
    function init() {
        var form = document.getElementById('offer-color-families-form');
        var board = document.getElementById('offer-color-families-board');
        var template = document.getElementById('offer-color-family-template');
        var addButton = document.getElementById('offer-color-family-add');
        if (!form || !board || !template || !addButton) {
            return;
        }

        var nextColumnId = 1;
        board.querySelectorAll('[data-family-column="family"]').forEach(function () {
            nextColumnId += 1;
        });
        var shadeConfig = { hues: [], saturation: 0.62, minLightness: 0.32, maxLightness: 0.5 };
        try {
            shadeConfig = JSON.parse(board.getAttribute('data-shade-config') || '{}');
        } catch (error) {
            shadeConfig = { hues: [], saturation: 0.62, minLightness: 0.32, maxLightness: 0.5 };
        }

        function hslToHex(hue, saturation, lightness) {
            var h = ((hue % 360) + 360) % 360;
            var chroma = (1 - Math.abs(2 * lightness - 1)) * saturation;
            var x = chroma * (1 - Math.abs((h / 60) % 2 - 1));
            var match = lightness - chroma / 2;
            var red = 0;
            var green = 0;
            var blue = 0;
            if (h < 60) {
                red = chroma;
                green = x;
            } else if (h < 120) {
                red = x;
                green = chroma;
            } else if (h < 180) {
                green = chroma;
                blue = x;
            } else if (h < 240) {
                green = x;
                blue = chroma;
            } else if (h < 300) {
                red = x;
                blue = chroma;
            } else {
                red = chroma;
                blue = x;
            }
            function channel(value) {
                var hex = Math.round((value + match) * 255).toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            }
            return '#' + channel(red) + channel(green) + channel(blue);
        }

        function shadesFor(hue, count) {
            if (count < 1) {
                return [];
            }
            var hexes = [];
            var index;
            for (index = 0; index < count; index += 1) {
                var lightness = count === 1
                    ? (shadeConfig.minLightness + shadeConfig.maxLightness) / 2
                    : shadeConfig.minLightness + (shadeConfig.maxLightness - shadeConfig.minLightness) * index / (count - 1);
                var hex = hslToHex(hue, shadeConfig.saturation, lightness);
                while (hexes.indexOf(hex) !== -1 && lightness < 0.9) {
                    lightness += 0.01;
                    hex = hslToHex(hue, shadeConfig.saturation, lightness);
                }
                hexes.push(hex);
            }
            return hexes;
        }

        function setOfferColor(offer, hex) {
            var input = offer.querySelector('.js-offer-color');
            if (input) {
                input.value = hex;
            }
        }

        function explicitHue(column) {
            var select = column.querySelector('[data-field="hue"]');
            if (!select || select.value === '') {
                return null;
            }
            return parseInt(select.value, 10);
        }

        function resolvedHues() {
            var columns = familyColumns();
            var counts = {};
            shadeConfig.hues.forEach(function (hue) {
                counts[hue] = 0;
            });
            columns.forEach(function (column) {
                var hue = explicitHue(column);
                if (hue !== null && Object.prototype.hasOwnProperty.call(counts, hue)) {
                    counts[hue] += 1;
                }
            });
            return columns.map(function (column) {
                var hue = explicitHue(column);
                if (hue !== null) {
                    return hue;
                }
                var chosen = null;
                shadeConfig.hues.forEach(function (candidate) {
                    if (chosen === null && counts[candidate] === 0) {
                        chosen = candidate;
                    }
                });
                if (chosen === null) {
                    var least = null;
                    shadeConfig.hues.forEach(function (candidate) {
                        if (least === null || counts[candidate] < least) {
                            least = counts[candidate];
                        }
                    });
                    shadeConfig.hues.forEach(function (candidate) {
                        if (chosen === null && counts[candidate] === least) {
                            chosen = candidate;
                        }
                    });
                }
                counts[chosen] += 1;
                return chosen;
            });
        }

        function paintColumn(column) {
            var columns = familyColumns();
            var index = columns.indexOf(column);
            if (index === -1) {
                return;
            }
            var hue = resolvedHues()[index];
            var offers = column.querySelectorAll('.offer-color-family-offer');
            var shades = shadesFor(hue, offers.length);
            offers.forEach(function (offer, offerIndex) {
                setOfferColor(offer, shades[offerIndex]);
            });
        }

        function paintThemeColumns(changedColumn) {
            var columns = familyColumns();
            columns.forEach(function (column) {
                if (column === changedColumn || explicitHue(column) === null) {
                    paintColumn(column);
                }
            });
        }

        function columns() {
            return Array.prototype.slice.call(board.querySelectorAll('[data-family-column]'));
        }

        function familyColumns() {
            return columns().filter(function (column) {
                return column.getAttribute('data-family-column') === 'family';
            });
        }

        function columnById(columnId) {
            return board.querySelector('[data-column-id="' + columnId + '"]');
        }

        function placeOffer(offer, column) {
            var hidden = offer.querySelector('.js-offer-id');
            if (column.getAttribute('data-family-column') === 'unassigned') {
                if (hidden) {
                    hidden.remove();
                }
            } else if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.className = 'js-offer-id';
                hidden.value = offer.getAttribute('data-offer-id');
                offer.appendChild(hidden);
            }
            var select = offer.querySelector('.js-family-select');
            if (select) {
                select.value = column.getAttribute('data-column-id');
            }
            if (column.getAttribute('data-family-column') === 'unassigned') {
                setOfferColor(offer, offer.getAttribute('data-original-color'));
            } else if (explicitHue(column) !== null) {
                paintColumn(column);
            }
            refreshArrowStates();
        }

        function refreshArrowStates() {
            board.querySelectorAll('.offer-color-family-list').forEach(function (list) {
                var column = list.closest('[data-family-column]');
                var inFamily = column && column.getAttribute('data-family-column') === 'family';
                var offers = list.querySelectorAll('.offer-color-family-offer');
                offers.forEach(function (offer, index) {
                    var arrows = offer.querySelector('.offer-color-family-arrows');
                    if (arrows) {
                        arrows.hidden = !inFamily;
                    }
                    var up = offer.querySelector('.js-offer-up');
                    var down = offer.querySelector('.js-offer-down');
                    if (up) {
                        up.disabled = !inFamily || index === 0;
                    }
                    if (down) {
                        down.disabled = !inFamily || index === offers.length - 1;
                    }
                });
            });
        }

        function moveWithinFamily(offer, direction) {
            var list = offer.parentElement;
            var column = list ? list.closest('[data-family-column]') : null;
            if (!list || !column || column.getAttribute('data-family-column') !== 'family') {
                return;
            }
            var sibling = direction === 'up' ? offer.previousElementSibling : offer.nextElementSibling;
            if (!sibling || !sibling.classList.contains('offer-color-family-offer')) {
                return;
            }
            if (direction === 'up') {
                list.insertBefore(offer, sibling);
            } else {
                list.insertBefore(sibling, offer);
            }
            refreshArrowStates();
        }

        function rebuildSelects() {
            var families = familyColumns();
            board.querySelectorAll('.js-family-select').forEach(function (select) {
                var column = select.closest('[data-family-column]');
                var current = column ? column.getAttribute('data-column-id') : 'unassigned';
                select.innerHTML = '';
                var unassigned = document.createElement('option');
                unassigned.value = 'unassigned';
                unassigned.textContent = 'Sans famille';
                select.appendChild(unassigned);
                families.forEach(function (family) {
                    var option = document.createElement('option');
                    option.value = family.getAttribute('data-column-id');
                    var nameInput = family.querySelector('[data-field="name"]');
                    var label = nameInput && nameInput.value.trim() !== '' ? nameInput.value.trim() : 'Famille sans nom';
                    option.textContent = label;
                    select.appendChild(option);
                });
                select.value = current;
            });
        }

        function bindList(list) {
            list.addEventListener('dragover', function (event) {
                event.preventDefault();
                list.classList.add('is-drop-target');
            });
            list.addEventListener('dragleave', function () {
                list.classList.remove('is-drop-target');
            });
            list.addEventListener('drop', function (event) {
                event.preventDefault();
                list.classList.remove('is-drop-target');
                var offerId = event.dataTransfer.getData('text/plain');
                var offer = board.querySelector('[data-offer-id="' + offerId + '"]');
                var column = list.closest('[data-family-column]');
                if (!offer || !column || offer.parentElement === list) {
                    return;
                }
                list.appendChild(offer);
                placeOffer(offer, column);
            });
        }

        function bindOffer(offer) {
            offer.addEventListener('dragstart', function (event) {
                if (event.target.closest('.js-offer-up, .js-offer-down, .js-family-select, .js-offer-color')) {
                    event.preventDefault();
                    return;
                }
                event.dataTransfer.setData('text/plain', offer.getAttribute('data-offer-id'));
                event.dataTransfer.effectAllowed = 'move';
            });
            offer.querySelectorAll('.js-offer-up, .js-offer-down').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    moveWithinFamily(offer, button.classList.contains('js-offer-up') ? 'up' : 'down');
                });
            });
            var colorInput = offer.querySelector('.js-offer-color');
            if (colorInput) {
                colorInput.addEventListener('pointerdown', function () {
                    offer.draggable = false;
                });
                colorInput.addEventListener('pointerup', function () {
                    offer.draggable = true;
                });
                colorInput.addEventListener('click', function (event) {
                    event.stopPropagation();
                });
                colorInput.addEventListener('change', function () {
                    offer.draggable = true;
                });
                colorInput.addEventListener('blur', function () {
                    offer.draggable = true;
                });
            }
            var select = offer.querySelector('.js-family-select');
            if (!select) {
                return;
            }
            select.addEventListener('change', function () {
                var column = columnById(select.value);
                var list = column ? column.querySelector('.offer-color-family-list') : null;
                if (!column || !list) {
                    return;
                }
                list.appendChild(offer);
                placeOffer(offer, column);
            });
        }

        function bindColumn(column) {
            var list = column.querySelector('.offer-color-family-list');
            if (list) {
                bindList(list);
            }
            column.querySelectorAll('.offer-color-family-offer').forEach(bindOffer);
            var nameInput = column.querySelector('[data-field="name"]');
            if (nameInput) {
                nameInput.addEventListener('input', rebuildSelects);
            }
            var hueSelect = column.querySelector('[data-field="hue"]');
            if (hueSelect) {
                hueSelect.addEventListener('change', function () {
                    paintThemeColumns(column);
                });
            }
            var removeButton = column.querySelector('.js-remove-family');
            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    var unassigned = board.querySelector('[data-family-column="unassigned"] .offer-color-family-list');
                    column.querySelectorAll('.offer-color-family-offer').forEach(function (offer) {
                        unassigned.appendChild(offer);
                        placeOffer(offer, unassigned.closest('[data-family-column]'));
                    });
                    column.remove();
                    rebuildSelects();
                });
            }
        }

        columns().forEach(bindColumn);
        rebuildSelects();
        refreshArrowStates();
        familyColumns().forEach(function (column) {
            if (explicitHue(column) !== null) {
                paintColumn(column);
            }
        });

        addButton.addEventListener('click', function () {
            var fragment = template.content.cloneNode(true);
            var column = fragment.querySelector('[data-family-column]');
            var columnId = 'new-' + String(nextColumnId);
            nextColumnId += 1;
            column.setAttribute('data-column-id', columnId);
            board.appendChild(column);
            bindColumn(column);
            rebuildSelects();
            var nameInput = column.querySelector('[data-field="name"]');
            if (nameInput) {
                nameInput.focus();
            }
        });

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter;
            if (submitter && submitter.id === 'offer-color-family-apply') {
                var confirmed = window.confirm('Appliquer les couleurs affichées et cet ordre au planning ? C\'est immédiat pour tout le monde.');
                if (!confirmed) {
                    event.preventDefault();
                    return;
                }
            }
            var applying = submitter && submitter.id === 'offer-color-family-apply';
            var hues = applying ? resolvedHues() : [];
            familyColumns().forEach(function (column, index) {
                var nameInput = column.querySelector('[data-field="name"]');
                var positionInput = column.querySelector('[data-field="position"]');
                var hueInput = column.querySelector('[data-field="hue"]');
                nameInput.name = 'families[' + index + '][name]';
                positionInput.name = 'families[' + index + '][position]';
                positionInput.value = String(index);
                if (hueInput) {
                    hueInput.name = 'families[' + index + '][hue]';
                    if (applying && hueInput.value === '' && hues[index] !== undefined && hues[index] !== null) {
                        hueInput.value = String(hues[index]);
                    }
                }
                column.querySelectorAll('.js-offer-id').forEach(function (input) {
                    input.name = 'families[' + index + '][offer_ids][]';
                });
                column.querySelectorAll('.js-offer-color').forEach(function (input) {
                    if (applying) {
                        input.name = 'families[' + index + '][colors][]';
                    } else {
                        input.removeAttribute('name');
                    }
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
