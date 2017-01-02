define(
    ['jquery', './car-type-picker', 'typeahead'],
    function($, CarTypePicker) {
        return {
            init: function(options) {
                CarTypePicker.init($('select[multiple]'));
            }
        };
    }
);