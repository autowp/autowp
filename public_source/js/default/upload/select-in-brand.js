define(
    'default/upload/select-in-brand',
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
        return {
            init: function() {
                $('.select-in-brand').on('click', '.arrow', function(e) {
                    e.preventDefault();
                    
                    var $content = $(this).next('div:first');
                    
                    var url = $(this).data('load');
                    if (url) {
                        $(this).data('load', false);
                        
                        $.get(url, {}, function(html) {
                            $content.children('.loading').replaceWith(html);
                        });
                    }
                    
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