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

    function countWorkingDays(start, end) {
        let count = 0;
        const cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate());
        const last = new Date(end.getFullYear(), end.getMonth(), end.getDate());
        while (cursor <= last) {
            const day = cursor.getDay();
            if (day !== 0 && day !== 6) {
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

    function clearFilterError(form) {
        const box = form.querySelector('.grids-filter-error');
        if (box) {
            box.textContent = '';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('.grids-app form.grids-filters-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', (e) => {
            const start = form.querySelector('#date-start');
            const end = form.querySelector('#date-end');
            const check = canSubmit(form, start ? start.value : '', end ? end.value : '');
            if (!check.ok) {
                e.preventDefault();
                showFilterError(form, check.message);
            } else {
                clearFilterError(form);
            }
        });

        const dateStart = form.querySelector('#date-start');
        if (dateStart && window.jQuery) {
            window.jQuery(dateStart).on('apply.daterangepicker', function (ev, picker) {
                const startStr = picker.startDate.format('DD/MM/YYYY');
                const endStr = picker.endDate.format('DD/MM/YYYY');
                const startInput = form.querySelector('#date-start');
                const endInput = form.querySelector('#date-end');
                if (startInput) {
                    startInput.value = startStr;
                }
                if (endInput) {
                    endInput.value = endStr;
                }
                const check = canSubmit(form, startStr, endStr);
                if (!check.ok) {
                    ev.preventDefault();
                    showFilterError(form, check.message);
                    return false;
                }
                clearFilterError(form);
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        }
    });
}());
