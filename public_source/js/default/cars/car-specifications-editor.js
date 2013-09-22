define(
    'default/cars/car-specifications-editor',
    ['jquery'],
    function($) {
        return {
            init: function(options) {
                
                $('.spec-editor-form .subform-header').hover(function() {
                    $(this).addClass('hover');
                    $('.' + $(this).attr('id')).addClass('hover');
                }, function() {
                    $(this).removeClass('hover');
                    $('.' + $(this).attr('id')).removeClass('hover');
                });
                
            }
        }
    }
);