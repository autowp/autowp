var BrandPopover = require("brand-popover");
require("brandicon/brandicon");

module.exports = {
    init: function() {
        BrandPopover.apply('.popover-handler');
    }
};