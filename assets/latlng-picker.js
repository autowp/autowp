var $ = require("jquery");
var leaflet = require("leaflet-bundle");

var defaultCenter = [55.7423627, 37.6786422];

var Picker = function(options) {
    this.init(options);
};

Picker.prototype = {
    init: function(options) {
        var startPosition = defaultCenter;
        var $lat = $(options.lat);
        var $lng = $(options.lng);
        var lat = parseFloat($lat.val());
        var lng = parseFloat($lng.val());
        if (lng && lat) {
            startPosition = [lat, lng];
        }

        var node = $('<div style="width:100%; height: 300px" />').insertAfter($lng)[0];
        var marker = null;
        
        var map = leaflet.map(node).setView(startPosition, 2);
        
        leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
        }).addTo(map);
        
        if (lng && lat) {
            setMarkerPoint(startPosition);
        }

        function setMarkerPoint(latLng) {
            if (marker) {
                marker.setLatLng(latLng);
            } else {
                marker = leaflet.marker(latLng).addTo(map);
            }
        }

        function clearMarker() {
            if (marker) {
                marker.remove();
                marker = null;
            }
        }

        $(':input[name=lat], :input[name=lng]').on('change', function() {
            var lat = parseFloat($lat.val());
            var lng = parseFloat($lng.val());
            if (lng && lat) {
                setMarkerPoint([lat, lng]);
            } else {
                clearMarker();
            }
        });
        
        map.on('click', function(e) {
            setMarkerPoint([e.latlng.lat, e.latlng.lng]);
            $lng.val(e.latlng.lng);
            $lat.val(e.latlng.lat);
        });

        $('#address').change(function() {
            $.getJSON(options.addressToLatLngUrl, {address: $(this).val()}, function(point) {
                if (point) {
                    var latLng = [point.lat, point.lng];
                    setMarkerPoint(latLng);
                    $lat.val(point.lat);
                    $lng.val(point.lng);
                }
            });
        });
    }
};

module.exports = Picker;
