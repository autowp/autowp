define(
    'default/upload/select-in-brand',
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
        return {
            init: function() {
                $('.arrow').on('click', function(e) {
                    e.preventDefault();

                    var $content = $(this).next('div:first');
                    var $icon = $(this).children('span');

                    if ($content.is(':visible')) {
                        $icon.removeClass('glyphicon-minus-sign').addClass('glyphicon-plus-sign');
                        $content.slideUp();
                    } else {
                        $icon.removeClass('glyphicon-plus-sign').addClass('glyphicon-minus-sign');
                        $content.slideDown();
                    }
                });
            }
        }
    }
);