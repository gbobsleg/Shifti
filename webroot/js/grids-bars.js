(function () {
    'use strict';

    function kindOf(td) {
        if (td.classList.contains('td_unavailable')) {
            return 'unavailable';
        }
        const id = td.getAttribute('data-offer-id') || '0';
        if (id === '0' || id === '') {
            return 'empty';
        }
        const type = td.getAttribute('data-offer-type') || 'normal';
        if (type === 'pause') {
            return 'pause';
        }
        if (type === 'lunch') {
            return 'lunch';
        }
        if (type === 'remote_work') {
            return 'empty';
        }
        return 'prod';
    }

    function clearBar(td) {
        td.classList.remove(
            'bar-start', 'bar-mid', 'bar-end', 'bar-single',
            'bar-pause', 'bar-lunch',
            'bar-day-start', 'bar-day-end',
            'hour-end'
        );
        const label = td.querySelector('.bar-label');
        if (label) {
            label.remove();
        }
    }

    function recomputeRow(tr) {
        if (!tr) {
            return;
        }
        const cells = Array.from(tr.querySelectorAll('td.td_quarter'));
        const n = cells.length;
        const kinds = cells.map(kindOf);
        const offerIds = cells.map((td) => td.getAttribute('data-offer-id') || '0');

        requestAnimationFrame(() => {
            cells.forEach((td, i) => {
                clearBar(td);
                if ((i + 1) % 4 === 0) {
                    td.classList.add('hour-end');
                }
            });

            let i = 0;
            while (i < n) {
                const kind = kinds[i];
                if (kind === 'empty' || kind === 'unavailable') {
                    i += 1;
                    continue;
                }
                if (kind === 'pause') {
                    cells[i].classList.add('bar-pause');
                }
                if (kind === 'lunch') {
                    cells[i].classList.add('bar-lunch');
                }
                const offerId = offerIds[i];
                let j = i + 1;
                while (j < n && kinds[j] === kind && offerIds[j] === offerId) {
                    if (kind === 'pause') {
                        cells[j].classList.add('bar-pause');
                    }
                    if (kind === 'lunch') {
                        cells[j].classList.add('bar-lunch');
                    }
                    j += 1;
                }
                const len = j - i;
                for (let k = i; k < j; k += 1) {
                    if (len === 1) {
                        cells[k].classList.add('bar-single');
                    } else if (k === i) {
                        cells[k].classList.add('bar-start');
                    } else if (k === j - 1) {
                        cells[k].classList.add('bar-end');
                    } else {
                        cells[k].classList.add('bar-mid');
                    }
                }
                const labelText = (cells[i].getAttribute('data-offer-label') || '').trim();
                if (labelText && len >= 2) {
                    const span = document.createElement('span');
                    span.className = 'bar-label';
                    span.textContent = labelText;
                    cells[i].appendChild(span);
                }
                i = j;
            }

            // Marquer le premier et dernier créneau occupé de la journée
            let first = -1;
            let last  = -1;
            for (let k = 0; k < n; k++) {
                if (kinds[k] !== 'empty' && kinds[k] !== 'unavailable') {
                    if (first === -1) { first = k; }
                    last = k;
                }
            }
            if (first !== -1) {
                cells[first].classList.add('bar-day-start');
                cells[last].classList.add('bar-day-end');
            }
        });
    }

    function recomputeAll() {
        document.querySelectorAll('.grids-app tr.tr_quarter').forEach(recomputeRow);
    }

    window.gridsOnPaintedRow = recomputeRow;
    window.gridsRecomputeBars = recomputeAll;

    document.addEventListener('DOMContentLoaded', recomputeAll);
}());
