var $ = require('jquery');
module.exports = {
    init: function() {
        $('.select-in-brand').on('click', '.arrow', function(e) {
            e.preventDefault();
            
            var $content = $(this).nextAll('div:first');
            
            var url = $(this).data('load');
            if (url) {
                $(this).data('load', false);
                
                $.get(url, {}, function(html) {
                    $content.children('.loading').replaceWith(html);
                });
            }
            
            var $icon = $(this).children('.fa');

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
