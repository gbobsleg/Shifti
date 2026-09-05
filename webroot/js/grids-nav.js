/**
 * Chevrons grille : glisser la période d’un jour ouvré, ou scroller jusqu’au jour chargé.
 */
(function () {
    'use strict';

    function root() {
        return document.querySelector('.grids-app');
    }

    function budget() {
        const el = root();
        if (!el || !el.dataset.budget) {
            return null;
        }
        try {
            return JSON.parse(el.dataset.budget);
        } catch (e) {
            return null;
        }
    }

    function parseFrDate(value) {
        const parts = String(value || '').split('/');
        if (parts.length !== 3) {
            return null;
        }
        const d = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
        return Number.isNaN(d.getTime()) ? null : d;
    }

    function parseIsoDate(value) {
        const parts = String(value || '').split('-');
        if (parts.length !== 3) {
            return null;
        }
        const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        return Number.isNaN(d.getTime()) ? null : d;
    }

    function formatFr(d) {
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        return dd + '/' + mm + '/' + d.getFullYear();
    }

    function formatIso(d) {
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function isWeekend(d) {
        const day = d.getDay();
        return day === 0 || day === 6;
    }

    function addWorkingDays(date, step) {
        const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const dir = step > 0 ? 1 : -1;
        let left = Math.abs(step);
        while (left > 0) {
            d.setDate(d.getDate() + dir);
            if (!isWeekend(d)) {
                left -= 1;
            }
        }
        return d;
    }

    function countWorkingDays(start, end) {
        let count = 0;
        const cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate());
        const last = new Date(end.getFullYear(), end.getMonth(), end.getDate());
        while (cursor <= last) {
            if (!isWeekend(cursor)) {
                count += 1;
            }
            cursor.setDate(cursor.getDate() + 1);
        }
        return count;
    }

    function hasDimension(form) {
        const site = form.querySelector('#site-filter');
        const user = form.querySelector('#user-filter');
        return !!(site && site.value) || !!(user && user.value);
    }

    function canSubmit(form, startStr, endStr) {
        const cfg = budget();
        if (!cfg) {
            return { ok: true, message: '' };
        }
        const start = parseFrDate(startStr);
        const end = parseFrDate(endStr || startStr);
        if (!start || !end) {
            return { ok: true, message: '' };
        }
        const months = cfg.max_calendar_months || 1;
        const maxEnd = new Date(start.getFullYear(), start.getMonth() + months, start.getDate());
        if (end > maxEnd) {
            return { ok: false, message: 'La période ne peut pas dépasser ' + months + ' mois.' };
        }
        const working = countWorkingDays(start, end);
        const needAfter = cfg.need_site_or_user_after || 5;
        if (working > needAfter && !hasDimension(form)) {
            return {
                ok: false,
                message: 'Plus de ' + needAfter + ' jours ouvrés : choisis un site ou un agent.',
            };
        }
        return { ok: true, message: '' };
    }

    function showFilterError(form, message) {
        let box = form.querySelector('.grids-filter-error');
        if (!box) {
            box = document.createElement('div');
            box.className = 'grids-filter-error text-danger small mt-1';
            form.appendChild(box);
        }
        box.textContent = message;
    }

    function filterForm() {
        return document.querySelector('.grids-app form.grids-filters-form');
    }

    function syncPicker(start, end) {
        if (!window.jQuery) {
            return;
        }
        const $start = window.jQuery('#date-start');
        const picker = $start.data('daterangepicker');
        if (!picker) {
            return;
        }
        picker.setStartDate(formatFr(start));
        picker.setEndDate(formatFr(end));
    }

    function navigateRange(start, end, hash) {
        const form = filterForm();
        if (!form) {
            return;
        }
        const startStr = formatFr(start);
        const endStr = formatFr(end);
        const check = canSubmit(form, startStr, endStr);
        if (!check.ok) {
            showFilterError(form, check.message);
            return;
        }
        const startInput = form.querySelector('#date-start');
        const endInput = form.querySelector('#date-end');
        if (startInput) {
            startInput.value = startStr;
        }
        if (endInput) {
            endInput.value = endStr;
        }
        syncPicker(start, end);

        const action = form.getAttribute('action') || window.location.pathname;
        const url = new URL(action, window.location.origin);
        const params = new URLSearchParams();
        new FormData(form).forEach((value, key) => {
            if (value !== '') {
                params.append(key, value);
            }
        });
        url.search = params.toString();
        if (hash) {
            url.hash = hash.replace(/^#/, '');
        }
        window.location.assign(url.toString());
    }

    function shiftLoadedRange(step, hash) {
        const form = filterForm();
        if (!form) {
            return;
        }
        const start = parseFrDate((form.querySelector('#date-start') || {}).value);
        const end = parseFrDate((form.querySelector('#date-end') || {}).value) || start;
        if (!start || !end) {
            return;
        }
        navigateRange(addWorkingDays(start, step), addWorkingDays(end, step), hash);
    }

    function scrollToDayEl(el) {
        if (!el) {
            return;
        }
        const app = root();
        const styles = app ? getComputedStyle(app) : null;
        const chromeH = styles ? (parseFloat(styles.getPropertyValue('--grids-chrome-h')) || 0) : 0;
        const navH = styles ? (parseFloat(styles.getPropertyValue('--grids-navbar')) || 56) : 56;
        const embed = app && app.classList.contains('grids-app--embed');
        const offset = embed ? 8 : (navH + chromeH + 8);
        const top = el.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: Math.max(0, top), behavior: 'auto' });
    }

    function dayAnchorId(iso) {
        return 'grid-day-' + iso;
    }

    function onDayStep(iso, step) {
        const from = parseIsoDate(iso);
        if (!from || !step) {
            return;
        }
        const target = addWorkingDays(from, step);
        const targetIso = formatIso(target);
        const el = document.getElementById(dayAnchorId(targetIso));
        if (el) {
            scrollToDayEl(el);
            return;
        }
        shiftLoadedRange(step, dayAnchorId(targetIso));
    }

    document.addEventListener('click', (event) => {
        const rangeBtn = event.target.closest('[data-grid-range-step]');
        if (rangeBtn) {
            event.preventDefault();
            const step = parseInt(rangeBtn.getAttribute('data-grid-range-step'), 10);
            if (step) {
                shiftLoadedRange(step);
            }
            return;
        }
        const dayBtn = event.target.closest('[data-grid-day-step]');
        if (!dayBtn) {
            return;
        }
        event.preventDefault();
        const step = parseInt(dayBtn.getAttribute('data-grid-day-step'), 10);
        const iso = dayBtn.getAttribute('data-grid-day');
        onDayStep(iso, step);
    });

    function scrollHashTarget() {
        const hash = window.location.hash.replace(/^#/, '');
        if (!/^grid-day-\d{4}-\d{2}-\d{2}$/.test(hash)) {
            return;
        }
        const el = document.getElementById(hash);
        if (el) {
            scrollToDayEl(el);
        }
    }

    window.addEventListener('load', () => {
        window.requestAnimationFrame(scrollHashTarget);
    });
}());
