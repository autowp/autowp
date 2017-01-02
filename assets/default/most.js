define(
    ['jquery'],
    function($) {
        return {
            init: function() {
                $('small.unit').tooltip({
                    placement: 'bottom'
                });
            }
        };
    }
);