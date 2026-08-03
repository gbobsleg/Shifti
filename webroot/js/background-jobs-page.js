/**
 * Page Jobs — polling 6s, pause si onglet caché.
 */
(function () {
    'use strict';

    var POLL_MS = 6000;
    var TYPE_LABELS = {
        optuna: 'Optuna',
        forecast: 'Prévision',
        planning: 'Planning'
    };

    function qs(root, sel) {
        return root ? root.querySelector(sel) : null;
    }

    function esc(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }

    function statusBadgeClass(status) {
        switch (String(status || '')) {
            case 'running':
                return 'badge-primary';
            case 'queued':
                return 'badge-warning';
            case 'completed':
            case 'finished':
                return 'badge-success';
            case 'failed':
            case 'error':
            case 'infeasible':
            case 'finished_with_errors':
                return 'badge-danger';
            default:
                return 'badge-secondary';
        }
    }

    function isActiveStatus(status) {
        return status === 'queued' || status === 'running';
    }

    function renderRows(items) {
        if (!items || !items.length) {
            return '<tr><td colspan="7" class="text-muted text-center py-4">Aucun job actif ni historique récent (24 h).</td></tr>';
        }
        return items.map(function (item) {
            var type = TYPE_LABELS[item.type] || item.type || '—';
            var label = item.label || '—';
            var status = item.status || '—';
            var progress = item.progress || '—';
            var started = item.started_at || '—';
            var finished = item.finished_at || '—';
            var err = item.error_message
                ? '<div class="small text-danger mt-1">' + esc(item.error_message) + '</div>'
                : '';
            var url = item.url || '#';
            var rowClass = isActiveStatus(status) ? 'table-row-active' : '';

            return (
                '<tr class="' + rowClass + '">' +
                    '<td><span class="badge badge-light border">' + esc(type) + '</span></td>' +
                    '<td>' + esc(label) + err + '</td>' +
                    '<td><span class="badge ' + statusBadgeClass(status) + '">' + esc(status) + '</span></td>' +
                    '<td class="small">' + esc(progress) + '</td>' +
                    '<td class="small text-nowrap">' + esc(started) + '</td>' +
                    '<td class="small text-nowrap">' + esc(finished) + '</td>' +
                    '<td><a class="btn btn-sm btn-outline-primary" href="' + esc(url) + '">Ouvrir</a></td>' +
                '</tr>'
            );
        }).join('');
    }

    function init(root) {
        if (!root) {
            return;
        }
        var url = root.getAttribute('data-url-status');
        if (!url) {
            return;
        }

        var elActive = qs(root, '[data-bj-active-count]');
        var elOptuna = qs(root, '[data-bj-type-optuna]');
        var elForecast = qs(root, '[data-bj-type-forecast]');
        var elPlanning = qs(root, '[data-bj-type-planning]');
        var elBody = qs(root, '[data-bj-tbody]');
        var elUpdated = qs(root, '[data-bj-updated]');
        var timer = null;

        function setText(el, text) {
            if (el) {
                el.textContent = text == null ? '' : String(text);
            }
        }

        function apply(data) {
            if (!data || !data.success) {
                return;
            }
            setText(elActive, data.active_count != null ? data.active_count : 0);
            var by = data.by_type || {};
            setText(elOptuna, by.optuna != null ? by.optuna : 0);
            setText(elForecast, by.forecast != null ? by.forecast : 0);
            setText(elPlanning, by.planning != null ? by.planning : 0);
            if (elBody) {
                elBody.innerHTML = renderRows(data.items || []);
            }
            if (elUpdated) {
                var now = new Date();
                elUpdated.textContent =
                    now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }

        function fetchStatus() {
            if (document.hidden) {
                return Promise.resolve();
            }
            return fetch(url, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(apply)
                .catch(function (err) {
                    console.error('[background-jobs-page]', err);
                });
        }

        function stopPoll() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        function startPoll() {
            stopPoll();
            timer = setInterval(fetchStatus, POLL_MS);
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPoll();
                return;
            }
            fetchStatus().then(startPoll);
        });

        if (!document.hidden) {
            startPoll();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        init(document.getElementById('background-jobs-root'));
    });
})();
