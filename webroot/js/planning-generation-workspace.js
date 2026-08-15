/**
 * Workspace génération de planning : polling statut + scroll équité + actions.
 */
(function () {
    'use strict';

    var root = document.getElementById('planning-generation-workspace');
    if (!root) {
        return;
    }

    var csrfToken = root.getAttribute('data-csrf-token') || '';

    // Tooltips Bootstrap 4 (si le plugin jQuery est disponible)
    if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.tooltip === 'function') {
        window.jQuery(function () {
            window.jQuery('[data-toggle="tooltip"]').tooltip();
        });
    }

    // Copie CSV équité
    var copyBtn = document.getElementById('equity-copy-csv');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var table = document.getElementById('equity-report-table');
            if (!table) {
                return;
            }
            function escapeCsv(str) {
                if (str == null) {
                    return '';
                }
                str = String(str).trim();
                if (str.indexOf('"') >= 0) {
                    str = str.replace(/"/g, '""');
                }
                if (str.indexOf(',') >= 0 || str.indexOf('\n') >= 0 || str.indexOf('"') >= 0) {
                    return '"' + str + '"';
                }
                return str;
            }
            var rows = [];
            var thead = table.querySelector('thead tr');
            var visibleIndexes = [];
            if (thead) {
                var ths = Array.from(thead.querySelectorAll('th'));
                ths.forEach(function (th, i) {
                    if (th.style.display !== 'none') {
                        visibleIndexes.push(i);
                    }
                });
                rows.push(visibleIndexes.map(function (i) {
                    return escapeCsv(ths[i].textContent);
                }).join(','));
            }
            table.querySelectorAll('tbody tr').forEach(function (tr) {
                var tds = Array.from(tr.querySelectorAll('td'));
                rows.push(visibleIndexes.map(function (i) {
                    var td = tds[i];
                    var content = td ? td.getAttribute('data-csv-content') : null;
                    return escapeCsv(content != null ? content : (td ? td.textContent : ''));
                }).join(','));
            });
            navigator.clipboard.writeText(rows.join('\r\n')).then(function () {
                var oldHtml = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="bi bi-check2"></i> Copié !';
                copyBtn.classList.add('btn-success');
                copyBtn.classList.remove('btn-outline-secondary');
                setTimeout(function () {
                    copyBtn.innerHTML = oldHtml;
                    copyBtn.classList.remove('btn-success');
                    copyBtn.classList.add('btn-outline-secondary');
                }, 2000);
            }).catch(function () {
                alert('Impossible de copier dans le presse-papier.');
            });
        });
    }

    // Tri au clic du tableau d'équité
    (function initEquitySort() {
        var table = document.getElementById('equity-report-table');
        if (!table) {
            return;
        }
        var headers = table.querySelectorAll('thead th[data-sort]');
        headers.forEach(function (th) {
            th.addEventListener('click', function () {
                var column = Array.prototype.indexOf.call(th.parentNode.children, th);
                var sortType = th.getAttribute('data-sort') || 'text';
                var isAsc = th.classList.contains('sort-asc');

                headers.forEach(function (h) {
                    h.classList.remove('sort-asc', 'sort-desc');
                });
                th.classList.add(isAsc ? 'sort-desc' : 'sort-asc');

                var tbody = table.querySelector('tbody');
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

                rows.sort(function (a, b) {
                    var aCell = a.querySelectorAll('td')[column];
                    var bCell = b.querySelectorAll('td')[column];
                    var aVal, bVal;

                    if (sortType === 'number') {
                        var aRaw = aCell ? aCell.getAttribute('data-sort-value') : null;
                        var bRaw = bCell ? bCell.getAttribute('data-sort-value') : null;
                        aVal = aRaw !== null ? (parseFloat(aRaw) || 0) : 0;
                        bVal = bRaw !== null ? (parseFloat(bRaw) || 0) : 0;
                    } else {
                        aVal = (aCell ? aCell.textContent.trim() : '').toLowerCase();
                        bVal = (bCell ? bCell.textContent.trim() : '').toLowerCase();
                    }

                    if (aVal < bVal) {
                        return isAsc ? 1 : -1;
                    }
                    if (aVal > bVal) {
                        return isAsc ? -1 : 1;
                    }
                    return 0;
                });

                rows.forEach(function (row) {
                    tbody.appendChild(row);
                });
            });
        });
    })();

    // Visibilité des colonnes du tableau d'équité
    (function initEquityColumns() {
        var table = document.getElementById('equity-report-table');
        var menu = document.getElementById('equity-cols-menu');
        if (!table || !menu) {
            return;
        }
        var STORAGE_KEY = 'shifti.equity.cols';
        var checkboxes = Array.prototype.slice.call(menu.querySelectorAll('.equity-col-toggle'));
        var headerCells = Array.prototype.slice.call(table.querySelectorAll('thead tr th'));

        function apply() {
            var hidden = {};
            checkboxes.forEach(function (cb) {
                hidden[cb.getAttribute('data-col')] = !cb.checked;
            });
            headerCells.forEach(function (th, i) {
                var key = th.getAttribute('data-col');
                var isHidden = hidden[key] === true;
                th.style.display = isHidden ? 'none' : '';
                table.querySelectorAll('tbody tr').forEach(function (tr) {
                    var td = tr.querySelectorAll('td')[i];
                    if (td) {
                        td.style.display = isHidden ? 'none' : '';
                    }
                });
            });
            var saved = {};
            checkboxes.forEach(function (cb) {
                saved[cb.getAttribute('data-col')] = cb.checked ? 1 : 0;
            });
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(saved));
            } catch (e) { /* ignore */ }
        }

        var saved = null;
        try {
            saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        } catch (e) { saved = null; }
        if (saved && typeof saved === 'object') {
            checkboxes.forEach(function (cb) {
                var key = cb.getAttribute('data-col');
                if (Object.prototype.hasOwnProperty.call(saved, key)) {
                    cb.checked = saved[key] === 1;
                }
            });
        }

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', apply);
        });

        apply();
    })();

    // Actions retry / delete
    document.addEventListener('click', function (e) {
        var retryLink = e.target.closest('.job-retry-link');
        var deleteLink = e.target.closest('.job-delete-link');
        var link = retryLink || deleteLink;
        if (!link) {
            return;
        }
        e.preventDefault();
        var confirmMsg = link.getAttribute('data-confirm');
        var url = link.getAttribute('data-url');
        if (!confirm(confirmMsg)) {
            return;
        }
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';
        if (csrfToken) {
            var csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_csrfToken';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }
        document.body.appendChild(form);
        form.submit();
    });

    // Conformité : ouverture auto si KO (filtres = radio/CSS dans le template)
    (function initCompliancePanel() {
        var panel = document.getElementById('compliance-panel');
        if (!panel) {
            return;
        }
        var ko = parseInt(panel.getAttribute('data-compliance-ko') || '0', 10);
        if (ko > 0) {
            var collapseEl = document.getElementById('ws-quality-compliance');
            if (collapseEl) {
                if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.collapse === 'function') {
                    window.jQuery(collapseEl).collapse('show');
                } else {
                    collapseEl.classList.add('show');
                }
            }
        }
    })();

    // Filtres Agents exclus (catégorie + raison)
    (function initExcludedAgentsFilters() {
        var panel = document.getElementById('excluded-agents-panel');
        if (!panel) {
            return;
        }

        var category = 'all';
        var reason = '';
        var categoryChips = panel.querySelectorAll('.excluded-filter-chip');
        var reasonChips = panel.querySelectorAll('.excluded-reason-chip');
        var clearReasonBtn = document.getElementById('excluded-filter-clear-reason');
        var emptyMsg = document.getElementById('excluded-agents-empty-filter');
        var rows = panel.querySelectorAll('#excluded-agents-table tbody tr.excluded-agent-row, #excluded-agents-table tbody tr.excluded-agent-detail-row');

        function applyFilter() {
            var visibleAgents = 0;
            rows.forEach(function (row) {
                var rowCat = row.getAttribute('data-agent-category') || '';
                var rowReasons = (row.getAttribute('data-agent-reasons') || '').split('|');
                var catOk = category === 'all' || rowCat === category;
                var reasonOk = !reason || rowReasons.indexOf(reason) !== -1;
                var show = catOk && reasonOk;
                row.style.display = show ? '' : 'none';
                if (show && row.classList.contains('excluded-agent-row')) {
                    visibleAgents += 1;
                }
            });
            if (emptyMsg) {
                emptyMsg.style.display = visibleAgents === 0 ? '' : 'none';
            }
            if (clearReasonBtn) {
                if (reason) {
                    clearReasonBtn.classList.remove('d-none');
                } else {
                    clearReasonBtn.classList.add('d-none');
                }
            }
        }

        categoryChips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                category = chip.getAttribute('data-filter-category') || 'all';
                categoryChips.forEach(function (c) {
                    c.classList.remove('active', 'btn-primary', 'btn-danger', 'btn-secondary');
                    var cat = c.getAttribute('data-filter-category');
                    if (cat === 'actionable') {
                        c.classList.add('btn-outline-danger');
                    } else if (cat === 'expected') {
                        c.classList.add('btn-outline-secondary');
                    } else {
                        c.classList.add('btn-outline-primary');
                    }
                });
                chip.classList.remove('btn-outline-primary', 'btn-outline-danger', 'btn-outline-secondary');
                if (category === 'actionable') {
                    chip.classList.add('active', 'btn-danger');
                } else if (category === 'expected') {
                    chip.classList.add('active', 'btn-secondary');
                } else {
                    chip.classList.add('active', 'btn-primary');
                }
                applyFilter();
            });
        });

        reasonChips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                var next = chip.getAttribute('data-filter-reason') || '';
                reason = reason === next ? '' : next;
                reasonChips.forEach(function (c) {
                    c.classList.toggle('active', (c.getAttribute('data-filter-reason') || '') === reason);
                });
                applyFilter();
            });
        });

        if (clearReasonBtn) {
            clearReasonBtn.addEventListener('click', function () {
                reason = '';
                reasonChips.forEach(function (c) {
                    c.classList.remove('active');
                });
                applyFilter();
            });
        }

        // Sync chevron aria-expanded on agent toggles
        panel.querySelectorAll('.excluded-agent-toggle').forEach(function (btn) {
            var targetSel = btn.getAttribute('data-target');
            if (!targetSel || typeof window.jQuery === 'undefined') {
                return;
            }
            window.jQuery(targetSel).on('show.bs.collapse', function () {
                btn.setAttribute('aria-expanded', 'true');
            });
            window.jQuery(targetSel).on('hide.bs.collapse', function () {
                btn.setAttribute('aria-expanded', 'false');
            });
        });
    })();

    // Polling progression (absent si job terminé → ne pas bloquer les inits UI ci-dessus)
    var progress = document.getElementById('workspace-progress');
    if (!progress) {
        return;
    }

    var statusUrl = progress.getAttribute('data-status-url');
    var initialStatus = progress.getAttribute('data-initial-status') || '';
    var isPolling = initialStatus === 'running' || initialStatus === 'queued';
    var pollingErrorCount = 0;
    var MAX_ERRORS = 3;

    var stepNames = {
        preparation_donnees: 'Préparation des données',
        passe1_activites_fixes: 'Passe 1 : activités fixes',
        passe1_5_rotation: 'Passe 1.5 : activités avec rotation',
        passe2_planning_previsions: 'Passe 2 : activités avec prévisions',
        fusion_segments: 'Fusion des segments',
        sauvegarde_brouillon: 'Sauvegarde du brouillon',
        day_generation: 'Génération du jour',
        starting: 'Démarrage'
    };

    function formatEta(seconds) {
        if (seconds === null || seconds === undefined) {
            return '—';
        }
        var s = Number(seconds);
        if (!Number.isFinite(s) || s <= 0) {
            return '—';
        }
        var m = Math.floor(s / 60);
        var r = s % 60;
        if (m <= 0) {
            return r + 's';
        }
        var h = Math.floor(m / 60);
        var rm = m % 60;
        if (h <= 0) {
            return rm + 'm ' + r + 's';
        }
        return h + 'h ' + rm + 'm';
    }

    function getStatusBadgeClass(status) {
        if (status === 'finished' || status === 'finished_with_errors') {
            return 'success';
        }
        if (status === 'running') {
            return 'info';
        }
        if (status === 'queued') {
            return 'warning';
        }
        if (status === 'error' || status === 'infeasible') {
            return 'danger';
        }
        return 'secondary';
    }

    function getStatusIcon(status) {
        if (status === 'finished' || status === 'finished_with_errors') {
            return 'check-circle';
        }
        if (status === 'running') {
            return 'arrow-repeat';
        }
        if (status === 'queued') {
            return 'hourglass-split';
        }
        if (status === 'error') {
            return 'x-circle';
        }
        if (status === 'infeasible') {
            return 'exclamation-triangle';
        }
        return 'clock';
    }

    function updateUI(job) {
        var total = Number(job.total_days || 0);
        var done = Number(job.processed_days || 0);
        var pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        var status = String(job.status || '');

        var progressText = document.getElementById('jobProgressText');
        var progressPercent = document.getElementById('jobProgressPercent');
        if (progressText) {
            progressText.textContent = done + ' / ' + total;
        }
        if (progressPercent) {
            progressPercent.textContent = pct + '%';
        }

        var bar = document.getElementById('jobProgressBar');
        var barLabel = document.getElementById('jobProgressBarLabel');
        if (bar) {
            bar.style.width = pct + '%';
            bar.setAttribute('aria-valuenow', String(pct));
            bar.className = 'progress-bar ' +
                (status === 'running' ? 'progress-bar-striped progress-bar-animated ' : '') +
                'bg-' + getStatusBadgeClass(status);
        }
        if (barLabel) {
            barLabel.textContent = pct + '%';
        }

        var badgeClass = getStatusBadgeClass(status);
        var icon = getStatusIcon(status);
        ['jobStatusBadge', 'workspaceStatusBadge'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.className = 'badge badge-' + badgeClass;
                el.innerHTML = '<i class="bi bi-' + icon + '"></i> ' + status;
            }
        });

        var etaEl = document.getElementById('jobEta');
        if (etaEl) {
            etaEl.textContent = formatEta(job.eta_seconds);
        }
        var dayEl = document.getElementById('jobCurrentDate');
        if (dayEl) {
            dayEl.textContent = job.current_day || '—';
        }
        var stepEl = document.getElementById('jobCurrentStep');
        if (stepEl) {
            var step = job.current_step || '';
            stepEl.textContent = stepNames[step] || step || '—';
        }

        var indicator = document.getElementById('realtimeIndicator');
        if (indicator) {
            if (status === 'running') {
                indicator.classList.remove('inactive');
            } else {
                indicator.classList.add('inactive');
            }
        }

        if (
            status === 'finished' ||
            status === 'finished_with_errors' ||
            status === 'error' ||
            status === 'infeasible' ||
            status === 'failed' ||
            status === 'cancelled'
        ) {
            isPolling = false;
            if (indicator) {
                indicator.classList.add('inactive');
            }
            // Rechargement pour mettre à jour CTA / onglets
            window.location.reload();
        }
    }

    function poll() {
        if (!isPolling || !statusUrl) {
            return;
        }
        fetch(statusUrl, { headers: { Accept: 'application/json' } })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (json) {
                if (json && json.success && json.job) {
                    pollingErrorCount = 0;
                    updateUI(json.job);
                } else {
                    throw new Error('Invalid response');
                }
            })
            .catch(function (err) {
                pollingErrorCount += 1;
                console.error('Polling error:', err);
                if (pollingErrorCount >= MAX_ERRORS) {
                    isPolling = false;
                    var indicator = document.getElementById('realtimeIndicator');
                    if (indicator) {
                        indicator.classList.add('inactive');
                    }
                }
            })
            .finally(function () {
                if (isPolling) {
                    setTimeout(poll, 1500);
                }
            });
    }

    if (isPolling) {
        poll();
    } else {
        var indicator = document.getElementById('realtimeIndicator');
        if (indicator) {
            indicator.classList.add('inactive');
        }
    }
})();
