$(document).ready(function () {
    let selectedColor = 'white';
    let selectedId = '0';
    let selectedType = '';
    let selectedLabel = '';
    let offerColor = 'white';
    let offerId = '0';
    let offerType = '';
    let offerLabel = '';
    let userId = '';
    let isDragging = false;
    let paintedRow = null;

    window.selectedColor = selectedColor;
    window.selectedId = selectedId;

    $('.offerColor').click(function () {
        selectedColor = $(this).attr('data-color') || 'white';
        selectedId = $(this).attr('data-id') || '0';
        selectedType = $(this).attr('data-offer-type') || '';
        selectedLabel = $(this).attr('data-offer-label') || $(this).attr('title') || '';
        window.selectedColor = selectedColor;
        window.selectedId = selectedId;
        $('.offerColor').removeClass('selected-offer');
        $(this).addClass('selected-offer');
    });

    $('.td_quarter').bind('contextmenu', function (e) {
        e.preventDefault();
        return false;
    });

    function targetsFor(cell) {
        return [cell];
    }

    function applyChange(cellElement) {
        const $cell = $(cellElement);
        if (offerId === '0' || offerColor === 'white') {
            $cell.css('background-color', '');
        } else {
            $cell.css('background-color', offerColor);
        }
        $cell.addClass('is-modified');
        $cell.attr('data-offer-id', offerId);
        $cell.attr('data-offer-type', offerType);
        $cell.attr('data-offer-label', offerLabel);
        if (offerId !== '0') {
            $cell.removeClass('td_unavailable force-selected');
        }
    }

    function paintCell(cell) {
        targetsFor(cell).forEach(applyChange);
        paintedRow = cell.closest('tr');
    }

    $('.td_quarter').mousedown(function (e) {
        const $cell = $(this);

        if ($cell.hasClass('td_unavailable')) {
            if (selectedColor === 'white' || selectedId === '0') {
                e.preventDefault();
                return false;
            }
            if ($cell.hasClass('force-selected')) {
                $cell.removeClass('td_unavailable force-selected');
                $cell.css('background', '');
            } else {
                if (!confirm('Cet agent n\'est pas disponible sur ce créneau selon son contrat. Voulez-vous forcer l\'affectation ?')) {
                    e.preventDefault();
                    return false;
                }
                $cell.removeClass('td_unavailable');
                $cell.css('background', '');
            }
        }

        isDragging = true;
        userId = $cell.closest('tr').attr('data_user');

        if (e.buttons === 1) {
            offerColor = selectedColor;
            offerId = selectedId;
            offerType = selectedType;
            offerLabel = selectedLabel;
            paintCell(this);
        } else if (e.buttons === 2) {
            offerColor = 'white';
            offerId = '0';
            offerType = '';
            offerLabel = '';
            paintCell(this);
        }
        e.preventDefault();
    });

    $('.td_quarter').mouseover(function () {
        if (!isDragging) {
            return;
        }
        const $cell = $(this);
        const newUserId = $cell.closest('tr').attr('data_user');
        if (userId === newUserId) {
            if ($cell.hasClass('td_unavailable')) {
                $cell.removeClass('td_unavailable force-selected');
                $cell.css('background', '');
            }
            paintCell(this);
        }
    });

    $(document).mouseup(function () {
        if (isDragging) {
            isDragging = false;
            userId = '';
            if (paintedRow && typeof window.gridsOnPaintedRow === 'function') {
                window.gridsOnPaintedRow(paintedRow);
            }
            paintedRow = null;
        }
    });
});
