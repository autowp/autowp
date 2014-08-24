define(
    'moder/pictures/picture-select-car',
    ['jquery'],
    function($) {
        return {
            init: function(options) {
                
                /*$('.model, .generation, .design-project, .concepts, .car').children('p').each(function() {
                    var $p = $(this),
                        $content = $p.next();

                    $p.children('a').first().on('click', function(e) {
                        e.preventDefault();

                        if ($content.is(':visible')) {
                            $content.slideUp();
                            $(this).find('span').removeClass('glyphicon-minus-sign').addClass('glyphicon-plus-sign');
                        } else {
                            $content.slideDown();
                            $(this).find('span').removeClass('glyphicon-plus-sign').addClass('glyphicon-minus-sign');
                        }
                    });
                });*/
                
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