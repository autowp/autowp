define(
    'default/picture',
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
        return {
            init: function(options) {
                $('.picture-preview-medium a').on('click', function() {
                    window.open($(this).attr('href'), '_blank');
                    return false;
                });
            }
        }
    }
);