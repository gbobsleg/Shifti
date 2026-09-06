/**
 * Script pour la visualisation des données historiques
 */

$(document).ready(function() {
    
    // Limiter la sélection à 3 offres maximum
    $('.offer-checkbox').on('change', function() {
        const checkedCount = $('.offer-checkbox:checked').length;
        if (checkedCount >= 3) {
            $('.offer-checkbox:not(:checked)').prop('disabled', true);
        } else {
            $('.offer-checkbox').prop('disabled', false);
        }
    });
    
    // Trigger initial au chargement
    $('.offer-checkbox:checked').trigger('change');
    
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

    function refreshPresetRows(activeUnit) {
        $('.hv-preset-row').each(function () {
            const $row = $(this);
            const unit = $row.data('unit');
            const offset = parseInt($row.attr('data-offset'), 10) || 0;
            const range = periodRange(unit, offset);
            $row.find('.preset-current').text(periodLabel(unit, offset, range));
            $row.find('.preset-step[data-step="1"]').prop('disabled', !canGoForward(unit, offset));
            $row.toggleClass('is-active', unit === activeUnit);
        });
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
        syncGranularity(true);
        refreshPresetRows(unit);
    }

    $('.preset-current').on('click', function () {
        applyPeriod($(this).closest('.hv-preset-row'), 0);
    });

    $('.preset-step').on('click', function () {
        const $row = $(this).closest('.hv-preset-row');
        const step = parseInt($(this).data('step'), 10);
        const offset = (parseInt($row.attr('data-offset'), 10) || 0) + step;
        if (step > 0 && !canGoForward($row.data('unit'), offset - step)) {
            return;
        }
        applyPeriod($row, offset);
    });

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
 * Formate une date pour input type="date"
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
 * Rend les graphiques ApexCharts
 */
function renderCharts() {
    const data = window.historicalChartData;
    
    if (!data || !data.categories || data.categories.length === 0) {
        const empty = '<p class="text-muted mb-0">Aucune donnée à afficher pour cette période.</p>';
        const volume = document.getElementById('volume-chart');
        const dmt = document.getElementById('dmt-chart');
        if (volume) {
            volume.innerHTML = empty;
        }
        if (dmt) {
            dmt.innerHTML = empty;
        }
        return;
    }
    
    // Graphique Volume
    if (data.volumeSeries && data.volumeSeries.length > 0) {
        window.renderApexLine('volume-chart', data.categories, data.volumeSeries, {
            colors: ['#007bff', '#28a745', '#dc3545'],
            yaxis: {
                title: {
                    text: 'Volume d\'appels'
                },
                labels: {
                    formatter: function(val) {
                        return Math.round(val);
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return Math.round(val) + ' appels';
                    }
                }
            }
        });
    }
    
    // Graphique DMT
    if (data.dmtSeries && data.dmtSeries.length > 0) {
        window.renderApexLine('dmt-chart', data.categories, data.dmtSeries, {
            colors: ['#17a2b8', '#ffc107', '#6f42c1'],
            yaxis: {
                title: {
                    text: 'DMT (secondes)'
                },
                labels: {
                    formatter: function(val) {
                        return Math.round(val) + 's';
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        const minutes = Math.floor(val / 60);
                        const seconds = Math.round(val % 60);
                        return minutes + 'min ' + seconds + 's';
                    }
                }
            }
        });
    }
}

/**
 * Exporte les données en CSV
 */
function exportToCSV() {
    const data = window.historicalChartData;
    
    if (!data || !data.categories) {
        return;
    }
    
    // Construction du CSV
    let csv = 'Date/Heure';
    
    // En-têtes colonnes (Volume pour chaque offre)
    if (data.volumeSeries) {
        data.volumeSeries.forEach(function(series) {
            csv += ';' + series.name;
        });
    }
    
    // En-têtes colonnes (DMT pour chaque offre)
    if (data.dmtSeries) {
        data.dmtSeries.forEach(function(series) {
            csv += ';' + series.name;
        });
    }
    
    csv += '\n';
    
    // Lignes de données
    for (let i = 0; i < data.categories.length; i++) {
        csv += data.categories[i];
        
        // Volumes
        if (data.volumeSeries) {
            data.volumeSeries.forEach(function(series) {
                csv += ';' + (series.data[i] !== null ? series.data[i] : '');
            });
        }
        
        // DMT
        if (data.dmtSeries) {
            data.dmtSeries.forEach(function(series) {
                csv += ';' + (series.data[i] !== null ? series.data[i] : '');
            });
        }
        
        csv += '\n';
    }
    
    // Téléchargement du fichier
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const now = new Date();
    const filename = 'donnees_historiques_' + 
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

