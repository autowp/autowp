var $ = require("jquery");
var leaflet = require("leaflet-bundle");

module.exports = {
    init: function(options) {
        if (options.lat && options.lng) {
            
            $('#google-map').each(function() {
                
                $(this).css({
                    width: '100%',
                    height: '300px',
                    margin: '0 0 40px'
                });
                
                var map = leaflet.map(this).setView([options.lat, options.lng], 17);
                leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
                }).addTo(map);
              
                leaflet.marker([options.lat, options.lng]).addTo(map);
            });
        }
    }
};
