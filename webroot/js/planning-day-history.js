/**
 * Historique planning agent × jour
 * - Clic droit uniquement sur th agent (pas sur .td_quarter)
 * - Mini-barres dynamiques selon horaires WFM + restauration via API JSON
 */
(function ($) {
    'use strict';

    var SOURCE_LABELS = {
        manual: 'Manuel',
        publish: 'Publication',
        generation: 'Génération',
        restore: 'Restauration'
    };

    var $menu;
    var $modal;
    var $list;
    var $meta;
    var pending = null;
    var historyUrl = '';
    var restoreUrl = '';

    function csrfToken() {
        // Méthode standard du projet pour les appels AJAX isolés :
        // 1) data-csrf-token sur l'élément racine de la feature (cf. Offers, BackgroundJobs, PlanningGenerationJobs...)
        var root = document.getElementById('planningDayHistoryRoot');
        var token = root ? root.getAttribute('data-csrf-token') : null;
        if (token) {
            return String(token);
        }

        // 2) Fallback : input caché _csrfToken généré par $this->Form->create() (utilisé par grids.js)
        token = $('[name="_csrfToken"]').val() || $('#rangesForm [name="_csrfToken"]').val();
        return token ? String(token) : '';
    }

    function hideMenu() {
        if ($menu && $menu.length) {
            $menu.attr('hidden', 'hidden');
        }
    }

    function showMenu(pageX, pageY) {
        if (!$menu.length) {
            return;
        }
        $menu.css({
            left: pageX + 'px',
            top: pageY + 'px'
        }).removeAttr('hidden');

        var rect = $menu[0].getBoundingClientRect();
        var maxLeft = window.innerWidth - rect.width - 8;
        var maxTop = window.innerHeight - rect.height - 8;
        var left = Math.max(8, Math.min(pageX, maxLeft));
        var top = Math.max(8, Math.min(pageY, maxTop));
        $menu.css({ left: left + 'px', top: top + 'px' });
    }

    function parseWallDateTime(value) {
        if (!value) {
            return null;
        }
        var raw = String(value).trim().replace('T', ' ');
        var m = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ ](\d{2}):(\d{2})(?::(\d{2}))?/);
        if (!m) {
            return null;
        }
        return new Date(
            Number(m[1]),
            Number(m[2]) - 1,
            Number(m[3]),
            Number(m[4]),
            Number(m[5]),
            Number(m[6] || 0)
        );
    }

    function extractDayFromRow($tr) {
        var start = $tr.find('td.td_quarter[data-start]').first().attr('data-start');
        if (!start) {
            return null;
        }
        var day = String(start).slice(0, 10);
        return /^\d{4}-\d{2}-\d{2}$/.test(day) ? day : null;
    }

    function agentLabel($th, userId) {
        var text = $.trim($th.clone().children().remove().end().text());
        if (text) {
            return text;
        }
        return 'Agent #' + userId;
    }

    function sourceLabel(source) {
        return SOURCE_LABELS[source] || source || '—';
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function parseTimeToFloat(dt) {
        if (!dt) return 0;
        return dt.getHours() + (dt.getMinutes() / 60);
    }

    function formatHour(h) {
        return Math.floor(h) + 'h';
    }

    function buildMiniBarHtml(snapshot, startTime, endTime) {
        var segments = Array.isArray(snapshot) ? snapshot : [];
        startTime = startTime != null ? startTime : 0;
        endTime = endTime != null ? endTime : 24;
        var totalHours = endTime - startTime;
        if (totalHours <= 0) {
            totalHours = 24;
        }

        var html = '<div class="pdh-minibarre" title="Journée ' + formatHour(startTime) + '–' + formatHour(endTime) + '">';

        segments.forEach(function (seg) {
            var start = parseWallDateTime(seg.date_start);
            var end = parseWallDateTime(seg.date_end);
            if (!start || !end || end <= start) {
                return;
            }

            var segStartH = parseTimeToFloat(start);
            var segEndH = parseTimeToFloat(end);

            // Clipping pour ne pas sortir de la plage configurée
            var clippedStart = Math.max(segStartH, startTime);
            var clippedEnd = Math.min(segEndH, endTime);

            if (clippedEnd <= clippedStart) {
                return;
            }

            var leftPct = ((clippedStart - startTime) / totalHours) * 100;
            var widthPct = ((clippedEnd - clippedStart) / totalHours) * 100;

            leftPct = Math.max(0, Math.min(100, leftPct));
            widthPct = Math.max(0.15, Math.min(100 - leftPct, widthPct));

            var color = seg.color && String(seg.color).trim() !== '' ? String(seg.color) : '#6c757d';
            html += '<span class="pdh-minibarre__segment" style="left:' + leftPct.toFixed(3) + '%;width:'
                + widthPct.toFixed(3) + '%;background-color:' + escapeHtml(color) + ';"></span>';
        });

        html += '</div>';
        html += '<div class="pdh-minibarre__hours"><span>' + formatHour(startTime) + '</span><span>' + formatHour(endTime) + '</span></div>';
        return html;
    }

    function renderVersions(versions, startTime, endTime) {
        if (!versions || !versions.length) {
            $list.html('<p class="pdh-empty">Aucune version enregistrée pour ce jour.</p>');
            return;
        }

        var html = '';
        versions.forEach(function (version) {
            var created = version.created || '—';
            var actor = version.actor_name || 'Système';
            var source = sourceLabel(version.source);
            var emptyNote = (!version.snapshot || !version.snapshot.length)
                ? '<p class="small text-muted mb-2 mb-md-0">Journée vide</p>'
                : '';

            html += '<div class="pdh-version" data-history-id="' + escapeHtml(version.id) + '">';
            html += '<div class="pdh-version__header">';
            html += '<p class="pdh-version__meta"><strong>' + escapeHtml(created) + '</strong>';
            html += ' — ' + escapeHtml(actor);
            html += '<span class="pdh-version__source">' + escapeHtml(source) + '</span></p>';
            html += '<button type="button" class="btn btn-sm btn-outline-primary pdh-restore-btn" data-history-id="'
                + escapeHtml(version.id) + '">Restaurer cette version</button>';
            html += '</div>';
            html += emptyNote;
            html += buildMiniBarHtml(version.snapshot, startTime, endTime);
            html += '</div>';
        });

        $list.html(html);
    }

    function openHistoryModal() {
        if (!pending || !historyUrl) {
            return;
        }

        hideMenu();
        $meta.text(pending.agentName + ' — ' + pending.day);
        $list.html('<p class="pdh-loading text-muted">Chargement de l\'historique…</p>');
        $modal.modal('show');

        $.ajax({
            url: historyUrl,
            method: 'GET',
            dataType: 'json',
            cache: false,
            data: {
                user_id: pending.userId,
                day: pending.day
            }
        }).done(function (data) {
            if (!data || data.success !== true) {
                $list.html('<p class="pdh-error text-danger">Impossible de charger l\'historique.</p>');
                return;
            }
            var startTime = data.start_time != null ? data.start_time : 0;
            var endTime = data.end_time != null ? data.end_time : 24;
            renderVersions(Array.isArray(data.versions) ? data.versions : [], startTime, endTime);
        }).fail(function (xhr) {
            var detail = '';
            if (xhr && xhr.status) {
                detail = ' (HTTP ' + xhr.status + ')';
            }
            $list.html('<p class="pdh-error text-danger">Erreur lors du chargement de l\'historique'
                + detail + '.</p>');
        });
    }

    function restoreVersion(historyId) {
        if (!historyId || !restoreUrl) {
            return;
        }
        if (!window.confirm('Restaurer cette version du planning ? Les créneaux actuels de la journée seront remplacés.')) {
            return;
        }

        var $btn = $list.find('.pdh-restore-btn[data-history-id="' + historyId + '"]');
        $btn.prop('disabled', true).text('Restauration…');

        var token = csrfToken();
        $.ajax({
            url: restoreUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                history_id: historyId,
                _csrfToken: token
            },
            headers: {
                'X-CSRF-Token': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).done(function (data) {
            if (data && data.success === true) {
                window.location.reload();
                return;
            }
            window.alert((data && data.message) ? data.message : 'Échec de la restauration.');
            $btn.prop('disabled', false).text('Restaurer cette version');
        }).fail(function (xhr) {
            var msg = 'Échec de la restauration.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            window.alert(msg);
            $btn.prop('disabled', false).text('Restaurer cette version');
        });
    }

    $(function () {
        var $root = $('#planningDayHistoryRoot');
        $menu = $('#planningDayHistoryMenu');
        $modal = $('#planningDayHistoryModal');
        $list = $('#planningDayHistoryList');
        $meta = $('#planningDayHistoryMeta');

        if (!$root.length || !$menu.length || !$modal.length) {
            return;
        }

        // attr() plutôt que .data() pour éviter un cache jQuery d'anciennes URLs
        historyUrl = $root.attr('data-history-url') || $root.data('history-url') || '';
        restoreUrl = $root.attr('data-restore-url') || $root.data('restore-url') || '';

        // IMPORTANT : uniquement sur la cellule agent — jamais sur .td_quarter
        $(document).on('contextmenu', 'tr.tr_quarter > th.th_row:not(.site-column)', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $th = $(this);
            var $tr = $th.closest('tr.tr_quarter');
            var userId = parseInt($tr.attr('data_user'), 10);
            var day = extractDayFromRow($tr);

            if (!userId || !day) {
                hideMenu();
                return;
            }

            pending = {
                userId: userId,
                day: day,
                agentName: agentLabel($th, userId)
            };
            showMenu(e.clientX, e.clientY);
        });

        $('#planningDayHistoryMenuOpen').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openHistoryModal();
        });

        $list.on('click', '.pdh-restore-btn', function (e) {
            e.preventDefault();
            restoreVersion($(this).data('history-id'));
        });

        $(document).on('click', function () {
            hideMenu();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                hideMenu();
            }
        });

        $(window).on('scroll resize', hideMenu);

        $menu.on('click', function (e) {
            e.stopPropagation();
        });
    });
}(jQuery));
