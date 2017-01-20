var $ = require("jquery");
var ol = require("openlayers");

var defaultCenter = [37.6786422, 55.7423627];

var Picker = function(options) {
    this.init(options);
};

Picker.prototype = {
    init: function(options) {
        var startPosition = ol.proj.fromLonLat(defaultCenter);
        var $lat = $(options.lat);
        var $lng = $(options.lng);
        var lat = parseFloat($lat.val());
        var lng = parseFloat($lng.val());
        if (lng && lat) {
            startPosition = ol.proj.fromLonLat([lng, lat]);
        }

        var node = $('<div style="width:100%; height: 300px" />').insertAfter($lng)[0];
        var marker = null;
        
        var map = new ol.Map({
            target: node,
            layers: [
                new ol.layer.Tile({
                    source: new ol.source.OSM()
                })
            ],
            view: new ol.View({
                center: startPosition,
                zoom: 2
            })
        });
        
        var vectorLayer = new ol.layer.Vector();
        map.addLayer(vectorLayer);
        if (lng && lat) {
            setMarkerPoint(startPosition);
        }

        function setMarkerPoint(latLng) {
            var point = new ol.geom.Point(latLng);
            if (marker) {
                marker.setGeometry(point);
            } else {
                marker = new ol.Feature({
                    geometry: point
                });
                marker.setStyle(require('map/icon-style'));
                var vectorSource = new ol.source.Vector({
                    features: [
                        marker
                    ]
                });
                vectorLayer.setSource(vectorSource);
            }
        }

        function clearMarker() {
            if (marker) {
                vectorLayer.setSource(null);
                marker = null;
            }
        }

        $(':input[name=lat], :input[name=lng]').on('change', function() {
            var lat = parseFloat($lat.val());
            var lng = parseFloat($lng.val());
            if (lng && lat) {
                var point = ol.proj.fromLonLat([lng, lat]);
                setMarkerPoint(point);
            } else {
                clearMarker();
            }
        });

        map.on("click", function(event) {
            setMarkerPoint(event.coordinate);
            var point = ol.proj.toLonLat(event.coordinate);
            $lng.val(point[0]);
            $lat.val(point[1]);
        });

        $('#address').change(function() {
            $.getJSON(options.addressToLatLngUrl, {address: $(this).val()}, function(point) {
                if (point) {
                    var latLng = ol.proj.fromLonLat([point.lng, point.lat]);
                    setMarkerPoint(latLng);
                    $lat.val(point.lat);
                    $lng.val(point.lng);
                }
            });
        });
    }
};

module.exports = Picker;
