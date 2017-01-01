define(
    ['jquery'],
    function($) {
        return {
            init: function(options) {
                
                /*$('.concepts, .car').children('p').each(function() {
                    var $p = $(this),
                        $content = $p.next();

                    $p.children('a').first().on('click', function(e) {
                        e.preventDefault();

                        if ($content.is(':visible')) {
                            $content.slideUp();
                            $(this).find('span').removeClass('fa-minus-circle').addClass('fa-plus-circle');
                        } else {
                            $content.slideDown();
                            $(this).find('span').removeClass('fa-plus-circle').addClass('fa-minus-circle');
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
                        $icon.removeClass('fa-minus-circle').addClass('fa-plus-circle');
                        $content.slideUp();
                    } else {
                        $icon.removeClass('fa-plus-circle').addClass('fa-minus-circle');
                        $content.slideDown();
                    }
                });
                
            }
        };
    }
);