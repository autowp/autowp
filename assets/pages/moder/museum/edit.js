var Picker = require("latlng-picker");

module.exports = {
    init: function(options) {
        new Picker({
            lat: ':input[name=lat]',
            lng: ':input[name=lng]'
        });
    }
};
