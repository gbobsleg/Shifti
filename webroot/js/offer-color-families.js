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
        var shadeConfig = {};
        try {
            shadeConfig = JSON.parse(board.getAttribute('data-shade-config') || '{}');
        } catch (error) {
            shadeConfig = {};
        }
        var dirty = board.getAttribute('data-board-dirty') === '1';

        function configNumber(key) {
            var value = shadeConfig[key];
            return typeof value === 'number' ? value : null;
        }

        function shadeReady() {
            return configNumber('saturation') !== null
                && configNumber('warmHueMin') !== null
                && configNumber('warmHueMax') !== null
                && configNumber('hueArcLeft') !== null
                && configNumber('hueArcRight') !== null
                && configNumber('saturationDark') !== null
                && configNumber('saturationLight') !== null
                && configNumber('saturationWarm') !== null
                && configNumber('contrastLight') !== null
                && configNumber('contrastDarkWarm') !== null
                && configNumber('contrastDark') !== null
                && configNumber('lightnessMin') !== null
                && configNumber('lightnessMax') !== null
                && configNumber('contrastTolerance') !== null
                && configNumber('maxIterations') !== null
                && configNumber('pairLightness') !== null
                && configNumber('pastelSaturation') !== null
                && configNumber('pastelLightness') !== null
                && configNumber('pastelPairSaturation') !== null
                && configNumber('pastelPairLightness') !== null
                && Array.isArray(shadeConfig.hues);
        }

        function normalizeHue(hue) {
            return ((hue % 360) + 360) % 360;
        }

        function linearize(channel) {
            if (channel <= 0.04045) {
                return channel / 12.92;
            }
            return Math.pow((channel + 0.055) / 1.055, 2.4);
        }

        function hslToRgb(hue, saturation, lightness) {
            var h = normalizeHue(hue);
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
            return [red + match, green + match, blue + match];
        }

        function contrastWithWhite(hue, lightness, saturation) {
            var rgb = hslToRgb(hue, saturation, lightness);
            var red = Math.round(rgb[0] * 255) / 255;
            var green = Math.round(rgb[1] * 255) / 255;
            var blue = Math.round(rgb[2] * 255) / 255;
            var luminance = 0.2126 * linearize(red) + 0.7152 * linearize(green) + 0.0722 * linearize(blue);
            return 1.05 / (luminance + 0.05);
        }

        function lightnessForContrast(hue, target, saturation) {
            var minLightness = configNumber('lightnessMin');
            var maxLightness = configNumber('lightnessMax');
            var tolerance = configNumber('contrastTolerance');
            var iterations = configNumber('maxIterations');
            if (contrastWithWhite(hue, minLightness, saturation) < target) {
                return minLightness;
            }
            if (contrastWithWhite(hue, maxLightness, saturation) > target) {
                return highestLightnessAtLeast(hue, configNumber('contrastLight'), saturation);
            }
            var low = minLightness;
            var high = maxLightness;
            var best = (low + high) / 2;
            var iteration;
            for (iteration = 0; iteration < iterations; iteration += 1) {
                var mid = (low + high) / 2;
                best = mid;
                var contrast = contrastWithWhite(hue, mid, saturation);
                if (Math.abs(contrast - target) <= tolerance) {
                    return mid;
                }
                if (contrast > target) {
                    low = mid;
                } else {
                    high = mid;
                }
            }
            return best;
        }

        function highestLightnessAtLeast(hue, minimum, saturation) {
            var minLightness = configNumber('lightnessMin');
            var maxLightness = configNumber('lightnessMax');
            var iterations = configNumber('maxIterations');
            if (contrastWithWhite(hue, maxLightness, saturation) >= minimum) {
                return maxLightness;
            }
            if (contrastWithWhite(hue, minLightness, saturation) < minimum) {
                return minLightness;
            }
            var low = minLightness;
            var high = maxLightness;
            var iteration;
            for (iteration = 0; iteration < iterations; iteration += 1) {
                var mid = (low + high) / 2;
                if (contrastWithWhite(hue, mid, saturation) >= minimum) {
                    low = mid;
                } else {
                    high = mid;
                }
            }
            return low;
        }

        function stayInsideContrastBand(hue, lightness, ceiling, saturation) {
            var minLightness = configNumber('lightnessMin');
            var maxLightness = configNumber('lightnessMax');
            var floor = configNumber('contrastLight');
            var guard;
            for (guard = 0; guard < 200; guard += 1) {
                var contrast = contrastWithWhite(hue, lightness, saturation);
                if (contrast < floor && lightness > minLightness) {
                    lightness = Math.max(minLightness, lightness - 0.002);
                    continue;
                }
                if (contrast > ceiling && lightness < maxLightness) {
                    lightness = Math.min(maxLightness, lightness + 0.002);
                    continue;
                }
                break;
            }
            return lightness;
        }

        function hslToHex(hue, saturation, lightness) {
            var rgb = hslToRgb(hue, saturation, lightness);
            function channel(value) {
                var hex = Math.round(value * 255).toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            }
            return '#' + channel(rgb[0]) + channel(rgb[1]) + channel(rgb[2]);
        }

        function arcRooms() {
            return [configNumber('hueArcLeft'), configNumber('hueArcRight')];
        }

        function shadesFor(hue, count, pastel) {
            if (!shadeReady() || count < 1) {
                return [];
            }
            var normalized = normalizeHue(hue);
            var saturation = pastel ? configNumber('pastelSaturation') : 1;
            var lightness = pastel ? configNumber('pastelLightness') : 0.5;
            if (count === 1) {
                return [hslToHex(normalized, saturation, lightness)];
            }
            var rooms = arcRooms();
            if (count === 2) {
                if (pastel) {
                    return [
                        hslToHex(normalized, configNumber('pastelPairSaturation'), configNumber('pastelPairLightness')),
                        hslToHex(normalized, saturation, lightness)
                    ];
                }
                return [
                    hslToHex(normalized, 1, configNumber('pairLightness')),
                    hslToHex(normalized, 1, 0.5)
                ];
            }
            var center = Math.round((count - 1) / 2);
            var hexes = [];
            var index;
            for (index = 0; index < count; index += 1) {
                if (index === center) {
                    hexes.push(hslToHex(normalized, saturation, lightness));
                    continue;
                }
                var offerHue = normalized;
                if (index < center) {
                    offerHue = normalizeHue(Math.round(normalized - rooms[0] * (center - index) / center));
                } else {
                    offerHue = normalizeHue(Math.round(
                        normalized + rooms[1] * (index - center) / (count - 1 - center)
                    ));
                }
                hexes.push(hslToHex(offerHue, saturation, lightness));
            }
            return hexes;
        }

        function markDirty() {
            dirty = true;
        }

        function setOfferColor(offer, hex) {
            var input = offer.querySelector('.js-offer-color');
            if (input) {
                input.value = hex;
            }
        }

        function columnPastel(column) {
            var input = column.querySelector('[data-field="pastel"]');
            return !!(input && input.checked);
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
            var hues = Array.isArray(shadeConfig.hues) ? shadeConfig.hues : [];
            hues.forEach(function (hue) {
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
                hues.forEach(function (candidate) {
                    if (chosen === null && counts[candidate] === 0) {
                        chosen = candidate;
                    }
                });
                if (chosen === null) {
                    var least = null;
                    hues.forEach(function (candidate) {
                        if (least === null || counts[candidate] < least) {
                            least = counts[candidate];
                        }
                    });
                    hues.forEach(function (candidate) {
                        if (chosen === null && counts[candidate] === least) {
                            chosen = candidate;
                        }
                    });
                }
                if (chosen !== null) {
                    counts[chosen] += 1;
                }
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
            var shades = shadesFor(hue, offers.length, columnPastel(column));
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
            markDirty();
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

        var draggedOffer = null;

        function clearDropMarker() {
            board.querySelectorAll('.is-drop-before, .is-drop-end').forEach(function (node) {
                node.classList.remove('is-drop-before', 'is-drop-end');
            });
        }

        function offersIn(list) {
            return Array.prototype.filter.call(list.children, function (node) {
                return node.classList && node.classList.contains('offer-color-family-offer');
            });
        }

        // L'offre trainée occupe encore sa place. L'index vise la liste telle qu'elle est.
        function dropIndex(list, clientY) {
            var offers = offersIn(list);
            var draggedIndex = offers.indexOf(draggedOffer);
            if (draggedIndex === -1) {
                return null;
            }
            var targetIndex = offers.length;
            var index;
            for (index = 0; index < offers.length; index += 1) {
                if (offers[index] === draggedOffer) {
                    continue;
                }
                var rect = offers[index].getBoundingClientRect();
                if (clientY < rect.top + rect.height / 2) {
                    targetIndex = index;
                    break;
                }
            }
            if (targetIndex === draggedIndex || targetIndex === draggedIndex + 1) {
                return null;
            }
            return targetIndex;
        }

        function showDropMarker(list, targetIndex) {
            clearDropMarker();
            if (targetIndex === null) {
                return;
            }
            var offers = offersIn(list);
            if (targetIndex >= offers.length) {
                list.classList.add('is-drop-end');
                return;
            }
            offers[targetIndex].classList.add('is-drop-before');
        }

        function moveToIndex(list, offer, targetIndex) {
            var offers = offersIn(list);
            if (targetIndex >= offers.length) {
                list.appendChild(offer);
                return;
            }
            list.insertBefore(offer, offers[targetIndex]);
        }

        function bindList(list) {
            list.addEventListener('dragover', function (event) {
                event.preventDefault();
                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'move';
                }
                if (draggedOffer && draggedOffer.parentElement === list) {
                    list.classList.remove('is-drop-target');
                    showDropMarker(list, dropIndex(list, event.clientY));
                    return;
                }
                clearDropMarker();
                list.classList.add('is-drop-target');
            });
            list.addEventListener('dragleave', function () {
                list.classList.remove('is-drop-target');
            });
            list.addEventListener('drop', function (event) {
                event.preventDefault();
                var offerId = event.dataTransfer.getData('text/plain');
                var offer = board.querySelector('.offer-color-family-offer[data-offer-id="' + offerId + '"]');
                var column = list.closest('[data-family-column]');
                var sameList = draggedOffer && draggedOffer.parentElement === list;
                var targetIndex = sameList ? dropIndex(list, event.clientY) : null;
                clearDropMarker();
                list.classList.remove('is-drop-target');
                if (!offer || !column) {
                    return;
                }
                if (offer.parentElement === list) {
                    if (targetIndex === null) {
                        return;
                    }
                    moveToIndex(list, offer, targetIndex);
                    refreshArrowStates();
                    markDirty();
                    return;
                }
                list.appendChild(offer);
                placeOffer(offer, column);
                markDirty();
            });
        }

        function bindOffer(offer) {
            offer.addEventListener('dragstart', function (event) {
                if (event.target.closest('.js-offer-up, .js-offer-down, .js-family-select, .js-offer-color')) {
                    event.preventDefault();
                    return;
                }
                draggedOffer = offer;
                event.dataTransfer.setData('text/plain', offer.getAttribute('data-offer-id'));
                event.dataTransfer.effectAllowed = 'move';
            });
            offer.addEventListener('dragend', function () {
                draggedOffer = null;
                clearDropMarker();
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
                    markDirty();
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
                markDirty();
            });
        }

        var openHueMenu = null;

        function closeHueMenu() {
            if (!openHueMenu) {
                return;
            }
            openHueMenu.hidden = true;
            var toggle = openHueMenu.parentElement.querySelector('.offer-color-hue-button');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            openHueMenu = null;
        }

        function paintHueButton(select) {
            var picker = select.closest('.offer-color-hue-picker');
            if (!picker) {
                return;
            }
            var button = picker.querySelector('.offer-color-hue-button');
            var selected = select.options[select.selectedIndex];
            button.replaceChildren();
            if (selected && selected.getAttribute('data-base')) {
                var swatch = document.createElement('span');
                swatch.className = 'offer-color-hue-swatch';
                swatch.style.backgroundColor = selected.getAttribute('data-base');
                button.appendChild(swatch);
            }
            var label = document.createElement('span');
            label.textContent = selected ? selected.textContent : '';
            button.appendChild(label);
            picker.querySelectorAll('.offer-color-hue-option').forEach(function (item) {
                item.classList.toggle('is-selected', item.getAttribute('data-value') === select.value);
            });
        }

        function placeHueMenu(button, menu) {
            var rect = button.getBoundingClientRect();
            menu.style.left = rect.left + 'px';
            menu.style.width = rect.width + 'px';
            var below = window.innerHeight - rect.bottom;
            if (below < 180 && rect.top > below) {
                menu.style.top = 'auto';
                menu.style.bottom = (window.innerHeight - rect.top + 2) + 'px';
            } else {
                menu.style.bottom = 'auto';
                menu.style.top = (rect.bottom + 2) + 'px';
            }
        }

        function enhanceHueSelect(select) {
            if (select.closest('.offer-color-hue-picker')) {
                return;
            }
            var picker = document.createElement('div');
            picker.className = 'offer-color-hue-picker';
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'form-select form-select-sm offer-color-hue-button';
            button.setAttribute('aria-haspopup', 'listbox');
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', select.getAttribute('aria-label') || 'Teinte de la famille');
            var menu = document.createElement('ul');
            menu.className = 'offer-color-hue-menu';
            menu.hidden = true;
            menu.setAttribute('role', 'listbox');
            Array.prototype.forEach.call(select.options, function (option) {
                var item = document.createElement('li');
                item.className = 'offer-color-hue-option';
                item.setAttribute('role', 'option');
                item.setAttribute('data-value', option.value);
                if (option.getAttribute('data-base')) {
                    var swatch = document.createElement('span');
                    swatch.className = 'offer-color-hue-swatch';
                    swatch.style.backgroundColor = option.getAttribute('data-base');
                    item.appendChild(swatch);
                }
                var label = document.createElement('span');
                label.textContent = option.textContent;
                item.appendChild(label);
                item.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var changed = select.value !== option.value;
                    select.value = option.value;
                    paintHueButton(select);
                    closeHueMenu();
                    if (changed) {
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                menu.appendChild(item);
            });
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                var willOpen = menu.hidden;
                closeHueMenu();
                if (!willOpen) {
                    return;
                }
                placeHueMenu(button, menu);
                menu.hidden = false;
                button.setAttribute('aria-expanded', 'true');
                openHueMenu = menu;
            });
            select.classList.add('offer-color-hue-native');
            select.setAttribute('aria-hidden', 'true');
            select.tabIndex = -1;
            select.parentNode.insertBefore(picker, select);
            picker.appendChild(button);
            picker.appendChild(menu);
            picker.appendChild(select);
            paintHueButton(select);
        }

        document.addEventListener('click', function (event) {
            if (!openHueMenu || openHueMenu.parentElement.contains(event.target)) {
                return;
            }
            closeHueMenu();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeHueMenu();
            }
        });

        function bindColumn(column) {
            var list = column.querySelector('.offer-color-family-list');
            if (list) {
                bindList(list);
            }
            column.querySelectorAll('.offer-color-family-offer').forEach(bindOffer);
            var nameInput = column.querySelector('[data-field="name"]');
            if (nameInput) {
                nameInput.addEventListener('input', function () {
                    markDirty();
                    rebuildSelects();
                });
            }
            var hueSelect = column.querySelector('[data-field="hue"]');
            if (hueSelect) {
                enhanceHueSelect(hueSelect);
                hueSelect.addEventListener('change', function () {
                    markDirty();
                    paintThemeColumns(column);
                });
            }
            var pastelInput = column.querySelector('[data-field="pastel"]');
            if (pastelInput) {
                pastelInput.addEventListener('change', function () {
                    markDirty();
                    paintColumn(column);
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
                    markDirty();
                });
            }
        }

        columns().forEach(bindColumn);
        rebuildSelects();
        refreshArrowStates();
        if (board.getAttribute('data-preserve-colors') !== '1') {
            familyColumns().forEach(function (column) {
                if (explicitHue(column) !== null) {
                    paintColumn(column);
                }
            });
        }

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
            markDirty();
        });

        var paletteName = document.getElementById('palette-name');
        var creatingFromBoard = false;
        var createName = '';

        var renameButton = document.querySelector('.js-rename-palette');
        var renameBox = document.querySelector('.js-palette-rename');
        var renameInput = document.getElementById('palette-name-edit');
        var renameCommit = document.querySelector('.js-rename-commit');
        var title = document.getElementById('offer-color-palette-title');

        function commitRename() {
            if (!renameInput || !paletteName || !title || !renameBox || !renameButton) {
                return;
            }
            var next = renameInput.value.trim();
            if (next === '') {
                renameInput.focus();
                return;
            }
            paletteName.value = next;
            title.textContent = next;
            renameBox.classList.add('d-none');
            renameBox.classList.remove('d-flex');
            renameButton.classList.remove('d-none');
            title.classList.remove('d-none');
            markDirty();
        }

        if (renameButton && renameBox && renameInput && renameCommit && title) {
            renameButton.addEventListener('click', function () {
                renameInput.value = title.textContent;
                title.classList.add('d-none');
                renameButton.classList.add('d-none');
                renameBox.classList.remove('d-none');
                renameBox.classList.add('d-flex');
                renameInput.focus();
                renameInput.select();
            });
            renameCommit.addEventListener('click', commitRename);
            renameInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    commitRename();
                }
                if (event.key === 'Escape') {
                    renameBox.classList.add('d-none');
                    renameBox.classList.remove('d-flex');
                    renameButton.classList.remove('d-none');
                    title.classList.remove('d-none');
                }
            });
        }

        var createToggle = document.getElementById('offer-color-create-toggle');
        var createPanel = document.getElementById('offer-color-create-panel');
        var createForm = document.getElementById('offer-color-create-form');
        if (createToggle && createPanel) {
            createToggle.addEventListener('click', function () {
                createPanel.classList.toggle('d-none');
                var nameField = document.getElementById('offer-color-create-name');
                if (!createPanel.classList.contains('d-none') && nameField) {
                    nameField.focus();
                }
            });
        }
        if (createForm) {
            createForm.addEventListener('submit', function (event) {
                var source = createForm.querySelector('[name="source"]');
                var nameField = document.getElementById('offer-color-create-name');
                var sourceValue = source ? source.value : 'current';
                if (sourceValue !== 'current') {
                    if (dirty && !window.confirm('Les changements non enregistrés du bandeau seront perdus.')) {
                        event.preventDefault();
                    }
                    return;
                }
                event.preventDefault();
                if (nameField && nameField.value.trim() === '') {
                    nameField.focus();
                    return;
                }
                if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                    return;
                }
                creatingFromBoard = true;
                createName = nameField ? nameField.value.trim() : '';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        }

        document.addEventListener('submit', function (event) {
            var submitted = event.target;
            if (!submitted || !submitted.classList || !submitted.classList.contains('js-confirm-if-dirty')) {
                return;
            }
            if (dirty && !window.confirm('Les changements non enregistrés du bandeau seront perdus.')) {
                event.preventDefault();
                return;
            }
            var message = submitted.getAttribute('data-confirm');
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });

        form.addEventListener('submit', function () {
            var createThis = creatingFromBoard;
            creatingFromBoard = false;
            if (createThis && paletteName) {
                paletteName.value = createName;
                var flag = document.getElementById('offer-color-save-as-new');
                if (!flag) {
                    flag = document.createElement('input');
                    flag.type = 'hidden';
                    flag.name = 'save_as_new';
                    flag.id = 'offer-color-save-as-new';
                    form.appendChild(flag);
                }
                flag.value = '1';
            } else {
                var existingFlag = document.getElementById('offer-color-save-as-new');
                if (existingFlag) {
                    existingFlag.remove();
                }
            }
            familyColumns().forEach(function (column, index) {
                var nameInput = column.querySelector('[data-field="name"]');
                var positionInput = column.querySelector('[data-field="position"]');
                var hueInput = column.querySelector('[data-field="hue"]');
                nameInput.name = 'families[' + index + '][name]';
                positionInput.name = 'families[' + index + '][position]';
                positionInput.value = String(index);
                if (hueInput) {
                    hueInput.name = 'families[' + index + '][hue]';
                }
                var pastelInput = column.querySelector('[data-field="pastel"]');
                if (pastelInput) {
                    pastelInput.name = 'families[' + index + '][pastel]';
                }
                column.querySelectorAll('.js-offer-id').forEach(function (input) {
                    input.name = 'families[' + index + '][offer_ids][]';
                });
                column.querySelectorAll('.js-offer-color').forEach(function (input) {
                    input.name = 'families[' + index + '][colors][]';
                });
            });
            var unassigned = board.querySelector('[data-family-column="unassigned"]');
            if (unassigned) {
                unassigned.querySelectorAll('.offer-color-family-offer').forEach(function (offer, index) {
                    var idInput = offer.querySelector('.js-unassigned-id');
                    if (!idInput) {
                        idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.className = 'js-unassigned-id';
                        offer.appendChild(idInput);
                    }
                    idInput.name = 'unassigned[' + index + '][offer_id]';
                    idInput.value = offer.getAttribute('data-offer-id');
                    var colorInput = offer.querySelector('.js-offer-color');
                    if (colorInput) {
                        colorInput.name = 'unassigned[' + index + '][color]';
                    }
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
