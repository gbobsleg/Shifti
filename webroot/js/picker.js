var currDate = moment().format('DD/MM/YYYY');

$('#date-start, #date-end').daterangepicker({
    "startDate" : currDate + ", 08:00",
    "endDate" : currDate + ", 18:00",
    "showWeekNumbers": true,
    "timePicker": true,
    "timePicker24Hour": true,
    "timePickerIncrement": 15,
    "autoApply": true,
    "autoUpdateInput": false,
    "locale": {
        "format": "DD/MM/YYYY, HH:mm",
        "applyLabel": "Valider",
        "cancelLabel": "Annuler",
        "fromLabel": "De",
        "toLabel": "A",
        "customRangeLabel": "Personnaliser",
        "weekLabel": "W",
        "daysOfWeek": [
            "Di",
            "Lu",
            "Ma",
            "Me",
            "Je",
            "Ve",
            "Sa"
        ],
        "monthNames": [
            "Janvier",
            "Février",
            "Mars",
            "Avril",
            "Mai",
            "Juin",
            "Juillet",
            "Août",
            "Septembre",
            "Octobre",
            "Novembre",
            "Décembre"
        ],
        "firstDay": 1
    },
    "drops": "down"
}, function(start, end, label) {
    console.log('New date range selected: ' + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD') + ' (predefined range: ' + label + ')');
// Lets update the fields manually this event fires on selection of range
    var selectedStartDate = start.format('DD/MM/YYYY, HH:mm'); // selected start
    var selectedEndDate = end.format('DD/MM/YYYY, HH:mm'); // selected end

    $checkinInput = $('#date-start');
    $checkoutInput = $('#date-end');

// Updating Fields with selected dates
    $checkinInput.val(selectedStartDate);
    $checkoutInput.val(selectedEndDate);
    console.log(selectedStartDate + ' ' + selectedEndDate);

// Setting the Selection of dates on calender on CHECKOUT FIELD (To get this it must be binded by Ids not Calss)
    var checkOutPicker = $checkoutInput.data('daterangepicker');
    checkOutPicker.setStartDate(selectedStartDate);
    checkOutPicker.setEndDate(selectedEndDate);

// Setting the Selection of dates on calender on CHECKIN FIELD (To get this it must be binded by Ids not Calss)
    var checkInPicker = $checkinInput.data('daterangepicker');
    checkInPicker.setStartDate(selectedStartDate);
    checkInPicker.setEndDate(selectedEndDate);

});
$('.week-day').hide();
$("#offer-id").change(function(){
    var selectedOffer = $(this).val();
    if ((selectedOffer == 16) || (selectedOffer == 17))
    {
        $('.week-day').slideDown();
    } else {
        $('.week-day').slideUp();
    }
});
