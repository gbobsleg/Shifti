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

    function seriesValueMap(data) {
        const map = {};
        if (!data || typeof data !== 'object') {
            return map;
        }
        Object.entries(data).forEach(([key, val]) => {
            const slot = key.substring(0, 5);
            let value = 0;
            if (typeof val === 'object' && val !== null && !Array.isArray(val)) {
                value = val.volume || val.value || 0;
            } else {
                value = val;
            }
            const numValue = Number(value);
            map[slot] = isNaN(numValue) ? 0 : numValue;
        });
        return map;
    }

    function normalizeData(data, allSlots) {
        const source = seriesValueMap(data);
        const normalized = {};
        allSlots.forEach((slot) => {
            normalized[slot] = Number(source[slot]) || 0;
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
        const seriesCache = new Map();
        const OFFER_KEY = 'gridsChartOfferId';
        let lastChartRender = null;

        function currentOfferSelect() {
            return document.querySelector('[id^="offerSelect"]');
        }

        function restoreOffer() {
            const select = currentOfferSelect();
            if (!select) {
                return false;
            }
            const saved = localStorage.getItem(OFFER_KEY);
            if (!saved) {
                return false;
            }
            const exists = Array.from(select.options).some((opt) => opt.value === saved);
            if (!exists) {
                return false;
            }
            select.value = saved;
            return true;
        }

        function persistOffer(offerId) {
            if (offerId) {
                localStorage.setItem(OFFER_KEY, offerId);
            }
        }

        function isChartOpen(dayKey) {
            const collapseEl = document.getElementById('collapseChart' + dayKey);
            return !!(collapseEl && collapseEl.classList.contains('show'));
        }

        function expandChart(dayKey) {
            const collapseEl = document.getElementById('collapseChart' + dayKey);
            if (!collapseEl || typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
                return;
            }
            bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
        }

        function paintChart(dayKey, allSlots, series) {
            lastChartRender = { dayKey: dayKey, allSlots: allSlots, series: series };
            if (!isChartOpen(dayKey) || typeof window.renderApexStacked !== 'function') {
                return;
            }
            window.renderApexStacked('compareChart' + dayKey, allSlots, series, {
                chart: { type: 'bar', height: 250, stacked: true, toolbar: { show: false } },
                tooltip: { enabled: false },
            });
        }

        function cacheKey(dayKey, offerId) {
            return dayKey + '|' + offerId;
        }

        async function fetchDaySeries(dayKey, offerId, scenarioId) {
            const plannedUrl = plannedBase + '?offer_id=' + encodeURIComponent(offerId)
                + '&date=' + encodeURIComponent(dayKey) + plannedExtra;
            const plannedRes = await fetch(plannedUrl, { headers: { Accept: 'application/json' } });
            if (!plannedRes.ok) {
                throw new Error('Planned data fetch failed: ' + plannedRes.status);
            }
            const planned = await plannedRes.json();
            let need = null;
            if (scenarioId) {
                const needUrl = needBase + '/' + encodeURIComponent(scenarioId)
                    + '.json?offer_id=' + encodeURIComponent(offerId)
                    + '&date=' + encodeURIComponent(dayKey) + '&type=need';
                const needRes = await fetch(needUrl, { headers: { Accept: 'application/json' } });
                if (needRes.ok) {
                    need = await needRes.json();
                }
            }
            return { planned, need };
        }

        async function getDaySeries(dayKey, offerId, scenarioId) {
            const key = cacheKey(dayKey, offerId);
            if (seriesCache.has(key)) {
                return seriesCache.get(key);
            }
            const pending = fetchDaySeries(dayKey, offerId, scenarioId);
            seriesCache.set(key, pending);
            try {
                return await pending;
            } catch (err) {
                seriesCache.delete(key);
                throw err;
            }
        }

        function gapKind(needV, planV) {
            if (needV > planV) {
                return 'short';
            }
            if (planV > needV) {
                return 'surplus';
            }
            if (needV > 0) {
                return 'ok';
            }
            return '';
        }

        function gapTitle(slot, needV, planV, kind) {
            const label = kind === 'short'
                ? 'manque ' + (needV - planV)
                : (kind === 'surplus' ? 'surplus ' + (planV - needV) : 'couvert');
            return slot + ' — besoin ' + needV + ' / réel ' + planV + (kind ? ' (' + label + ')' : '');
        }

        function paintLoadCell(cell, n, kind, title) {
            cell.textContent = String(n);
            cell.setAttribute('title', title);
            cell.setAttribute('data-value', String(n));
            cell.classList.remove('is-short', 'is-surplus', 'is-ok');
            if (kind) {
                cell.classList.add('is-' + kind);
            }
        }

        function fillDayLoadRows(table, plannedMap, needMap) {
            const needRow = table.querySelector('.grids-load-row--need');
            const plannedRow = table.querySelector('.grids-load-row--planned');
            if (!needRow || !plannedRow) {
                return;
            }
            needRow.querySelectorAll('.grids-load-cell[data-slot]').forEach((needCell) => {
                const slot = needCell.getAttribute('data-slot');
                const plannedCell = plannedRow.querySelector('.grids-load-cell[data-slot="' + slot + '"]');
                const needV = Number(needMap[slot]) || 0;
                const planV = Number(plannedMap[slot]) || 0;
                const kind = gapKind(needV, planV);
                const title = gapTitle(slot, needV, planV, kind);
                paintLoadCell(needCell, needV, kind, title);
                if (plannedCell) {
                    paintLoadCell(plannedCell, planV, kind, title);
                }
            });
        }

        function isLoadRowsOn() {
            const toggle = document.getElementById('gridsLoadRowsToggle');
            return !!(toggle && toggle.checked);
        }

        function setLoadRowsVisible(visible) {
            document.querySelectorAll('.grids-load-row').forEach((row) => {
                row.hidden = !visible;
            });
        }

        async function loadAllLoadRows() {
            if (!isLoadRowsOn()) {
                return;
            }
            const offerSelect = document.querySelector('[id^="offerSelect"]');
            if (!offerSelect || !offerSelect.value) {
                return;
            }
            const offerId = offerSelect.value;
            const tables = document.querySelectorAll('table.quarter[id^="grid-day-"]');
            await Promise.all(Array.from(tables).map(async (table) => {
                const dayKey = table.id.replace('grid-day-', '');
                const scenarioId = table.getAttribute('data-scenario-id') || '';
                try {
                    const { planned, need } = await getDaySeries(dayKey, offerId, scenarioId);
                    fillDayLoadRows(
                        table,
                        seriesValueMap(planned && planned.series ? planned.series.data : null),
                        seriesValueMap(need && need.series ? need.series.data : null)
                    );
                } catch (err) {
                    fillDayLoadRows(table, {}, {});
                }
            }));
        }

        async function loadDayChart(dayKey, options) {
            const expand = !!(options && options.expand);
            if (!dayKey || loadingDays.has(dayKey)) {
                return;
            }
            if (expand) {
                expandChart(dayKey);
            }
            if (!expand && !isChartOpen(dayKey)) {
                if (isLoadRowsOn()) {
                    loadAllLoadRows();
                }
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
            persistOffer(offerSelect.value);
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
                if (isLoadRowsOn()) {
                    loadAllLoadRows();
                }
                return;
            }
            const offerId = offerSelect.value;
            try {
                const { planned, need } = await getDaySeries(dayKey, offerId, scenarioId);
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
                paintChart(dayKey, allSlots, [
                    { name: 'Couvert', data: covered },
                    { name: 'Manque', data: shortage },
                    { name: 'Surplus', data: surplus },
                ]);
            } catch (err) {
                chartEl.innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
            } finally {
                loadingDays.delete(dayKey);
                if (btn) {
                    btn.disabled = false;
                }
                if (isLoadRowsOn()) {
                    loadAllLoadRows();
                }
            }
        }

        const loadToggle = document.getElementById('gridsLoadRowsToggle');
        restoreOffer();
        if (loadToggle) {
            const savedOn = localStorage.getItem('gridsLoadRowsOn') === 'true';
            if (savedOn) {
                loadToggle.checked = true;
                setLoadRowsVisible(true);
            }
            loadToggle.addEventListener('change', () => {
                localStorage.setItem('gridsLoadRowsOn', loadToggle.checked ? 'true' : 'false');
                setLoadRowsVisible(loadToggle.checked);
                if (loadToggle.checked) {
                    loadAllLoadRows();
                }
            });
        }

        document.querySelectorAll('[id^="collapseChart"]').forEach((el) => {
            el.addEventListener('shown.bs.collapse', () => {
                const dayKey = el.id.replace('collapseChart', '');
                if (
                    lastChartRender
                    && lastChartRender.dayKey === dayKey
                    && typeof window.renderApexStacked === 'function'
                ) {
                    window.renderApexStacked(
                        'compareChart' + dayKey,
                        lastChartRender.allSlots,
                        lastChartRender.series,
                        {
                            chart: { type: 'bar', height: 250, stacked: true, toolbar: { show: false } },
                            tooltip: { enabled: false },
                        }
                    );
                    return;
                }
                loadDayChart(dayKey, { expand: false });
            });
        });

        document.querySelectorAll('[id^="compareBtn"]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                loadDayChart(btn.id.replace('compareBtn', ''), { expand: true });
            });
        });

        document.querySelectorAll('[id^="offerSelect"]').forEach((select) => {
            select.addEventListener('change', () => {
                persistOffer(select.value);
                if (isChartOpen(select.id.replace('offerSelect', ''))) {
                    loadDayChart(select.id.replace('offerSelect', ''), { expand: false });
                } else if (isLoadRowsOn()) {
                    loadAllLoadRows();
                }
            });
        });

        if (loadToggle && loadToggle.checked) {
            loadAllLoadRows();
        }
    });
}());
