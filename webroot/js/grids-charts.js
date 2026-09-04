(function () {
    'use strict';

    function timeToMinutes(timeStr) {
        const parts = timeStr.substring(0, 5).split(':').map(Number);
        if (parts.length !== 2 || isNaN(parts[0]) || isNaN(parts[1])) {
            return 0;
        }
        return parts[0] * 60 + parts[1];
    }

    function compareTimes(time1, time2) {
        return timeToMinutes(time1) - timeToMinutes(time2);
    }

    function generateTimeSlots(start, end) {
        const slots = [];
        const startParts = start.substring(0, 5).split(':').map(Number);
        const endParts = end.substring(0, 5).split(':').map(Number);
        if (startParts.length !== 2 || endParts.length !== 2) {
            return [];
        }
        let h = startParts[0];
        let m = startParts[1];
        const endH = endParts[0];
        const endM = endParts[1];
        while (h < endH || (h === endH && m <= endM)) {
            slots.push(String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'));
            m += 15;
            if (m >= 60) {
                m = 0;
                h += 1;
            }
        }
        return slots;
    }

    function normalizeData(data, allSlots) {
        if (!data || typeof data !== 'object') {
            return {};
        }
        const normalized = {};
        allSlots.forEach((slot) => {
            let value = 0;
            Object.entries(data).forEach(([key, val]) => {
                if (key.substring(0, 5) === slot) {
                    if (typeof val === 'object' && val !== null && !Array.isArray(val)) {
                        value = val.volume || val.value || 0;
                    } else {
                        value = val;
                    }
                }
            });
            const numValue = Number(value);
            normalized[slot] = isNaN(numValue) ? 0 : numValue;
        });
        return normalized;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('grids-charts-root');
        if (!root) {
            return;
        }
        const plannedBase = root.getAttribute('data-planned-base') || '';
        const plannedExtra = root.getAttribute('data-planned-extra') || '';
        const needBase = root.getAttribute('data-need-base') || '';

        const loadingDays = new Set();

        function expandChart(dayKey) {
            const collapseEl = document.getElementById('collapseChart' + dayKey);
            if (!collapseEl || typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
                return;
            }
            bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
        }

        async function loadDayChart(dayKey) {
            if (!dayKey || loadingDays.has(dayKey)) {
                return;
            }
            const btn = document.getElementById('compareBtn' + dayKey);
            const offerSelect = document.getElementById('offerSelect' + dayKey);
            const parentCard = (btn || offerSelect) ? (btn || offerSelect).closest('[data-scenario-id]') : null;
            const scenarioId = parentCard ? parentCard.getAttribute('data-scenario-id') : '';
            const chartEl = document.getElementById('compareChart' + dayKey);
            if (!chartEl || !offerSelect) {
                return;
            }
            expandChart(dayKey);
            loadingDays.add(dayKey);
            if (btn) {
                btn.disabled = true;
            }
            if (!scenarioId) {
                chartEl.innerHTML = '<div class="alert alert-info">Aucun scénario publié pour ce jour.</div>';
                loadingDays.delete(dayKey);
                if (btn) {
                    btn.disabled = false;
                }
                return;
            }
            const offerId = offerSelect.value;
            const plannedUrl = plannedBase + '?offer_id=' + encodeURIComponent(offerId)
                + '&date=' + encodeURIComponent(dayKey) + plannedExtra;
            try {
                const plannedRes = await fetch(plannedUrl, { headers: { Accept: 'application/json' } });
                if (!plannedRes.ok) {
                    throw new Error('Planned data fetch failed: ' + plannedRes.status);
                }
                const planned = await plannedRes.json();
                let need = null;
                const needUrl = needBase + '/' + encodeURIComponent(scenarioId)
                    + '.json?offer_id=' + encodeURIComponent(offerId)
                    + '&date=' + encodeURIComponent(dayKey) + '&type=need';
                const needRes = await fetch(needUrl, { headers: { Accept: 'application/json' } });
                if (needRes.ok) {
                    need = await needRes.json();
                }
                const plannedSeries = planned?.series || {};
                const needSeries = need?.series || {};
                const plannedStart = plannedSeries.startTime || '09:00:00';
                const plannedEnd = plannedSeries.endTime || '17:00:00';
                const needStart = needSeries.startTime || plannedStart;
                const needEnd = needSeries.endTime || plannedEnd;
                const minStart = compareTimes(plannedStart, needStart) <= 0 ? plannedStart : needStart;
                const maxEnd = compareTimes(plannedEnd, needEnd) >= 0 ? plannedEnd : needEnd;
                const allSlots = generateTimeSlots(minStart, maxEnd);
                if (allSlots.length === 0) {
                    chartEl.innerHTML = '<div class="alert alert-info">Aucune donnée à afficher.</div>';
                    return;
                }
                const plannedMap = normalizeData(plannedSeries.data || {}, allSlots);
                const needMap = normalizeData(needSeries.data || {}, allSlots);
                const covered = [];
                const shortage = [];
                const surplus = [];
                allSlots.forEach((slot) => {
                    const needV = Number(needMap[slot]) || 0;
                    const planV = Number(plannedMap[slot]) || 0;
                    covered.push(Math.min(needV, planV));
                    shortage.push(Math.max(needV - planV, 0));
                    surplus.push(Math.max(planV - needV, 0));
                });
                if (typeof window.renderApexStacked === 'function') {
                    window.renderApexStacked('compareChart' + dayKey, allSlots, [
                        { name: 'Couvert', data: covered },
                        { name: 'Manque', data: shortage },
                        { name: 'Surplus', data: surplus },
                    ], {
                        chart: { type: 'bar', height: 250, stacked: true, toolbar: { show: false } },
                        tooltip: { enabled: false },
                    });
                }
            } catch (err) {
                chartEl.innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
            } finally {
                loadingDays.delete(dayKey);
                if (btn) {
                    btn.disabled = false;
                }
            }
        }

        document.querySelectorAll('[id^="compareBtn"]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                loadDayChart(btn.id.replace('compareBtn', ''));
            });
        });

        document.querySelectorAll('[id^="offerSelect"]').forEach((select) => {
            select.addEventListener('change', () => {
                loadDayChart(select.id.replace('offerSelect', ''));
            });
        });
    });
}());
