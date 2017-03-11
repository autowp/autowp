var CarTypePicker = require('./car-type-picker');
var $ = require('jquery');
var Picker = require("latlng-picker");
require('typeahead');

module.exports = {
    init: function(options) {
        CarTypePicker.init($('select[multiple]'));
        
        new Picker({
            lat: ':input[name=lat]',
            lng: ':input[name=lng]'
        });
    }
};
