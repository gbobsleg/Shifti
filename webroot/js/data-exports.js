document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('data-exports');
    var form = root ? root.querySelector('form') : null;
    if (!root || !form) {
        return;
    }

    var typeField = document.getElementById('export-type');
    var scenarioField = document.getElementById('export-scenario-field');
    var scenarioSelect = document.getElementById('export-scenario');
    var startField = document.getElementById('export-start');
    var endField = document.getElementById('export-end');
    var scenarios = [];
    try {
        scenarios = JSON.parse(root.getAttribute('data-scenarios') || '[]');
    } catch (error) {
        scenarios = [];
    }

    function selectedScenario() {
        var id = scenarioSelect ? scenarioSelect.value : '';
        for (var i = 0; i < scenarios.length; i++) {
            if (String(scenarios[i].id) === String(id)) {
                return scenarios[i];
            }
        }
        return null;
    }

    function inclusiveDays(start, end) {
        if (!start || !end) {
            return 0;
        }
        var from = new Date(start + 'T00:00:00');
        var to = new Date(end + 'T00:00:00');
        return Math.round((to.getTime() - from.getTime()) / 86400000) + 1;
    }

    function syncOffers() {
        var type = typeField.value;
        var scenario = type === 'scenario' ? selectedScenario() : null;
        var allowed = scenario ? scenario.offers.map(String) : null;
        root.querySelectorAll('.export-offer').forEach(function (row) {
            var id = row.getAttribute('data-offer-id');
            var forecastable = row.getAttribute('data-forecastable') === '1';
            var visible = type === 'scenario'
                ? allowed !== null && allowed.indexOf(id) !== -1
                : forecastable;
            row.hidden = !visible;
            var input = row.querySelector('input');
            if (!input) {
                return;
            }
            input.disabled = !visible;
            if (!visible) {
                input.checked = false;
            }
        });
    }

    function syncDates() {
        if (typeField.value !== 'scenario') {
            startField.removeAttribute('min');
            startField.removeAttribute('max');
            endField.removeAttribute('min');
            endField.removeAttribute('max');
            return;
        }
        var scenario = selectedScenario();
        if (!scenario) {
            return;
        }
        startField.min = scenario.start;
        startField.max = scenario.end;
        endField.min = scenario.start;
        endField.max = scenario.end;
        if (startField.value && startField.value < scenario.start) {
            startField.value = scenario.start;
        }
        if (endField.value && endField.value > scenario.end) {
            endField.value = scenario.end;
        }
    }

    function syncScenarioField() {
        var show = typeField.value === 'scenario';
        scenarioField.hidden = !show;
        if (scenarioSelect) {
            scenarioSelect.disabled = !show;
        }
    }

    typeField.addEventListener('change', function () {
        syncScenarioField();
        syncDates();
        syncOffers();
    });
    if (scenarioSelect) {
        scenarioSelect.addEventListener('change', function () {
            syncDates();
            syncOffers();
        });
    }

    form.addEventListener('submit', function (event) {
        if (startField.value && endField.value && endField.value < startField.value) {
            event.preventDefault();
            window.alert('La date de fin est avant la date de début.');
            return;
        }
        if (inclusiveDays(startField.value, endField.value) > 366) {
            event.preventDefault();
            window.alert('La période ne peut pas dépasser 366 jours.');
        }
    });

    syncScenarioField();
    syncDates();
    syncOffers();
});
