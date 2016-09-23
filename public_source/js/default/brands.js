define(
    ['jquery', 'bootstrap', 'brand-popover'],
    function($, Bootstrap, BrandPopover) {
        return {
            init: function() {
                BrandPopover.apply('.popover-handler');
            }
        }
    }
);