/**
 * Filtres CRUD — un moteur pour tous les form.filters-toolbar.
 * select : submit immédiat ; texte : debounce ; date : change.
 */
(function () {
    'use strict';

    var TEXT_DEBOUNCE_MS = 350;
    var DATE_DEBOUNCE_MS = 300;

    function focusKey() {
        return 'crud.filters.focus:' + window.location.pathname;
    }

    function isTextInput(el) {
        if (!el || el.tagName !== 'INPUT') {
            return false;
        }
        var type = (el.getAttribute('type') || 'text').toLowerCase();
        return type === 'text' || type === 'search';
    }

    function saveFocus(form) {
        var el = document.activeElement;
        if (!el || !form.contains(el) || !isTextInput(el) || !el.id) {
            return;
        }
        try {
            sessionStorage.setItem(focusKey(), JSON.stringify({
                id: el.id,
                start: el.selectionStart,
                end: el.selectionEnd
            }));
        } catch (e) {
            // sessionStorage peut être bloqué
        }
    }

    function restoreFocus() {
        var raw;
        try {
            raw = sessionStorage.getItem(focusKey());
            sessionStorage.removeItem(focusKey());
        } catch (e) {
            return;
        }
        if (!raw) {
            return;
        }
        try {
            var state = JSON.parse(raw);
            var el = document.getElementById(state.id);
            if (!el) {
                return;
            }
            el.focus();
            if (typeof state.start === 'number' && typeof el.setSelectionRange === 'function') {
                el.setSelectionRange(state.start, state.end);
            }
        } catch (e) {
            // ignore
        }
    }

    function submitFilters(form) {
        saveFocus(form);
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function bindForm(form) {
        var timer = null;
        var composing = false;

        function scheduleSubmit(delay) {
            if (composing) {
                return;
            }
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                submitFilters(form);
            }, delay);
        }

        form.querySelectorAll('input[type="text"], input[type="search"], input:not([type])').forEach(function (input) {
            if (input.type && input.type !== 'text' && input.type !== 'search') {
                return;
            }
            input.addEventListener('compositionstart', function () {
                composing = true;
            });
            input.addEventListener('compositionend', function () {
                composing = false;
                scheduleSubmit(TEXT_DEBOUNCE_MS);
            });
            input.addEventListener('input', function () {
                scheduleSubmit(TEXT_DEBOUNCE_MS);
            });
        });

        form.querySelectorAll('input[type="date"]').forEach(function (input) {
            input.addEventListener('change', function () {
                scheduleSubmit(DATE_DEBOUNCE_MS);
            });
        });

        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                window.clearTimeout(timer);
                submitFilters(form);
            });
        });

        form.addEventListener('submit', function () {
            window.clearTimeout(timer);
            saveFocus(form);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('form.filters-toolbar');
        if (!forms.length) {
            return;
        }
        restoreFocus();
        forms.forEach(bindForm);
    });
}());
