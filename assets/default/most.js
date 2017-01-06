var $ = require('jquery');

module.exports = {
    init: function() {
        $('small.unit').tooltip({
            placement: 'bottom'
        });
    }
};
