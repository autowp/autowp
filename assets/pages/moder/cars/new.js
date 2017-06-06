var CarTypePicker = require('./car-type-picker');
var $ = require('jquery');
var Picker = require("latlng-picker");
require('corejs-typeahead');

module.exports = {
    init: function(options) {
        CarTypePicker.init($('select[multiple]'));
        
        if ($(':input[name=lat]').length) {
            new Picker({
                lat: ':input[name=lat]',
                lng: ':input[name=lng]'
            });
        }
    }
};
