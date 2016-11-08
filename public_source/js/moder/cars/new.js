define(
    ['jquery', './car-type-picker', 'bootstrap', 'typeahead'],
    function($, CarTypePicker) {
        return {
            init: function(options) {
                CarTypePicker.init($('select[multiple]'));
            }
        }
    }
);