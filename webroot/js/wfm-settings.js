/**
 * WfmSettings - UX + validation live des fenêtres pause / repas
 */

(function ($) {
    'use strict';

    function parseTimeToMinutes(value) {
        if (!value) {
            return null;
        }
        var parts = String(value).trim().split(':');
        if (parts.length < 2) {
            return null;
        }
        var h = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);
        if (isNaN(h) || isNaN(m)) {
            return null;
        }
        return h * 60 + m;
    }

    function formatMinutes(total) {
        var h = Math.floor(total / 60);
        var m = total % 60;
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }

    function fieldByName(name) {
        return $('[name="' + name + '"]');
    }

    function ensureFeedback($input) {
        var $fb = $input.siblings('.wfm-live-error').first();
        if (!$fb.length) {
            $fb = $('<div class="invalid-feedback wfm-live-error d-block"></div>');
            $input.after($fb);
        }
        return $fb;
    }

    function clearFieldError($input) {
        $input.removeClass('is-invalid');
        if ($input[0] && typeof $input[0].setCustomValidity === 'function') {
            $input[0].setCustomValidity('');
        }
        $input.siblings('.wfm-live-error').text('').hide();
    }

    function setFieldError($input, message) {
        $input.addClass('is-invalid');
        if ($input[0] && typeof $input[0].setCustomValidity === 'function') {
            $input[0].setCustomValidity(message);
        }
        ensureFeedback($input).text(message).show();
    }

    function validatePauseWindows(slotMinutes) {
        var slot = parseInt(slotMinutes, 10) || 15;
        var ok = true;
        var watched = [
            'am_pause_start_time', 'am_pause_end_time',
            'lunch_start_time', 'lunch_end_time',
            'pm_pause_start_time', 'pm_pause_end_time',
            'day_start_time', 'day_end_time'
        ];

        watched.forEach(function (name) {
            var $f = fieldByName(name);
            if ($f.length) {
                clearFieldError($f);
            }
        });

        var pairs = [
            {
                start: 'am_pause_start_time',
                end: 'am_pause_end_time',
                msg: 'La fin de la fenêtre pause AM doit être strictement après son début.'
            },
            {
                start: 'lunch_start_time',
                end: 'lunch_end_time',
                msg: 'La fin de la fenêtre repas doit être strictement après son début.'
            },
            {
                start: 'pm_pause_start_time',
                end: 'pm_pause_end_time',
                msg: 'La fin de la fenêtre pause PM doit être strictement après son début.'
            },
            {
                start: 'day_start_time',
                end: 'day_end_time',
                msg: 'La fin de journée doit être strictement après le début de journée.'
            }
        ];

        pairs.forEach(function (p) {
            var $start = fieldByName(p.start);
            var $end = fieldByName(p.end);
            if (!$start.length || !$end.length) {
                return;
            }
            var s = parseTimeToMinutes($start.val());
            var e = parseTimeToMinutes($end.val());
            if (s === null || e === null) {
                return;
            }
            if (s >= e) {
                setFieldError($end, p.msg);
                ok = false;
            }
        });

        var $amEnd = fieldByName('am_pause_end_time');
        var $lunchStart = fieldByName('lunch_start_time');
        if ($amEnd.length && $lunchStart.length && !$amEnd.hasClass('is-invalid')) {
            var amEnd = parseTimeToMinutes($amEnd.val());
            var lunchStart = parseTimeToMinutes($lunchStart.val());
            if (amEnd !== null && lunchStart !== null) {
                var amLimit = lunchStart - slot;
                if (amEnd > amLimit) {
                    setFieldError(
                        $amEnd,
                        'Fin saisie : ' + formatMinutes(amEnd) +
                        '. Maximum autorisé : ' + formatMinutes(amLimit) +
                        ' (début de la fenêtre repas ' + formatMinutes(lunchStart) +
                        ' moins ' + slot + ' min).'
                    );
                    ok = false;
                }
            }
        }

        var $pmStart = fieldByName('pm_pause_start_time');
        var $lunchEnd = fieldByName('lunch_end_time');
        if ($pmStart.length && $lunchEnd.length && !$pmStart.hasClass('is-invalid')) {
            var pmStart = parseTimeToMinutes($pmStart.val());
            var lunchEnd = parseTimeToMinutes($lunchEnd.val());
            if (pmStart !== null && lunchEnd !== null && pmStart < lunchEnd) {
                setFieldError(
                    $pmStart,
                    'Début saisi : ' + formatMinutes(pmStart) +
                    '. Minimum autorisé : ' + formatMinutes(lunchEnd) +
                    ' (fin de la fenêtre repas).'
                );
                ok = false;
            }
        }

        return ok;
    }

    $(document).ready(function () {
        if (typeof window.initTooltips === 'function') {
            window.initTooltips();
        }

        var $form = $('form').has('[name="am_pause_end_time"]').first();
        if (!$form.length) {
            return;
        }

        var slotMinutes = parseInt($form.attr('data-slot-minutes'), 10) || 15;

        var fields = [
            'am_pause_start_time', 'am_pause_end_time',
            'lunch_start_time', 'lunch_end_time',
            'pm_pause_start_time', 'pm_pause_end_time',
            'day_start_time', 'day_end_time'
        ];

        function run() {
            validatePauseWindows(slotMinutes);
        }

        fields.forEach(function (name) {
            fieldByName(name).on('change input blur', run);
        });

        $form.on('submit', function (e) {
            if (!validatePauseWindows(slotMinutes)) {
                e.preventDefault();
                var $first = $form.find('.is-invalid').first();
                if ($first.length && $first[0].reportValidity) {
                    $first[0].reportValidity();
                }
                if ($first.length) {
                    $('html, body').animate({ scrollTop: $first.offset().top - 120 }, 200);
                }
            }
        });

        run();
    });
})(jQuery);
