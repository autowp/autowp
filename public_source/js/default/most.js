define(
    'default/most',
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
        return {
            init: function() {
                $('small.unit').tooltip({
                    placement: 'bottom'
                });
            }
        }
    }
);