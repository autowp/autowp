define(
    ['jquery', 'brand-popover'],
    function($, BrandPopover) {
        return {
            init: function() {
                BrandPopover.apply('.popover-handler');
            }
        };
    }
);