/**
 * Script pour la visualisation des données historiques
 */

/**
 * « 01/09/2026 » ou « 01/09/2026 10:00 » → « Lundi 01/09/2026 … ».
 * L'axe garde la date seule ; seul le titre de l'info-bulle change.
 */
function categoryLabelWithWeekday(value, opts) {
    let label = value;
    const index = opts && typeof opts.dataPointIndex === 'number' ? opts.dataPointIndex : -1;
    const globals = opts && opts.w && opts.w.globals;
    const categories = (globals && (globals.categoryLabels || globals.labels)) || [];
    if (index >= 0 && categories[index]) {
        label = categories[index];
    }

    const text = String(label == null ? '' : label);
    const match = text.match(/^(\d{2})\/(\d{2})\/(\d{4})([\s\S]*)$/);
    if (!match) {
        return text;
    }

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return text;
    }

    const weekday = date.toLocaleDateString('fr-FR', { weekday: 'long' });
    const titled = weekday.charAt(0).toLocaleUpperCase('fr-FR') + weekday.slice(1);

    return titled + ' ' + match[1] + '/' + match[2] + '/' + match[3] + match[4];
}

/**
 * Décale un libellé « jj/mm/aaaa HH:MM » de deltaMinutes.
 * Sert aux créneaux virtuels entre deux jours.
 */
function shiftCategoryLabel(label, deltaMinutes) {
    const match = String(label).match(/^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})$/);
    if (!match) {
        return null;
    }

    const date = new Date(
        Number(match[3]),
        Number(match[2]) - 1,
        Number(match[1]),
        Number(match[4]),
        Number(match[5])
    );
    date.setMinutes(date.getMinutes() + deltaMinutes);

    const pad = function (value) {
        return String(value).padStart(2, '0');
    };

    return pad(date.getDate()) + '/' + pad(date.getMonth() + 1) + '/' + date.getFullYear()
        + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
}

/**
 * Entre deux jours, ajoute le créneau juste après le dernier point
 * et celui juste avant le premier, tous deux à 0.
 * La courbe retombe au lieu de relier 17 h à 9 h.
 * Affichage seulement : l'export garde les créneaux réels.
 * La DMT reste vide sur ces points.
 */
function withVirtualDayEdges(categories, series) {
    const granularity = $('#granularity-select').val();
    const step = granularity === 'hour' ? 60 : (granularity === '15min' ? 15 : 0);
    if (!step || categories.length < 2) {
        return { categories: categories, series: series };
    }

    const known = {};
    categories.forEach(function (label) {
        known[label] = true;
    });

    const dateOf = function (label) {
        const cut = String(label).indexOf(' ');
        return cut > 0 ? String(label).slice(0, cut) : null;
    };

    const nextCategories = [];
    const nextData = series.map(function () {
        return [];
    });

    const pushVirtual = function (label) {
        if (!label || known[label]) {
            return;
        }
        known[label] = true;
        nextCategories.push(label);
        series.forEach(function (serie, index) {
            const isDmt = String(serie.name).indexOf('DMT') !== -1;
            nextData[index].push(isDmt ? null : 0);
        });
    };

    for (let i = 0; i < categories.length; i++) {
        if (i > 0) {
            const prevDate = dateOf(categories[i - 1]);
            const currDate = dateOf(categories[i]);
            if (prevDate && currDate && prevDate !== currDate) {
                pushVirtual(shiftCategoryLabel(categories[i - 1], step));
                pushVirtual(shiftCategoryLabel(categories[i], -step));
            }
        }
        nextCategories.push(categories[i]);
        series.forEach(function (serie, serieIndex) {
            nextData[serieIndex].push(serie.data[i]);
        });
    }

    return {
        categories: nextCategories,
        series: series.map(function (serie, index) {
            return Object.assign({}, serie, { data: nextData[index] });
        })
    };
}

$(document).ready(function() {
    function enforceOfferLimit() {
        const checkedCount = $('.offer-checkbox:checked').length;
        $('.offer-checkbox:not(:checked)').prop('disabled', checkedCount >= 3);
    }

    $('.offer-checkbox').on('change', function() {
        enforceOfferLimit();
        refreshOfferSummary();
    });

    function refreshOfferSummary() {
        const $checked = $('.offer-checkbox:checked');
        const count = $checked.length;
        let label = 'Aucune offre';
        if (count === 1) {
            label = $('label[for="' + $checked.attr('id') + '"]').text().trim() || '1 offre';
        } else if (count > 1) {
            label = count + ' offres';
        }
        $('#offers-toggle').text(label);
    }

    enforceOfferLimit();
    
    function parseIsoDateLocal(value) {
        if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return null;
        }
        const parts = value.split('-').map(Number);
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function dateRangeDays(startValue, endValue) {
        const start = parseIsoDateLocal(startValue);
        const end = parseIsoDateLocal(endValue);
        if (!start || !end) {
            return null;
        }
        return Math.round((end.getTime() - start.getTime()) / 86400000);
    }

    function recommendedGranularity(days) {
        if (days === null || days < 0) {
            return '15min';
        }
        if (days <= 7) {
            return '15min';
        }
        if (days <= 30) {
            return 'hour';
        }
        return 'day';
    }

    function rangeLabel(days) {
        if (days === null || days < 0) {
            return '';
        }
        if (days <= 7) {
            return '7 jours ou moins';
        }
        if (days <= 30) {
            return '8 à 30 jours';
        }
        return 'plus de 30 jours';
    }

    function syncGranularity(applyRecommended) {
        const days = dateRangeDays($('#start-date').val(), $('#end-date').val());
        const recommended = recommendedGranularity(days);
        const $select = $('#granularity-select');
        const $hint = $('#granularity-hint');
        const labels = {
            '15min': '15 minutes',
            'hour': 'Heure',
            'day': 'Jour',
        };

        if (applyRecommended) {
            $select.val(recommended);
        }

        const current = $select.val();
        const plage = rangeLabel(days);
        $hint.removeClass('text-warning text-success font-weight-bold');

        if (current === recommended) {
            $hint.text(plage ? 'Adaptée à cette plage (' + plage + ')' : '');
        } else {
            $hint.text((labels[recommended] || recommended) + ' plus adaptée à cette plage' + (plage ? ' (' + plage + ')' : ''));
            $hint.addClass('text-warning font-weight-bold');
        }
    }

    $('#start-date, #end-date').on('change', function () {
        syncGranularity(true);
        restorePresets();
    });
    $('#granularity-select').on('change', function () {
        syncGranularity(false);
    });
    syncGranularity(false);

    const MONTHS_FR = [
        'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    function startOfDay(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    function mondayOf(date) {
        const day = startOfDay(date);
        const mondayOffset = (day.getDay() + 6) % 7;
        day.setDate(day.getDate() - mondayOffset);
        return day;
    }

    function formatDayMonth(date) {
        return date.getDate() + ' ' + MONTHS_FR[date.getMonth()];
    }

    function periodRange(unit, offset) {
        const today = startOfDay(new Date());
        let start = today;
        let end = today;

        if (unit === 'week') {
            start = mondayOf(today);
            start.setDate(start.getDate() + (offset * 7));
            end = new Date(start);
            end.setDate(start.getDate() + 6);
        } else if (unit === 'month') {
            start = new Date(today.getFullYear(), today.getMonth() + offset, 1);
            end = new Date(today.getFullYear(), today.getMonth() + offset + 1, 0);
        } else if (unit === 'quarter') {
            const quarterIndex = Math.floor(today.getMonth() / 3) + offset;
            start = new Date(today.getFullYear(), quarterIndex * 3, 1);
            end = new Date(today.getFullYear(), quarterIndex * 3 + 3, 0);
        } else if (unit === 'year') {
            start = new Date(today.getFullYear() + offset, 0, 1);
            end = new Date(today.getFullYear() + offset, 11, 31);
        }

        if (end.getTime() > today.getTime()) {
            end = today;
        }

        return { start: start, end: end };
    }

    function periodLabel(unit, offset, range) {
        if (offset === 0) {
            return {
                week: 'Cette semaine',
                month: 'Ce mois',
                quarter: 'Ce trimestre',
                year: 'Cette année',
            }[unit];
        }
        if (offset === -1) {
            return {
                week: 'Semaine dernière',
                month: 'Mois dernier',
                quarter: 'Trimestre dernier',
                year: 'Année dernière',
            }[unit];
        }
        if (unit === 'week') {
            if (range.start.getMonth() === range.end.getMonth()) {
                return 'Semaine du ' + range.start.getDate() + ' au ' + formatDayMonth(range.end);
            }
            return 'Semaine du ' + formatDayMonth(range.start) + ' au ' + formatDayMonth(range.end);
        }
        if (unit === 'month') {
            return MONTHS_FR[range.start.getMonth()] + ' ' + range.start.getFullYear();
        }
        if (unit === 'quarter') {
            const quarter = Math.floor(range.start.getMonth() / 3) + 1;
            return (quarter === 1 ? '1er' : quarter + 'e') + ' trimestre ' + range.start.getFullYear();
        }
        return String(range.start.getFullYear());
    }

    function canGoForward(unit, offset) {
        const next = periodRange(unit, offset + 1);
        return next.start.getTime() <= startOfDay(new Date()).getTime();
    }

    function formatFrenchDate(date) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        return day + '/' + month + '/' + date.getFullYear();
    }

    function syncPeriodPicker() {
        const start = parseIsoDateLocal($('#start-date').val());
        const end = parseIsoDateLocal($('#end-date').val());
        if (!start || !end) {
            return;
        }
        $('#period-display').val(formatFrenchDate(start) + ' – ' + formatFrenchDate(end));
        const picker = $('#period-display').data('daterangepicker');
        if (picker && typeof moment !== 'undefined') {
            picker.setStartDate(moment($('#start-date').val(), 'YYYY-MM-DD'));
            picker.setEndDate(moment($('#end-date').val(), 'YYYY-MM-DD'));
        }
    }

    function initPeriodPicker() {
        const $display = $('#period-display');
        if (!$display.length || typeof moment === 'undefined' || typeof $.fn.daterangepicker !== 'function') {
            return;
        }
        const start = moment($('#start-date').val(), 'YYYY-MM-DD');
        const end = moment($('#end-date').val(), 'YYYY-MM-DD');
        $display.daterangepicker({
            startDate: start.isValid() ? start : moment(),
            endDate: end.isValid() ? end : moment(),
            autoApply: true,
            autoUpdateInput: false,
            showDropdowns: true,
            minYear: 2020,
            maxYear: 2035,
            maxSpan: { days: 366 },
            opens: 'center',
            locale: {
                format: 'DD/MM/YYYY',
                separator: ' – ',
                applyLabel: 'Valider',
                cancelLabel: 'Annuler',
                fromLabel: 'Du',
                toLabel: 'Au',
                customRangeLabel: 'Personnaliser',
                weekLabel: 'S',
                daysOfWeek: ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'],
                monthNames: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
                firstDay: 1
            }
        }, function (startDate, endDate) {
            $('#start-date').val(startDate.format('YYYY-MM-DD'));
            $('#end-date').val(endDate.format('YYYY-MM-DD'));
            $display.val(startDate.format('DD/MM/YYYY') + ' – ' + endDate.format('DD/MM/YYYY'));
            syncGranularity(true);
            restorePresets();
        });
    }

    function refreshPresetRows(activeUnit) {
        $('.hv-preset-row').each(function () {
            const $row = $(this);
            const unit = $row.data('unit');
            const offset = parseInt($row.attr('data-offset'), 10) || 0;
            const range = periodRange(unit, offset);
            $row.find('.preset-current').text(periodLabel(unit, offset, range));
            $row.toggleClass('is-active', unit === activeUnit);
        });
        const $active = activeUnit ? $('.hv-preset-row[data-unit="' + activeUnit + '"]') : $();
        $('#preset-prev').prop('disabled', $active.length === 0);
        $('#preset-next').prop('disabled', $active.length === 0 || !canGoForward(activeUnit, parseInt($active.attr('data-offset'), 10) || 0));
    }

    function applyPeriod($row, offset) {
        const unit = $row.data('unit');
        $('.hv-preset-row').each(function () {
            const $other = $(this);
            if (!$other.is($row)) {
                $other.attr('data-offset', 0);
            }
        });
        $row.attr('data-offset', offset);
        $('#period-unit').val(unit);
        $('#period-offset').val(String(offset));
        const range = periodRange(unit, offset);
        $('#start-date').val(formatDateForInput(range.start));
        $('#end-date').val(formatDateForInput(range.end));
        syncPeriodPicker();
        syncGranularity(true);
        refreshPresetRows(unit);
    }

    $('.preset-current').on('click', function () {
        applyPeriod($(this).closest('.hv-preset-row'), 0);
    });

    $('#preset-prev').on('click', function () {
        const $row = $('.hv-preset-row.is-active');
        if (!$row.length) {
            return;
        }
        applyPeriod($row, (parseInt($row.attr('data-offset'), 10) || 0) - 1);
    });

    $('#preset-next').on('click', function () {
        const $row = $('.hv-preset-row.is-active');
        if (!$row.length) {
            return;
        }
        const offset = parseInt($row.attr('data-offset'), 10) || 0;
        if (!canGoForward($row.data('unit'), offset)) {
            return;
        }
        applyPeriod($row, offset + 1);
    });

    initPeriodPicker();

    function findPeriodOffset(unit, startValue, endValue) {
        for (let offset = 0; offset >= -80; offset--) {
            const range = periodRange(unit, offset);
            if (formatDateForInput(range.start) === startValue && formatDateForInput(range.end) === endValue) {
                return offset;
            }
        }
        return null;
    }

    function restorePresets() {
        const startValue = $('#start-date').val();
        const endValue = $('#end-date').val();
        const savedUnit = $('#period-unit').val();
        const units = ['week', 'month', 'quarter', 'year'];
        const ordered = savedUnit && units.indexOf(savedUnit) !== -1
            ? [savedUnit].concat(units.filter(function (unit) { return unit !== savedUnit; }))
            : units;

        for (let i = 0; i < ordered.length; i++) {
            const unit = ordered[i];
            const offset = findPeriodOffset(unit, startValue, endValue);
            if (offset === null) {
                continue;
            }
            $('.hv-preset-row').attr('data-offset', 0);
            $('.hv-preset-row[data-unit="' + unit + '"]').attr('data-offset', offset);
            $('#period-unit').val(unit);
            $('#period-offset').val(String(offset));
            refreshPresetRows(unit);
            return;
        }

        $('#period-unit').val('');
        $('#period-offset').val('');
        $('.hv-preset-row').attr('data-offset', 0);
        refreshPresetRows(null);
    }

    restorePresets();
    
    // Export CSV
    $('#export-csv-btn').on('click', function() {
        if (!window.historicalChartData) {
            alert('Aucune donnée à exporter');
            return;
        }
        
        exportToCSV();
    });
    
    // Rendu des graphiques si données disponibles
    if (typeof window.historicalChartData !== 'undefined' && window.historicalChartData) {
        renderCharts();
        renderVolumeShare(window.historicalStatistics || {});
    }
});

/**
 * Formate une date en AAAA-MM-JJ pour les champs cachés du formulaire.
 */
function formatDateForInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function renderVolumeShare(statistics) {
    const el = document.getElementById('volume-share-chart');
    if (!el || typeof ApexCharts === 'undefined' || !statistics) {
        return;
    }

    const slices = Object.keys(statistics).map(function (name) {
        return {
            name: name,
            total: Number(statistics[name].volume_total) || 0,
        };
    }).filter(function (item) {
        return item.total > 0;
    });

    if (slices.length < 2) {
        const share = el.closest('.hv-stats-share');
        const layout = el.closest('.hv-stats-layout');
        if (share) {
            share.remove();
        }
        if (layout) {
            layout.classList.remove('has-share');
        }
        return;
    }

    const total = slices.reduce(function (sum, item) {
        return sum + item.total;
    }, 0);

    el.innerHTML = '';
    const chart = new ApexCharts(el, {
        chart: {
            type: 'donut',
            height: 280,
            toolbar: { show: false },
        },
        series: slices.map(function (item) { return item.total; }),
        labels: slices.map(function (item) { return item.name; }),
        colors: ['#007bff', '#28a745', '#dc3545'],
        legend: {
            position: 'bottom',
        },
        dataLabels: {
            formatter: function (value) {
                return Math.round(value) + ' %';
            },
        },
        tooltip: {
            y: {
                formatter: function (value) {
                    const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                    return Math.round(value).toLocaleString('fr-FR') + ' appels (' + pct + ' %)';
                },
            },
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '62%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                        },
                        value: {
                            formatter: function (value) {
                                return Math.round(Number(value)).toLocaleString('fr-FR');
                            },
                        },
                        total: {
                            show: true,
                            label: 'Volume total',
                            formatter: function () {
                                return Math.round(total).toLocaleString('fr-FR');
                            },
                        },
                    },
                },
            },
        },
    });
    chart.render();
}

/**
 * Rend le graphe unique : volume, prévision éventuelle, DMT en minutes.
 */
function renderCharts() {
    const data = window.historicalChartData;
    const chart = document.getElementById('volume-chart');

    if (!chart) {
        return;
    }

    if (!data || !data.categories || data.categories.length === 0) {
        chart.innerHTML = '<p class="text-muted mb-0">Aucune donnée à afficher pour cette période.</p>';
        return;
    }

    const series = [];
    const colors = [];
    const strokeWidth = [];
    const dashArray = [];
    const fillTypes = [];
    const fillOpacity = [];
    const volumeColors = ['#007bff', '#28a745', '#dc3545'];
    const volumeSeries = data.volumeSeries || [];
    const singleVolume = volumeSeries.length === 1;
    const firstVolumeName = volumeSeries.length ? volumeSeries[0].name : 'Volume réel';

    volumeSeries.forEach(function(item, index) {
        series.push({
            name: item.name,
            type: singleVolume ? 'area' : 'line',
            data: item.data
        });
        colors.push(volumeColors[index % volumeColors.length]);
        strokeWidth.push(2);
        dashArray.push(0);
        fillTypes.push(singleVolume ? 'gradient' : 'solid');
        fillOpacity.push(singleVolume ? 0.3 : 1);
    });

    const forecastSeries = data.forecastSeries || [];
    forecastSeries.forEach(function(item) {
        series.push({
            name: item.name,
            type: 'line',
            data: item.data
        });
        colors.push('#0056b3');
        strokeWidth.push(2);
        dashArray.push(6);
        fillTypes.push('solid');
        fillOpacity.push(1);
    });

    const dmtSeries = data.dmtSeries || [];
    dmtSeries.forEach(function(item) {
        series.push({
            name: item.name,
            type: 'line',
            data: (item.data || []).map(function(value) {
                return value === null || typeof value === 'undefined' ? null : value / 60;
            })
        });
        colors.push('#fd7e14');
        strokeWidth.push(2);
        dashArray.push(0);
        fillTypes.push('solid');
        fillOpacity.push(1);
    });

    if (series.length === 0 || typeof window.renderApexArea !== 'function') {
        chart.innerHTML = '<p class="text-muted mb-0">Aucune donnée à afficher pour cette période.</p>';
        return;
    }

    const yaxis = [];
    volumeSeries.forEach(function(item, index) {
        yaxis.push({
            seriesName: firstVolumeName,
            min: 0,
            tickAmount: 5,
            forceNiceScale: true,
            show: index === 0,
            title: { text: index === 0 ? 'Appels' : '' }
        });
    });
    forecastSeries.forEach(function() {
        yaxis.push({
            seriesName: firstVolumeName,
            min: 0,
            show: false
        });
    });
    dmtSeries.forEach(function(item) {
        yaxis.push({
            seriesName: item.name,
            opposite: true,
            min: 0,
            tickAmount: 5,
            forceNiceScale: true,
            title: { text: 'DMT (min)' }
        });
    });

    const edged = withVirtualDayEdges(data.categories, series);

    window.renderApexArea('volume-chart', edged.categories, edged.series, {
        chart: { type: 'line' },
        colors: colors,
        stroke: {
            width: strokeWidth,
            curve: 'smooth',
            dashArray: dashArray
        },
        fill: {
            type: fillTypes,
            opacity: fillOpacity,
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.35,
                opacityTo: 0.05,
                stops: [0, 90, 100]
            }
        },
        yaxis: yaxis,
        tooltip: {
            x: {
                formatter: categoryLabelWithWeekday
            }
        },
        customFormats: {
            'DMT réelle': 'time',
            'default': 'int'
        }
    });
}

/**
 * Exporte les séries déjà agrégées. La colonne prévu n'existe que si elle est dans le payload.
 * La DMT reste en secondes.
 */
function exportToCSV() {
    const data = window.historicalChartData;

    if (!data || !data.categories) {
        return;
    }

    const columns = [];
    (data.volumeSeries || []).forEach(function(series) {
        columns.push({ header: series.name, data: series.data });
    });
    (data.forecastSeries || []).forEach(function(series) {
        columns.push({ header: series.name, data: series.data });
    });
    (data.dmtSeries || []).forEach(function(series) {
        columns.push({ header: series.name + ' (s)', data: series.data });
    });

    let csv = 'Date/Heure';
    columns.forEach(function(column) {
        csv += ';' + column.header;
    });
    csv += '\n';

    for (let i = 0; i < data.categories.length; i++) {
        csv += data.categories[i];
        columns.forEach(function(column) {
            const value = column.data ? column.data[i] : null;
            csv += ';' + (value !== null && typeof value !== 'undefined' ? value : '');
        });
        csv += '\n';
    }

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    const now = new Date();
    const filename = 'reel_et_prevision_' +
        now.getFullYear() +
        String(now.getMonth() + 1).padStart(2, '0') +
        String(now.getDate()).padStart(2, '0') +
        '_' +
        String(now.getHours()).padStart(2, '0') +
        String(now.getMinutes()).padStart(2, '0') +
        '.csv';

    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

