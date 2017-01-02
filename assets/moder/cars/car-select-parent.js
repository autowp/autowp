define(
    ['jquery'],
    function($) {
        return {
            init: function(options) {
                $('.car-node .toggle').on('click', function(e) {
                    e.preventDefault();
                    
                    var $node = $(this).closest('.car-node');
                    $node[$node.hasClass('open') ? 'removeClass' : 'addClass']('open');
                });
            }
        };
    }
);