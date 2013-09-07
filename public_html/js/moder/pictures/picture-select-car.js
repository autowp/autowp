define(
    'moder/pictures/picture-select-car',
    ['jquery'],
    function($) {
        return {
            init: function(options) {
                
                $('.model, .generation, .design-project, .concepts').children('p').each(function() {
                    var $p = $(this),
                        $content = $p.next();

                    $p.children('a').on('click', function(e) {
                        e.preventDefault();

                        if ($content.is(':visible')) {
                            $content.slideUp();
                            $(this).find('span').removeClass('glyphicon-minus-sign').addClass('glyphicon-plus-sign');
                        } else {
                            $content.slideDown();
                            $(this).find('span').removeClass('glyphicon-plus-sign').addClass('glyphicon-minus-sign');
                        }
                    });
                });
                
            }
        }
    }
);