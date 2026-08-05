/**
 * Page Jobs — polling 6s sur le bloc Actifs uniquement (historique = serveur).
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
            case 'cancelled':
                return 'badge-secondary';
            default:
                return 'badge-secondary';
        }
    }

    function isActiveStatus(status) {
        return status === 'queued' || status === 'running';
    }

    function cancelUrlFor(template, jobId) {
        if (!template) {
            return '';
        }
        if (template.indexOf('/0') !== -1) {
            return template.replace(/\/0(?=\/?$|\?)/, '/' + String(jobId));
        }
        return template.replace(/\/$/, '') + '/' + String(jobId);
    }

    function renderActiveRows(items) {
        if (!items || !items.length) {
            return '<tr><td colspan="7" class="text-muted text-center py-4">Aucun job actif.</td></tr>';
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
            var actions =
                '<a class="btn btn-sm btn-outline-primary" href="' + esc(url) + '">Ouvrir</a>';
            var canCancel = item.can_cancel === true || item.can_cancel === 1 || item.can_cancel === '1';
            if (!canCancel && item.type === 'optuna' && isActiveStatus(status) && item.id) {
                canCancel = true;
            }
            if (canCancel && item.id) {
                actions +=
                    ' <button type="button" class="btn btn-sm btn-outline-danger ml-1"' +
                    ' data-bj-cancel-optuna="' + esc(item.id) + '">Annuler</button>';
            }

            return (
                '<tr class="' + rowClass + '">' +
                    '<td><span class="badge badge-light border">' + esc(type) + '</span></td>' +
                    '<td>' + esc(label) + err + '</td>' +
                    '<td><span class="badge ' + statusBadgeClass(status) + '">' + esc(status) + '</span></td>' +
                    '<td class="small">' + esc(progress) + '</td>' +
                    '<td class="small text-nowrap">' + esc(started) + '</td>' +
                    '<td class="small text-nowrap">' + esc(finished) + '</td>' +
                    '<td>' + actions + '</td>' +
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
        var cancelTemplate = root.getAttribute('data-url-cancel-optuna') || '';
        var csrf = root.getAttribute('data-csrf-token') || '';

        var elActive = qs(root, '[data-bj-active-count]');
        var elOptuna = qs(root, '[data-bj-type-optuna]');
        var elForecast = qs(root, '[data-bj-type-forecast]');
        var elPlanning = qs(root, '[data-bj-type-planning]');
        var elBody = qs(root, '[data-bj-active-tbody]');
        var elUpdated = qs(root, '[data-bj-updated]');
        var timer = null;
        var cancelBusy = false;

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
                elBody.innerHTML = renderActiveRows(data.items || []);
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

        root.addEventListener('click', function (ev) {
            var btn = ev.target && ev.target.closest
                ? ev.target.closest('[data-bj-cancel-optuna]')
                : null;
            if (!btn || !root.contains(btn)) {
                return;
            }
            ev.preventDefault();
            if (cancelBusy) {
                return;
            }
            var jobId = btn.getAttribute('data-bj-cancel-optuna');
            if (!jobId) {
                return;
            }
            if (!window.confirm('Annuler le job Optuna #' + jobId + ' ?')) {
                return;
            }
            var cancelUrl = cancelUrlFor(cancelTemplate, jobId);
            if (!cancelUrl) {
                return;
            }
            cancelBusy = true;
            btn.disabled = true;
            fetch(cancelUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-Token': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (res) {
                    return res.json().then(function (body) {
                        return { ok: res.ok, body: body };
                    });
                })
                .then(function (r) {
                    if (!r.ok) {
                        window.alert((r.body && r.body.message) || 'Annulation impossible');
                    }
                    return fetchStatus();
                })
                .catch(function (err) {
                    console.error('[background-jobs-page] cancel', err);
                    window.alert(String(err));
                })
                .finally(function () {
                    cancelBusy = false;
                });
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPoll();
                return;
            }
            fetchStatus().then(startPoll);
        });

        if (!document.hidden) {
            fetchStatus().then(startPoll);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        init(document.getElementById('background-jobs-root'));
    });
})();
