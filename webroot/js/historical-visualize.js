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
    
    // Suggestion automatique de granularité selon la plage
    function updateGranularitySuggestion() {
        const startDate = $('#start-date').val();
        const endDate = $('#end-date').val();
        
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            
            let recommended = '15min';
            let hint = '';
            
            if (daysDiff <= 7) {
                recommended = '15min';
                hint = '✓ 15 minutes recommandé (≤ 7 jours)';
            } else if (daysDiff <= 30) {
                recommended = 'hour';
                hint = '⚠ Heure recommandée (8-30 jours)';
            } else {
                recommended = 'day';
                hint = '⚠ Jour recommandé (> 30 jours)';
            }
            
            // Mettre à jour le hint
            $('#granularity-hint').html(hint);
            
            // Suggérer si différent de la sélection actuelle
            const current = $('#granularity-select').val();
            if (current !== recommended) {
                $('#granularity-hint').addClass('text-warning font-weight-bold');
            } else {
                $('#granularity-hint').removeClass('text-warning font-weight-bold');
                $('#granularity-hint').addClass('text-success');
            }
        }
    }
    
    // Écouter les changements de dates
    $('#start-date, #end-date').on('change', updateGranularitySuggestion);
    $('#granularity-select').on('change', updateGranularitySuggestion);
    
    // Appel initial
    updateGranularitySuggestion();
    
    // Boutons présets de dates
    $('.preset-btn').on('click', function() {
        const days = parseInt($(this).data('days'));
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(endDate.getDate() - days);
        
        $('#start-date').val(formatDateForInput(startDate));
        $('#end-date').val(formatDateForInput(endDate));
        
        // Mettre à jour la suggestion
        updateGranularitySuggestion();
        
        // Auto-sélectionner la granularité recommandée
        if (days <= 7) {
            $('#granularity-select').val('15min');
        } else if (days <= 30) {
            $('#granularity-select').val('hour');
        } else {
            $('#granularity-select').val('day');
        }
    });
    
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

/**
 * Rend les graphiques ApexCharts
 */
function renderCharts() {
    const data = window.historicalChartData;
    
    if (!data || !data.categories || data.categories.length === 0) {
        console.warn('Pas de données pour les graphiques');
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

