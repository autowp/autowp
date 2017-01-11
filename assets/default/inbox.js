var $ = require('jquery');

module.exports = {
    init: function() {
        $('select[name=brand]').on('change', function() {
            window.location = $(this).val();
        });
    }
};
